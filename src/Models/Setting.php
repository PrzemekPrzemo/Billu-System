<?php

namespace App\Models;

use App\Core\Database;

class Setting
{
    /** Per-request memoization. Klucz=setting_key, wartość=setting_value lub false dla "brak w DB". */
    private static array $memo = [];

    public static function get(string $key, string $default = ''): string
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key] === false ? $default : self::$memo[$key];
        }
        $result = Database::getInstance()->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = ?",
            [$key]
        );
        $value = $result ? (string)$result['setting_value'] : false;
        self::$memo[$key] = $value;
        return $value === false ? $default : $value;
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
        self::$memo[$key] = $value;
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
