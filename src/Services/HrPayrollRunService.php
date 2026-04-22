<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\AuditLog;
use App\Models\HrClientSettings;
use App\Models\HrContract;
use App\Models\HrEmployee;
use App\Models\HrPayrollItem;
use App\Models\HrPayrollRun;

class HrPayrollRunService
{
    public static function calculateRun(int $runId, string $actorType, int $actorId): array
    {
        $run = HrPayrollRun::findById($runId);
        if (!$run) return ['error' => 'Payroll run not found'];
        if ($run['status'] === 'locked') return ['error' => 'Payroll run is locked'];

        $clientId = (int) $run['client_id'];
        $month    = (int) $run['period_month'];
        $year     = (int) $run['period_year'];
        $settings = HrClientSettings::getOrCreate($clientId);

        $existingOverrides = [];
        foreach (HrPayrollItem::findByRun($runId) as $item) {
            $existingOverrides[$item['employee_id']] = [
                'overtime_pay' => (float) $item['overtime_pay'],
                'bonus' => (float) $item['bonus'],
                'other_additions' => (float) $item['other_additions'],
                'sick_pay_reduction' => (float) $item['sick_pay_reduction'],
            ];
        }

        HrPayrollItem::deleteByRun($runId);

        $employees = HrDatabase::getInstance()->fetchAll(
            "SELECT e.*, c.id AS contract_id, c.contract_type, c.base_salary, c.work_time_fraction,
                    c.zus_emerytalne_employee, c.zus_emerytalne_employer, c.zus_rentowe_employee,
                    c.zus_rentowe_employer, c.zus_chorobowe, c.zus_wypadkowe, c.zus_fp,
                    c.zus_fgsp, c.zus_fep, c.wypadkowe_rate, c.has_other_employment, c.position
             FROM hr_employees e
             JOIN hr_contracts c ON c.employee_id = e.id AND c.is_current = 1
             WHERE e.client_id = ? AND e.is_active = 1",
            [$clientId]
        );

        $totals = ['total_gross'=>0.0,'total_net'=>0.0,'total_zus_employee'=>0.0,'total_zus_employer'=>0.0,'total_pit_advance'=>0.0,'total_ppk_employee'=>0.0,'total_ppk_employer'=>0.0,'total_employer_cost'=>0.0,'employee_count'=>0];
        $errors = [];

        foreach ($employees as $emp) {
            try {
                $ytd = HrPayrollItem::getYtd($emp['id'], $year, $month);
                $overrides = $existingOverrides[$emp['id']] ?? ['overtime_pay'=>0.0,'bonus'=>0.0,'other_additions'=>0.0,'sick_pay_reduction'=>0.0];

                $employeeArr = ['pit2_submitted'=>$emp['pit2_submitted'],'kup_amount'=>$emp['kup_amount'],'ppk_enrolled'=>$emp['ppk_enrolled'],'ppk_employee_rate'=>$emp['ppk_employee_rate'],'ppk_employer_rate'=>$emp['ppk_employer_rate']];
                $contractArr = ['contract_type'=>$emp['contract_type'],'base_salary'=>$emp['base_salary'],'work_time_fraction'=>$emp['work_time_fraction'],'zus_emerytalne_employee'=>$emp['zus_emerytalne_employee'],'zus_emerytalne_employer'=>$emp['zus_emerytalne_employer'],'zus_rentowe_employee'=>$emp['zus_rentowe_employee'],'zus_rentowe_employer'=>$emp['zus_rentowe_employer'],'zus_chorobowe'=>$emp['zus_chorobowe'],'zus_wypadkowe'=>$emp['zus_wypadkowe'],'zus_fp'=>$emp['zus_fp'],'zus_fgsp'=>$emp['zus_fgsp'],'wypadkowe_rate'=>$emp['wypadkowe_rate'],'has_other_employment'=>$emp['has_other_employment']];

                $result = HrPayrollCalculationService::calculate($employeeArr, $contractArr, $settings, $overrides, $ytd);

                HrPayrollItem::upsert(array_merge($result, ['payroll_run_id'=>$runId,'employee_id'=>$emp['id'],'contract_id'=>$emp['contract_id'],'client_id'=>$clientId]));

                $totals['total_gross'] += $result['gross_salary'];
                $totals['total_net'] += $result['net_salary'];
                $totals['total_zus_employee'] += $result['zus_total_employee'];
                $totals['total_zus_employer'] += $result['zus_total_employer'];
                $totals['total_pit_advance'] += $result['pit_advance'];
                $totals['total_ppk_employee'] += $result['ppk_employee'];
                $totals['total_ppk_employer'] += $result['ppk_employer'];
                $totals['total_employer_cost'] += $result['employer_total_cost'];
                $totals['employee_count']++;
            } catch (\Throwable $e) {
                $errors[] = "Employee #{$emp['id']}: " . $e->getMessage();
            }
        }

        foreach ($totals as $k => $v) {
            if ($k !== 'employee_count') $totals[$k] = round((float) $v, 2);
        }

        HrPayrollRun::updateTotals($runId, $totals);
        HrPayrollRun::updateStatus($runId, 'calculated', $actorType, $actorId);
        AuditLog::log($actorType, $actorId, 'hr_payroll_calculate', json_encode(['run_id'=>$runId,'employee_count'=>$totals['employee_count']]), 'hr_payroll_run', $runId);

        return array_merge($totals, ['errors' => $errors]);
    }

