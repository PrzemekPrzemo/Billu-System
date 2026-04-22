<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrZusDeclaration
{
    public static function findById(int $id): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT d.*, r.status AS run_status
             FROM hr_zus_declarations d
             LEFT JOIN hr_payroll_runs r ON d.payroll_run_id = r.id
             WHERE d.id = ?",
            [$id]
        );
    }

    public static function findByClientAndPeriod(int $clientId, int $month, int $year): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_zus_declarations WHERE client_id = ? AND period_month = ? AND period_year = ?",
            [$clientId, $month, $year]
        );
    }

    public static function findByClient(int $clientId, ?int $year = null): array
    {
        if ($year) {
            return HrDatabase::getInstance()->fetchAll(
                "SELECT * FROM hr_zus_declarations WHERE client_id = ? AND period_year = ? ORDER BY period_month DESC",
                [$clientId, $year]
            );
        }
        return HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_zus_declarations WHERE client_id = ? ORDER BY period_year DESC, period_month DESC",
            [$clientId]
        );
    }

    public static function create(array $data): int
    {
        return HrDatabase::getInstance()->insert('hr_zus_declarations', $data);
    }

    public static function markGenerated(int $id, string $xmlPath, string $status = 'generated'): void
    {
        HrDatabase::getInstance()->update('hr_zus_declarations', [
            'xml_path'     => $xmlPath,
            'status'       => $status,
            'generated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public static function getYearsForClient(int $clientId): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT DISTINCT period_year FROM hr_zus_declarations WHERE client_id = ? ORDER BY period_year DESC",
            [$clientId]
        );
        return array_column($rows, 'period_year');
    }

    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending'   => 'Nie wygenerowana',
            'generated' => 'Wygenerowana',
            'sent'      => 'Wysłana',
            default     => $status,
        };
    }
}
