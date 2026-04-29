<?php

namespace App\Models;

use App\Core\Database;
use App\Services\IpGeoService;

/**
 * Trusted device — lets a user skip 2FA on a specific browser for a
 * limited time after they've already passed 2FA once. Token plaintext
 * is held only in the user's HttpOnly cookie; the DB stores sha256 of
 * the token, so a DB leak doesn't enable a 2FA bypass.
 */
class TrustedDevice
{
    public const COOKIE_NAME = 'billu_trusted_device';
    public const DEFAULT_TTL_DAYS = 5;

    /**
     * Issue a new device token for (user_type, user_id) and persist its hash.
     * Returns the plaintext token that the caller should put into a Secure HttpOnly cookie.
     */
    public static function issue(string $userType, int $userId, int $ttlDays = self::DEFAULT_TTL_DAYS, ?string $label = null): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlDays * 86400);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // Best-effort geo lookup. Cached per-IP for 30 days, 2 s timeout, never throws.
        $geo = $ip ? IpGeoService::lookup($ip) : null;

        Database::getInstance()->insert('trusted_devices', [
            'user_type'        => $userType,
            'user_id'          => $userId,
            'token_hash'       => $hash,
            'expires_at'       => $expiresAt,
            'user_agent'       => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'ip_address'       => $ip,
            'device_label'     => $label,
            'geo_country'      => $geo['country']      ?? null,
            'geo_country_code' => $geo['country_code'] ?? null,
            'geo_region'       => $geo['region']       ?? null,
            'geo_city'         => $geo['city']         ?? null,
        ]);

        return $token;
    }

    /**
     * Verify a token from cookie against (user_type, user_id). On success,
     * touches last_used_at and returns true. Expired or unknown token returns false.
     */
    public static function verify(string $userType, int $userId, string $token): bool
    {
        if ($token === '' || strlen($token) !== 64) {
            return false;
        }
        $hash = hash('sha256', $token);
        $row = Database::getInstance()->fetchOne(
            "SELECT id FROM trusted_devices
             WHERE user_type = ? AND user_id = ? AND token_hash = ? AND expires_at > NOW()
             LIMIT 1",
            [$userType, $userId, $hash]
        );
        if (!$row) {
            return false;
        }
        Database::getInstance()->update('trusted_devices',
            ['last_used_at' => date('Y-m-d H:i:s')],
            'id = ?', [(int) $row['id']]
        );
        return true;
    }

    /** All non-expired devices for a user, newest first. Used by the profile page. */
    public static function findByUser(string $userType, int $userId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT id, expires_at, created_at, last_used_at, user_agent, ip_address, device_label,
                    geo_country, geo_country_code, geo_region, geo_city
             FROM trusted_devices
             WHERE user_type = ? AND user_id = ? AND expires_at > NOW()
             ORDER BY created_at DESC",
            [$userType, $userId]
        );
    }

    /**
     * Lazily fill geo columns for rows that were created before geo support
     * (or where the issue-time lookup failed). Called from /trusted-devices.
     * Caps at $limit lookups per call so opening the page never blocks longer
     * than ~$limit * 2s. Returns the (possibly mutated) device list.
     */
    public static function attachGeoIfMissing(array $devices, int $limit = 5): array
    {
        $remaining = $limit;
        foreach ($devices as &$d) {
            if ($remaining <= 0) {
                break;
            }
            if (!empty($d['geo_country']) || empty($d['ip_address'])) {
                continue;
            }
            $geo = IpGeoService::lookup((string) $d['ip_address']);
            if ($geo === null) {
                continue;
            }
            Database::getInstance()->update('trusted_devices', [
                'geo_country'      => $geo['country'],
                'geo_country_code' => $geo['country_code'],
                'geo_region'       => $geo['region'],
                'geo_city'         => $geo['city'],
            ], 'id = ?', [(int) $d['id']]);
            $d['geo_country']      = $geo['country'];
            $d['geo_country_code'] = $geo['country_code'];
            $d['geo_region']       = $geo['region'];
            $d['geo_city']         = $geo['city'];
            $remaining--;
        }
        unset($d);
        return $devices;
    }

    /** Revoke a single device, ownership-checked. Returns true if a row was removed. */
    public static function revoke(int $id, string $userType, int $userId): bool
    {
        $rows = Database::getInstance()->update('trusted_devices',
            ['expires_at' => date('Y-m-d H:i:s', time() - 1)],
            'id = ? AND user_type = ? AND user_id = ?',
            [$id, $userType, $userId]
        );
        return $rows > 0;
    }

    /** Revoke ALL devices for a user. Call after password change / 2FA disable. */
    public static function revokeAllForUser(string $userType, int $userId): int
    {
        return Database::getInstance()->update('trusted_devices',
            ['expires_at' => date('Y-m-d H:i:s', time() - 1)],
            'user_type = ? AND user_id = ?',
            [$userType, $userId]
        );
    }

    /** Hard-delete fully expired rows. Call from cron. */
    public static function cleanupExpired(): int
    {
        $stmt = Database::getInstance()->query(
            "DELETE FROM trusted_devices WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        return $stmt->rowCount();
    }
}
