<?php

namespace App\Services;

use App\Core\Database;
use App\Core\HrDatabase;

class HrBatchService
{
    public static function getClientsOverview(int $officeId, int $month, int $year): array
    {
        $db   = Database::getInstance();
        $hrDb = HrDatabase::hrDbName();

        $clients = $db->fetchAll(
            "SELECT c.id, c.company_name, c.nip,
                    cs.hr_enabled
             FROM clients c
             JOIN {$hrDb}.hr_client_settings cs ON cs.client_id = c.id
             WHERE c.office_id = ? AND cs.hr_enabled = 1
             ORDER BY c.company_name",
            [$officeId]
        );

        if (empty($clients)) return [];

        $clientIds    = array_column($clients, 'id');
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $hrDbI        = HrDatabase::getInstance();

        $empCounts = $hrDbI->fetchAll(
            "SELECT client_id, COUNT(*) AS cnt
             FROM hr_employees WHERE is_active = 1 AND client_id IN ({$placeholders})
             GROUP BY client_id",
            $clientIds
        );
        $empMap = array_column($empCounts, 'cnt', 'client_id');

        $payrollStatus = $hrDbI->fetchAll(
            "SELECT client_id, status, total_employer_cost
             FROM hr_payroll_runs
             WHERE period_month = ? AND period_year = ? AND client_id IN ({$placeholders})",
            array_merge([$month, $year], $clientIds)
        );
        $payrollMap = [];
        foreach ($payrollStatus as $pr) {
            $payrollMap[$pr['client_id']] = $pr;
        }

        $zusStatus = $hrDbI->fetchAll(
            "SELECT client_id, status
             FROM hr_zus_declarations
             WHERE period_month = ? AND period_year = ? AND declaration_type = 'DRA' AND client_id IN ({$placeholders})",
            array_merge([$month, $year], $clientIds)
        );
        $zusMap = array_column($zusStatus, 'status', 'client_id');

        $pendingLeaves = $hrDbI->fetchAll(
            "SELECT client_id, COUNT(*) AS cnt
             FROM hr_leave_requests WHERE status = 'pending' AND client_id IN ({$placeholders})
             GROUP BY client_id",
            $clientIds
        );
        $pendingMap = array_column($pendingLeaves, 'cnt', 'client_id');

        foreach ($clients as &$c) {
            $cid = $c['id'];
            $c['employee_count']     = (int) ($empMap[$cid] ?? 0);
            $c['pending_leaves']     = (int) ($pendingMap[$cid] ?? 0);
            $pr = $payrollMap[$cid] ?? null;
            $c['payroll_status']     = $pr['status'] ?? null;
            $c['total_employer_cost']= $pr['total_employer_cost'] ?? null;
            $c['zus_status']         = $zusMap[$cid] ?? null;
        }
        unset($c);

        return $clients;
    }

    public static function getComplianceMatrix(int $officeId, int $month, int $year): array
    {
        $overview = self::getClientsOverview($officeId, $month, $year);

        if (empty($overview)) return [];

        $today      = time();
        $zusDl      = mktime(23,59,59, $month + 1, 15, $year);
        $pitDl      = mktime(23,59,59, $month + 1, 20, $year);
        $zusOverdue = $today > $zusDl;
        $pitOverdue = $today > $pitDl;

        foreach ($overview as &$c) {
            if ($c['zus_status'] === 'submitted') {
                $c['zus_compliance'] = 'green';
            } elseif ($c['zus_status'] === 'generated') {
                $c['zus_compliance'] = $zusOverdue ? 'red' : 'yellow';
            } else {
                $c['zus_compliance'] = ($zusOverdue && $c['employee_count'] > 0) ? 'red' : 'gray';
            }

            $pitSt = $c['payroll_status'] ?? null;
            if ($pitSt === 'locked') {
                $c['pit_compliance'] = 'green';
            } elseif ($pitSt === 'approved') {
                $c['pit_compliance'] = $pitOverdue ? 'yellow' : 'green';
            } elseif ($pitSt === 'calculated') {
                $c['pit_compliance'] = 'yellow';
            } else {
                $c['pit_compliance'] = ($pitOverdue && $c['employee_count'] > 0) ? 'red' : 'gray';
            }

            if (in_array($c['payroll_status'], ['approved', 'locked'])) {
                $c['payroll_compliance'] = 'green';
            } elseif ($c['payroll_status'] === 'calculated') {
                $c['payroll_compliance'] = 'yellow';
            } elseif ($c['payroll_status'] === 'draft') {
                $c['payroll_compliance'] = 'yellow';
            } else {
                $c['payroll_compliance'] = $c['employee_count'] > 0 ? 'red' : 'gray';
            }
        }
        unset($c);

        return $overview;
    }
}
