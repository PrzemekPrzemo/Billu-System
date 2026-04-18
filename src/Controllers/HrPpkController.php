<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrPpkEnrollment;
use App\Services\HrPpkReportService;

class HrPpkController extends HrController
{
    public function ppkManagement(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $year  = (int)($_GET['year']  ?? date('Y'));
        $month = max(1, min(12, (int)($_GET['month'] ?? date('n'))));

        $employees  = HrPpkEnrollment::getEnrolledForClient($clientId);
        $alerts     = HrPpkEnrollment::getAutoEnrollmentAlerts($clientId);
        $ytdSummary = HrPpkReportService::getYtdSummary($clientId, $year);

        $this->render('office/hr/ppk_management', compact(
            'client', 'clientId', 'employees', 'alerts', 'ytdSummary', 'year', 'month'
        ));
    }

    public function ppkEnroll(string $clientId, string $empId): void
    {
        $clientId = (int) $clientId;
        $empId    = (int) $empId;
        $this->authorizeClientHr($clientId);
        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/ppk");
            return;
        }

        $db  = \App\Core\HrDatabase::getInstance();
        $emp = $db->fetchOne(
            "SELECT id FROM hr_employees WHERE id = ? AND client_id = ?",
            [$empId, $clientId]
        );
        if (!$emp) $this->forbidden();

        $data = [
            'effective_date' => $_POST['effective_date'] ?? date('Y-m-d'),
            'institution'    => trim($_POST['institution'] ?? '') ?: null,
            'employee_rate'  => max(0.5, min(4.0, (float)($_POST['employee_rate'] ?? 2.00))),
            'employer_rate'  => max(1.5, min(4.0, (float)($_POST['employer_rate'] ?? 1.50))),
        ];

        try {
            HrPpkEnrollment::enroll($empId, $clientId, $data);
            AuditLog::log($this->actorType(), $this->actorId(), 'hr_ppk_enroll',
                json_encode(['employee_id' => $empId, 'institution' => $data['institution']]),
                'hr_employee', $empId);
            Session::flash('success', 'Pracownik zosta\u0142 zapisany do PPK.');
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d zapisu PPK: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/ppk");
    }

    public function ppkOptOut(string $clientId, string $empId): void
    {
        $clientId = (int) $clientId;
        $empId    = (int) $empId;
        $this->authorizeClientHr($clientId);
        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/ppk");
            return;
        }

        $db  = \App\Core\HrDatabase::getInstance();
        $emp = $db->fetchOne(
            "SELECT id FROM hr_employees WHERE id = ? AND client_id = ?",
            [$empId, $clientId]
        );
        if (!$emp) $this->forbidden();

        $date = $_POST['opt_out_date'] ?? date('Y-m-d');

        try {
            HrPpkEnrollment::optOut($empId, $clientId, $date);
            AuditLog::log($this->actorType(), $this->actorId(), 'hr_ppk_opt_out',
                json_encode(['employee_id' => $empId, 'date' => $date]),
                'hr_employee', $empId);
            Session::flash('success', 'Pracownik zosta\u0142 wypisany z PPK.');
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d wypisania z PPK: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/ppk");
    }

    public function ppkReportExport(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        $month = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
        $year  = (int)($_GET['year'] ?? date('Y'));

        try {
            $path = HrPpkReportService::generateMonthlyReport($clientId, $month, $year);

            AuditLog::log($this->actorType(), $this->actorId(), 'hr_ppk_report_export',
                json_encode(['month' => $month, 'year' => $year]),
                'client', $clientId);

            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d eksportu PPK: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/ppk?month={$month}&year={$year}");
        }
    }
}
