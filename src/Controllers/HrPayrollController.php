<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrEmployee;
use App\Models\HrPayrollRun;
use App\Services\HrPayrollRunService;
use App\Services\HrPayslipPdfService;
use App\Services\HrPayslipEmailService;
use App\Services\HrNotificationService;

class HrPayrollController extends HrController
{
    public function payrollList(string $clientId): void
    {
        $clientId    = (int) $clientId;
        $client      = $this->authorizeClientHr($clientId);
        $selectedYear= (int) ($_GET['year'] ?? date('Y'));
        $years       = HrPayrollRun::getYearsForClient($clientId);
        if (empty($years)) $years = [(int) date('Y')];
        $runs        = HrPayrollRun::findByClient($clientId, $selectedYear);
        $this->render('office/hr/payroll_list', compact('client', 'clientId', 'runs', 'years', 'selectedYear'));
    }

    public function payrollCreate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/payroll");
            return;
        }

        $month = max(1, min(12, (int) ($_POST['month'] ?? date('n'))));
        $year  = max(2020, (int) ($_POST['year'] ?? date('Y')));

        $run = HrPayrollRun::createOrGet($clientId, $month, $year);
        AuditLog::log($this->actorType(), $this->actorId(), 'hr_payroll_run_create', json_encode(['run_id' => $run['id']]), 'hr_payroll_run', $run['id']);
        $this->redirect("/office/hr/{$clientId}/payroll/{$run['id']}");
    }

    public function payrollRun(string $clientId, string $runId): void
    {
        $clientId = (int) $clientId;
        $runId    = (int) $runId;
        $client   = $this->authorizeClientHr($clientId);
        $run      = HrPayrollRun::findById($runId);

        if (!$run || (int)$run['client_id'] !== $clientId) {
            $this->forbidden();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrf()) {
                $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
                return;
            }

            $empId      = (int) ($_POST['employee_id'] ?? 0);
            $contractId = (int) ($_POST['contract_id'] ?? 0);
            if ($empId && $contractId) {
                HrPayrollRunService::saveOverrides($runId, $empId, $contractId, $clientId, [
                    'overtime_pay'       => (float) ($_POST['overtime_pay']       ?? 0),
                    'bonus'              => (float) ($_POST['bonus']              ?? 0),
                    'other_additions'    => (float) ($_POST['other_additions']    ?? 0),
                    'sick_pay_reduction' => (float) ($_POST['sick_pay_reduction'] ?? 0),
                ]);
                Session::flash('success', 'Korekta składników zapisana. Przelicz listę aby zaktualizować obliczenia.');
            }
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        $items    = \App\Models\HrPayrollItem::findByRun($runId);
        $errors   = [];
        $emailLog = HrPayslipEmailService::getLogForRun($runId);
        $this->render('office/hr/payroll_run', compact('client', 'clientId', 'run', 'items', 'errors', 'emailLog'));
    }

    public function payrollCalculate(string $clientId, string $runId): void
    {
        $clientId = (int) $clientId;
        $runId    = (int) $runId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $clientId) {
            $this->forbidden();
        }
        if ($run['status'] === 'locked') {
            Session::flash('error', 'Lista płac jest zablokowana.');
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        $result = HrPayrollRunService::calculateRun($runId, $this->actorType(), $this->actorId());

        if (!empty($result['errors'])) {
            Session::flash('error', 'Obliczono z błędami: ' . implode('; ', $result['errors']));
        } else {
            Session::flash('success', "Lista płac obliczona. Pracownicy: {$result['employee_count']}, brutto: " . number_format($result['total_gross'], 2, ',', ' ') . ' zł.');
        }
        $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
    }

    public function payrollApprove(string $clientId, string $runId): void
    {
        $clientId = (int) $clientId;
        $runId    = (int) $runId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $clientId || $run['status'] !== 'calculated') {
            Session::flash('error', 'Można zatwierdzić tylko obliczoną listę płac.');
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        HrPayrollRun::updateStatus($runId, 'approved', $this->actorType(), $this->actorId());
        AuditLog::log($this->actorType(), $this->actorId(), 'hr_payroll_approve', json_encode(['run_id' => $runId]), 'hr_payroll_run', $runId);
        HrNotificationService::notifyPayrollApproved($clientId, (int)$run['period_month'], (int)$run['period_year'], number_format((float)($run['total_employer_cost'] ?? 0), 2, ',', ' '));

        if (!empty($run['is_correction'])) {
            try {
                HrPayrollRunService::applyYtdImpact($runId);
            } catch (\Throwable $e) {
                error_log('[HR] YTD impact error: ' . $e->getMessage());
            }
        }

        if (HrPayslipEmailService::isEnabledForClient($clientId)) {
            try {
                $emailResult = HrPayslipEmailService::sendForRun($runId);
                if ($emailResult['sent'] > 0) {
                    Session::flash('success', 'Lista płac została zatwierdzona. Wysłano ' . $emailResult['sent'] . ' odcinek/ów płacowych.');
                } elseif (!empty($emailResult['errors'])) {
                    Session::flash('success', 'Lista płac została zatwierdzona. Błąd wysyłki e-mail: ' . $emailResult['errors'][0]);
                } else {
                    Session::flash('success', 'Lista płac została zatwierdzona.');
                }
            } catch (\Throwable $e) {
                error_log('[HR] Payslip email error: ' . $e->getMessage());
                Session::flash('success', 'Lista płac została zatwierdzona. (Błąd wysyłki e-mail)');
            }
        } else {
            Session::flash('success', 'Lista płac została zatwierdzona.');
        }

        $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
    }

    public function payrollLock(string $clientId, string $runId): void
    {
        $clientId = (int) $clientId;
        $runId    = (int) $runId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $clientId || $run['status'] !== 'approved') {
            Session::flash('error', 'Można zablokować tylko zatwierdzoną listę płac.');
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        HrPayrollRun::updateStatus($runId, 'locked', $this->actorType(), $this->actorId());
        AuditLog::log($this->actorType(), $this->actorId(), 'hr_payroll_lock', json_encode(['run_id' => $runId]), 'hr_payroll_run', $runId);
        HrNotificationService::notifyPayrollLocked($clientId, (int)$run['period_month'], (int)$run['period_year']);
        Session::flash('success', 'Lista płac została zablokowana.');
        $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
    }

    public function payrollUnlock(string $clientId, string $runId): void
    {
        $clientId = (int) $clientId;
        $runId    = (int) $runId;
        $this->authorizeClientHr($clientId);
        $this->validateCsrf();

        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $reason = trim($_POST['unlock_reason'] ?? '');
        if ($reason === '') {
            Session::flash('error', 'Podaj powód odblokowania listy płac.');
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        try {
            HrPayrollRunService::unlockRun($runId, $reason, $this->actorType(), $this->actorId());
            Session::flash('success', 'Lista płac została odblokowana i można ją ponownie edytować.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Błąd odblokowania: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
    }

    public function payrollCorrectionCreate(string $clientId, string $runId): void
    {
        $clientId = (int) $clientId;
        $runId    = (int) $runId;
        $this->authorizeClientHr($clientId);
        $this->validateCsrf();

        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $employeeIds = array_map('intval', (array)($_POST['employee_ids'] ?? []));
        $employeeIds = array_filter($employeeIds, fn($id) => $id > 0);

        if (empty($employeeIds)) {
            Session::flash('error', 'Wybierz co najmniej jednego pracownika do korekty.');
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        try {
            $correctionRunId = HrPayrollRunService::createCorrectionRun(
                $runId, array_values($employeeIds), $this->actorType(), $this->actorId()
            );
            Session::flash('success', "Lista korygująca #{$correctionRunId} została utworzona. Przelicz ją i zatwierdź.");
            $this->redirect("/office/hr/{$clientId}/payroll/{$correctionRunId}");
        } catch (\Throwable $e) {
            Session::flash('error', 'Błąd tworzenia korekty: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
        }
    }

    public function payslipPdf(string $clientId, string $runId, string $empId): void
    {
        $clientId = (int) $clientId;
        $runId    = (int) $runId;
        $empId    = (int) $empId;
        $this->authorizeClientHr($clientId);

        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $clientId) {
            $this->forbidden();
        }
        if ($run['status'] === 'draft') {
            Session::flash('error', 'Odcinek jest dostępny dopiero po obliczeniu listy płac.');
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
            return;
        }

        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        try {
            $path = HrPayslipPdfService::generate($runId, $empId);
            AuditLog::log($this->actorType(), $this->actorId(), 'hr_payslip_download', json_encode(['run_id' => $runId, 'employee_id' => $empId]), 'hr_payroll_item', $empId);
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'Błąd generowania PDF: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/payroll/{$runId}");
        }
    }
}
