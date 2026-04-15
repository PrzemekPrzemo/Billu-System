<?php

namespace App\Models;

use App\Core\Database;

class PayrollEntry
{
    /**
     * Find a payroll entry by ID.
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM payroll_entries WHERE id = ?",
            [$id]
        );
    }

    /**
     * Find all entries for a payroll list.
     */
    public static function findByPayrollList(int $payrollListId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT pe.*, ce.first_name, ce.last_name
             FROM payroll_entries pe
             JOIN client_employees ce ON ce.id = pe.employee_id
             WHERE pe.payroll_list_id = ?
             ORDER BY ce.last_name ASC, ce.first_name ASC",
            [$payrollListId]
        );
    }

    /**
     * Find entries for an employee, optionally filtered by year.
     */
    public static function findByEmployee(int $employeeId, ?int $year = null): array
    {
        $sql = "SELECT pe.*, pl.year, pl.month
                FROM payroll_entries pe
                JOIN payroll_lists pl ON pl.id = pe.payroll_list_id
                WHERE pe.employee_id = ?";
        $params = [$employeeId];

        if ($year !== null) {
            $sql .= " AND pl.year = ?";
            $params[] = $year;
        }

        $sql .= " ORDER BY pl.year DESC, pl.month DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Create a new payroll entry.
     */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('payroll_entries', $data);
    }

    /**
     * Delete all entries for a payroll list (e.g. before recalculation).
     */
    public static function deleteByPayrollList(int $payrollListId): void
    {
        Database::getInstance()->query(
            "DELETE FROM payroll_entries WHERE payroll_list_id = ?",
            [$payrollListId]
        );
    }
}
