<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrLeaveBalance
{
    public static function findByEmployeeYear(int $employeeId, int $year): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT lb.*, lt.name_pl, lt.name_en, lt.code,
                    (lb.limit_days + lb.carried_over - lb.used_days - lb.planned_days) AS remaining_days
             FROM hr_leave_balances lb
             JOIN hr_leave_types lt ON lb.leave_type_id = lt.id
             WHERE lb.employee_id = ? AND lb.year = ?
             ORDER BY lt.sort_order",
            [$employeeId, $year]
        );
    }

    public static function findByClientYear(int $clientId, int $year): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT lb.*, lt.name_pl, lt.code,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    (lb.limit_days + lb.carried_over - lb.used_days - lb.planned_days) AS remaining_days
             FROM hr_leave_balances lb
             JOIN hr_leave_types lt ON lb.leave_type_id = lt.id
             JOIN hr_employees e ON lb.employee_id = e.id
             WHERE lb.client_id = ? AND lb.year = ?
             ORDER BY e.last_name, e.first_name, lt.sort_order",
            [$clientId, $year]
        );
    }

    public static function getOrCreate(int $employeeId, int $clientId, int $year, int $leaveTypeId, float $limitDays = 0.0): array
    {
        $db = HrDatabase::getInstance();
        $existing = $db->fetchOne(
            "SELECT * FROM hr_leave_balances WHERE employee_id = ? AND year = ? AND leave_type_id = ?",
            [$employeeId, $year, $leaveTypeId]
        );
        if ($existing) return $existing;

        $db->insert('hr_leave_balances', [
            'employee_id'   => $employeeId,
            'client_id'     => $clientId,
            'year'          => $year,
            'leave_type_id' => $leaveTypeId,
            'limit_days'    => $limitDays,
        ]);

        return $db->fetchOne(
            "SELECT * FROM hr_leave_balances WHERE employee_id = ? AND year = ? AND leave_type_id = ?",
            [$employeeId, $year, $leaveTypeId]
        );
    }

    public static function initForYear(int $employeeId, int $clientId, int $year, int $annualLeaveDays): void
    {
        $wypoczynkowy = HrLeaveType::findByCode('wypoczynkowy');
        if (!$wypoczynkowy) return;

        HrDatabase::getInstance()->query(
            "INSERT INTO hr_leave_balances (employee_id, client_id, year, leave_type_id, limit_days)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE limit_days = VALUES(limit_days)",
            [$employeeId, $clientId, $year, $wypoczynkowy['id'], $annualLeaveDays]
        );
    }

    public static function adjustUsed(int $employeeId, int $year, int $leaveTypeId, float $days, bool $planned = false): void
    {
        $col = $planned ? 'planned_days' : 'used_days';
        HrDatabase::getInstance()->query(
            "UPDATE hr_leave_balances SET {$col} = {$col} + ?
             WHERE employee_id = ? AND year = ? AND leave_type_id = ?",
            [$days, $employeeId, $year, $leaveTypeId]
        );
    }

    public static function adjustPlanned(int $employeeId, int $year, int $leaveTypeId, float $delta): void
    {
        HrDatabase::getInstance()->query(
            "UPDATE hr_leave_balances SET planned_days = GREATEST(0, planned_days + ?)
             WHERE employee_id = ? AND year = ? AND leave_type_id = ?",
            [$delta, $employeeId, $year, $leaveTypeId]
        );
    }

    public static function initForYearWithCarryover(
        int $employeeId,
        int $clientId,
        int $year,
        int $annualLeaveDays,
        float $carriedOver = 0.0
    ): void {
        $wypoczynkowy = HrLeaveType::findByCode('wypoczynkowy');
        if (!$wypoczynkowy) return;

        HrDatabase::getInstance()->query(
            "INSERT INTO hr_leave_balances
                (employee_id, client_id, year, leave_type_id, limit_days, carried_over)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                limit_days   = VALUES(limit_days),
                carried_over = VALUES(carried_over)",
            [$employeeId, $clientId, $year, $wypoczynkowy['id'], $annualLeaveDays, $carriedOver]
        );
    }
}
