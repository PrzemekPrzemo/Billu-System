<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrPayrollBudget
{
    public static function findByClientAndYear(int $clientId, int $year): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_payroll_budget WHERE client_id = ? AND budget_year = ? ORDER BY period_month",
            [$clientId, $year]
        );
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int)$row['period_month']] = $row;
        }
        return $indexed;
    }

    public static function upsertMonth(int $clientId, int $year, int $month, float $plannedGross, float $plannedCost, ?string $notes = null): void
    {
        $db = HrDatabase::getInstance();
        $db->query(
            "INSERT INTO hr_payroll_budget (client_id, budget_year, period_month, planned_gross, planned_cost, notes)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                planned_gross = VALUES(planned_gross),
                planned_cost  = VALUES(planned_cost),
                notes         = VALUES(notes)",
            [$clientId, $year, $month, $plannedGross, $plannedCost, $notes]
        );
    }

    public static function upsertAllMonths(int $clientId, int $year, array $monthData): void
    {
        foreach (range(1, 12) as $m) {
            if (!isset($monthData[$m])) continue;
            $row = $monthData[$m];
            self::upsertMonth(
                $clientId,
                $year,
                $m,
                (float) ($row['planned_gross'] ?? 0),
                (float) ($row['planned_cost']  ?? 0),
                $row['notes'] ?? null
            );
        }
    }

    public static function deleteByClientAndYear(int $clientId, int $year): void
    {
        HrDatabase::getInstance()->query(
            "DELETE FROM hr_payroll_budget WHERE client_id = ? AND budget_year = ?",
            [$clientId, $year]
        );
    }

    public static function getYearsForClient(int $clientId): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT DISTINCT budget_year FROM hr_payroll_budget WHERE client_id = ? ORDER BY budget_year DESC",
            [$clientId]
        );
        return array_column($rows, 'budget_year');
    }
}
