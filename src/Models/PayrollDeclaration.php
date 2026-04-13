<?php

namespace App\Models;

use App\Core\Database;

class PayrollDeclaration
{
    /**
     * Find a declaration by ID.
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM payroll_declarations WHERE id = ?",
            [$id]
        );
    }

    /**
     * Find declarations by client, optionally filtered by year.
     */
    public static function findByClient(int $clientId, ?int $year = null): array
    {
        $sql = "SELECT * FROM payroll_declarations WHERE client_id = ?";
        $params = [$clientId];

        if ($year !== null) {
            $sql .= " AND year = ?";
            $params[] = $year;
        }

        $sql .= " ORDER BY year DESC, month DESC, declaration_type ASC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Find declarations for an employee.
     */
    public static function findByEmployee(int $employeeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM payroll_declarations WHERE employee_id = ? ORDER BY year DESC, month DESC",
            [$employeeId]
        );
    }

    /**
     * Create a new declaration.
     */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('payroll_declarations', $data);
    }

    /**
     * Update an existing declaration.
     */
    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('payroll_declarations', $data, 'id = ?', [$id]);
    }
}
