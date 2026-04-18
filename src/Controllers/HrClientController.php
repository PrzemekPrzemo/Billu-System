<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HrDatabase;
use App\Core\Language;
use App\Core\Session;
use App\Core\Database;
use App\Models\AuditLog;
use App\Models\HrContract;
use App\Models\HrDocument;
use App\Models\HrEmployee;
use App\Models\HrLeaveBalance;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\HrOnboardingTask;
use App\Models\HrPayrollItem;
use App\Models\HrPayrollRun;
use App\Services\HrAccessService;
use App\Services\HrDocumentStorageService;
use App\Services\HrLeaveService;
use App\Services\HrPayslipPdfService;

class HrClientController extends Controller
{
    private int $clientId;

    public function __construct()
    {
        Auth::requireClient();
        $lang = Session::get('client_language', 'pl');
        Language::setLocale($lang);
        $this->clientId = (int) Session::get('client_id');

        if (!HrAccessService::isEnabledForClient($this->clientId)) {
            Session::flash('error', 'Modu\u0142 Kadry i P\u0142ace nie jest aktywny dla Twojej firmy.');
            $this->redirect('/client');
        }
    }

    public function dashboard(): void
    {
        $db = HrDatabase::getInstance();
        $activeCount    = HrEmployee::countByClient($this->clientId, true);
        $pendingLeaves  = HrLeaveRequest::countPendingByClient($this->clientId);

        $expiringContracts = $db->fetchAll(
            "SELECT c.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.id AS emp_id
             FROM hr_contracts c
             JOIN hr_employees e ON c.employee_id = e.id
             WHERE c.client_id = ? AND c.is_current = 1
               AND c.end_date IS NOT NULL
               AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY c.end_date ASC",
            [$this->clientId]
        );

        $latestPayroll = $db->fetchOne(
            "SELECT * FROM hr_payroll_runs
             WHERE client_id = ? AND status != 'draft'
             ORDER BY period_year DESC, period_month DESC LIMIT 1",
            [$this->clientId]
        );

        $costTrend = $db->fetchAll(
            "SELECT period_month, period_year, total_gross, total_employer_cost
             FROM hr_payroll_runs
             WHERE client_id = ? AND status IN ('calculated','approved','locked')
             ORDER BY period_year DESC, period_month DESC LIMIT 6",
            [$this->clientId]
        );
        $costTrend = array_reverse($costTrend);

        $this->render('client/hr/dashboard', compact(
            'activeCount', 'pendingLeaves', 'expiringContracts', 'latestPayroll', 'costTrend'
        ));
    }

    public function employees(): void
    {
        $employees = HrEmployee::findByClient($this->clientId);
        $this->render('client/hr/employees', compact('employees'));
    }

    public function employeeDetail(string $id): void
    {
        $id       = (int) $id;
        $employee = HrEmployee::findById($id);
        if (!$employee || (int)$employee['client_id'] !== $this->clientId) $this->forbidden();

        $contracts       = HrContract::findByEmployee($id);
        $currentContract = HrContract::findCurrentByEmployee($id);
        $leaveBalance    = HrLeaveBalance::findByEmployeeYear($id, (int)date('Y'));

        $this->render('client/hr/employee_detail', compact('employee', 'contracts', 'currentContract', 'leaveBalance'));
    }

    public function leaveRequests(): void
    {
        $status       = $_GET['status'] ?? null;
        $requests     = HrLeaveRequest::findByClient($this->clientId, $status ?: null);
        $leaveTypes   = HrLeaveType::findAll();
        $employees    = HrEmployee::findByClient($this->clientId, true);
        $pendingCount = HrLeaveRequest::countPendingByClient($this->clientId);
        $this->render('client/hr/leave_requests', compact('requests', 'leaveTypes', 'employees', 'status', 'pendingCount'));
    }

    public function leaveCreate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/leaves'); return; }

        $empId       = (int) ($_POST['employee_id'] ?? 0);
        $leaveTypeId = (int) ($_POST['leave_type_id'] ?? 0);
        $dateFrom    = trim($_POST['date_from'] ?? '');
        $dateTo      = trim($_POST['date_to'] ?? '');

