<?php

namespace App\Models;

use App\Core\Database;

class CompanyService
{
    public static function findByClient(int $clientId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM company_services WHERE client_id = ?";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY sort_order, name";

        return Database::getInstance()->fetchAll($sql, [$clientId]);
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM company_services WHERE id = ?",
            [$id]
        );
    }

    public static function create(int $clientId, array $data): int
    {
        $data['client_id'] = $clientId;
        return Database::getInstance()->insert('company_services', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('company_services', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM company_services WHERE id = ?", [$id]);
    }
}
