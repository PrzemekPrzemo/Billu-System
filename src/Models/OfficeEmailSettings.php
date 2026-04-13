<?php

namespace App\Models;

use App\Core\Database;

class OfficeEmailSettings
{
    public static function findByOfficeId(int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM office_email_settings WHERE office_id = ?",
            [$officeId]
        );
    }

    public static function upsert(int $officeId, array $data): void
    {
        $existing = self::findByOfficeId($officeId);
        $db = Database::getInstance();

        if ($existing) {
            $sets = [];
            $params = [];
            foreach ($data as $key => $value) {
                $sets[] = "{$key} = ?";
                $params[] = $value;
            }
            $params[] = $officeId;
            $db->query(
                "UPDATE office_email_settings SET " . implode(', ', $sets) . " WHERE office_id = ?",
                $params
            );
        } else {
            $data['office_id'] = $officeId;
            $cols = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $db->query(
                "INSERT INTO office_email_settings ({$cols}) VALUES ({$placeholders})",
                array_values($data)
            );
        }
    }
}
