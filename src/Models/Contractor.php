<?php

namespace App\Models;

use App\Core\Database;

class Contractor
{
    public static function findByClient(int $clientId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM contractors WHERE client_id = ?";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY company_name";

        return Database::getInstance()->fetchAll($sql, [$clientId]);
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM contractors WHERE id = ?", [$id]);
    }

    public static function findByClientAndNip(int $clientId, string $nip): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM contractors WHERE client_id = ? AND nip = ?",
            [$clientId, $nip]
        );
    }

    public static function create(int $clientId, array $data): int
    {
        $data['client_id'] = $clientId;
        return Database::getInstance()->insert('contractors', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('contractors', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM contractors WHERE id = ?", [$id]);
    }

    public static function search(int $clientId, string $query): array
    {
        $like = '%' . $query . '%';
        return Database::getInstance()->fetchAll(
            "SELECT * FROM contractors WHERE client_id = ? AND is_active = 1
             AND (company_name LIKE ? OR short_name LIKE ? OR nip LIKE ?)
             ORDER BY company_name LIMIT 20",
            [$clientId, $like, $like, $like]
        );
    }
}
