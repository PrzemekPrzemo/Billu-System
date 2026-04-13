<?php

namespace App\Models;

use App\Core\Database;

class EmployeeLeaveBalance
{
    /**
     * Find leave balance for a specific employee, contract, and year.
     */
    public static function findByEmployeeYear(int $employeeId, int $contractId, int $year): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM employee_leave_balances WHERE employee_id = ? AND contract_id = ? AND year = ?",
            [$employeeId, $contractId, $year]
        );
    }

    /**
     * Insert or update leave balance for an employee/contract/year.
     */
    public static function upsert(int $employeeId, int $contractId, int $year, array $data): void
    {
        $db = Database::getInstance();
        $existing = self::findByEmployeeYear($employeeId, $contractId, $year);

        if ($existing) {
            $db->update('employee_leave_balances', $data, 'id = ?', [$existing['id']]);
        } else {
            $db->insert('employee_leave_balances', array_merge([
                'employee_id' => $employeeId,
                'contract_id' => $contractId,
                'year' => $year,
            ], $data));
        }
    }

    /**
     * Increment used days for an employee/contract/year.
     */
    public static function incrementUsed(int $employeeId, int $contractId, int $year, int $days): void
    {
        $db = Database::getInstance();
        $existing = self::findByEmployeeYear($employeeId, $contractId, $year);

        if ($existing) {
            $db->query(
                "UPDATE employee_leave_balances SET used_days = used_days + ? WHERE id = ?",
                [$days, $existing['id']]
            );
        } else {
            $db->insert('employee_leave_balances', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId,
                'year' => $year,
                'used_days' => $days,
            ]);
        }
    }

    /**
     * Get remaining leave days for an employee/contract/year.
     */
    public static function getRemaining(int $employeeId, int $contractId, int $year): int
    {
        $row = self::findByEmployeeYear($employeeId, $contractId, $year);

        if (!$row) {
            return 0;
        }

        return (int) $row['annual_entitlement'] + (int) $row['carried_over'] - (int) $row['used_days'];
    }
}
