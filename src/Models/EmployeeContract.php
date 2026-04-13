<?php

namespace App\Models;

use App\Core\Database;

class EmployeeContract
{
    /**
     * Find a contract by ID.
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM employee_contracts WHERE id = ?",
            [$id]
        );
    }

    /**
     * Find all contracts for an employee.
     */
    public static function findByEmployee(int $employeeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM employee_contracts WHERE employee_id = ? ORDER BY start_date DESC",
            [$employeeId]
        );
    }

    /**
     * Find contracts by client, optionally filtered by status.
     */
    public static function findByClient(int $clientId, ?string $status = null): array
    {
        $sql = "SELECT ec.*, ce.first_name, ce.last_name
                FROM employee_contracts ec
                JOIN client_employees ce ON ce.id = ec.employee_id
                WHERE ec.client_id = ?";
        $params = [$clientId];

        if ($status !== null) {
            $sql .= " AND ec.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY ec.start_date DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Find all active contracts for a client.
     */
    public static function findActiveByClient(int $clientId): array
    {
        return self::findByClient($clientId, 'active');
    }

    /**
     * Create a new contract.
     */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('employee_contracts', $data);
    }

    /**
     * Update an existing contract.
     */
    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('employee_contracts', $data, 'id = ?', [$id]);
    }
}
