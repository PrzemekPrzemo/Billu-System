<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrContract
{
    public static function findById(int $id): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_contracts WHERE id = ?",
            [$id]
        );
    }

    public static function findCurrentByEmployee(int $employeeId): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_contracts WHERE employee_id = ? AND is_current = 1 ORDER BY start_date DESC LIMIT 1",
            [$employeeId]
        );
    }

    public static function findByEmployee(int $employeeId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_contracts WHERE employee_id = ? ORDER BY start_date DESC",
            [$employeeId]
        );
    }

    public static function findByClient(int $clientId, bool $currentOnly = false): array
    {
        $sql = "SELECT c.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
                FROM hr_contracts c
                JOIN hr_employees e ON c.employee_id = e.id
                WHERE c.client_id = ?";
        if ($currentOnly) $sql .= " AND c.is_current = 1";
        $sql .= " ORDER BY e.last_name, e.first_name, c.start_date DESC";
        return HrDatabase::getInstance()->fetchAll($sql, [$clientId]);
    }

    public static function create(array $data): int
    {
        if (!empty($data['employee_id']) && ($data['is_current'] ?? 1)) {
            HrDatabase::getInstance()->update(
                'hr_contracts',
                ['is_current' => 0],
                'employee_id = ?',
                [$data['employee_id']]
            );
        }
        return HrDatabase::getInstance()->insert('hr_contracts', $data);
    }

    public static function update(int $id, array $data): int
    {
        return HrDatabase::getInstance()->update('hr_contracts', $data, 'id = ?', [$id]);
    }

    public static function terminate(int $id, string $endDate): void
    {
        HrDatabase::getInstance()->update('hr_contracts', [
            'end_date'   => $endDate,
            'is_current' => 0,
        ], 'id = ?', [$id]);
    }

    public static function getContractTypeLabel(string $type): string
    {
        return match($type) {
            'uop' => 'Umowa o pracę',
            'uz'  => 'Umowa zlecenie',
            'uod' => 'Umowa o dzieło',
            default => $type,
        };
    }

    public static function markWithPdf(int $id, string $path): void
    {
        HrDatabase::getInstance()->update('hr_contracts', [
            'umowa_pdf_path' => $path,
        ], 'id = ?', [$id]);
    }
}
