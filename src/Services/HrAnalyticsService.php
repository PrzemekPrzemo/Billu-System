<?php

namespace App\Services;

use App\Core\HrDatabase;

class HrAnalyticsService
{
    public static function getKpiSummary(int $clientId): array
    {
        $db   = HrDatabase::getInstance();
        $year = (int) date('Y');

        $row = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees WHERE client_id = ? AND is_active = 1",
            [$clientId]
        );
        $activeEmployees = (int) ($row['cnt'] ?? 0);

        $row = $db->fetchOne(
            "SELECT AVG(c.base_salary) AS avg_sal
             FROM hr_employees e
             JOIN hr_contracts c ON c.employee_id = e.id AND c.is_current = 1
             WHERE e.client_id = ? AND e.is_active = 1",
            [$clientId]
        );
        $avgSalary = round((float)($row['avg_sal'] ?? 0), 2);

        $row = $db->fetchOne(
            "SELECT total_employer_cost FROM hr_payroll_runs
             WHERE client_id = ? AND status != 'draft'
             ORDER BY period_year DESC, period_month DESC LIMIT 1",
            [$clientId]
        );
        $costCurrentMonth = (float)($row['total_employer_cost'] ?? 0);

        $row = $db->fetchOne(
            "SELECT COALESCE(SUM(total_employer_cost), 0) AS total
             FROM hr_payroll_runs
             WHERE client_id = ? AND status != 'draft' AND period_year = ?",
            [$clientId, $year]
        );
        $costYtd = (float)($row['total'] ?? 0);

        $row = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND YEAR(employment_start) = ?",
            [$clientId, $year]
        );
        $hiredYtd = (int)($row['cnt'] ?? 0);

        $row = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND YEAR(archived_at) = ?",
            [$clientId, $year]
        );
        $leftYtd = (int)($row['cnt'] ?? 0);

        return [
            'active_employees'    => $activeEmployees,
            'cost_current_month'  => $costCurrentMonth,
            'cost_ytd'            => $costYtd,
            'avg_salary'          => $avgSalary,
            'employees_hired_ytd' => $hiredYtd,
            'employees_left_ytd'  => $leftYtd,
        ];
    }

    public static function getMonthlyCostTrend(int $clientId, int $months = 12): array
    {
        $db   = HrDatabase::getInstance();
        $from = date('Y-m-d', strtotime("-{$months} months"));

        $rows = $db->fetchAll(
            "SELECT period_year AS year, period_month AS month,
                    total_gross, total_employer_cost
             FROM hr_payroll_runs
             WHERE client_id = ? AND status != 'draft'
               AND STR_TO_DATE(CONCAT(period_year, '-', LPAD(period_month,2,'0'), '-01'), '%Y-%m-%d') >= ?
             ORDER BY period_year ASC, period_month ASC",
            [$clientId, $from]
        );

        return array_map(fn($r) => [
            'year'               => (int) $r['year'],
            'month'              => (int) $r['month'],
            'total_gross'        => (float) $r['total_gross'],
            'total_employer_cost'=> (float) $r['total_employer_cost'],
        ], $rows);
    }

    public static function getContractTypeDistribution(int $clientId): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT c.contract_type, COUNT(*) AS cnt
             FROM hr_employees e
             JOIN hr_contracts c ON c.employee_id = e.id AND c.is_current = 1
             WHERE e.client_id = ? AND e.is_active = 1
             GROUP BY c.contract_type",
            [$clientId]
        );

        return array_map(fn($r) => [
            'contract_type' => $r['contract_type'],
            'count'         => (int) $r['cnt'],
        ], $rows);
    }

    public static function getTopEmployeesByCost(int $clientId, int $limit = 5): array
    {
        $db  = HrDatabase::getInstance();
        $run = $db->fetchOne(
            "SELECT id FROM hr_payroll_runs
             WHERE client_id = ? AND status != 'draft'
             ORDER BY period_year DESC, period_month DESC LIMIT 1",
            [$clientId]
        );

        if (!$run) return [];

        $runId = (int) $run['id'];

        return $db->fetchAll(
            "SELECT CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    pi.position, pi.employer_total_cost
             FROM hr_payroll_items pi
             JOIN hr_employees e ON pi.employee_id = e.id
             WHERE pi.payroll_run_id = ?
             ORDER BY pi.employer_total_cost DESC
             LIMIT ?",
            [$runId, $limit]
        );
    }

    public static function getRotationRate(int $clientId, int $year): float
    {
        $db = HrDatabase::getInstance();

        $row = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND YEAR(archived_at) = ?",
            [$clientId, $year]
        );
        $archived = (int)($row['cnt'] ?? 0);

        $row = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND employment_start < ?",
            [$clientId, $year . '-01-01']
        );
        $atStart = (int)($row['cnt'] ?? 0);

        $row = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND is_active = 1",
            [$clientId]
        );
        $atEnd = (int)($row['cnt'] ?? 0);

        $avgHeadcount = ($atStart + $atEnd) / 2;

        if ($avgHeadcount <= 0) return 0.0;

        return round(($archived / $avgHeadcount) * 100, 1);
    }
}
