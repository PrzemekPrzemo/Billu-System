<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrMedicalExam
{
    public const TYPE_LABELS = [
        'wstepne'   => 'Badanie wstępne',
        'okresowe'  => 'Badanie okresowe',
        'kontrolne' => 'Badanie kontrolne',
    ];

    public const RESULT_LABELS = [
        'zdolny'       => 'Zdolny do pracy',
        'niezdolny'    => 'Niezdolny do pracy',
        'ograniczenia' => 'Zdolny z ograniczeniami',
    ];

    public static function findByEmployee(int $employeeId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_medical_exams WHERE employee_id = ? ORDER BY exam_date DESC",
            [$employeeId]
        );
    }

    public static function findByClient(int $clientId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT m.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
             FROM hr_medical_exams m
             JOIN hr_employees e ON m.employee_id = e.id
             WHERE m.client_id = ?
             ORDER BY m.valid_until ASC",
            [$clientId]
        );
    }

    public static function findExpiredOrExpiring(int $clientId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT m.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    CASE WHEN m.valid_until < CURDATE() THEN 'expired'
                         WHEN m.valid_until <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring'
                         ELSE 'valid' END AS status
             FROM hr_medical_exams m
             JOIN hr_employees e ON m.employee_id = e.id
             WHERE m.client_id = ? AND e.is_active = 1
               AND m.valid_until IS NOT NULL
               AND m.valid_until <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
             ORDER BY m.valid_until ASC",
            [$clientId]
        );
    }

    public static function create(array $data): int
    {
        return HrDatabase::getInstance()->insert('hr_medical_exams', $data);
    }

    public static function findById(int $id): ?array
    {
        return HrDatabase::getInstance()->fetchOne("SELECT * FROM hr_medical_exams WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void
    {
        HrDatabase::getInstance()->query("DELETE FROM hr_medical_exams WHERE id = ?", [$id]);
    }

    public static function findExpiringCrossClient(array $clientIds, int $days = 30): array
    {
        if (empty($clientIds)) return [];
        $ph = implode(',', array_fill(0, count($clientIds), '?'));
        return HrDatabase::getInstance()->fetchAll(
            "SELECT m.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.client_id
             FROM hr_medical_exams m
             JOIN hr_employees e ON m.employee_id = e.id
             WHERE m.client_id IN ({$ph}) AND e.is_active = 1
               AND m.valid_until IS NOT NULL
               AND m.valid_until <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY m.valid_until ASC",
            array_merge($clientIds, [$days])
        );
    }
}
