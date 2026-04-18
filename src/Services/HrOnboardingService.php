<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrOnboardingTask;
use App\Models\HrEmployee;

class HrOnboardingService
{
    public static function initOnboarding(int $empId, int $clientId): void
    {
        HrOnboardingTask::createDefaultsForEmployee($empId, $clientId, 'onboarding');
    }

    public static function initOffboarding(int $empId, int $clientId): void
    {
        HrOnboardingTask::createDefaultsForEmployee($empId, $clientId, 'offboarding');
    }

    public static function getOnboardingStatus(int $clientId): array
    {
        $db = HrDatabase::getInstance();

        $rows = $db->fetchAll(
            "SELECT ot.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                    COUNT(*) AS total,
                    SUM(ot.is_done) AS done
             FROM hr_onboarding_tasks ot
             JOIN hr_employees e ON ot.employee_id = e.id
             WHERE ot.client_id = ? AND ot.phase = 'onboarding'
               AND e.is_active = 1
             GROUP BY ot.employee_id, e.first_name, e.last_name
             HAVING done < total
             ORDER BY done / total ASC",
            [$clientId]
        );

        return array_map(function ($row) {
            $total = (int) $row['total'];
            $done  = (int) $row['done'];
            return [
                'employee_id' => (int)  $row['employee_id'],
                'full_name'   => $row['full_name'],
                'done'        => $done,
                'total'       => $total,
                'pct'         => $total > 0 ? (int)round($done / $total * 100) : 0,
            ];
        }, $rows);
    }
}
