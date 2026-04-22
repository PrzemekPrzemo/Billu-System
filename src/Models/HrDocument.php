<?php

namespace App\Models;

use App\Core\HrDatabase;
use App\Services\HrEncryptionService;

class HrDocument
{
    public static function findById(int $id): ?array
    {
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_documents WHERE id = ?",
            [$id]
        );
        if ($row) {
            $row = HrEncryptionService::decryptFields($row, ['original_name']);
        }
        return $row;
    }

    public static function findByEmployee(int $employeeId): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_documents WHERE employee_id = ? ORDER BY uploaded_at DESC",
            [$employeeId]
        );
        return HrEncryptionService::decryptRows($rows, ['original_name']);
    }

    public static function findExpiringSoon(int $days): array
    {
        $col = match($days) {
            30 => 'alert_sent_30',
            14 => 'alert_sent_14',
            7  => 'alert_sent_7',
            default => null,
        };

        if ($col === null) return [];

        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT d.*,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.client_id
             FROM hr_documents d
             JOIN hr_employees e ON d.employee_id = e.id
             WHERE d.expiry_date IS NOT NULL
               AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND d.{$col} = 0",
            [$days]
        );
        return HrEncryptionService::decryptRows($rows, ['original_name']);
    }

    public static function create(array $data): int
    {
        $data = HrEncryptionService::encryptFields($data, ['original_name']);
        return HrDatabase::getInstance()->insert('hr_documents', $data);
    }

    public static function delete(int $id): void
    {
        HrDatabase::getInstance()->query(
            "DELETE FROM hr_documents WHERE id = ?",
            [$id]
        );
    }

    public static function markAlertSent(int $id, int $days): void
    {
        $col = match($days) {
            30 => 'alert_sent_30',
            14 => 'alert_sent_14',
            7  => 'alert_sent_7',
            default => null,
        };

        if ($col === null) return;

        HrDatabase::getInstance()->update('hr_documents', [$col => 1], 'id = ?', [$id]);
    }
}
