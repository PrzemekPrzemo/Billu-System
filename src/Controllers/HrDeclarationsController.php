<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrPayrollRun;
use App\Models\HrZusDeclaration;
use App\Models\HrPitDeclaration;
use App\Services\HrNotificationService;
use App\Services\HrZusDraService;
use App\Services\HrPit11Service;
use App\Services\HrPit4rService;

class HrDeclarationsController extends HrController
{
    public function zusDeclarations(string $clientId): void
    {
        $clientId     = (int) $clientId;
        $client       = $this->authorizeClientHr($clientId);
        $selectedYear = (int) ($_GET['year'] ?? date('Y'));

        $years        = HrZusDeclaration::getYearsForClient($clientId);
        if (empty($years)) $years = [(int) date('Y')];

        $declarations = HrZusDeclaration::findByClient($clientId, $selectedYear);
        $payrollRuns  = HrPayrollRun::findByClient($clientId, $selectedYear);

        $this->render('office/hr/zus_declarations', compact(
            'client', 'clientId', 'declarations', 'years', 'selectedYear', 'payrollRuns'
        ));
    }

    public function zusGenerate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/zus");
            return;
        }

        $month = max(1, min(12, (int) ($_POST['month'] ?? date('n'))));
        $year  = max(2020, (int) ($_POST['year'] ?? date('Y')));
        $runId = (int) ($_POST['payroll_run_id'] ?? 0) ?: null;

        if ($runId) {
            $run = HrPayrollRun::findById($runId);
            if (!$run || (int)$run['client_id'] !== $clientId) {
                Session::flash('error', 'Nieprawid\u0142owa lista p\u0142ac.');
                $this->redirect("/office/hr/{$clientId}/zus");
                return;
            }
            if ($run['status'] === 'draft') {
                Session::flash('error', 'Lista p\u0142ac musi by\u0107 co najmniej obliczona.');
                $this->redirect("/office/hr/{$clientId}/zus");
                return;
            }
        } else {
            $run = HrPayrollRun::findByClientAndPeriod($clientId, $month, $year);
            if ($run && $run['status'] !== 'draft') {
                $runId = (int) $run['id'];
            } else {
                Session::flash('error', 'Nie znaleziono obliczonej listy p\u0142ac za wybrany okres.');
                $this->redirect("/office/hr/{$clientId}/zus");
                return;
            }
        }

        try {
            $xmlPath = HrZusDraService::generate($clientId, $runId);
            $relPath = 'storage/hr/zus/' . basename($xmlPath);

            $existing = HrZusDeclaration::findByClientAndPeriod($clientId, $month, $year);
            if ($existing) {
                HrZusDeclaration::markGenerated($existing['id'], $relPath, 'generated');
                $declId = $existing['id'];
            } else {
                $declId = HrZusDeclaration::create([
                    'client_id' => $clientId, 'payroll_run_id' => $runId,
                    'period_month' => $month, 'period_year' => $year,
                    'xml_path' => $relPath, 'status' => 'generated',
                    'generated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            AuditLog::log($this->actorType(), $this->actorId(), 'hr_zus_generate',
                json_encode(['declaration_id' => $declId, 'run_id' => $runId]),
                'hr_zus_declaration', $declId);
            HrNotificationService::notifyZusGenerated($clientId, $month, $year);
            Session::flash('success', 'Deklaracja ZUS DRA+RCA zosta\u0142a wygenerowana.');
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d generowania deklaracji ZUS: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/zus");
    }

    public function zusRegenerate(string $clientId, string $declarationId): void
    {
        $clientId      = (int) $clientId;
        $declarationId = (int) $declarationId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/zus");
            return;
        }

        $decl = HrZusDeclaration::findById($declarationId);
        if (!$decl || (int)$decl['client_id'] !== $clientId || !$decl['payroll_run_id']) {
            Session::flash('error', 'Nie mo\u017cna zregenerowa\u0107 tej deklaracji.');
            $this->redirect("/office/hr/{$clientId}/zus");
            return;
        }

        try {
            $xmlPath = HrZusDraService::generate($clientId, (int)$decl['payroll_run_id']);
            $relPath = 'storage/hr/zus/' . basename($xmlPath);
            HrZusDeclaration::markGenerated($declarationId, $relPath, 'generated');
            AuditLog::log($this->actorType(), $this->actorId(), 'hr_zus_regenerate',
                json_encode(['declaration_id' => $declarationId]),
                'hr_zus_declaration', $declarationId);
            Session::flash('success', 'Deklaracja ZUS wygenerowana ponownie.');
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/zus");
    }

    public function zusDownload(string $clientId, string $declarationId): void
    {
        $clientId      = (int) $clientId;
        $declarationId = (int) $declarationId;
        $this->authorizeClientHr($clientId);

        $decl = HrZusDeclaration::findById($declarationId);
        if (!$decl || (int)$decl['client_id'] !== $clientId || empty($decl['xml_path'])) {
            $this->forbidden();
        }

        $absPath = __DIR__ . '/../../' . $decl['xml_path'];
        if (!file_exists($absPath)) {
            Session::flash('error', 'Plik XML nie istnieje.');
            $this->redirect("/office/hr/{$clientId}/zus");
            return;
        }

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($absPath) . '"');
        header('Content-Length: ' . filesize($absPath));
        readfile($absPath);
        exit;
    }

    public function pitDeclarations(string $clientId): void
    {
        $clientId     = (int) $clientId;
        $client       = $this->authorizeClientHr($clientId);
        $selectedYear = (int) ($_GET['year'] ?? date('Y'));

        $years    = HrPitDeclaration::getYearsForClient($clientId);
        $runYears = HrPayrollRun::getYearsForClient($clientId);
        $years    = array_unique(array_merge($years, $runYears));
        rsort($years);
        if (empty($years)) $years = [(int) date('Y')];

        $declarations = HrPitDeclaration::findByClientAndYear($clientId, $selectedYear);

        $employees = \App\Core\HrDatabase::getInstance()->fetchAll(
            "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name
             FROM hr_employees WHERE client_id = ? AND is_active = 1
             ORDER BY last_name, first_name",
            [$clientId]
        );

        $this->render('office/hr/pit_declarations', compact(
            'client', 'clientId', 'declarations', 'years', 'selectedYear', 'employees'
        ));
    }

    public function pitGenerate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/pit");
            return;
        }

        $type  = $_POST['type'] ?? '';
        $year  = max(2020, (int) ($_POST['year'] ?? date('Y')));
        $empId = (int) ($_POST['employee_id'] ?? 0);

        try {
            if ($type === 'pit11' && $empId) {
                $this->generateOnePit11($clientId, $empId, $year);
                Session::flash('success', 'PIT-11 zosta\u0142 wygenerowany.');

            } elseif ($type === 'pit11-all') {
                $employees = \App\Core\HrDatabase::getInstance()->fetchAll(
                    "SELECT id FROM hr_employees WHERE client_id = ? AND is_active = 1",
                    [$clientId]
                );
                $success = 0;
                $errors  = [];
                foreach ($employees as $emp) {
                    try {
                        $this->generateOnePit11($clientId, (int)$emp['id'], $year);
                        $success++;
                    } catch (\Throwable $e) {
                        $errors[] = "Pracownik #{$emp['id']}: " . $e->getMessage();
                    }
                }
                $msg = "Wygenerowano PIT-11 dla {$success} pracownik\u00f3w.";
                if ($errors) $msg .= ' B\u0142\u0119dy: ' . implode('; ', $errors);
                Session::flash($errors ? 'error' : 'success', $msg);

            } elseif ($type === 'pit4r') {
                $pdfPath = HrPit4rService::generate($clientId, $year);
                $relPath = 'storage/hr/pit/' . basename($pdfPath);

                $existing = HrPitDeclaration::findPit4rForClient($clientId, $year);
                if ($existing) {
                    HrPitDeclaration::markGenerated((int)$existing['id'], $relPath, 'generated');
                } else {
                    HrPitDeclaration::upsert([
                        'client_id' => $clientId, 'employee_id' => null,
                        'declaration_type' => 'PIT-4R', 'tax_year' => $year,
                        'total_gross' => 0, 'total_zus_employee' => 0,
                        'total_pit_advances' => 0, 'total_kup' => 0,
                        'status' => 'generated', 'export_path' => $relPath,
                        'generated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                AuditLog::log($this->actorType(), $this->actorId(), 'hr_pit4r_generate',
                    json_encode(['client_id' => $clientId, 'year' => $year]),
                    'hr_pit_declaration', 0);
                Session::flash('success', 'PIT-4R zosta\u0142 wygenerowany.');
            } else {
                Session::flash('error', 'Nieznany typ deklaracji.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d generowania: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/pit?year={$year}");
    }

    private function generateOnePit11(int $clientId, int $empId, int $year): void
    {
        $pdfPath = HrPit11Service::generate($clientId, $empId, $year);
        $relPath = 'storage/hr/pit/' . basename($pdfPath);
        $agg     = HrPitDeclaration::aggregateYearForEmployee($empId, $year);

        HrPitDeclaration::upsert([
            'client_id' => $clientId, 'employee_id' => $empId,
            'declaration_type' => 'PIT-11', 'tax_year' => $year,
            'total_gross' => $agg['total_gross'],
            'total_zus_employee' => $agg['total_zus_employee'],
            'total_pit_advances' => $agg['total_pit_advances'],
            'total_kup' => $agg['total_kup'],
            'status' => 'generated', 'export_path' => $relPath,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_pit11_generate',
            json_encode(['employee_id' => $empId, 'year' => $year]),
            'hr_pit_declaration', $empId);
    }

    public function pitDownload(string $clientId, string $declarationId): void
    {
        $clientId      = (int) $clientId;
        $declarationId = (int) $declarationId;
        $this->authorizeClientHr($clientId);

        $decl = HrPitDeclaration::findById($declarationId);
        if (!$decl || (int)$decl['client_id'] !== $clientId || empty($decl['export_path'])) {
            $this->forbidden();
        }

        $absPath = __DIR__ . '/../../' . $decl['export_path'];
        if (!file_exists($absPath)) {
            Session::flash('error', 'Plik PDF nie istnieje.');
            $this->redirect("/office/hr/{$clientId}/pit");
            return;
        }

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($absPath) . '"');
        header('Content-Length: ' . filesize($absPath));
        readfile($absPath);
        exit;
    }
}
