<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrAttendance;
use App\Models\HrPayrollRun;
use App\Services\HrAttendanceService;
use App\Services\HrAttendancePdfService;
use App\Core\HrDatabase;

class HrAttendanceController extends HrController
{
    public function attendance(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
        $year  = max(2000, (int) ($_GET['year']  ?? date('Y')));

        $employees = HrDatabase::getInstance()->fetchAll(
            "SELECT id, first_name, last_name
             FROM hr_employees
             WHERE client_id = ? AND is_active = 1
             ORDER BY last_name, first_name",
            [$clientId]
        );

        $grid = HrAttendanceService::buildMonthGrid($clientId, $month, $year, $employees);

        $payrollRuns = HrPayrollRun::findByClient($clientId, $year);
        $runForMonth = null;
        foreach ($payrollRuns as $run) {
            if ((int)$run['period_month'] === $month && $run['status'] !== 'draft') {
                $runForMonth = $run;
                break;
            }
        }

        $typeLabels = HrAttendance::TYPE_LABELS;

        $this->render('office/hr/attendance', compact(
            'client', 'clientId', 'month', 'year',
            'grid', 'typeLabels', 'runForMonth'
        ));
    }

    public function attendanceSave(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);
        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/attendance");
            return;
        }

        $month = max(1, min(12, (int) ($_POST['month'] ?? date('n'))));
        $year  = max(2000, (int) ($_POST['year']  ?? date('Y')));
        $days  = $_POST['day'] ?? [];
        $saved = 0;

        foreach ($days as $empId => $dayData) {
            $empId = (int) $empId;
            if ($empId <= 0) continue;

            foreach ($dayData as $day => $data) {
                $day = (int) $day;
                if ($day < 1 || $day > 31) continue;
                if (empty($data['type'])) continue;

                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

                $empRow = HrDatabase::getInstance()->fetchOne(
                    "SELECT id FROM hr_employees WHERE id = ? AND client_id = ?",
                    [$empId, $clientId]
                );
                if (!$empRow) continue;

                HrAttendance::upsert($empId, $clientId, $date, $data);
                $saved++;
            }
        }

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_attendance_save',
            json_encode(['client_id' => $clientId, 'month' => $month, 'year' => $year, 'rows' => $saved]),
            'client', $clientId);

        Session::flash('success', "Zapisano {$saved} wpis\u00f3w ewidencji czasu pracy.");
        $this->redirect("/office/hr/{$clientId}/attendance?month={$month}&year={$year}");
    }

    public function attendanceExportPdf(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
        $year  = max(2000, (int) ($_GET['year']  ?? date('Y')));

        try {
            $path = HrAttendancePdfService::generate($clientId, $month, $year);

            AuditLog::log($this->actorType(), $this->actorId(), 'hr_attendance_export_pdf',
                json_encode(['month' => $month, 'year' => $year]), 'client', $clientId);

            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d generowania PDF: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/attendance?month={$month}&year={$year}");
        }
    }

    public function attendanceInjectOvertime(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);
        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/attendance");
            return;
        }

        $month = max(1, min(12, (int) ($_POST['month'] ?? date('n'))));
        $year  = max(2000, (int) ($_POST['year']  ?? date('Y')));
        $runId = (int) ($_POST['payroll_run_id'] ?? 0);

        if ($runId <= 0) {
            Session::flash('error', 'Nie wybrano listy p\u0142ac.');
            $this->redirect("/office/hr/{$clientId}/attendance?month={$month}&year={$year}");
            return;
        }

        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $clientId) {
            Session::flash('error', 'Nieprawid\u0142owa lista p\u0142ac.');
            $this->redirect("/office/hr/{$clientId}/attendance?month={$month}&year={$year}");
            return;
        }

        if ($run['status'] === 'locked') {
            Session::flash('error', 'Lista p\u0142ac jest zablokowana.');
            $this->redirect("/office/hr/{$clientId}/attendance?month={$month}&year={$year}");
            return;
        }

        try {
            $count = HrAttendanceService::injectOvertimeToPayroll($runId, $clientId, $month, $year);

            AuditLog::log($this->actorType(), $this->actorId(), 'hr_attendance_inject_overtime',
                json_encode(['run_id' => $runId, 'employees_updated' => $count]),
                'hr_payroll_run', $runId);

            Session::flash('success', "Nadgodziny wstawione do listy p\u0142ac dla {$count} pracownik\u00f3w.");
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/attendance?month={$month}&year={$year}");
    }
}
