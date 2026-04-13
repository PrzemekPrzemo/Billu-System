<?php

namespace App\Core;

class Session
{
    private const TIMEOUT_MINUTES = 30;
    private const MAX_SESSIONS = 2;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly'  => true,
                'samesite'  => 'Strict',
            ]);
            session_start();
        }

        // Check session timeout
        if (self::has('last_activity')) {
            $timeout = self::getTimeoutMinutes() * 60;
            if (time() - self::get('last_activity') > $timeout) {
                $userType = self::get('user_type');
                $userId = self::get('user_id') ?? self::get('client_id') ?? self::get('office_id');
                if ($userType && $userId) {
                    self::removeSessionRecord(session_id());
                }
                self::destroy();
                session_start();
                self::flash('error', 'session_expired');
                return;
            }
        }
        self::set('last_activity', time());
    }

    private static function getTimeoutMinutes(): int
    {
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'session_timeout_minutes'");
            return $result ? (int) $result['setting_value'] : self::TIMEOUT_MINUTES;
        } catch (\Exception $e) {
            return self::TIMEOUT_MINUTES;
        }
    }

    public static function registerSession(string $userType, int $userId): void
    {
        $db = Database::getInstance();
        $sessionId = session_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Get max sessions setting
        try {
            $result = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'max_sessions_per_user'");
            $maxSessions = $result ? (int) $result['setting_value'] : self::MAX_SESSIONS;
        } catch (\Exception $e) {
            $maxSessions = self::MAX_SESSIONS;
        }

        // Count existing sessions for this user
        $existing = $db->fetchAll(
            "SELECT id, session_id FROM user_sessions WHERE user_type = ? AND user_id = ? ORDER BY last_activity ASC",
            [$userType, $userId]
        );

        // If at max, remove the oldest session(s)
        while (count($existing) >= $maxSessions) {
            $oldest = array_shift($existing);
            $db->query("DELETE FROM user_sessions WHERE id = ?", [$oldest['id']]);
        }

        // Remove existing record for this session_id if any
        $db->query("DELETE FROM user_sessions WHERE session_id = ?", [$sessionId]);

        // Insert new session
        $db->insert('user_sessions', [
            'user_type'     => $userType,
            'user_id'       => $userId,
            'session_id'    => $sessionId,
            'ip_address'    => $ip,
            'user_agent'    => $ua,
            'last_activity' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function updateActivity(): void
    {
        try {
            $db = Database::getInstance();
            $db->query(
                "UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?",
                [session_id()]
            );
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    public static function removeSessionRecord(string $sessionId): void
    {
        try {
            $db = Database::getInstance();
            $db->query("DELETE FROM user_sessions WHERE session_id = ?", [$sessionId]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    public static function cleanExpiredSessions(): void
    {
        try {
            $db = Database::getInstance();
            $timeout = self::getTimeoutMinutes();
            $db->query(
                "DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                [$timeout]
            );
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::removeSessionRecord(session_id());
            session_destroy();
        }
        $_SESSION = [];
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function generateCsrfToken(): string
    {
        if (!self::has('csrf_token')) {
            self::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return self::get('csrf_token');
    }

    public static function validateCsrfToken(string $token): bool
    {
        $stored = self::get('csrf_token', '');
        if (empty($stored) || empty($token)) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    public static function regenerateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        self::set('csrf_token', $token);
        return $token;
    }
}
