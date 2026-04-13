<?php

namespace App\Models;

use App\Core\Database;

class ExportTemplate
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM export_templates WHERE id = ?", [$id]);
    }

    public static function findByFormat(string $formatType): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM export_templates WHERE format_type = ? ORDER BY is_default DESC, name",
            [$formatType]
        );
    }

    public static function findAll(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM export_templates ORDER BY format_type, is_default DESC, name"
        );
    }

    public static function findDefault(string $formatType): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM export_templates WHERE format_type = ? AND is_default = 1 LIMIT 1",
            [$formatType]
        );
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO export_templates (name, format_type, column_mapping, separator, encoding, date_format, is_default)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['format_type'],
                is_array($data['column_mapping']) ? json_encode($data['column_mapping']) : $data['column_mapping'],
                $data['separator'] ?? ';',
                $data['encoding'] ?? 'Windows-1250',
                $data['date_format'] ?? 'd.m.Y',
                $data['is_default'] ?? 0,
            ]
        );
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        foreach (['name', 'column_mapping', 'separator', 'encoding', 'date_format', 'is_default'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $value = $data[$field];
                if ($field === 'column_mapping' && is_array($value)) {
                    $value = json_encode($value);
                }
                $params[] = $value;
            }
        }

        if (empty($fields)) return;

        $params[] = $id;
        Database::getInstance()->query(
            "UPDATE export_templates SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM export_templates WHERE id = ?", [$id]);
    }

    public static function getColumnMapping(int $id): array
    {
        $template = self::findById($id);
        if (!$template || empty($template['column_mapping'])) {
            return [];
        }
        $mapping = json_decode($template['column_mapping'], true);
        return $mapping['columns'] ?? [];
    }
}
