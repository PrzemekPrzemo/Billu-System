<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrPpkEnrollment
{
    public static function findByEmployee(int $empId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_ppk_enrollments WHERE employee_id = ? ORDER BY created_at DESC",
            [$empId]
        );
    }

    public static function getEnrolledForClient(int $clientId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT e.id, e.first_name, e.last_name,
                    e.employment_start, e.ppk_institution,
                    e.ppk_enrolled_at, e.ppk_opted_out_at,
                    e.ppk_enrolled,
                    c.base_salary, c.ppk_employee_rate, c.ppk_employer_rate
             FROM hr_employees e
             LEFT JOIN hr_contracts c ON c.employee_id = e.id AND c.is_current = 1
             WHERE e.client_id = ? AND e.is_active = 1
             ORDER BY e.last_name, e.first_name",
            [$clientId]
        );
    }

    public static function getAutoEnrollmentAlerts(int $clientId): array
    {
        $threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));

        return HrDatabase::getInstance()->fetchAll(
            "SELECT e.id, e.first_name, e.last_name, e.employment_start,
                    TIMESTAMPDIFF(MONTH, e.employment_start, CURDATE()) AS months_employed
             FROM hr_employees e
             WHERE e.client_id = ?
               AND e.is_active = 1
               AND e.ppk_enrolled = 0
               AND e.ppk_opted_out_at IS NULL
               AND e.employment_start <= ?
             ORDER BY e.employment_start ASC",
            [$clientId, $threeMonthsAgo]
        );
    }

    public static function enroll(int $empId, int $clientId, array $data): void
    {
        $db = HrDatabase::getInstance();

        $effectiveDate = $data['effective_date'] ?? date('Y-m-d');
        $institution   = $data['institution']    ?? null;
        $empRate       = (float)($data['employee_rate'] ?? 2.00);
        $emplRate      = (float)($data['employer_rate'] ?? 1.50);

        $db->insert('hr_ppk_enrollments', [
            'employee_id'    => $empId,
            'client_id'      => $clientId,
            'action'         => 'enroll',
            'effective_date' => $effectiveDate,
            'institution'    => $institution,
            'employee_rate'  => $empRate,
            'employer_rate'  => $emplRate,
        ]);

        $db->update('hr_employees', [
            'ppk_enrolled'     => 1,
            'ppk_enrolled_at'  => $effectiveDate,
            'ppk_opted_out_at' => null,
            'ppk_institution'  => $institution,
        ], 'id = ?', [$empId]);

        $db->update('hr_contracts', [
            'ppk_enrolled'      => 1,
            'ppk_employee_rate' => $empRate,
            'ppk_employer_rate' => $emplRate,
        ], 'employee_id = ? AND is_current = 1', [$empId]);
    }

    public static function optOut(int $empId, int $clientId, string $date): void
    {
        $db = HrDatabase::getInstance();

        $db->insert('hr_ppk_enrollments', [
            'employee_id'    => $empId,
            'client_id'      => $clientId,
            'action'         => 'opt_out',
            'effective_date' => $date,
            'employee_rate'  => 0.00,
            'employer_rate'  => 0.00,
        ]);

        $db->update('hr_employees', [
            'ppk_enrolled'     => 0,
            'ppk_opted_out_at' => $date,
        ], 'id = ?', [$empId]);

        $db->update('hr_contracts', [
            'ppk_enrolled' => 0,
        ], 'employee_id = ? AND is_current = 1', [$empId]);
    }
}
