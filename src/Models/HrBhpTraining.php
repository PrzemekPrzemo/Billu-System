<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrBhpTraining
{
    public const TYPE_LABELS = [
        'wstepne'       => 'Szkolenie wstępne',
        'okresowe'      => 'Szkolenie okresowe',
        'stanowiskowe'  => 'Instruktaż stanowiskowy',
        'bhp_ogolne'    => 'Szkolenie ogólne BHP',
    ];

    public static function findByEmployee(int $employeeId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_bhp_trainings WHERE employee_id = ? ORDER BY completed_at DESC",
            [$employeeId]
        );
    }

    public static function findByClient(int $clientId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT t.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
             FROM hr_bhp_trainings t
             JOIN hr_employees e ON t.employee_id = e.id
             WHERE t.client_id = ?
             ORDER BY t.expires_at ASC",
            [$clientId]
        );
    }

    public static function findExpiring(int $clientId, int $days = 30): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT t.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
             FROM hr_bhp_trainings t
             JOIN hr_employees e ON t.employee_id = e.id
             WHERE t.client_id = ? AND e.is_active = 1
               AND t.expires_at IS NOT NULL
               AND t.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY t.expires_at ASC",
            [$clientId, $days]
        );
    }

    public static function findExpiredOrExpiring(int $clientId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT t.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    CASE WHEN t.expires_at < CURDATE() THEN 'expired'
                         WHEN t.expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring'
                         ELSE 'valid' END AS status
             FROM hr_bhp_trainings t
             JOIN hr_employees e ON t.employee_id = e.id
             WHERE t.client_id = ? AND e.is_active = 1
               AND t.expires_at IS NOT NULL
               AND t.expires_at <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
             ORDER BY t.expires_at ASC",
            [$clientId]
        );
    }

    public static function create(array $data): int
    {
        return HrDatabase::getInstance()->insert('hr_bhp_trainings', $data);
    }

    public static function findById(int $id): ?array
    {
        return HrDatabase::getInstance()->fetchOne("SELECT * FROM hr_bhp_trainings WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void
    {
        HrDatabase::getInstance()->query("DELETE FROM hr_bhp_trainings WHERE id = ?", [$id]);
    }

    public static function findExpiringCrossClient(array $clientIds, int $days = 30): array
    {
        if (empty($clientIds)) return [];
        $ph = implode(',', array_fill(0, count($clientIds), '?'));
        return HrDatabase::getInstance()->fetchAll(
            "SELECT t.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.client_id
             FROM hr_bhp_trainings t
             JOIN hr_employees e ON t.employee_id = e.id
             WHERE t.client_id IN ({$ph}) AND e.is_active = 1
               AND t.expires_at IS NOT NULL
               AND t.expires_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY t.expires_at ASC",
            array_merge($clientIds, [$days])
        );
    }
}
