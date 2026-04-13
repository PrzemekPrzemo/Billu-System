<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiResponse;
use App\Api\Auth\JwtService;

class JwtMiddleware
{
    /**
     * Extract and verify the Bearer token from the Authorization header.
     * Returns the authenticated clientId, or calls ApiResponse::unauthorized() and exits.
     */
    public static function requireAuth(): int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            ApiResponse::unauthorized('missing_token');
        }

        $token    = substr($header, 7);
        $clientId = JwtService::verifyAccessToken($token);

        if ($clientId === null) {
            ApiResponse::unauthorized('invalid_or_expired_token');
        }

        return $clientId;
    }

    /**
     * Extract and verify a pre-auth token (for 2FA verification endpoint).
     * Returns clientId, or exits with 401.
     */
    public static function requirePreAuth(): int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            ApiResponse::unauthorized('missing_pre_auth_token');
        }

        $token    = substr($header, 7);
        $clientId = JwtService::verifyPreAuthToken($token);

        if ($clientId === null) {
            ApiResponse::unauthorized('invalid_or_expired_pre_auth_token');
        }

        return $clientId;
    }

    /**
     * Optionally extract the client ID without requiring auth.
     * Returns null if no valid token is present.
     */
    public static function optionalAuth(): ?int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = substr($header, 7);
        return JwtService::verifyAccessToken($token);
    }
}
