<?php

declare(strict_types=1);

namespace App\Api\Auth;

use App\Core\Database;

class JwtService
{
    private const ACCESS_TTL    = 900;       // 15 minutes
    private const PRE_AUTH_TTL  = 300;       // 5 minutes
    private const REFRESH_TTL   = 7776000;   // 90 days
    private const ALGO          = 'HS256';

    // ── Token Issuance ─────────────────────────────

    public static function issueTokenPair(int $clientId, string $deviceName = '', string $ip = ''): array
    {
        $accessToken  = self::createJwt($clientId, 'client', self::ACCESS_TTL);
        $refreshToken = self::createRefreshToken($clientId, $deviceName, $ip);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => self::ACCESS_TTL,
            'token_type'    => 'Bearer',
        ];
    }

    public static function issuePreAuthToken(int $clientId): string
    {
        return self::createJwt($clientId, 'pre_auth', self::PRE_AUTH_TTL);
    }

    // ── Token Verification ─────────────────────────

    /**
     * Verify an access token. Returns clientId or null.
     */
    public static function verifyAccessToken(string $token): ?int
    {
        $payload = self::decode($token);
        if (!$payload) return null;
        if (($payload['type'] ?? '') !== 'client') return null;
        return (int) $payload['sub'];
    }

    /**
     * Verify a pre-auth token. Returns clientId or null.
     */
    public static function verifyPreAuthToken(string $token): ?int
    {
        $payload = self::decode($token);
        if (!$payload) return null;
        if (($payload['type'] ?? '') !== 'pre_auth') return null;
        return (int) $payload['sub'];
    }

    // ── Refresh Token ──────────────────────────────

    /**
     * Rotate refresh token: revoke old, issue new. Returns new token pair or null.
     */
    public static function rotateRefreshToken(string $oldToken, string $ip = ''): ?array
    {
        $hash = hash('sha256', $oldToken);
        $db   = Database::getInstance();

        $record = $db->fetchOne(
            "SELECT * FROM api_tokens WHERE refresh_token_hash = ? AND revoked_at IS NULL AND expires_at > NOW()",
            [$hash]
        );

        if (!$record) {
            return null;
        }

        // Revoke old token
        $db->execute(
            "UPDATE api_tokens SET revoked_at = NOW() WHERE id = ?",
            [$record['id']]
        );

        return self::issueTokenPair(
            (int) $record['client_id'],
            $record['device_name'] ?? '',
            $ip
        );
    }

    /**
     * Revoke all refresh tokens for a client (logout from all devices).
     */
    public static function revokeAllForClient(int $clientId): void
    {
        Database::getInstance()->execute(
            "UPDATE api_tokens SET revoked_at = NOW() WHERE client_id = ? AND revoked_at IS NULL",
            [$clientId]
        );
    }

    /**
     * Revoke a specific refresh token by its raw value.
     */
    public static function revokeRefreshToken(string $token): void
    {
        $hash = hash('sha256', $token);
        Database::getInstance()->execute(
            "UPDATE api_tokens SET revoked_at = NOW() WHERE refresh_token_hash = ?",
            [$hash]
        );
    }

    // ── Helpers ────────────────────────────────────

    private static function createJwt(int $clientId, string $type, int $ttl): string
    {
        $header  = self::base64UrlEncode(json_encode(['alg' => self::ALGO, 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode([
            'sub'  => $clientId,
            'type' => $type,
            'jti'  => bin2hex(random_bytes(8)),
            'iat'  => time(),
            'exp'  => time() + $ttl,
        ]));

        $signature = self::sign("{$header}.{$payload}");
        return "{$header}.{$payload}.{$signature}";
    }

    private static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $sig] = $parts;

        // Verify signature
        $expected = self::sign("{$header}.{$payload}");
        if (!hash_equals($expected, $sig)) return null;

        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($data)) return null;

        // Check expiry
        if (($data['exp'] ?? 0) < time()) return null;

        return $data;
    }

    private static function sign(string $data): string
    {
        $key = self::getSecretKey();
        return self::base64UrlEncode(hash_hmac('sha256', $data, $key, true));
    }

    private static function getSecretKey(): string
    {
        $config = require __DIR__ . '/../../../config/app.php';
        return $config['secret_key'] ?? throw new \RuntimeException('secret_key not configured');
    }

    private static function createRefreshToken(int $clientId, string $deviceName, string $ip): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $rawToken);

        Database::getInstance()->insert('api_tokens', [
            'client_id'          => $clientId,
            'refresh_token_hash' => $hash,
            'device_name'        => $deviceName ?: null,
            'ip_address'         => $ip ?: null,
            'expires_at'         => date('Y-m-d H:i:s', time() + self::REFRESH_TTL),
        ]);

        return $rawToken;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // ── Cleanup (call from cron) ───────────────────

    public static function purgeExpired(): void
    {
        Database::getInstance()->execute(
            "DELETE FROM api_tokens WHERE expires_at < NOW() OR revoked_at IS NOT NULL"
        );
    }
}