        $validDate = fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)
            && \DateTime::createFromFormat('Y-m-d', $d) !== false;

        if (!$empId || !$leaveTypeId || !$validDate($dateFrom) || !$validDate($dateTo)) {
            Session::flash('error', 'Wype\u0142nij wszystkie wymagane pola.');
            $this->redirect('/client/hr/leaves');
            return;
        }

        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $this->clientId) $this->forbidden();

        $daysCount = HrLeaveService::countBusinessDays($dateFrom, $dateTo);

        $requestId = HrLeaveRequest::create([
            'employee_id' => $empId, 'client_id' => $this->clientId,
            'leave_type_id' => $leaveTypeId, 'date_from' => $dateFrom, 'date_to' => $dateTo,
            'days_count' => $daysCount, 'notes' => trim($_POST['notes'] ?? '') ?: null,
            'status' => 'pending', 'submitted_by_type' => 'client', 'submitted_by_id' => $this->clientId,
        ]);

        AuditLog::log('client', $this->clientId, 'hr_leave_create', json_encode(['request_id' => $requestId]), 'hr_leave_request', $requestId);
        Session::flash('success', Language::get('hr_leave_submitted'));
        $this->redirect('/client/hr/leaves');
    }

    public function leaveCancel(string $id): void
    {
        $id = (int) $id;
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/leaves'); return; }

        $request = HrLeaveRequest::findById($id);
        if (!$request || (int)$request['client_id'] !== $this->clientId || $request['status'] !== 'pending') {
            Session::flash('error', 'Nie mo\u017cna anulowa\u0107 tego wniosku.');
            $this->redirect('/client/hr/leaves');
            return;
        }

        HrLeaveRequest::cancel($id);
        AuditLog::log('client', $this->clientId, 'hr_leave_cancel', json_encode(['request_id' => $id]), 'hr_leave_request', $id);
        Session::flash('success', Language::get('hr_leave_cancelled'));
        $this->redirect('/client/hr/leaves');
    }

    public function leaveApprove(string $id): void
    {
        $id = (int) $id;
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/leaves'); return; }

        $request = HrLeaveRequest::findById($id);
        if (!$request || (int)$request['client_id'] !== $this->clientId || $request['status'] !== 'pending') {
            Session::flash('error', 'Nie mo\u017cna zatwierdzi\u0107 tego wniosku.');
            $this->redirect('/client/hr/leaves');
            return;
        }

        HrLeaveRequest::approve($id, 'client', $this->clientId);
        $year = (int) date('Y', strtotime($request['date_from']));
        HrLeaveBalance::adjustUsed($request['employee_id'], $year, $request['leave_type_id'], (float)$request['days_count']);

        AuditLog::log('client', $this->clientId, 'hr_leave_approve', json_encode(['request_id' => $id]), 'hr_leave_request', $id);
        Session::flash('success', 'Wniosek urlopowy zosta\u0142 zatwierdzony.');
        $this->redirect('/client/hr/leaves');
    }

    public function leaveReject(string $id): void
    {
        $id = (int) $id;
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/leaves'); return; }

        $request = HrLeaveRequest::findById($id);
        if (!$request || (int)$request['client_id'] !== $this->clientId || $request['status'] !== 'pending') {
            Session::flash('error', 'Nie mo\u017cna odrzuci\u0107 tego wniosku.');
            $this->redirect('/client/hr/leaves');
            return;
        }

        $reason = trim($_POST['rejection_reason'] ?? '');
        HrLeaveRequest::reject($id, 'client', $this->clientId, $reason);

        AuditLog::log('client', $this->clientId, 'hr_leave_reject', json_encode(['request_id' => $id]), 'hr_leave_request', $id);
        Session::flash('success', 'Wniosek urlopowy zosta\u0142 odrzucony.');
        $this->redirect('/client/hr/leaves');
    }

    public function leaveCalendar(): void
    {
        $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
        $year  = max(2020, (int) ($_GET['year'] ?? date('Y')));

        $firstDay = sprintf('%04d-%02d-01', $year, $month);
        $lastDay  = date('Y-m-t', strtotime($firstDay));

        $requests = HrDatabase::getInstance()->fetchAll(
            "SELECT lr.*, lt.name_pl AS leave_type, lt.code AS leave_code,
                    e.id AS emp_id, e.first_name, e.last_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name
             FROM hr_leave_requests lr
             JOIN hr_leave_types lt ON lr.leave_type_id = lt.id
             JOIN hr_employees e ON lr.employee_id = e.id
             WHERE lr.client_id = ?
               AND lr.status IN ('pending','approved')
               AND lr.date_from <= ? AND lr.date_to >= ?
             ORDER BY e.last_name, lr.date_from",
            [$this->clientId, $lastDay, $firstDay]
        );

        $shortTypes = ['wypoczynkowy'=>'wys','chorobowy'=>'L4','macierzynski'=>'mac',
                       'ojcowski'=>'ojc','okolicznosciowy'=>'okol','bezplatny'=>'bezp',
                       'na_zadanie'=>'\u017c\u0105d','wychowawczy'=>'wych'];

        $calendarData = [];
        foreach ($requests as $req) {
            $from = max($req['date_from'], $firstDay);
            $to   = min($req['date_to'], $lastDay);
            $cur  = strtotime($from);
            $end  = strtotime($to);
            while ($cur <= $end) {
                $d = date('Y-m-d', $cur);
                $calendarData[$d][] = [
                    'employee_id'      => $req['emp_id'],
                    'employee_name'    => $req['employee_name'],
                    'first_name'       => $req['first_name'],
                    'last_name'        => $req['last_name'],
                    'leave_type'       => $req['leave_type'],
                    'leave_type_short' => $shortTypes[$req['leave_code']] ?? $req['leave_code'],
                    'status'           => $req['status'],
                ];
                $cur += 86400;
            }
        }

        $employees = HrDatabase::getInstance()->fetchAll(
            "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name
             FROM hr_employees WHERE client_id = ? AND is_active = 1 ORDER BY last_name, first_name",
            [$this->clientId]
        );

        $holidays = HrLeaveService::getPolishHolidays($year);

        $this->render('client/hr/leave_calendar', compact('month', 'year', 'calendarData', 'employees', 'holidays'));
    }

    public function attendance(): void
    {
        $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
        $year  = max(2020, (int) ($_GET['year'] ?? date('Y')));
        $grid  = \App\Services\HrAttendanceService::buildMonthGrid($this->clientId, $month, $year);
        $this->render('client/hr/attendance', compact('grid', 'month', 'year'));
    }

    public function contracts(): void
    {
        $contracts = HrContract::findByClient($this->clientId);
        $this->render('client/hr/contracts', compact('contracts'));
    }

    public function contractPdf(string $contractId): void
    {
        $contractId = (int) $contractId;
        $contract   = HrContract::findById($contractId);
        if (!$contract || (int)$contract['client_id'] !== $this->clientId) $this->forbidden();

        try {
            $path = \App\Services\HrUmowaPdfService::generate($contractId);
            AuditLog::log('client', $this->clientId, 'hr_contract_pdf_download',
                json_encode(['contract_id' => $contractId]), 'hr_contract', $contractId);
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="umowa_' . $contractId . '.pdf"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d generowania PDF: ' . $e->getMessage());
            $this->redirect('/client/hr/contracts');
        }
    }

    public function costs(): void
    {
        $selectedYear = (int) ($_GET['year'] ?? date('Y'));
        $db = HrDatabase::getInstance();

        $monthlyCosts = $db->fetchAll(
            "SELECT period_month, period_year, total_gross, total_net,
                    total_zus_employee, total_zus_employer, total_pit_advance,
                    total_ppk_employee, total_ppk_employer, total_employer_cost,
                    employee_count, status
             FROM hr_payroll_runs
             WHERE client_id = ? AND period_year = ? AND status IN ('calculated','approved','locked')
             ORDER BY period_month ASC",
            [$this->clientId, $selectedYear]
        );

        $ytd = ['gross' => 0, 'net' => 0, 'employer_cost' => 0, 'zus_employer' => 0, 'pit' => 0];
        foreach ($monthlyCosts as $row) {
            $ytd['gross']         += (float)$row['total_gross'];
            $ytd['net']           += (float)$row['total_net'];
            $ytd['employer_cost'] += (float)$row['total_employer_cost'];
            $ytd['zus_employer']  += (float)$row['total_zus_employer'];
            $ytd['pit']           += (float)$row['total_pit_advance'];
        }

        $latestRun = $db->fetchOne(
            "SELECT id FROM hr_payroll_runs
             WHERE client_id = ? AND status IN ('calculated','approved','locked')
             ORDER BY period_year DESC, period_month DESC LIMIT 1",
            [$this->clientId]
        );
        $employeeCosts = [];
        if ($latestRun) {
            $employeeCosts = $db->fetchAll(
                "SELECT pi.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name
                 FROM hr_payroll_items pi
                 JOIN hr_employees e ON pi.employee_id = e.id
                 WHERE pi.payroll_run_id = ?
                 ORDER BY pi.employer_total_cost DESC",
                [$latestRun['id']]
            );
        }

        $years = HrPayrollRun::getYearsForClient($this->clientId);
        if (empty($years)) $years = [(int) date('Y')];

        $this->render('client/hr/costs', compact('monthlyCosts', 'ytd', 'employeeCosts', 'selectedYear', 'years'));
    }

    public function documents(string $empId): void
    {
        $empId    = (int) $empId;
        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $this->clientId) $this->forbidden();

        $documents = HrDocument::findByEmployee($empId);
        $this->render('client/hr/documents', compact('employee', 'documents'));
    }

    public function documentDownload(string $empId, string $docId): void
    {
        $empId = (int) $empId;
        $docId = (int) $docId;

        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $this->clientId) $this->forbidden();

        $doc = HrDocument::findById($docId);
        if (!$doc || (int)$doc['employee_id'] !== $empId || (int)$doc['client_id'] !== $this->clientId) $this->forbidden();

        try {
            $content  = HrDocumentStorageService::readDecrypted($doc['stored_path']);
            $filename = $doc['original_name'] ?? 'document';
            $mimeType = $doc['mime_type'] ?? 'application/octet-stream';

            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png',
                             'application/msword',
                             'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $safeMime     = in_array($mimeType, $allowedMimes, true) ? $mimeType : 'application/octet-stream';
            $safeFilename = str_replace(['"', '\\', "\n", "\r", "\0"], '', basename($filename));

            while (ob_get_level()) ob_end_clean();
            header('Content-Type: ' . $safeMime);
            header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d pobierania pliku: ' . $e->getMessage());
            $this->redirect("/client/hr/employees/{$empId}/documents");
        }
    }

    public function documentUpload(string $empId): void
    {
        $empId = (int) $empId;
        if (!$this->validateCsrf()) {
            $this->redirect("/client/hr/employees/{$empId}/documents");
            return;
        }

        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $this->clientId) $this->forbidden();

        if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Brak pliku lub b\u0142\u0105d przesy\u0142ania.');
            $this->redirect("/client/hr/employees/{$empId}/documents");
            return;
        }

        $file     = $_FILES['document'];
        $tmpPath  = $file['tmp_name'];
        $fileSize = (int) $file['size'];

        if ($fileSize > 10 * 1024 * 1024) {
            Session::flash('error', 'Plik jest za du\u017cy (max 10 MB).');
            $this->redirect("/client/hr/employees/{$empId}/documents");
            return;
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);
        $allowed  = ['application/pdf', 'image/jpeg', 'image/png',
                     'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($mimeType, $allowed, true)) {
            Session::flash('error', 'Niedozwolony typ pliku.');
            $this->redirect("/client/hr/employees/{$empId}/documents");
            return;
        }

        $category    = $_POST['category'] ?? 'inne';
        $allowedCats = ['pit2', 'bhp', 'badanie', 'certyfikat', 'inne'];
        if (!in_array($category, $allowedCats, true)) $category = 'inne';

        try {
            $storedPath = HrDocumentStorageService::storeEncrypted($tmpPath, $this->clientId, $empId);

            HrDocument::create([
                'employee_id' => $empId, 'client_id' => $this->clientId,
                'category' => $category, 'original_name' => $file['name'],
                'stored_path' => $storedPath, 'mime_type' => $mimeType, 'file_size' => $fileSize,
                'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                'uploaded_by_type' => 'client', 'uploaded_by_id' => $this->clientId,
            ]);

            AuditLog::log('client', $this->clientId, 'hr_doc_upload',
                json_encode(['employee_id' => $empId, 'category' => $category]), 'hr_employee', $empId);
            Session::flash('success', 'Dokument zosta\u0142 przes\u0142any.');
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d przesy\u0142ania: ' . $e->getMessage());
        }

        $this->redirect("/client/hr/employees/{$empId}/documents");
    }

    public function onboarding(string $empId): void
    {
        $empId    = (int) $empId;
        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $this->clientId) $this->forbidden();

        $phase = $_GET['phase'] ?? 'onboarding';
        if (!in_array($phase, ['onboarding', 'offboarding'], true)) $phase = 'onboarding';

        $tasks       = HrOnboardingTask::findByEmployee($empId, $phase);
        $progressOn  = HrOnboardingTask::getProgress($empId, 'onboarding');
        $progressOff = HrOnboardingTask::getProgress($empId, 'offboarding');

        $this->render('client/hr/onboarding', compact('employee', 'phase', 'tasks', 'progressOn', 'progressOff'));
    }

    public function analytics(): void
    {
        $year = (int) ($_GET['year'] ?? date('Y'));
        $db   = HrDatabase::getInstance();

        $costTrend = $db->fetchAll(
            "SELECT period_month, period_year, total_gross, total_employer_cost
             FROM hr_payroll_runs
             WHERE client_id = ? AND status IN ('calculated','approved','locked')
             ORDER BY period_year DESC, period_month DESC LIMIT 12",
            [$this->clientId]
        );
        $costTrend = array_reverse($costTrend);

        $distribution = $db->fetchAll(
            "SELECT c.contract_type, COUNT(*) AS count
             FROM hr_contracts c
             JOIN hr_employees e ON c.employee_id = e.id
             WHERE c.client_id = ? AND c.is_current = 1 AND e.is_active = 1
             GROUP BY c.contract_type",
            [$this->clientId]
        );

        $leaveUsage = $db->fetchAll(
            "SELECT lt.name_pl AS leave_type, SUM(lr.days_count) AS total_days, COUNT(*) AS request_count
             FROM hr_leave_requests lr
             JOIN hr_leave_types lt ON lr.leave_type_id = lt.id
             WHERE lr.client_id = ? AND lr.status = 'approved' AND YEAR(lr.date_from) = ?
             GROUP BY lt.id, lt.name_pl ORDER BY total_days DESC",
            [$this->clientId, $year]
        );

        $activeCount = HrEmployee::countByClient($this->clientId, true);
        $years = HrPayrollRun::getYearsForClient($this->clientId);
        if (empty($years)) $years = [(int) date('Y')];

        $this->render('client/hr/analytics', compact('costTrend', 'distribution', 'leaveUsage', 'activeCount', 'year', 'years'));
    }

    public function messages(): void
    {
        $messages = Database::getInstance()->fetchAll(
            "SELECT m.*,
                    CASE WHEN m.hr_employee_id IS NOT NULL
                         THEN (SELECT CONCAT(e.first_name, ' ', e.last_name) FROM " . HrDatabase::hrDbName() . ".hr_employees e WHERE e.id = m.hr_employee_id)
                         ELSE NULL END AS hr_employee_name
             FROM messages m
             WHERE m.client_id = ? AND m.hr_context IS NOT NULL AND m.parent_id IS NULL
             ORDER BY m.created_at DESC LIMIT 50",
            [$this->clientId]
        );

        $employees = HrEmployee::findByClient($this->clientId, true);
        $this->render('client/hr/messages', compact('messages', 'employees'));
    }

    public function messageCreate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/messages'); return; }

        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');
        $context = $_POST['hr_context'] ?? 'general';
        $empId   = (int) ($_POST['hr_employee_id'] ?? 0);

        if (!$body) {
            Session::flash('error', 'Tre\u015b\u0107 wiadomo\u015bci jest wymagana.');
            $this->redirect('/client/hr/messages');
            return;
        }

        $allowedContexts = ['employee', 'contract', 'payroll', 'leave', 'general'];
        if (!in_array($context, $allowedContexts, true)) $context = 'general';

        if ($empId) {
            $emp = HrEmployee::findById($empId);
            if (!$emp || (int)$emp['client_id'] !== $this->clientId) $empId = 0;
        }

        Database::getInstance()->query(
            "INSERT INTO messages (client_id, sender_type, sender_id, subject, body, hr_employee_id, hr_context, is_read_by_client, is_read_by_office)
             VALUES (?, 'client', ?, ?, ?, ?, ?, 1, 0)",
            [$this->clientId, $this->clientId, $subject ?: null, $body, $empId ?: null, $context]
        );

        Session::flash('success', 'Wiadomo\u015b\u0107 zosta\u0142a wys\u0142ana.');
        $this->redirect('/client/hr/messages');
    }

    public function payslips(): void
    {
        $allRuns     = HrPayrollRun::findByClient($this->clientId);
        $visibleRuns = array_filter($allRuns, fn($r) => $r['status'] !== 'draft');

        $runsByYear = [];
        foreach ($visibleRuns as $run) {
            $runsByYear[$run['period_year']][] = $run;
        }
        krsort($runsByYear);

        $employeesByRun = [];
        foreach ($visibleRuns as $run) {
            $items = HrPayrollItem::findByRun($run['id']);
            if (!empty($items)) $employeesByRun[$run['id']] = $items;
        }

        $this->render('client/hr/payslips', compact('runsByYear', 'employeesByRun'));
    }

    public function payslipPdf(string $runId, string $empId): void
    {
        $runId = (int) $runId;
        $empId = (int) $empId;

        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $this->clientId || $run['status'] === 'draft') $this->forbidden();

        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $this->clientId) $this->forbidden();

        try {
            $path = HrPayslipPdfService::generate($runId, $empId);
            AuditLog::log('client', $this->clientId, 'hr_payslip_download',
                json_encode(['run_id' => $runId, 'employee_id' => $empId]), 'hr_payroll_item', $empId);
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'B\u0142\u0105d generowania PDF: ' . $e->getMessage());
            $this->redirect('/client/hr/payslips');
        }
    }
}
