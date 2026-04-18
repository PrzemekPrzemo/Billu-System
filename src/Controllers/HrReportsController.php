<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrClientSettings;
use App\Models\HrPayrollBudget;
use App\Models\HrPayrollRun;
use App\Services\HrAccessService;
use App\Services\HrBudgetExportService;
use App\Services\HrPayrollCalculationService;
use App\Services\HrReportService;

class HrReportsController extends HrController
{
    public function hrReports(string $clientId): void
    {
        $clientId      = (int) $clientId;
        $client        = $this->authorizeClientHr($clientId);
        $selectedYear  = (int) ($_GET['year']  ?? date('Y'));
        $selectedMonth = (int) ($_GET['month'] ?? 0);

        $years = HrPayrollRun::getYearsForClient($clientId);
        if (empty($years)) $years = [(int) date('Y')];

        if ($selectedMonth > 0) {
            $reportData = HrReportService::getMonthlySummary($clientId, $selectedYear, $selectedMonth);
        } else {
            $reportData = HrReportService::getAnnualSummary($clientId, $selectedYear);
        }

        $this->render('office/hr/hr_reports', compact(
            'client', 'clientId', 'years', 'selectedYear', 'selectedMonth', 'reportData'
        ));
    }

    public function hrReportExportMonthly(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        $year  = (int) ($_GET['year']  ?? date('Y'));
        $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));

        try {
            $path = HrReportService::exportMonthlyExcel($clientId, $year, $month);
            AuditLog::log($this->actorType(), $this->actorId(), 'hr_report_export_monthly',
                json_encode(['year' => $year, 'month' => $month]), 'client', $clientId);

            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d generowania raportu: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/reports?year={$year}&month={$month}");
        }
    }

    public function hrReportExportAnnual(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        $year = (int) ($_GET['year'] ?? date('Y'));

        try {
            $path = HrReportService::exportAnnualExcel($clientId, $year);
            AuditLog::log($this->actorType(), $this->actorId(), 'hr_report_export_annual',
                json_encode(['year' => $year]), 'client', $clientId);

            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d generowania raportu: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/reports?year={$year}");
        }
    }

    public function calculator(): void
    {
        $officeId = $this->getOfficeId();
        $clients  = HrAccessService::getClientsWithHrStatus($officeId);
        $clients  = array_values(array_filter($clients, fn($c) => (bool)$c['hr_enabled']));

        $this->render('office/hr/calculator', [
            'clients' => $clients,
            'result'  => null,
            'inputs'  => [],
        ]);
    }

    public function calculatorResult(): void
    {
        $officeId = $this->getOfficeId();
        $this->validateCsrf();

        $clients = HrAccessService::getClientsWithHrStatus($officeId);
        $clients = array_values(array_filter($clients, fn($c) => (bool)$c['hr_enabled']));

        $clientId     = (int) ($_POST['client_id'] ?? 0);
        $brutto       = (float) ($_POST['brutto'] ?? 0);
        $contractType = $_POST['contract_type'] ?? 'uop';
        $pit2         = (bool) ($_POST['pit2'] ?? false);
        $kup          = $_POST['kup'] ?? '250';
        $ppk          = (bool) ($_POST['ppk'] ?? false);

        $inputs = compact('clientId', 'brutto', 'contractType', 'pit2', 'kup', 'ppk');

        $result = null;
        if ($brutto > 0) {
            $settings = $clientId > 0 ? HrClientSettings::getOrCreate($clientId) : [];

            $employee = [
                'pit2_submitted' => $pit2, 'kup_amount' => $kup,
                'ppk_enrolled' => $ppk, 'ppk_employee_rate' => 2.00, 'ppk_employer_rate' => 1.50,
            ];
            $contract = [
                'contract_type' => $contractType, 'base_salary' => $brutto,
                'work_time_fraction' => 1.0,
                'zus_emerytalne_employee' => 1, 'zus_emerytalne_employer' => 1,
                'zus_rentowe_employee' => 1, 'zus_rentowe_employer' => 1,
                'zus_chorobowe' => 1, 'zus_wypadkowe' => 1,
                'zus_fp' => 1, 'zus_fgsp' => 1,
                'has_other_employment' => 0, 'wypadkowe_rate' => null,
            ];
            $ytd      = ['ytd_gross' => 0, 'ytd_zus_base' => 0];
            $overrides = ['overtime_pay' => 0, 'bonus' => 0, 'other_additions' => 0, 'sick_pay_reduction' => 0];

            $result = HrPayrollCalculationService::calculate($employee, $contract, $settings, $overrides, $ytd);
        }

        $this->render('office/hr/calculator', ['clients' => $clients, 'result' => $result, 'inputs' => $inputs]);
    }

    public function budget(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);
        $year     = (int) ($_GET['year'] ?? date('Y'));

        $budget        = HrPayrollBudget::findByClientAndYear($clientId, $year);
        $actualByMonth = $this->getActualByMonth($clientId, $year);

        $years = HrPayrollBudget::getYearsForClient($clientId);
        $years = array_unique(array_merge([$year], $years ?: [$year - 1, $year + 1]));
        rsort($years);

        $this->render('office/hr/budget', compact('client', 'clientId', 'year', 'years', 'budget', 'actualByMonth'));
    }

    public function budgetSave(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);
        $this->validateCsrf();

        $year = (int) ($_POST['year'] ?? date('Y'));
        $rows = $_POST['budget'] ?? [];

        $monthData = [];
        foreach ($rows as $m => $row) {
            $monthData[(int)$m] = [
                'planned_gross' => (float) ($row['planned_gross'] ?? 0),
                'planned_cost'  => (float) ($row['planned_cost']  ?? 0),
                'notes'         => trim($row['notes'] ?? ''),
            ];
        }

        HrPayrollBudget::upsertAllMonths($clientId, $year, $monthData);
        Session::flash('success', 'Bud\u017cet zosta\u0142 zapisany');
        $this->redirect("/office/hr/{$clientId}/budget?year={$year}");
    }

    public function budgetExportExcel(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        $year          = (int) ($_GET['year'] ?? date('Y'));
        $actualByMonth = $this->getActualByMonth($clientId, $year);

        try {
            $path = HrBudgetExportService::export($clientId, $year, $actualByMonth);

            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d eksportu: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/budget?year={$year}");
        }
    }

    private function getActualByMonth(int $clientId, int $year): array
    {
        $runs    = HrPayrollRun::findByClient($clientId, $year);
        $byMonth = [];
        foreach ($runs as $run) {
            if ($run['status'] === 'draft') continue;
            $m = (int) $run['period_month'];
            $byMonth[$m] = [
                'gross_salary'        => (float) $run['total_gross'],
                'employer_total_cost' => (float) $run['total_employer_cost'],
            ];
        }
        return $byMonth;
    }
}
