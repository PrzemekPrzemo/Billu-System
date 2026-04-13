<?php

namespace App\Models;

use App\Core\Database;

class PayrollList
{
    /**
     * Find a payroll list by ID.
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM payroll_lists WHERE id = ?",
            [$id]
        );
    }

    /**
     * Find payroll lists by client, optionally filtered by year.
     */
    public static function findByClient(int $clientId, ?int $year = null): array
    {
        $sql = "SELECT * FROM payroll_lists WHERE client_id = ?";
        $params = [$clientId];

        if ($year !== null) {
            $sql .= " AND year = ?";
            $params[] = $year;
        }

        $sql .= " ORDER BY year DESC, month DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Find a payroll list for a specific client, year, and month.
     */
    public static function findByClientPeriod(int $clientId, int $year, int $month): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM payroll_lists WHERE client_id = ? AND year = ? AND month = ?",
            [$clientId, $year, $month]
        );
    }

    /**
     * Create a new payroll list.
     */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('payroll_lists', $data);
    }

    /**
     * Update an existing payroll list.
     */
    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('payroll_lists', $data, 'id = ?', [$id]);
    }

    /**
     * Approve a payroll list.
     */
    public static function approve(int $id, string $byType, int $byId): void
    {
        Database::getInstance()->update('payroll_lists', [
            'status' => 'approved',
            'approved_by_type' => $byType,
            'approved_by_id' => $byId,
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }
}
