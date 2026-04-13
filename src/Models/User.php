<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$id]
        );
    }

    public static function findByUsername(string $username): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
    }

    public static function findAll(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM users ORDER BY username"
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('users', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::getInstance()->update('users', $data, 'id = ?', [$id]);
    }
}
