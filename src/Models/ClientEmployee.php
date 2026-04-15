<?php

namespace App\Models;

use App\Core\Database;

class ClientEmployee
{
    /**
     * Find an employee by ID.
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_employees WHERE id = ?",
            [$id]
        );
    }

    /**
     * Find all employees for a client, optionally only active ones.
     */
    public static function findByClient(int $clientId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM client_employees WHERE client_id = ?";
        $params = [$clientId];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY last_name ASC, first_name ASC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Create a new employee record.
     */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('client_employees', $data);
    }

    /**
     * Update an existing employee record.
     */
    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('client_employees', $data, 'id = ?', [$id]);
    }

    /**
     * Count employees for a client.
     */
    public static function countByClient(int $clientId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM client_employees WHERE client_id = ? AND is_active = 1",
            [$clientId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get full name string from an employee array.
     */
    public static function getFullName(array $employee): string
    {
        return trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
    }
}
