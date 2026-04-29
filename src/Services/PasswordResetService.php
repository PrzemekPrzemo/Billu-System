<?php

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\Auth;
use App\Models\Client;
use App\Models\Office;

class PasswordResetService
{
    /** Max password-reset requests per IP+NIP per window. */
    private const THROTTLE_MAX = 5;
    private const THROTTLE_WINDOW = 3600;

    /**
     * Create a password reset token and send email.
     */
    public static function createReset(string $userType, string $nip): bool
    {
        if (self::isThrottled($nip)) {
            return false;
        }

        $user = null;
        if ($userType === 'client') {
            $user = Client::findByNip($nip);
        } elseif ($userType === 'office') {
            $user = Office::findByNip($nip);
        }

        if (!$user || !$user['is_active']) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Invalidate old tokens
        Database::getInstance()->query(
            "UPDATE password_resets SET used = 1 WHERE user_type = ? AND user_id = ? AND used = 0",
            [$userType, $user['id']]
        );

        Database::getInstance()->insert('password_resets', [
            'user_type'  => $userType,
            'user_id'    => $user['id'],
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        // Send email - use app config URL instead of HTTP_HOST to prevent host header injection
        $appConfig = require __DIR__ . '/../../config/app.php';
        $baseUrl = rtrim($appConfig['url'] ?? 'https://portal.billu.pl', '/');
        $resetUrl = $baseUrl . '/reset-password?token=' . $token;

        return MailService::sendPasswordReset(
            $user['email'],
            $user['company_name'] ?? $user['name'] ?? '',
            $resetUrl,
            $user['language'] ?? 'pl'
        );
    }

    /** Per-IP+NIP request throttle backed by Cache. Fails open if Cache driver is null. */
    private static function isThrottled(string $nip): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '') {
            return false;
        }
        $cache = Cache::getInstance();
        $key = 'pwd_reset_throttle:' . sha1($ip . '|' . $nip);
        $attempts = (int) ($cache->get($key) ?? 0);
        if ($attempts >= self::THROTTLE_MAX) {
            return true;
        }
        $cache->set($key, $attempts + 1, self::THROTTLE_WINDOW);
        return false;
    }

    /**
     * Validate a reset token.
     */
    public static function validateToken(string $token): ?array
    {
        $reset = Database::getInstance()->fetchOne(
            "SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()",
            [$token]
        );

        return $reset;
    }

    /**
     * Execute password reset.
     */
    public static function resetPassword(string $token, string $newPassword): bool
    {
        $reset = self::validateToken($token);
        if (!$reset) {
            return false;
        }

        $hash = Auth::hashPassword($newPassword);

        if ($reset['user_type'] === 'client') {
            Client::updatePassword($reset['user_id'], $hash);
        } elseif ($reset['user_type'] === 'office') {
            Office::updatePassword($reset['user_id'], $hash);
        }

        // Mark token as used
        Database::getInstance()->update('password_resets', ['used' => 1], 'id = ?', [$reset['id']]);

        return true;
    }
}