    public static function saveOverrides(int $runId, int $employeeId, int $contractId, int $clientId, array $overrides): void
    {
        $run = HrPayrollRun::findById($runId);
        if (!$run || $run['status'] === 'locked') return;

        $existing = HrPayrollItem::findByEmployee($employeeId, $runId);

        if ($existing) {
            HrDatabase::getInstance()->update('hr_payroll_items', [
                'overtime_pay' => $overrides['overtime_pay'] ?? $existing['overtime_pay'],
                'bonus' => $overrides['bonus'] ?? $existing['bonus'],
                'other_additions' => $overrides['other_additions'] ?? $existing['other_additions'],
                'sick_pay_reduction' => $overrides['sick_pay_reduction'] ?? $existing['sick_pay_reduction'],
            ], 'id = ?', [$existing['id']]);
        } else {
            HrDatabase::getInstance()->insert('hr_payroll_items', [
                'payroll_run_id' => $runId, 'employee_id' => $employeeId,
                'contract_id' => $contractId, 'client_id' => $clientId,
                'overtime_pay' => $overrides['overtime_pay'] ?? 0,
                'bonus' => $overrides['bonus'] ?? 0,
                'other_additions' => $overrides['other_additions'] ?? 0,
                'sick_pay_reduction' => $overrides['sick_pay_reduction'] ?? 0,
            ]);
        }

        if ($run['status'] === 'calculated') {
            HrPayrollRun::updateStatus($runId, 'draft');
        }
    }

    public static function unlockRun(int $runId, string $reason, string $actorType, int $actorId): void
    {
        $run = HrPayrollRun::findById($runId);
        if (!$run) throw new \RuntimeException("Payroll run not found: {$runId}");
        if ($run['status'] !== 'locked') throw new \RuntimeException("Tylko zablokowane listy płac mogą być odblokowane.");

        HrPayrollRun::unlock($runId, $reason, $actorType, $actorId);
        AuditLog::log($actorType, $actorId, 'hr_payroll_unlock', json_encode(['run_id'=>$runId,'reason'=>$reason]), 'hr_payroll_run', $runId);
    }

    public static function createCorrectionRun(int $originalRunId, array $employeeIds, string $actorType, int $actorId): int
    {
        $originalRun = HrPayrollRun::findById($originalRunId);
        if (!$originalRun) throw new \RuntimeException("Nie znaleziono oryginalnej listy płac: {$originalRunId}");

        $clientId = (int) $originalRun['client_id'];
        $correctionRunId = HrPayrollRun::createCorrection($originalRunId, $clientId);

        $db = HrDatabase::getInstance();

        foreach ($employeeIds as $empId) {
            $empId = (int)$empId;
            if ($empId <= 0) continue;

            $originalItem = $db->fetchOne("SELECT * FROM hr_payroll_items WHERE payroll_run_id = ? AND employee_id = ?", [$originalRunId, $empId]);
            if (!$originalItem) continue;

            $newItem = $originalItem;
            unset($newItem['id'], $newItem['created_at'], $newItem['updated_at']);
            $newItem['payroll_run_id'] = $correctionRunId;
            $newItem['is_correction'] = 1;
            $newItem['original_item_id'] = (int)$originalItem['id'];

            $db->insert('hr_payroll_items', $newItem);
        }

        AuditLog::log($actorType, $actorId, 'hr_payroll_correction_create', json_encode(['original_run_id'=>$originalRunId,'correction_run_id'=>$correctionRunId,'employee_ids'=>$employeeIds]), 'hr_payroll_run', $correctionRunId);

        return $correctionRunId;
    }

    public static function applyYtdImpact(int $correctionRunId): void
    {
        $db  = HrDatabase::getInstance();
        $run = HrPayrollRun::findById($correctionRunId);
        if (!$run || !$run['is_correction']) return;

        $clientId = (int) $run['client_id'];
        $year     = (int) $run['period_year'];
        $month    = (int) $run['period_month'];

        $corrItems = $db->fetchAll(
            "SELECT pi.*, orig.gross_salary AS orig_gross, orig.pit_advance AS orig_pit, orig.zus_employee AS orig_zus
             FROM hr_payroll_items pi
             LEFT JOIN hr_payroll_items orig ON pi.original_item_id = orig.id
             WHERE pi.payroll_run_id = ? AND pi.is_correction = 1",
            [$correctionRunId]
        );

        $laterRuns = $db->fetchAll(
            "SELECT id, period_month FROM hr_payroll_runs
             WHERE client_id = ? AND period_year = ? AND period_month > ? AND is_correction = 0 AND status != 'draft'
             ORDER BY period_month ASC",
            [$clientId, $year, $month]
        );

        if (empty($laterRuns) || empty($corrItems)) return;

        foreach ($corrItems as $item) {
            $empId = (int)$item['employee_id'];
            $deltaGross = (float)$item['gross_salary'] - (float)($item['orig_gross'] ?? $item['gross_salary']);
            $deltaZus   = (float)$item['zus_employee'] - (float)($item['orig_zus'] ?? $item['zus_employee']);

            if (abs($deltaGross) < 0.01 && abs($deltaZus) < 0.01) continue;

            foreach ($laterRuns as $laterRun) {
                $laterItem = $db->fetchOne("SELECT id, ytd_gross, ytd_zus_base FROM hr_payroll_items WHERE payroll_run_id = ? AND employee_id = ?", [(int)$laterRun['id'], $empId]);
                if (!$laterItem) continue;

                $db->update('hr_payroll_items', [
                    'ytd_gross' => max(0, (float)$laterItem['ytd_gross'] + $deltaGross),
                    'ytd_zus_base' => max(0, (float)$laterItem['ytd_zus_base'] + $deltaZus),
                ], 'id = ?', [(int)$laterItem['id']]);
            }
        }
    }
}
