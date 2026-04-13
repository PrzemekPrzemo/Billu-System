<?php

namespace App\Models;

use App\Core\Database;

class MessageNotificationPref
{
    private static array $defaults = [
        'notify_new_thread' => 1,
        'notify_new_reply' => 1,
        'notify_email' => 1,
    ];

    /**
     * Get notification preferences for a user. Returns defaults if no record exists.
     */
    public static function getPrefs(string $userType, int $userId): array
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT * FROM message_notification_prefs WHERE user_type = ? AND user_id = ?",
            [$userType, $userId]
        );
        if (!$row) {
            return self::$defaults;
        }
        return [
            'notify_new_thread' => (int) $row['notify_new_thread'],
            'notify_new_reply' => (int) $row['notify_new_reply'],
            'notify_email' => (int) $row['notify_email'],
        ];
    }

    /**
     * Save preferences (INSERT or UPDATE on duplicate).
     */
    public static function savePrefs(string $userType, int $userId, array $data): void
    {
        Database::getInstance()->query(
            "INSERT INTO message_notification_prefs (user_type, user_id, notify_new_thread, notify_new_reply, notify_email)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE notify_new_thread = VALUES(notify_new_thread),
                                     notify_new_reply = VALUES(notify_new_reply),
                                     notify_email = VALUES(notify_email)",
            [
                $userType,
                $userId,
                (int) ($data['notify_new_thread'] ?? 1),
                (int) ($data['notify_new_reply'] ?? 1),
                (int) ($data['notify_email'] ?? 1),
            ]
        );
    }

    /**
     * Check if a user should receive a notification for a given event type.
     * @param string $eventType 'new_thread' or 'new_reply'
     */
    public static function shouldNotify(string $userType, int $userId, string $eventType): bool
    {
        $prefs = self::getPrefs($userType, $userId);
        return match ($eventType) {
            'new_thread' => (bool) $prefs['notify_new_thread'],
            'new_reply' => (bool) $prefs['notify_new_reply'],
            default => true,
        };
    }

    /**
     * Check if email should be sent for a user.
     */
    public static function shouldEmail(string $userType, int $userId, string $eventType): bool
    {
        $prefs = self::getPrefs($userType, $userId);
        if (!$prefs['notify_email']) {
            return false;
        }
        return self::shouldNotify($userType, $userId, $eventType);
    }
}
