<?php

namespace App\Models;

use App\Core\Database;

class Notification
{
    public static function create(string $userType, int $userId, string $title, ?string $message = null, string $type = 'info', ?string $link = null): int
    {
        return Database::getInstance()->insert('notifications', [
            'user_type' => $userType,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
        ]);
    }

    public static function notify(string $userType, int $userId, string $title, ?string $message = null, string $type = 'info', ?string $link = null): int
    {
        return self::create($userType, $userId, $title, $message, $type, $link);
    }

    public static function notifyAllAdmins(string $title, ?string $message = null, string $type = 'info', ?string $link = null): void
    {
        $admins = User::findAll();
        foreach ($admins as $admin) {
            self::create('admin', (int)$admin['id'], $title, $message, $type, $link);
        }
    }

    public static function getUnread(string $userType, int $userId, int $limit = 20): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?",
            [$userType, $userId, $limit]
        );
    }

    public static function getAll(string $userType, int $userId, int $limit = 50): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM notifications WHERE user_type = ? AND user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userType, $userId, $limit]
        );
    }

    public static function countUnread(string $userType, int $userId): int
    {
        $result = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0",
            [$userType, $userId]
        );
        return (int)($result['cnt'] ?? 0);
    }

    public static function markAsRead(int $id, string $userType, int $userId): void
    {
        Database::getInstance()->query(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = ? AND user_id = ?",
            [$id, $userType, $userId]
        );
    }

    public static function markAllAsRead(string $userType, int $userId): void
    {
        Database::getInstance()->query(
            "UPDATE notifications SET is_read = 1 WHERE user_type = ? AND user_id = ? AND is_read = 0",
            [$userType, $userId]
        );
    }

    public static function deleteOld(int $days = 90): int
    {
        $stmt = Database::getInstance()->query(
            "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        return $stmt->rowCount();
    }
}
