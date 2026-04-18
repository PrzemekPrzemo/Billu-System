<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrLeaveType
{
    private static ?array $cache = null;

    public static function findAll(): array
    {
        if (self::$cache !== null) return self::$cache;
        self::$cache = HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_leave_types ORDER BY id"
        );
        return self::$cache;
    }

    public static function findById(int $id): ?array
    {
        foreach (self::findAll() as $type) {
            if ((int)$type['id'] === $id) return $type;
        }
        return null;
    }

    public static function findByCode(string $code): ?array
    {
        foreach (self::findAll() as $type) {
            if ($type['code'] === $code) return $type;
        }
        return null;
    }

    public static function getLabel(int $id): string
    {
        $type = self::findById($id);
        return $type ? $type['name'] : '';
    }
}
