<?php

namespace App\Models;

use App\Core\Database;

class Setting
{
    public static function get(string $key, string $default = ''): string
    {
        $result = Database::getInstance()->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        );
        return $result ? $result['setting_value'] : $default;
    }

    public static function set(string $key, string $value): void
    {
        $existing = Database::getInstance()->fetchOne(
            "SELECT id FROM settings WHERE setting_key = ?",
            [$key]
        );

        if ($existing) {
            Database::getInstance()->update(
                'settings',
                ['setting_value' => $value],
                'setting_key = ?',
                [$key]
            );
        } else {
            Database::getInstance()->insert('settings', [
                'setting_key'   => $key,
                'setting_value' => $value,
            ]);
        }
    }

    public static function getAll(): array
    {
        return Database::getInstance()->fetchAll("SELECT * FROM settings ORDER BY setting_key");
    }

    public static function getVerificationDeadlineDay(): int
    {
        return (int) self::get('verification_deadline_day', '5');
    }

    public static function getAutoAcceptOnDeadline(): bool
    {
        return (bool) self::get('auto_accept_on_deadline', '1');
    }
}
