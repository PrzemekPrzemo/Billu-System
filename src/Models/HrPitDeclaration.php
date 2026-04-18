<?php

namespace App\Models;

use App\Core\HrDatabase;
use App\Services\HrEncryptionService;

class HrPitDeclaration
{
    public static function findById(int $id): ?array
    {
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT d.*, e.first_name, e.last_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.pesel
             FROM hr_pit_declarations d
             LEFT JOIN hr_employees e ON e.id = d.employee_id
             WHERE d.id = ?",
            [$id]
        );
        if ($row) {
            $row = HrEncryptionService::decryptFields($row, ['pesel']);
        }
        return $row;
    }

    public static function findByClientAndYear(int $clientId, int $year): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT d.*, e.first_name, e.last_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.pesel
             FROM hr_pit_declarations d
             LEFT JOIN hr_employees e ON e.id = d.employee_id
             WHERE d.client_id = ? AND d.tax_year = ?
             ORDER BY d.declaration_type, e.last_name, e.first_name",
            [$clientId, $year]
        );
        return HrEncryptionService::decryptRows($rows, ['pesel']);
    }

    public static function findPit11ForEmployee(int $clientId, int $empId, int $year): ?array
    {
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT d.*, e.first_name, e.last_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.pesel
             FROM hr_pit_declarations d
             LEFT JOIN hr_employees e ON e.id = d.employee_id
             WHERE d.client_id = ? AND d.employee_id = ? AND d.tax_year = ?
               AND d.declaration_type = 'PIT-11'",
            [$clientId, $empId, $year]
        );
        if ($row) {
            $row = HrEncryptionService::decryptFields($row, ['pesel']);
        }
        return $row;
    }

    public static function findPit4rForClient(int $clientId, int $year): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_pit_declarations
             WHERE client_id = ? AND tax_year = ? AND declaration_type = 'PIT-4R'",
            [$clientId, $year]
        );
    }

    public static function upsert(array $data): int
    {
        $db = HrDatabase::getInstance();

        $cols = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $colList = implode(', ', $cols);

        $updateParts = [];
        foreach ($cols as $col) {
            if (!in_array($col, ['client_id', 'employee_id', 'declaration_type', 'tax_year'])) {
                $updateParts[] = "{$col} = VALUES({$col})";
            }
        }
        $updateSql = implode(', ', $updateParts);

        $sql = "INSERT INTO hr_pit_declarations ({$colList}) VALUES ({$placeholders})
                ON DUPLICATE KEY UPDATE {$updateSql}";

        $db->query($sql, array_values($data));
        return (int) $db->lastInsertId();
    }

    public static function markGenerated(int $id, string $path, string $status = 'generated'): void
    {
        HrDatabase::getInstance()->query(
            "UPDATE hr_pit_declarations
             SET export_path = ?, status = ?, generated_at = NOW()
             WHERE id = ?",
            [$path, $status, $id]
        );
    }

    public static function aggregateYearForEmployee(int $employeeId, int $year): array
    {
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT
               COALESCE(SUM(pi.gross_salary), 0)       AS total_gross,
               COALESCE(SUM(pi.zus_total_employee), 0)  AS total_zus_employee,
               COALESCE(SUM(pi.pit_advance), 0)         AS total_pit_advances,
               COALESCE(SUM(pi.kup_amount), 0)          AS total_kup,
               COUNT(*)                                  AS months_count
             FROM hr_payroll_items pi
             JOIN hr_payroll_runs r ON pi.payroll_run_id = r.id
             WHERE pi.employee_id = ?
               AND r.period_year = ?
               AND r.status NOT IN ('draft')",
            [$employeeId, $year]
        );

        return $row ?? [
            'total_gross'        => 0,
            'total_zus_employee' => 0,
            'total_pit_advances' => 0,
            'total_kup'          => 0,
            'months_count'       => 0,
        ];
    }

    public static function aggregateYearForClient(int $clientId, int $year): array
    {
        $monthly = HrDatabase::getInstance()->fetchAll(
            "SELECT
               r.period_month,
               COALESCE(SUM(pi.pit_advance), 0)         AS pit_advances,
               COALESCE(SUM(pi.gross_salary), 0)         AS gross_total
             FROM hr_payroll_items pi
             JOIN hr_payroll_runs r ON pi.payroll_run_id = r.id
             WHERE r.client_id = ?
               AND r.period_year = ?
               AND r.status NOT IN ('draft')
             GROUP BY r.period_month
             ORDER BY r.period_month",
            [$clientId, $year]
        );

        $totals = HrDatabase::getInstance()->fetchOne(
            "SELECT
               COALESCE(SUM(pi.pit_advance), 0)  AS total_pit_advances,
               COALESCE(SUM(pi.gross_salary), 0)  AS total_gross
             FROM hr_payroll_items pi
             JOIN hr_payroll_runs r ON pi.payroll_run_id = r.id
             WHERE r.client_id = ?
               AND r.period_year = ?
               AND r.status NOT IN ('draft')",
            [$clientId, $year]
        );

        return [
            'monthly' => $monthly,
            'totals'  => $totals ?? ['total_pit_advances' => 0, 'total_gross' => 0],
        ];
    }

    public static function getYearsForClient(int $clientId): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT DISTINCT tax_year FROM hr_pit_declarations
             WHERE client_id = ? ORDER BY tax_year DESC",
            [$clientId]
        );
        return array_column($rows, 'tax_year');
    }

    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'draft'     => 'Szkic',
            'generated' => 'Wygenerowana',
            'issued'    => 'Wydana',
            default     => $status,
        };
    }
}
