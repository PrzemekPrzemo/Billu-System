<?php

namespace App\Models;

use App\Core\Database;

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

        Database::getInstance()->insert('trusted_devices', [
            'user_type'    => $userType,
            'user_id'      => $userId,
            'token_hash'   => $hash,
            'expires_at'   => $expiresAt,
            'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
            'device_label' => $label,
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
            "SELECT id, expires_at, created_at, last_used_at, user_agent, ip_address, device_label
             FROM trusted_devices
             WHERE user_type = ? AND user_id = ? AND expires_at > NOW()
             ORDER BY created_at DESC",
            [$userType, $userId]
        );
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
