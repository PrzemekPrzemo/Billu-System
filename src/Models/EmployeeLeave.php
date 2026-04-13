<?php

namespace App\Models;

use App\Core\Database;

class EmployeeLeave
{
    /**
     * Find a leave record by ID.
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM employee_leaves WHERE id = ?",
            [$id]
        );
    }

    /**
     * Find leaves by client, optionally filtered by status.
     */
    public static function findByClient(int $clientId, ?string $status = null): array
    {
        $sql = "SELECT el.*, ce.first_name, ce.last_name
                FROM employee_leaves el
                JOIN client_employees ce ON ce.id = el.employee_id
                WHERE el.client_id = ?";
        $params = [$clientId];

        if ($status !== null) {
            $sql .= " AND el.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY el.start_date DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Find leaves by employee, optionally filtered by year.
     */
    public static function findByEmployee(int $employeeId, ?int $year = null): array
    {
        $sql = "SELECT * FROM employee_leaves WHERE employee_id = ?";
        $params = [$employeeId];

        if ($year !== null) {
            $sql .= " AND YEAR(start_date) = ?";
            $params[] = $year;
        }

        $sql .= " ORDER BY start_date DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Create a new leave record.
     */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('employee_leaves', $data);
    }

    /**
     * Update an existing leave record.
     */
    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('employee_leaves', $data, 'id = ?', [$id]);
    }

    /**
     * Approve a leave request.
     */
    public static function approve(int $id, string $byType, int $byId): void
    {
        Database::getInstance()->update('employee_leaves', [
            'status' => 'approved',
            'approved_by_type' => $byType,
            'approved_by_id' => $byId,
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    /**
     * Reject a leave request.
     */
    public static function reject(int $id, string $byType, int $byId): void
    {
        Database::getInstance()->update('employee_leaves', [
            'status' => 'rejected',
            'approved_by_type' => $byType,
            'approved_by_id' => $byId,
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }
}
