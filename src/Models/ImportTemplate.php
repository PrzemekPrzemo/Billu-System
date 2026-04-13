<?php

namespace App\Models;

use App\Core\Database;

class ImportTemplate
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM import_templates WHERE id = ?", [$id]);
    }

    public static function findAll(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM import_templates ORDER BY name"
        );
    }

    public static function findByOffice(?int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM import_templates WHERE office_id IS NULL OR office_id = ? ORDER BY name",
            [$officeId]
        );
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO import_templates (office_id, name, column_mapping, separator, encoding, skip_rows)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['office_id'] ?? null,
                $data['name'],
                is_array($data['column_mapping']) ? json_encode($data['column_mapping']) : $data['column_mapping'],
                $data['separator'] ?? ';',
                $data['encoding'] ?? 'UTF-8',
                $data['skip_rows'] ?? 1,
            ]
        );
        return (int) $db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM import_templates WHERE id = ?", [$id]);
    }

    public static function getColumnMapping(int $id): array
    {
        $tpl = self::findById($id);
        if (!$tpl || empty($tpl['column_mapping'])) return [];
        $mapping = json_decode($tpl['column_mapping'], true);
        return is_array($mapping) ? $mapping : [];
    }
}
