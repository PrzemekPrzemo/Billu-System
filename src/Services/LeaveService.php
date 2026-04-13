<?php

namespace App\Services;

use App\Core\Database;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;

class LeaveService
{
    /**
     * Polish public holidays for 2026.
     * Update yearly.
     */
    private const HOLIDAYS_2026 = [
        '2026-01-01', // Nowy Rok
        '2026-01-06', // Trzech Króli
        '2026-04-05', // Wielkanoc
        '2026-04-06', // Poniedziałek Wielkanocny
        '2026-05-01', // Święto Pracy
        '2026-05-03', // Święto Konstytucji
        '2026-05-24', // Zielone Świątki
        '2026-06-04', // Boże Ciało
        '2026-08-15', // Wniebowzięcie NMP
        '2026-11-01', // Wszystkich Świętych
        '2026-11-11', // Święto Niepodległości
        '2026-12-25', // Boże Narodzenie
        '2026-12-26', // Drugi dzień Bożego Narodzenia
    ];

    /**
     * Calculate business days between two dates (excluding weekends and Polish holidays).
     */
    public static function calculateBusinessDays(string $startDate, string $endDate): int
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $days = 0;

        $current = clone $start;
        while ($current <= $end) {
            $dayOfWeek = (int)$current->format('N'); // 1=Mon, 7=Sun
            $dateStr = $current->format('Y-m-d');

            if ($dayOfWeek <= 5 && !in_array($dateStr, self::HOLIDAYS_2026, true)) {
                $days++;
            }
            $current->modify('+1 day');
        }

        return $days;
    }

    /**
     * Request a leave for an employee.
     */
    public static function requestLeave(
        int $clientId,
        int $employeeId,
        int $contractId,
        string $leaveType,
        string $startDate,
        string $endDate,
        ?string $notes = null
    ): ?int {
        $businessDays = self::calculateBusinessDays($startDate, $endDate);
        if ($businessDays <= 0) {
            return null;
        }

        // For wypoczynkowy: check balance
        if ($leaveType === 'wypoczynkowy' || $leaveType === 'na_zadanie') {
            $year = (int)date('Y', strtotime($startDate));
            $remaining = EmployeeLeaveBalance::getRemaining($employeeId, $contractId, $year);

            if ($businessDays > $remaining) {
                return null; // Insufficient balance
            }

            // Check na_zadanie limit (max 4 per year)
            if ($leaveType === 'na_zadanie') {
                $balance = EmployeeLeaveBalance::findByEmployeeYear($employeeId, $contractId, $year);
                $onDemandUsed = $balance ? (int)$balance['on_demand_used'] : 0;
                if ($onDemandUsed + $businessDays > 4) {
                    return null;
                }
            }
        }

        return EmployeeLeave::create([
            'client_id' => $clientId,
            'employee_id' => $employeeId,
            'contract_id' => $contractId,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'business_days' => $businessDays,
            'status' => 'pending',
            'notes' => $notes,
        ]);
    }

    /**
     * Approve a leave request and update balance.
     */
    public static function approveLeave(int $leaveId, string $byType, int $byId): bool
    {
        $leave = EmployeeLeave::findById($leaveId);
        if (!$leave || $leave['status'] !== 'pending') {
            return false;
        }

        EmployeeLeave::approve($leaveId, $byType, $byId);

        // Update balance for vacation-type leaves
        if (in_array($leave['leave_type'], ['wypoczynkowy', 'na_zadanie'], true)) {
            $year = (int)date('Y', strtotime($leave['start_date']));
            EmployeeLeaveBalance::incrementUsed(
                (int)$leave['employee_id'],
                (int)$leave['contract_id'],
                $year,
                (int)$leave['business_days']
            );

            // Track on-demand separately
            if ($leave['leave_type'] === 'na_zadanie') {
                $balance = EmployeeLeaveBalance::findByEmployeeYear(
                    (int)$leave['employee_id'],
                    (int)$leave['contract_id'],
                    $year
                );
                if ($balance) {
                    Database::getInstance()->query(
                        "UPDATE employee_leave_balances SET on_demand_used = on_demand_used + ? WHERE id = ?",
                        [(int)$leave['business_days'], $balance['id']]
                    );
                }
            }
        }

        return true;
    }

    /**
     * Reject a leave request.
     */
    public static function rejectLeave(int $leaveId, string $byType, int $byId): bool
    {
        $leave = EmployeeLeave::findById($leaveId);
        if (!$leave || $leave['status'] !== 'pending') {
            return false;
        }

        EmployeeLeave::reject($leaveId, $byType, $byId);
        return true;
    }

    /**
     * Initialize leave balance for a new year.
     */
    public static function initBalance(int $employeeId, int $contractId, int $year, int $annualEntitlement = 20, int $carriedOver = 0): void
    {
        EmployeeLeaveBalance::upsert($employeeId, $contractId, $year, [
            'annual_entitlement' => $annualEntitlement,
            'carried_over' => $carriedOver,
        ]);
    }

    /**
     * Get leave calendar for a client (all employees, given month).
     */
    public static function getCalendar(int $clientId, int $year, int $month): array
    {
        $db = Database::getInstance();
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        return $db->fetchAll(
            "SELECT el.*, ce.first_name, ce.last_name
             FROM employee_leaves el
             INNER JOIN client_employees ce ON ce.id = el.employee_id
             WHERE el.client_id = ?
               AND el.status = 'approved'
               AND el.start_date <= ?
               AND el.end_date >= ?
             ORDER BY el.start_date ASC",
            [$clientId, $endDate, $startDate]
        );
    }

    /**
     * Get all leave types with Polish labels.
     */
    public static function getLeaveTypes(): array
    {
        return [
            'wypoczynkowy' => 'Urlop wypoczynkowy',
            'chorobowy' => 'Zwolnienie chorobowe',
            'macierzynski' => 'Urlop macierzyński',
            'ojcowski' => 'Urlop ojcowski',
            'wychowawczy' => 'Urlop wychowawczy',
            'bezplatny' => 'Urlop bezpłatny',
            'okolicznosciowy' => 'Urlop okolicznościowy',
            'na_zadanie' => 'Urlop na żądanie',
            'opieka_art188' => 'Opieka nad dzieckiem (art. 188 KP)',
        ];
    }
}
