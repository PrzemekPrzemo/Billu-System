<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrPayrollRun
{
    public static function findById(int $id): ?array
    {
        $mainDb = HrDatabase::mainDbName();
        return HrDatabase::getInstance()->fetchOne(
            "SELECT r.*, c.company_name
             FROM hr_payroll_runs r
             JOIN {$mainDb}.clients c ON r.client_id = c.id
             WHERE r.id = ?",
            [$id]
        );
    }

    public static function findByClientAndPeriod(int $clientId, int $month, int $year): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_payroll_runs WHERE client_id = ? AND period_month = ? AND period_year = ?",
            [$clientId, $month, $year]
        );
    }

    public static function findByClient(int $clientId, ?int $year = null): array
    {
        if ($year) {
            return HrDatabase::getInstance()->fetchAll(
                "SELECT * FROM hr_payroll_runs WHERE client_id = ? AND period_year = ? ORDER BY period_month DESC",
                [$clientId, $year]
            );
        }
        return HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_payroll_runs WHERE client_id = ? ORDER BY period_year DESC, period_month DESC",
            [$clientId]
        );
    }

    public static function createOrGet(int $clientId, int $month, int $year): array
    {
        $existing = self::findByClientAndPeriod($clientId, $month, $year);
        if ($existing) return $existing;

        $id = HrDatabase::getInstance()->insert('hr_payroll_runs', [
            'client_id'    => $clientId,
            'period_month' => $month,
            'period_year'  => $year,
            'status'       => 'draft',
        ]);

        return self::findById($id);
    }

    public static function updateStatus(int $id, string $status, ?string $actorType = null, ?int $actorId = null): void
    {
        $data = ['status' => $status];

        if ($status === 'calculated' || $status === 'approved') {
            $field = $status === 'calculated' ? 'processed' : 'approved';
            if ($actorType) {
                $data["{$field}_by_type"] = $actorType;
                $data["{$field}_by_id"]   = $actorId;
                $data["{$field}_at"]      = date('Y-m-d H:i:s');
            }
        }

        if ($status === 'locked') {
            $data['locked_at'] = date('Y-m-d H:i:s');
        }

        HrDatabase::getInstance()->update('hr_payroll_runs', $data, 'id = ?', [$id]);
    }

    public static function updateTotals(int $id, array $totals): void
    {
        HrDatabase::getInstance()->update('hr_payroll_runs', [
            'total_gross'        => $totals['total_gross']        ?? 0,
            'total_net'          => $totals['total_net']          ?? 0,
            'total_zus_employee' => $totals['total_zus_employee'] ?? 0,
            'total_zus_employer' => $totals['total_zus_employer'] ?? 0,
            'total_pit_advance'  => $totals['total_pit_advance']  ?? 0,
            'total_ppk_employee' => $totals['total_ppk_employee'] ?? 0,
            'total_ppk_employer' => $totals['total_ppk_employer'] ?? 0,
            'total_employer_cost'=> $totals['total_employer_cost']?? 0,
            'employee_count'     => $totals['employee_count']     ?? 0,
        ], 'id = ?', [$id]);
    }

    public static function getYearsForClient(int $clientId): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT DISTINCT period_year FROM hr_payroll_runs WHERE client_id = ? ORDER BY period_year DESC",
            [$clientId]
        );
        return array_column($rows, 'period_year');
    }

    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'draft'      => 'Szkic',
            'calculated' => 'Obliczona',
            'approved'   => 'Zatwierdzona',
            'locked'     => 'Zablokowana',
            default      => $status,
        };
    }

    public static function unlock(int $id, string $reason, string $actorType, int $actorId): void
    {
        HrDatabase::getInstance()->update('hr_payroll_runs', [
            'status'            => 'approved',
            'unlock_reason'     => $reason,
            'unlocked_at'       => date('Y-m-d H:i:s'),
            'unlocked_by_type'  => $actorType,
            'unlocked_by_id'    => $actorId,
        ], 'id = ? AND status = ?', [$id, 'locked']);
    }

    public static function createCorrection(int $originalRunId, int $clientId): int
    {
        $original = self::findById($originalRunId);
        if (!$original || (int)$original['client_id'] !== $clientId) {
            throw new \RuntimeException("Original run not found or does not belong to client.");
        }

        return HrDatabase::getInstance()->insert('hr_payroll_runs', [
            'client_id'       => $clientId,
            'period_month'    => $original['period_month'],
            'period_year'     => $original['period_year'],
            'status'          => 'draft',
            'is_correction'   => 1,
            'corrects_run_id' => $originalRunId,
        ]);
    }
}
