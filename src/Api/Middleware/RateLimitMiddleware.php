<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiResponse;
use App\Core\Database;

class RateLimitMiddleware
{
    private const ANON_MAX       = 10;   // requests per minute for unauthenticated
    private const AUTH_MAX       = 300;  // requests per minute per clientId
    private const WINDOW_SECONDS = 60;

    /**
     * Check rate limit for anonymous (login) endpoints.
     * Uses IP address as key.
     */
    public static function checkAnonymous(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $db     = Database::getInstance();
            $result = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM login_history
                 WHERE ip_address = ?
                   AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$ip, self::WINDOW_SECONDS]
            );
            $count = (int) ($result['cnt'] ?? 0);

            if ($count >= self::ANON_MAX) {
                ApiResponse::tooManyRequests();
            }
        } catch (\Exception) {
            // Fail open — do not block on DB errors
        }
    }
}
