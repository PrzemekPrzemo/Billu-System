<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrPfronDeclaration;

class HrPfronService
{
    private const DEFAULT_AVG_SALARY = 8_061.00;
    private const DISABILITY_RATIO_THRESHOLD = 0.06;
    private const LEVY_COEFFICIENT = 0.4065;
    private const MIN_EMPLOYEES = 25;

    public static function calculate(int $clientId, int $month, int $year, string $actorType, int $actorId): array
    {
        $db = HrDatabase::getInstance();

        $periodEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        $totalEmployees = (int) ($db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND is_active = 1
               AND (employment_start IS NULL OR employment_start <= ?)",
            [$clientId, $periodEnd]
        )['cnt'] ?? 0);

        $disabledEmployees = (int) ($db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND is_active = 1
               AND disability_level != 'none'
               AND (employment_start IS NULL OR employment_start <= ?)",
            [$clientId, $periodEnd]
        )['cnt'] ?? 0);

        $ratio = $totalEmployees > 0 ? $disabledEmployees / $totalEmployees : 0;
        $liable = $totalEmployees >= self::MIN_EMPLOYEES && $ratio < self::DISABILITY_RATIO_THRESHOLD;

        $avgSalary = self::DEFAULT_AVG_SALARY;
        $levyAmount = 0;

        if ($liable) {
            $required  = $totalEmployees * self::DISABILITY_RATIO_THRESHOLD;
            $missing   = max(0, $required - $disabledEmployees);
            $levyAmount = round(self::LEVY_COEFFICIENT * $avgSalary * $missing, 2);
        }

        $data = [
            'total_employees'    => $totalEmployees,
            'disabled_employees' => $disabledEmployees,
            'disability_ratio'   => round($ratio, 4),
            'pfron_liable'       => $liable ? 1 : 0,
            'levy_amount'        => $levyAmount,
            'avg_salary'         => $avgSalary,
            'status'             => 'calculated',
            'calculated_by_type' => $actorType,
            'calculated_by_id'   => $actorId,
        ];

        $id = HrPfronDeclaration::upsert($clientId, $month, $year, $data);

        return $data + ['id' => $id];
    }
}
