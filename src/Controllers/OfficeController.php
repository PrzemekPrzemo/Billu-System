<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ModuleAccess;
use App\Core\Session;
use App\Core\Language;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Office;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\ClientCostCenter;
use App\Models\OfficeEmailSettings;
use App\Models\OfficeEmployee;
use App\Models\Report;
use App\Services\ImportService;
use App\Services\KsefApiService;
use App\Core\Pagination;
use App\Models\Notification;
use App\Models\ClientTaxConfig;
use App\Models\DuplicateCandidate;
use App\Services\TaxCalendarService;
use App\Services\DuplicateDetectionService;
use App\Models\Message;
use App\Models\MessageNotificationPref;
use App\Models\ClientTask;
use App\Models\TaxPayment;
use App\Models\ClientNote;
use App\Models\ClientMonthlyStatus;
use App\Models\KsefOperationLog;
use App\Models\ClientFile;
use App\Models\ClientEmployee;
use App\Models\EmployeeContract;
use App\Models\PayrollList;
use App\Models\PayrollEntry;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;
use App\Models\PayrollDeclaration;
use App\Services\PayrollCalculatorService;
use App\Services\PayrollListService;
use App\Services\PayrollPdfService;
use App\Services\PayrollDeclarationService;
use App\Services\LeaveService;

class OfficeController extends Controller
{
    public function __construct()
    {
        Auth::requireOfficeOrEmployee();
        $lang = Session::get('office_language', 'pl');
        Language::setLocale($lang);
    }

    /**
     * Returns array of client IDs the employee can access, or null if office (full access).
     */
    private function getEmployeeClientFilter(): ?array
    {
        if (Auth::isEmployee()) {
            $employeeId = Session::get('employee_id');
            return OfficeEmployee::getAssignedClientIds($employeeId);
        }
        return null;
    }

    /**
     * Centralized tenant gate for every endpoint that takes {clientId} in the URL.
     * Verifies (1) client exists, (2) client.office_id matches session office_id,
     * (3) for office-employees, the client is in the assignment filter.
     * Redirects + returns null on any mismatch — caller MUST check the return.
     */
    private function requireClientForOffice($clientId, string $redirectUrl = '/office/hr'): ?array
    {
        $clientId = (int) $clientId;
        $client = Client::findById($clientId);
        $officeId = (int) Session::get('office_id');

        if (!$client || (int) ($client['office_id'] ?? 0) !== $officeId) {
            $this->redirect($redirectUrl);
            return null;
        }

        $filter = $this->getEmployeeClientFilter();
        if ($filter !== null && !in_array($clientId, $filter, true)) {
            $this->redirect($redirectUrl);
            return null;
        }

        return $client;
    }

    /**
     * Verifies a record (already loaded) belongs to a client of the current office
     * and — for office-employees — that the client is assigned. Used for endpoints
     * that take a record id (payroll list / leave / contract / declaration) without
     * a client id in the URL. Returns the record on success, null after redirect.
     */
    private function requireRecordForOffice(?array $record, string $redirectUrl = '/office/hr'): ?array
    {
        if (!$record) {
            $this->redirect($redirectUrl);
            return null;
        }
        $clientId = (int) ($record['client_id'] ?? 0);
        if ($clientId === 0 || $this->requireClientForOffice($clientId, $redirectUrl) === null) {
            return null;
        }
        return $record;
    }

    public function dashboard(): void
    {
        $officeId = Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();

        $clients = Client::findByOffice($officeId, true);
        $batches = InvoiceBatch::findByOffice($officeId);

        // Filter for employee
        if ($clientFilter !== null) {
            $clients = array_values(array_filter($clients, fn($c) => in_array($c['id'], $clientFilter)));
            $batches = array_values(array_filter($batches, fn($b) => in_array($b['client_id'], $clientFilter)));
        }

        $totalPending = 0;
        foreach ($batches as $b) {
            $totalPending += $b['pending_count'] ?? 0;
        }

        $clientProgress = Invoice::getVerificationProgressByOffice($officeId);
        if ($clientFilter !== null) {
            $clientProgress = array_values(array_filter($clientProgress, fn($cp) => in_array($cp['client_id'] ?? 0, $clientFilter)));
        }
        $overdueBatches = InvoiceBatch::getOverdueByOffice($officeId);
        if ($clientFilter !== null) {
            $overdueBatches = array_values(array_filter($overdueBatches, fn($b) => in_array($b['client_id'], $clientFilter)));
        }

        $supportContact = [
            'name'  => Setting::get('support_contact_name'),
            'email' => Setting::get('support_contact_email'),
            'phone' => Setting::get('support_contact_phone'),
        ];

        // Office branding for employee dashboard
        $officeBranding = null;
        if (Auth::isEmployee()) {
            $office = Office::findById($officeId);
            if ($office) {
                $officeBranding = [
                    'name'      => $office['name'] ?? '',
                    'nip'       => $office['nip'] ?? '',
                    'email'     => $office['email'] ?? '',
                    'phone'     => $office['phone'] ?? '',
                    'address'   => $office['address'] ?? '',
                    'logo_path' => $office['logo_path'] ?? null,
                ];
            }
        }

        // Top 5 clients by pending invoices
        $topPending = array_filter($clientProgress, fn($cp) => ($cp['pending_count'] ?? 0) > 0);
        usort($topPending, fn($a, $b) => ($b['pending_count'] ?? 0) <=> ($a['pending_count'] ?? 0));
        $topPending = array_slice($topPending, 0, 5);

        // Tax payments summary for current/previous month
        $taxSummary = TaxPayment::getCurrentMonthSummaryByOffice($officeId, $clientFilter);

        // Pinned client notes
        $pinnedNotes = ClientNote::findPinnedByOffice($officeId, 3);

        // Charts data
        $monthlyStats = Invoice::getMonthlyStatsByOffice($officeId, 6, $clientFilter);
        $statusTotals = Invoice::getStatusTotalsByOffice($officeId, $clientFilter);

        // NBP exchange rates for dashboard widget
        $exchangeRates = \App\Services\NbpExchangeRateService::getLatestRates(['EUR', 'USD', 'GBP']);

        $this->render('office/dashboard', [
            'clients'        => $clients,
            'batches'        => $batches,
            'totalPending'   => $totalPending,
            'clientProgress' => $clientProgress,
            'overdueBatches' => $overdueBatches,
            'supportContact' => $supportContact,
            'isEmployee'     => Auth::isEmployee(),
            'officeBranding' => $officeBranding,
            'topPending'     => $topPending,
            'taxSummary'     => $taxSummary,
            'pinnedNotes'    => $pinnedNotes,
            'monthlyStats'   => $monthlyStats,
            'statusTotals'   => $statusTotals,
            'exchangeRates'  => $exchangeRates,
        ]);
    }

    public function clients(): void
    {
        $officeId = Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clients = Client::findByOffice($officeId);

        if ($clientFilter !== null) {
            $clients = array_values(array_filter($clients, fn($c) => in_array($c['id'], $clientFilter)));
        }

        // Get workflow statuses for current month
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $workflowStatuses = ClientMonthlyStatus::findByOfficePeriod($officeId, $currentYear, $currentMonth);

        // Get stats and note indicator per client
        foreach ($clients as &$c) {
            $c['stats'] = Invoice::countByClient($c['id']);
            $c['has_note'] = (bool) ClientNote::findLatestByClient($c['id'], $officeId);
            $c['workflow_status'] = $workflowStatuses[$c['id']] ?? 'import';
        }

        $this->render('office/clients', [
            'clients'    => $clients,
            'isEmployee' => Auth::isEmployee(),
        ]);
    }

    public function batches(): void
    {
        $officeId = Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $total = InvoiceBatch::countByOffice($officeId);
        $pagination = Pagination::fromRequest($total, 25);
        $batches = InvoiceBatch::findByOfficePaginated($officeId, $pagination->offset, $pagination->perPage);

        if ($clientFilter !== null) {
            $batches = array_values(array_filter($batches, fn($b) => in_array($b['client_id'], $clientFilter)));
        }

        $this->render('office/batches', ['batches' => $batches, 'pagination' => $pagination]);
    }

    public function batchDetail(string $id): void
    {
        $officeId = Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $batch = InvoiceBatch::findById((int) $id);

        if (!$batch) {
            $this->redirect('/office/batches');
            return;
        }

        // Verify this batch belongs to a client of this office
        $client = Client::findById($batch['client_id']);
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            $this->redirect('/office/batches');
            return;
        }

        // Employee can only see batches for assigned clients
        if ($clientFilter !== null && !in_array($batch['client_id'], $clientFilter)) {
            $this->redirect('/office/batches');
            return;
        }

        $filterStatus = $_GET['status'] ?? null;
        $filterSearch = $_GET['search'] ?? null;

        if ($filterStatus || $filterSearch) {
            $invoices = Invoice::findByBatchFiltered((int) $id, $filterStatus ?: null, $filterSearch ?: null);
        } else {
            $invoices = Invoice::findByBatch((int) $id);
        }
        $stats = Invoice::countByBatchAndStatus((int) $id);

        $commentCounts = \App\Models\InvoiceComment::countByBatch((int) $id);

        $this->render('office/batch_detail', [
            'batch'         => $batch,
            'invoices'      => $invoices,
            'stats'         => $stats,
            'commentCounts' => $commentCounts,
            'filters'       => ['status' => $filterStatus, 'search' => $filterSearch],
        ]);
    }

    /**
     * Office (or office-employee assigned to the client) accepts a cost invoice
     * that the client cannot accept on their own because whitelist_failed=1.
     * Requires a written justification (>=10 chars). All four tenant gates apply:
     * invoice → batch → client → office_id, plus the office-employee assignment
     * filter via requireClientForOffice. Auditable.
     */
    public function invoiceWhitelistOverride(string $id): void
    {
        $invoiceId = (int) $id;
        if (!$this->validateCsrf()) { $this->redirect('/office/batches'); return; }

        $invoice = Invoice::findById($invoiceId);
        if (!$invoice) { $this->redirect('/office/batches'); return; }

        // Tenant gate — the invoice must belong to a client of THIS office, AND
        // (for office-employees) the client must be in the assignment filter.
        if ($this->requireClientForOffice((int) $invoice['client_id'], '/office/batches') === null) {
            return;
        }

        // Override only makes sense when (a) the row is actually whitelist-failed
        // and (b) the invoice is still pending. Already-accepted/rejected: no-op.
        if (empty($invoice['whitelist_failed']) || ($invoice['status'] ?? '') !== 'pending') {
            Session::flash('error', 'whitelist_override_not_applicable');
            $this->redirect('/office/batches/' . (int) $invoice['batch_id']);
            return;
        }

        $reason = trim($_POST['reason'] ?? '');
        if (mb_strlen($reason) < 10) {
            Session::flash('error', 'whitelist_override_reason_required');
            $this->redirect('/office/batches/' . (int) $invoice['batch_id']);
            return;
        }
        if (mb_strlen($reason) > 1000) {
            $reason = mb_substr($reason, 0, 1000);
        }

        $byType = Auth::isEmployee() ? 'employee' : 'office';
        $byId   = (int) Auth::currentUserId();

        Invoice::acceptWithWhitelistOverride($invoiceId, $reason, $byType, $byId);

        AuditLog::log($byType, $byId, 'invoice_whitelist_override',
            "Invoice #{$invoiceId} accepted on behalf of client #{$invoice['client_id']} (whitelist override). Reason: " . mb_substr($reason, 0, 200),
            'invoice', $invoiceId);

        Session::flash('success', 'whitelist_override_applied');
        $this->redirect('/office/batches/' . (int) $invoice['batch_id']);
    }

    public function importForm(): void
    {
        $officeId = Session::get('office_id');
        $clients = Client::findByOffice($officeId, true);
        $importTemplates = \App\Models\ImportTemplate::findByOffice($officeId);
        $this->render('office/import', [
            'clients' => $clients,
            'importTemplates' => $importTemplates,
        ]);
    }

    public function importTemplateSave(): void
    {
        Auth::requireOffice();
        if (!$this->validateCsrf()) { $this->redirect('/office/import'); return; }

        $officeId = Session::get('office_id');
        $name = trim($_POST['template_name'] ?? '');
        $mapping = $_POST['column_mapping'] ?? '{}';

        if ($name === '') {
            Session::flash('error', 'import_template_name_required');
            $this->redirect('/office/import');
            return;
        }

        \App\Models\ImportTemplate::create([
            'office_id' => $officeId,
            'name' => $name,
            'column_mapping' => $mapping,
            'separator' => $_POST['separator'] ?? ';',
            'encoding' => $_POST['encoding'] ?? 'UTF-8',
            'skip_rows' => (int) ($_POST['skip_rows'] ?? 1),
        ]);

        Session::flash('success', 'import_template_saved');
        $this->redirect('/office/import');
    }

    public function importTemplateDelete(string $id): void
    {
        Auth::requireOffice();
        if (!$this->validateCsrf()) { $this->redirect('/office/import'); return; }

        $template = \App\Models\ImportTemplate::findById((int) $id);
        $officeId = Session::get('office_id');

        // Allow deletion only if it belongs to this office or is global
        if ($template && ($template['office_id'] === null || (int) $template['office_id'] === $officeId)) {
            \App\Models\ImportTemplate::delete((int) $id);
            Session::flash('success', 'import_template_deleted');
        }
        $this->redirect('/office/import');
    }

    public function importTemplate(): void
    {
        $path = \App\Services\ImportService::generateImportTemplate();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="szablon_import_faktur.xlsx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function import(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/import');
            return;
        }

        $officeId = Session::get('office_id');
        $clientId = $this->sanitizeInt($_POST['client_id'] ?? 0);
        $month = $this->sanitizeInt($_POST['month'] ?? date('n'));
        $year = $this->sanitizeInt($_POST['year'] ?? date('Y'));

        // Verify client belongs to this office
        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            Session::flash('error', 'access_denied');
            $this->redirect('/office/import');
            return;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'import_missing_data');
            $this->redirect('/office/import');
            return;
        }

        $file = $_FILES['file'];
        if ($file['size'] > 10 * 1024 * 1024) {
            Session::flash('error', 'file_too_large');
            $this->redirect('/office/import');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['xls', 'xlsx', 'txt', 'csv'])) {
            Session::flash('error', 'import_invalid_format');
            $this->redirect('/office/import');
            return;
        }

        $uploadPath = __DIR__ . '/../../storage/imports/' . uniqid('import_') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadPath);

        if (in_array($ext, ['xls', 'xlsx'])) {
            $result = ImportService::importFromExcel($uploadPath, $clientId, Session::get('office_id'), $month, $year, 'office', $officeId);
        } else {
            $result = ImportService::importFromText($uploadPath, $clientId, Session::get('office_id'), $month, $year, 'office', $officeId);
        }

        AuditLog::log('office', $officeId, 'invoices_imported', json_encode($result), 'batch', null);

        if ($result['success'] > 0) {
            Session::flash('success', 'import_success');
        }
        if (!empty($result['errors'])) {
            Session::flash('error', 'import_partial_errors');
        }

        Session::set('import_result', $result);
        $this->redirect('/office/import');
    }

    public function importKsef(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/import');
            return;
        }

        $officeId = Session::get('office_id');
        $clientId = $this->sanitizeInt($_POST['client_id'] ?? 0);
        $month = $this->sanitizeInt($_POST['month'] ?? date('n'));
        $year = $this->sanitizeInt($_POST['year'] ?? date('Y'));

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            Session::flash('error', 'access_denied');
            $this->redirect('/office/import');
            return;
        }

        $ksef = KsefApiService::forClient($client);
        if (!$ksef->isConfigured()) {
            Session::flash('error', 'ksef_not_configured');
            $this->redirect('/office/import');
            return;
        }

        $jobId = self::launchKsefImportJob($clientId, $month, $year, $officeId, 'office', $officeId);
        Session::set('ksef_import_job_id', $jobId);
        $this->redirect('/office/import');
    }

    public function ksefImportStatus(): void
    {
        $jobId = $_GET['job_id'] ?? '';
        $result = self::checkKsefImportStatus($jobId);
        if ($result === null) {
            $this->json(['error' => 'Job not found'], 404);
            return;
        }
        $this->json($result);
    }

    public function clientCostCenters(string $clientId): void
    {
        $officeId = Session::get('office_id');
        $client = Client::findById((int) $clientId);

        // Verify client belongs to this office
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            $this->redirect('/office/clients');
            return;
        }

        $costCenters = ClientCostCenter::findByClient((int) $clientId);

        $this->render('office/client_cost_centers', [
            'client'       => $client,
            'costCenters'  => $costCenters,
        ]);
    }

    public function clientCostCentersUpdate(string $clientId): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect("/office/clients/{$clientId}/cost-centers");
            return;
        }

        $officeId = Session::get('office_id');
        $client = Client::findById((int) $clientId);

        // Verify client belongs to this office
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            $this->redirect('/office/clients');
            return;
        }

        $hasCostCenters = isset($_POST['has_cost_centers']) ? 1 : 0;
        $costCenterNames = $_POST['cost_center_names'] ?? [];

        // Filter out empty names
        $costCenterNames = array_filter(array_map('trim', $costCenterNames));

        // Update client settings (cost centers)
        Client::update((int) $clientId, [
            'has_cost_centers' => $hasCostCenters,
        ]);

        // Sync cost centers
        if ($hasCostCenters && !empty($costCenterNames)) {
            ClientCostCenter::syncForClient((int) $clientId, $costCenterNames);
        } else {
            ClientCostCenter::deleteByClient((int) $clientId);
        }

        AuditLog::log('office', $officeId, 'client_cost_centers_updated', "Client ID: {$clientId}", 'client', (int) $clientId);
        Session::flash('success', 'cost_centers_updated');
        $this->redirect("/office/clients/{$clientId}/cost-centers");
    }

    // ── Analytics ─────────────────────────────────────────

    public function analytics(): void
    {
        ModuleAccess::requireModule('analytics');
        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();

        // Invoice stats (6 months, reuse existing)
        $monthlyStats = Invoice::getMonthlyStatsByOffice($officeId, 6, $clientFilter);
        $statusTotals = Invoice::getStatusTotalsByOffice($officeId, $clientFilter);

        // Verification progress per client
        $verificationProgress = Invoice::getVerificationProgressByOffice($officeId);
        if ($clientFilter !== null) {
            $verificationProgress = array_filter($verificationProgress, fn($v) => in_array((int)$v['client_id'], $clientFilter));
        }

        // Verification efficiency (batch processing times)
        $batchEfficiency = Invoice::getAvgVerificationTimeByOffice($officeId, 6);

        // Rejection rate per client
        $rejectionByClient = Invoice::getRejectionRateByClient($officeId, 10);

        // Monthly gross (accepted invoices value)
        $monthlyGross = Invoice::getMonthlyGrossByOffice($officeId, 12);

        // Client activity
        $clientActivity = Client::getActivityBreakdownByOffice($officeId);
        $totalClients = Client::countByOffice($officeId);

        // KSeF health
        $ksefHealth = KsefOperationLog::getHealthByOffice($officeId, 6);

        // Employee workload (if office has employees)
        $employeeWorkload = null;
        if ($clientFilter === null) { // only for office owner, not employees
            $db = \App\Core\Database::getInstance();
            $employeeWorkload = $db->fetchAll(
                "SELECT e.id, e.name,
                        COUNT(DISTINCT ec.client_id) as client_count,
                        COUNT(DISTINCT ib.id) as batch_count,
                        COUNT(i.id) as invoice_count
                 FROM office_employees e
                 LEFT JOIN office_employee_clients ec ON ec.employee_id = e.id
                 LEFT JOIN invoice_batches ib ON ib.client_id = ec.client_id AND ib.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                 LEFT JOIN invoices i ON i.batch_id = ib.id
                 WHERE e.office_id = ? AND e.is_active = 1
                 GROUP BY e.id, e.name
                 ORDER BY invoice_count DESC",
                [$officeId]
            );
        }

        $this->render('office/analytics', [
            'monthlyStats'        => $monthlyStats,
            'statusTotals'        => $statusTotals,
            'verificationProgress'=> $verificationProgress,
            'batchEfficiency'     => $batchEfficiency,
            'rejectionByClient'   => $rejectionByClient,
            'monthlyGross'        => $monthlyGross,
            'clientActivity'      => $clientActivity,
            'totalClients'        => $totalClients,
            'ksefHealth'          => $ksefHealth,
            'employeeWorkload'    => $employeeWorkload,
        ]);
    }

    public function reports(): void
    {
        $officeId = Session::get('office_id');
        $clientId = isset($_GET['client_id']) ? $this->sanitizeInt($_GET['client_id']) : null;

        // Verify client belongs to this office
        if ($clientId) {
            $client = Client::findById($clientId);
            if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
                $clientId = null;
            }
        }

        $reports = Report::findByOffice($officeId, $clientId);
        $clients = Client::findByOffice($officeId, true);

        $this->render('office/reports', [
            'reports'        => $reports,
            'clients'        => $clients,
            'selectedClient' => $clientId,
        ]);
    }

    public function downloadReport(string $id): void
    {
        $officeId = Session::get('office_id');
        $report = Report::findById((int) $id);

        if (!$report) {
            $this->redirect('/office/reports');
            return;
        }

        // Verify report belongs to a client of this office
        $client = Client::findById($report['client_id']);
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            $this->redirect('/office/reports');
            return;
        }

        $type = $_GET['type'] ?? 'pdf';

        if ($type === 'xml') {
            $path = $report['xml_path'] ?? null;
            $ct = 'application/xml';
        } elseif ($type === 'xls') {
            $path = $report['xls_path'] ?? null;
            $ct = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } else {
            $path = $report['pdf_path'] ?? null;
            $ct = 'application/pdf';
        }

        if ($path && file_exists($path)) {
            header('Content-Type: ' . $ct);
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }

        Session::flash('error', 'report_not_found');
        $this->redirect('/office/reports');
    }

    public function switchLanguage(): void
    {
        $lang = $_GET['lang'] ?? 'pl';
        if (!in_array($lang, ['pl', 'en'])) $lang = 'pl';

        $officeId = Session::get('office_id');
        Office::update($officeId, ['language' => $lang]);
        Session::set('office_language', $lang);
        Language::setLocale($lang);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/office';
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $this->redirect(($refererHost && $refererHost !== $currentHost) ? '/office' : $referer);
    }

    // ── Notifications ──────────────────────────────

    public function notifications(): void
    {
        $officeId = Session::get('office_id');
        if ($_GET['format'] ?? '' === 'json') {
            $notifications = Notification::getUnread('office', $officeId);
            $this->json(['notifications' => $notifications, 'count' => count($notifications)]);
            return;
        }
        $notifications = Notification::getAll('office', $officeId, 50);
        $this->render('office/notifications', ['notifications' => $notifications]);
    }

    public function notificationsMarkRead(): void
    {
        $officeId = Session::get('office_id');
        $id = $this->sanitizeInt($_POST['id'] ?? 0);
        if ($id > 0) {
            Notification::markAsRead($id, 'office', $officeId);
        } else {
            Notification::markAllAsRead('office', $officeId);
        }
        if ($_POST['ajax'] ?? false) {
            $this->json(['ok' => true]);
            return;
        }
        $this->redirect('/office/notifications');
    }

    public function security(): void
    {
        $officeId = Session::get('office_id');
        $office = Office::findById($officeId);

        // Password expiry calculation
        $expiryDays = (int) (Setting::get('password_expiry_days') ?: 90);

        if (Auth::isEmployee()) {
            $employeeId = Session::get('employee_id');
            $employee = OfficeEmployee::findById($employeeId);
            $passwordChangedAt = $employee['password_changed_at'] ?? $employee['created_at'] ?? date('Y-m-d H:i:s');
            $twoFactorEnabled = !empty($employee['two_factor_enabled']);
        } else {
            $passwordChangedAt = $office['password_changed_at'] ?? $office['created_at'] ?? date('Y-m-d H:i:s');
            $twoFactorEnabled = !empty($office['two_factor_enabled']);
        }

        $expiryDate = (new \DateTime($passwordChangedAt))->modify("+{$expiryDays} days");
        $passwordDaysLeft = max(0, (int) (new \DateTime())->diff($expiryDate)->format('%r%a'));

        $this->render('office/security', [
            'twoFactorEnabled'   => $twoFactorEnabled,
            'twoFactorAllowed'   => Auth::is2faEnabled(),
            'passwordDaysLeft'   => $passwordDaysLeft,
            'passwordChangedAt'  => $passwordChangedAt,
            'passwordExpiryDate' => $expiryDate->format('Y-m-d'),
        ]);
    }

    // ── Invoice Comments ────────────────────────────

    public function invoiceComments(): void
    {
        Auth::requireOffice();
        $officeId = Session::get('office_id');
        $invoiceId = (int) ($_GET['id'] ?? 0);
        $invoice = \App\Models\Invoice::findById($invoiceId);

        if (!$invoice) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'not_found']);
            exit;
        }

        // Verify the invoice belongs to a batch managed by this office
        $batch = \App\Models\InvoiceBatch::findById((int) $invoice['batch_id']);
        if (!$batch || (int) ($batch['office_id'] ?? 0) !== $officeId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'access_denied']);
            exit;
        }

        $comments = \App\Models\InvoiceComment::findByInvoice($invoiceId);
        foreach ($comments as &$c) {
            $c['user_name'] = \App\Models\InvoiceComment::getUserName($c['user_type'], (int) $c['user_id']);
        }

        header('Content-Type: application/json');
        echo json_encode($comments);
        exit;
    }

    // ── ERP Export ──────────────────────────────────

    public function erpExportForm(): void
    {
        Auth::requireOffice();
        ModuleAccess::requireModule('erp-export');
        $officeId = Session::get('office_id');
        $clients = \App\Models\Client::findByOffice($officeId);
        $batches = InvoiceBatch::findByOffice($officeId);
        $templates = \App\Models\ExportTemplate::findAll();

        $this->render('office/erp_export', [
            'clients' => $clients,
            'batches' => $batches,
            'templates' => $templates,
        ]);
    }

    public function erpExport(): void
    {
        Auth::requireOffice();
        ModuleAccess::requireModule('erp-export');
        if (!$this->validateCsrf()) { $this->redirect('/office/erp-export'); return; }

        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $format = $_POST['format'] ?? '';
        $onlyAccepted = ($_POST['only_accepted'] ?? '1') === '1';

        if (!$batchId || !$format) {
            Session::flash('error', 'erp_export_error');
            $this->redirect('/office/erp-export');
            return;
        }

        $path = match ($format) {
            'comarch_optima' => \App\Services\ErpExportService::exportComarchOptima($batchId, $onlyAccepted),
            'sage' => \App\Services\ErpExportService::exportSage($batchId, $onlyAccepted),
            'enova' => \App\Services\ErpExportService::exportEnova($batchId, $onlyAccepted),
            'insert_gt' => \App\Services\ErpExportService::exportInsertGt($batchId, $onlyAccepted),
            'rewizor' => \App\Services\ErpExportService::exportRewizor($batchId, $onlyAccepted),
            'wfirma' => \App\Services\ErpExportService::exportWfirma($batchId, $onlyAccepted),
            'universal_csv' => \App\Services\ErpExportService::exportUniversalCsv($batchId, $onlyAccepted),
            'jpk_vat7' => \App\Services\JpkVat7Service::generate($batchId, $onlyAccepted),
            'jpk_fa' => \App\Services\JpkFaService::generate($batchId, $onlyAccepted),
            default => '',
        };

        if (!$path || !file_exists($path)) {
            Session::flash('error', 'erp_no_invoices');
            $this->redirect('/office/erp-export');
            return;
        }

        $filename = basename($path);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeTypes = ['csv' => 'text/csv', 'xml' => 'application/xml'];

        // Flush any output buffers to prevent BOM/whitespace corruption
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ── Employee Management ────────────────────────────

    public function employees(): void
    {
        // Office staff (accountants) is a core feature — NOT part of the HR / Payroll module
        // (which manages client_employees, not office staff). Don't gate on \$canModule('hr').
        if (Auth::isEmployee()) { $this->redirect('/office'); return; }
        $officeId = Session::get('office_id');
        $employees = OfficeEmployee::findByOffice($officeId, false);
        $this->render('office/employees', ['employees' => $employees]);
    }

    public function employeeCreateForm(): void
    {
        if (Auth::isEmployee()) { $this->redirect('/office'); return; }
        $officeId = Session::get('office_id');
        $clients = Client::findByOffice($officeId, true);
        $this->render('office/employee_form', [
            'employee'         => null,
            'clients'          => $clients,
            'assignedClientIds' => [],
        ]);
    }

    public function employeeCreate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/office/employees'); return; }

        $officeId = Session::get('office_id');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');

        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($name)) {
            Session::flash('error', 'employee_name_required');
            $this->redirect('/office/employees/create');
            return;
        }

        if (empty($email)) {
            Session::flash('error', 'employee_email_required');
            $this->redirect('/office/employees/create');
            return;
        }

        // Check max_employees limit
        $office = \App\Models\Office::findById($officeId);
        if ($office && !empty($office['max_employees'])) {
            $currentCount = \App\Models\OfficeEmployee::countByOffice($officeId);
            if ($currentCount >= (int) $office['max_employees']) {
                Session::flash('error', 'Osiągnięto limit pracowników dla tego biura (' . $office['max_employees'] . ')');
                $this->redirect('/office/employees/create');
                return;
            }
        }

        $employeeData = [
            'office_id' => $officeId,
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'position'  => $position,
        ];

        // Password for login access
        if (!empty($password)) {
            if ($password !== $passwordConfirm) {
                Session::flash('error', 'passwords_not_match');
                $this->redirect('/office/employees/create');
                return;
            }
            $pwErrors = Auth::validatePasswordStrength($password);
            if (!empty($pwErrors)) {
                Session::flash('error', $pwErrors[0]);
                $this->redirect('/office/employees/create');
                return;
            }
            $employeeData['password_hash'] = Auth::hashPassword($password);
            $employeeData['password_changed_at'] = date('Y-m-d H:i:s');
            $employeeData['force_password_change'] = isset($_POST['force_password_change']) ? 1 : 0;
        }

        $id = OfficeEmployee::create($employeeData);

        $clientIds = array_map('intval', $_POST['client_ids'] ?? []);
        if (!empty($clientIds)) {
            OfficeEmployee::assignClients($id, $clientIds);
        }

        AuditLog::log('office', $officeId, 'employee_created', "Employee: {$name}" . (!empty($password) ? ' (with login access)' : ''), 'employee', $id);
        Session::flash('success', 'employee_created');
        $this->redirect('/office/employees');
    }

    public function employeeEditForm(string $id): void
    {
        $officeId = Session::get('office_id');
        $employee = OfficeEmployee::findById((int) $id);

        if (!$employee || (int) $employee['office_id'] !== $officeId) {
            $this->redirect('/office/employees');
            return;
        }

        $clients = Client::findByOffice($officeId, true);
        $assignedClientIds = OfficeEmployee::getAssignedClientIds((int) $id);

        $this->render('office/employee_form', [
            'employee'          => $employee,
            'clients'           => $clients,
            'assignedClientIds' => $assignedClientIds,
        ]);
    }

    public function employeeUpdate(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/office/employees'); return; }

        $officeId = Session::get('office_id');
        $employee = OfficeEmployee::findById((int) $id);

        if (!$employee || (int) $employee['office_id'] !== $officeId) {
            $this->redirect('/office/employees');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (empty($name)) {
            Session::flash('error', 'employee_name_required');
            $this->redirect("/office/employees/{$id}/edit");
            return;
        }

        $updateData = [
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'position'  => $position,
            'is_active' => $isActive,
        ];

        if (!empty($newPassword)) {
            if ($newPassword !== $newPasswordConfirm) {
                Session::flash('error', 'passwords_not_match');
                $this->redirect("/office/employees/{$id}/edit");
                return;
            }
            $pwErrors = Auth::validatePasswordStrength($newPassword);
            if (!empty($pwErrors)) {
                Session::flash('error', $pwErrors[0]);
                $this->redirect("/office/employees/{$id}/edit");
                return;
            }
            $updateData['password_hash'] = Auth::hashPassword($newPassword);
            $updateData['password_changed_at'] = date('Y-m-d H:i:s');
        }

        if (isset($_POST['force_password_change'])) {
            $updateData['force_password_change'] = 1;
        }

        OfficeEmployee::update((int) $id, $updateData);

        $clientIds = array_map('intval', $_POST['client_ids'] ?? []);
        OfficeEmployee::assignClients((int) $id, $clientIds);

        AuditLog::log('office', $officeId, 'employee_updated', "Employee: {$name}", 'employee', (int) $id);
        Session::flash('success', 'employee_updated');
        $this->redirect('/office/employees');
    }

    public function employeeDelete(string $id): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/office/employees'); return; }

        $officeId = Session::get('office_id');
        $employee = OfficeEmployee::findById((int) $id);

        if (!$employee || (int) $employee['office_id'] !== $officeId) {
            $this->redirect('/office/employees');
            return;
        }

        OfficeEmployee::delete((int) $id);
        AuditLog::log('office', $officeId, 'employee_deleted', "Employee: {$employee['name']}", 'employee', (int) $id);
        Session::flash('success', 'employee_deleted');
        $this->redirect('/office/employees');
    }

    // ── Employee impersonation as client ─────────────

    public function impersonateClient(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/office/clients'); return; }

        if (!Auth::isEmployee()) {
            Session::flash('error', 'access_denied');
            $this->redirect('/office/clients');
            return;
        }

        $clientId = (int) ($_POST['client_id'] ?? 0);
        if (Auth::employeeImpersonateClient($clientId)) {
            $this->redirect('/client');
        } else {
            Session::flash('error', 'client_not_assigned');
            $this->redirect('/office/clients');
        }
    }

    // ── Office Settings (logo) ──────────────────────

    public function settingsForm(): void
    {
        if (Auth::isEmployee()) {
            $this->redirect('/office');
            return;
        }
        $officeId = Session::get('office_id');
        $office = Office::findById($officeId);
        $this->render('office/settings', ['office' => $office]);
    }

    public function settingsUpdate(): void
    {
        if (Auth::isEmployee()) { $this->redirect('/office'); return; }
        if (!$this->validateCsrf()) { $this->redirect('/office/settings'); return; }

        $officeId = Session::get('office_id');
        $updateData = [];

        // Logo upload
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $maxSize = 2 * 1024 * 1024; // 2MB
            if ($_FILES['logo']['size'] > $maxSize) {
                Session::flash('error', 'logo_too_large');
                $this->redirect('/office/settings');
                return;
            }

            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                Session::flash('error', 'logo_invalid_format');
                $this->redirect('/office/settings');
                return;
            }

            // MIME type validation
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['logo']['tmp_name']);
            $allowedMimes = ['image/png', 'image/jpeg', 'image/webp'];
            if (!in_array($mimeType, $allowedMimes)) {
                Session::flash('error', 'logo_invalid_mime');
                $this->redirect('/office/settings');
                return;
            }

            // Dimension validation
            $imageInfo = @getimagesize($_FILES['logo']['tmp_name']);
            if ($imageInfo === false) {
                Session::flash('error', 'logo_not_image');
                $this->redirect('/office/settings');
                return;
            }
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            if ($width < 50 || $height < 50) {
                Session::flash('error', 'logo_too_small');
                $this->redirect('/office/settings');
                return;
            }
            if ($width > 2000 || $height > 2000) {
                Session::flash('error', 'logo_too_large_dimensions');
                $this->redirect('/office/settings');
                return;
            }

            $uploadDir = __DIR__ . '/../../public/assets/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = "office_{$officeId}_logo.{$ext}";
            $destPath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $destPath)) {
                $updateData['logo_path'] = '/assets/uploads/' . $filename;
            }
        }

        // Remove logo
        if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
            $office = Office::findById($officeId);
            if (!empty($office['logo_path'])) {
                $filePath = __DIR__ . '/../../public' . $office['logo_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $updateData['logo_path'] = null;
        }

        if (!empty($updateData)) {
            Office::update($officeId, $updateData);
            AuditLog::log('office', $officeId, 'office_settings_updated', 'Office settings updated');
        }

        Session::flash('success', 'settings_saved');
        $this->redirect('/office/settings');
    }

    // ── Employee-only: employees not managed ────────

    public function employeesRequireOffice(): bool
    {
        if (Auth::isEmployee()) {
            $this->redirect('/office');
            return false;
        }
        return true;
    }

    // ── Messages ──────────────────────────────────────

    public function messages(): void
    {
        ModuleAccess::requireModule('messages');
        $officeId = Session::get('office_id');
        $isEmployee = Auth::isEmployee();
        $employeeId = $isEmployee ? Session::get('employee_id') : null;
        $filterClientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;

        if ($isEmployee) {
            $threads = Message::findByEmployee($employeeId, $filterClientId);
            $clients = OfficeEmployee::getAssignedClients($employeeId);
        } else {
            $threads = Message::findByOffice($officeId, $filterClientId);
            $clients = Client::findByOffice($officeId, true);
        }

        // Enrich threads with sender names
        foreach ($threads as &$t) {
            $t['sender_name'] = Message::getSenderName($t['sender_type'], (int) $t['sender_id']);
        }
        unset($t);

        $this->render('office/messages', [
            'threads' => $threads,
            'clients' => $clients,
            'filterClientId' => $filterClientId,
        ]);
    }

    public function messageThread(int $id): void
    {
        ModuleAccess::requireModule('messages');
        $officeId = Session::get('office_id');
        $root = Message::findById($id);
        if (!$root || $root['parent_id'] !== null) {
            $this->redirect('/office/messages');
            return;
        }

        // Verify this message belongs to the office's client
        $client = Client::findById((int) $root['client_id']);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            $this->redirect('/office/messages');
            return;
        }

        // Employee access check
        if (Auth::isEmployee()) {
            $assignedIds = OfficeEmployee::getAssignedClientIds(Session::get('employee_id'));
            if (!in_array((int) $root['client_id'], $assignedIds)) {
                $this->redirect('/office/messages');
                return;
            }
        }

        Message::markReadByOffice($id);

        $messages = Message::findThread($id);
        foreach ($messages as &$m) {
            $m['sender_name'] = Message::getSenderName($m['sender_type'], (int) $m['sender_id']);
        }
        unset($m);

        $this->render('office/message_thread', [
            'thread' => $root,
            'messages' => $messages,
            'client' => $client,
        ]);
    }

    public function messageCreate(): void
    {
        ModuleAccess::requireModule('messages');
        if (!$this->validateCsrf()) { $this->redirect('/office/messages'); return; }
        $officeId = Session::get('office_id');
        $isEmployee = Auth::isEmployee();
        $senderType = $isEmployee ? 'employee' : 'office';
        $senderId = $isEmployee ? Session::get('employee_id') : $officeId;

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $invoiceId = !empty($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : null;

        if ($clientId <= 0 || $subject === '' || $body === '') {
            Session::flash('error', 'fill_required_fields');
            $this->redirect('/office/messages');
            return;
        }

        // Verify client belongs to this office
        $client = Client::findById($clientId);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            $this->redirect('/office/messages');
            return;
        }

        $msgId = Message::create($clientId, $senderType, $senderId, $body, $subject, $invoiceId);

        // Handle attachment
        $this->handleMessageAttachment($msgId, $client['nip'] ?? '');

        // Notify client (always email for new thread + system notification)
        $this->notifyAboutMessage($clientId, $senderType, $senderId, $subject, 'new_thread');

        Session::flash('success', 'message_sent');
        $this->redirect("/office/messages/{$msgId}");
    }

    public function messageReply(int $id): void
    {
        ModuleAccess::requireModule('messages');
        if (!$this->validateCsrf()) { $this->redirect("/office/messages/{$id}"); return; }
        $officeId = Session::get('office_id');
        $isEmployee = Auth::isEmployee();
        $senderType = $isEmployee ? 'employee' : 'office';
        $senderId = $isEmployee ? Session::get('employee_id') : $officeId;

        $root = Message::findById($id);
        if (!$root || $root['parent_id'] !== null) {
            $this->redirect('/office/messages');
            return;
        }

        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            $this->redirect("/office/messages/{$id}");
            return;
        }

        $replyId = Message::create((int) $root['client_id'], $senderType, $senderId, $body, null, null, null, $id);

        // Handle attachment
        $client = Client::findById((int) $root['client_id']);
        $this->handleMessageAttachment($replyId, $client['nip'] ?? '');

        // Notify client about reply
        $this->notifyAboutMessage((int) $root['client_id'], $senderType, $senderId, $root['subject'] ?? '', 'new_reply');

        $this->redirect("/office/messages/{$id}");
    }

    public function messageNotificationPrefs(): void
    {
        ModuleAccess::requireModule('messages');
        $isEmployee = Auth::isEmployee();
        $userType = $isEmployee ? 'employee' : 'office';
        $userId = $isEmployee ? Session::get('employee_id') : Session::get('office_id');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrf()) { $this->redirect('/office/messages/preferences'); return; }
            MessageNotificationPref::savePrefs($userType, $userId, [
                'notify_new_thread' => isset($_POST['notify_new_thread']) ? 1 : 0,
                'notify_new_reply' => isset($_POST['notify_new_reply']) ? 1 : 0,
                'notify_email' => isset($_POST['notify_email']) ? 1 : 0,
            ]);
            Session::flash('success', 'settings_saved');
            $this->redirect('/office/messages/preferences');
            return;
        }

        $prefs = MessageNotificationPref::getPrefs($userType, $userId);
        $this->render('office/message_prefs', ['prefs' => $prefs]);
    }

    // ── Tasks ─────────────────────────────────────────

    public function tasks(): void
    {
        ModuleAccess::requireModule('tasks');
        $officeId = Session::get('office_id');
        $isEmployee = Auth::isEmployee();
        $employeeId = $isEmployee ? Session::get('employee_id') : null;
        $filterClientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
        $filterStatus = $_GET['status'] ?? null;
        if ($filterStatus !== null && !in_array($filterStatus, ['open', 'in_progress', 'done'])) {
            $filterStatus = null;
        }

        if ($isEmployee) {
            $tasks = ClientTask::findByEmployee($employeeId, $filterStatus, $filterClientId);
            $clients = OfficeEmployee::getAssignedClients($employeeId);
        } else {
            $tasks = ClientTask::findByOffice($officeId, $filterStatus, $filterClientId);
            $clients = Client::findByOffice($officeId, true);
        }

        $this->render('office/tasks', [
            'tasks' => $tasks,
            'clients' => $clients,
            'filterClientId' => $filterClientId,
            'filterStatus' => $filterStatus,
        ]);
    }

    public function taskCreate(): void
    {
        ModuleAccess::requireModule('tasks');
        $this->validateCsrf();
        $officeId = Session::get('office_id');
        $isEmployee = Auth::isEmployee();
        $creatorType = $isEmployee ? 'employee' : 'office';
        $creatorId = $isEmployee ? Session::get('employee_id') : $officeId;

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '') ?: null;
        $priority = $_POST['priority'] ?? 'normal';
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if ($clientId <= 0 || $title === '' || $dueDate === null) {
            Session::flash('error', 'fill_required_fields');
            $this->redirect('/office/tasks');
            return;
        }

        // Verify client belongs to this office
        $client = Client::findById($clientId);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            $this->redirect('/office/tasks');
            return;
        }

        if (!in_array($priority, ['low', 'normal', 'high'])) {
            $priority = 'normal';
        }

        $isBillable = !empty($_POST['is_billable']);
        $taskPrice = $isBillable && !empty($_POST['task_price']) ? round((float) $_POST['task_price'], 2) : null;
        if ($taskPrice !== null && ($taskPrice < 0 || $taskPrice > 9999999.99)) {
            $taskPrice = null;
        }

        $taskId = ClientTask::create($clientId, $creatorType, $creatorId, $title, $description, $priority, $dueDate, null, null, $isBillable, $taskPrice);

        // Handle attachment
        $this->handleTaskAttachment($taskId, $client['nip'] ?? '');

        // Notify client about new task
        Notification::create('client', $clientId, 'Nowe zadanie: ' . $title, $description, 'info', '/client/tasks');

        // Send email to client
        try {
            if (!empty($client['email'])) {
                \App\Services\MailQueueService::enqueue(
                    $client['email'],
                    'Nowe zadanie od biura księgowego',
                    "<p>Utworzono nowe zadanie: <strong>" . htmlspecialchars($title) . "</strong></p>"
                    . ($description ? "<p>" . htmlspecialchars($description) . "</p>" : '')
                    . ($dueDate ? "<p>Termin: {$dueDate}</p>" : '')
                    . "<p><a href=\"/client/tasks\">Przejdź do zadań</a></p>",
                    $clientId
                );
            }
        } catch (\Throwable $e) {
            error_log("Task email enqueue failed: " . $e->getMessage());
        }

        Session::flash('success', 'task_created');
        $this->redirect('/office/tasks');
    }

    public function taskUpdate(int $id): void
    {
        ModuleAccess::requireModule('tasks');
        $this->validateCsrf();
        $officeId = Session::get('office_id');

        $task = ClientTask::findById($id);
        if (!$task) {
            $this->redirect('/office/tasks');
            return;
        }

        // Verify task's client belongs to this office
        $client = Client::findById((int) $task['client_id']);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            $this->redirect('/office/tasks');
            return;
        }

        $data = [];
        if (isset($_POST['title'])) $data['title'] = trim($_POST['title']);
        if (isset($_POST['description'])) $data['description'] = trim($_POST['description']) ?: null;
        if (isset($_POST['priority']) && in_array($_POST['priority'], ['low', 'normal', 'high'])) {
            $data['priority'] = $_POST['priority'];
        }
        if (array_key_exists('due_date', $_POST)) {
            $data['due_date'] = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        }
        if (isset($_POST['status']) && in_array($_POST['status'], ['open', 'in_progress', 'done'])) {
            $isEmployee = Auth::isEmployee();
            $completedByType = $isEmployee ? 'employee' : 'office';
            $completedById = $isEmployee ? Session::get('employee_id') : $officeId;
            ClientTask::markStatus($id, $_POST['status'], $completedByType, $completedById);
        }

        if (!empty($data)) {
            ClientTask::update($id, $data);
        }

        Session::flash('success', 'task_updated');
        $this->redirect('/office/tasks');
    }

    public function taskDelete(int $id): void
    {
        ModuleAccess::requireModule('tasks');
        $this->validateCsrf();
        $officeId = Session::get('office_id');

        $task = ClientTask::findById($id);
        if (!$task) {
            $this->redirect('/office/tasks');
            return;
        }

        $client = Client::findById((int) $task['client_id']);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            $this->redirect('/office/tasks');
            return;
        }

        ClientTask::delete($id);
        Session::flash('success', 'task_deleted');
        $this->redirect('/office/tasks');
    }

    // ── Tasks Billing ──────────────

    public function tasksBilling(): void
    {
        ModuleAccess::requireModule('tasks');
        $officeId = Session::get('office_id');
        $isEmployee = Auth::isEmployee();
        $employeeId = $isEmployee ? Session::get('employee_id') : null;

        $filterBillingStatus = $_GET['billing_status'] ?? 'all';
        if (!in_array($filterBillingStatus, ['all', 'none', 'to_invoice', 'invoiced'])) {
            $filterBillingStatus = 'all';
        }
        $filterClientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;

        if ($isEmployee) {
            $tasks = ClientTask::findDoneByEmployee($employeeId, $filterBillingStatus, $filterClientId);
            $clients = OfficeEmployee::getAssignedClients($employeeId);
        } else {
            $tasks = ClientTask::findDoneByOffice($officeId, $filterBillingStatus, $filterClientId);
            $clients = Client::findByOffice($officeId, true);
        }

        $this->render('office/tasks_billing', [
            'tasks' => $tasks,
            'clients' => $clients,
            'filterBillingStatus' => $filterBillingStatus,
            'filterClientId' => $filterClientId,
        ]);
    }

    public function taskBillingUpdate(int $id): void
    {
        ModuleAccess::requireModule('tasks');
        $this->validateCsrf();
        $officeId = Session::get('office_id');

        $task = ClientTask::findById($id);
        if (!$task) {
            $this->redirect('/office/tasks/billing');
            return;
        }

        $client = Client::findById((int) $task['client_id']);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            $this->redirect('/office/tasks/billing');
            return;
        }

        $billingStatus = $_POST['billing_status'] ?? '';
        if (in_array($billingStatus, ['none', 'to_invoice', 'invoiced'])) {
            ClientTask::update($id, ['billing_status' => $billingStatus]);
        }

        Session::flash('success', 'task_updated');
        $this->redirect('/office/tasks/billing');
    }

    // ── Tax Payments ──────────────

    public function taxPayments(): void
    {
        ModuleAccess::requireModule('tax-payments');
        $officeId = Session::get('office_id');
        $isEmployee = Auth::isEmployee();
        $employeeId = $isEmployee ? Session::get('employee_id') : null;

        if ($isEmployee) {
            $clients = OfficeEmployee::getAssignedClients($employeeId);
        } else {
            $clients = Client::findByOffice($officeId, true);
        }

        $filterClientId = !empty($_GET['client_id']) ? (int) $_GET['client_id'] : null;
        $filterYear = !empty($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        if ($filterYear < 2000 || $filterYear > 2100) {
            $filterYear = (int) date('Y');
        }

        // Validate employee access to selected client
        if ($filterClientId && $isEmployee) {
            $allowedIds = array_column($clients, 'id');
            if (!in_array($filterClientId, $allowedIds)) {
                $filterClientId = null;
            }
        }

        $grid = [];
        if ($filterClientId) {
            $rows = TaxPayment::findByClientAndYear($filterClientId, $filterYear);
            $grid = TaxPayment::buildGrid($rows);
        }

        $this->render('office/tax_payments', [
            'clients' => $clients,
            'filterClientId' => $filterClientId,
            'filterYear' => $filterYear,
            'grid' => $grid,
            'taxTypes' => TaxPayment::getTaxTypes(),
            'success' => Session::getFlash('success'),
        ]);
    }

    public function taxPaymentsSave(): void
    {
        ModuleAccess::requireModule('tax-payments');
        $this->validateCsrf();
        $officeId = Session::get('office_id');
        $isEmployee = Auth::isEmployee();
        $employeeId = $isEmployee ? Session::get('employee_id') : null;

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $year = (int) ($_POST['year'] ?? date('Y'));

        if (!$clientId || !$year || $year < 2000 || $year > 2100) {
            $this->redirect('/office/tax-payments');
            return;
        }

        // Verify access
        $client = Client::findById($clientId);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            $this->redirect('/office/tax-payments');
            return;
        }

        if ($isEmployee) {
            $allowedIds = OfficeEmployee::getAssignedClientIds($employeeId);
            if (!in_array($clientId, $allowedIds)) {
                $this->redirect('/office/tax-payments');
                return;
            }
        }

        $byType = $isEmployee ? 'employee' : 'office';
        $byId = $isEmployee ? $employeeId : $officeId;

        // Parse POST data: tax[month][TAX_TYPE][amount], tax[month][TAX_TYPE][status]
        $taxData = $_POST['tax'] ?? [];
        $entries = [];

        foreach ($taxData as $month => $types) {
            $month = (int) $month;
            if ($month < 1 || $month > 12) {
                continue;
            }
            foreach ($types as $taxType => $fields) {
                if (!in_array($taxType, TaxPayment::getTaxTypes(), true)) {
                    continue;
                }
                $amount = trim($fields['amount'] ?? '');
                if ($amount === '' || !is_numeric($amount) || (float) $amount < 0) {
                    continue;
                }
                $status = $fields['status'] ?? 'do_zaplaty';
                if (!in_array($status, ['do_zaplaty', 'do_przeniesienia'], true)) {
                    $status = 'do_zaplaty';
                }
                $entries[] = [
                    'month' => $month,
                    'tax_type' => $taxType,
                    'amount' => $amount,
                    'status' => $status,
                ];
            }
        }

        if (!empty($entries)) {
            TaxPayment::bulkUpsert($clientId, $year, $entries, $byType, $byId);
        }

        Session::flash('success', 'tax_payments_saved');
        $this->redirect('/office/tax-payments?client_id=' . $clientId . '&year=' . $year);
    }

    // ── Attachment download ──────────────

    public function messageAttachment(int $id): void
    {
        ModuleAccess::requireModule('messages');
        $officeId = Session::get('office_id');
        $msg = Message::findById($id);
        if (!$msg) {
            // Could be a reply — check by id directly
            $msg = \App\Core\Database::getInstance()->fetchOne("SELECT * FROM messages WHERE id = ?", [$id]);
        }
        if (!$msg || empty($msg['attachment_path'])) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        // Verify message belongs to this office's client
        $client = Client::findById((int) $msg['client_id']);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        // Employee can only access attachments for assigned clients
        if (Auth::isEmployee()) {
            $assignedIds = OfficeEmployee::getAssignedClientIds(Session::get('employee_id'));
            if (!in_array((int) $msg['client_id'], $assignedIds)) {
                http_response_code(403);
                echo 'Forbidden';
                return;
            }
        }

        $fullPath = Message::getAttachmentFullPath($msg);
        if (!$fullPath || !file_exists($fullPath)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fullPath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($msg['attachment_name']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    // ── Attachment upload helper ──────────────

    private function handleMessageAttachment(int $msgId, string $nip): void
    {
        if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $file = $_FILES['attachment'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'attachment_upload_error');
            return;
        }

        // Size check: max 3MB
        if ($file['size'] > 3 * 1024 * 1024) {
            Session::flash('error', 'attachment_too_large');
            return;
        }

        // Extension whitelist
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'txt', 'xls', 'xlsx'];
        if (!in_array($ext, $allowedExt, true)) {
            Session::flash('error', 'attachment_invalid_type');
            return;
        }

        // MIME check
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMime = [
            'application/pdf',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        if (!in_array($mime, $allowedMime, true)) {
            Session::flash('error', 'attachment_invalid_type');
            return;
        }

        // Create directory per NIP (fallback to msgId prefix if NIP is empty)
        $sanitizedNip = preg_replace('/[^0-9]/', '', $nip);
        if ($sanitizedNip === '') {
            $sanitizedNip = 'client_' . $msgId;
        }
        $dir = __DIR__ . '/../../storage/messages/' . $sanitizedNip;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Safe filename
        $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $fileName = $msgId . '_' . time() . '_' . $safeName . '.' . $ext;
        $relPath = 'storage/messages/' . $sanitizedNip . '/' . $fileName;
        $fullPath = $dir . '/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            Message::updateAttachment($msgId, $relPath, $file['name']);
        }
    }

    // ── Task attachment upload helper ──────────────

    private function handleTaskAttachment(int $taskId, string $nip): void
    {
        if (empty($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }

        $file = $_FILES['attachment'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'attachment_upload_error');
            return;
        }

        if ($file['size'] > 3 * 1024 * 1024) {
            Session::flash('error', 'attachment_too_large');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'txt', 'xls', 'xlsx'];
        if (!in_array($ext, $allowedExt, true)) {
            Session::flash('error', 'attachment_invalid_type');
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMime = [
            'application/pdf',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        if (!in_array($mime, $allowedMime, true)) {
            Session::flash('error', 'attachment_invalid_type');
            return;
        }

        $sanitizedNip = preg_replace('/[^0-9]/', '', $nip);
        $dir = __DIR__ . '/../../storage/tasks/' . $sanitizedNip;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $fileName = $taskId . '_' . time() . '_' . $safeName . '.' . $ext;
        $relPath = 'storage/tasks/' . $sanitizedNip . '/' . $fileName;
        $fullPath = $dir . '/' . $fileName;

        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            ClientTask::updateAttachment($taskId, $relPath, $file['name']);
        }
    }

    // ── Task attachment download ──────────────

    public function taskAttachment(int $id): void
    {
        ModuleAccess::requireModule('tasks');
        $officeId = Session::get('office_id');
        $task = ClientTask::findById($id);
        if (!$task || empty($task['attachment_path'])) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $client = Client::findById((int) $task['client_id']);
        if (!$client || (int) $client['office_id'] !== (int) $officeId) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        // Employee can only access attachments for assigned clients
        if (Auth::isEmployee()) {
            $assignedIds = OfficeEmployee::getAssignedClientIds(Session::get('employee_id'));
            if (!in_array((int) $task['client_id'], $assignedIds)) {
                http_response_code(403);
                echo 'Forbidden';
                return;
            }
        }

        $fullPath = __DIR__ . '/../../' . $task['attachment_path'];
        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fullPath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($task['attachment_name']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    // ── Email Settings (branding) ────────────────────

    public function emailSettings(): void
    {
        $officeId = Session::get('office_id');
        $settings = OfficeEmailSettings::findByOfficeId($officeId);
        $office = Office::findById($officeId);
        $this->render('office/email_settings', [
            'emailSettings' => $settings,
            'office' => $office,
        ]);
    }

    public function emailSettingsUpdate(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/office/email-settings'); return; }

        $officeId = Session::get('office_id');
        $data = [
            'header_color'   => $_POST['header_color'] ?? '#008F8F',
            'logo_in_emails' => !empty($_POST['logo_in_emails']) ? 1 : 0,
            'footer_text'    => trim($_POST['footer_text'] ?? ''),
        ];

        // Validate header_color format
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $data['header_color'])) {
            $data['header_color'] = '#008F8F';
        }

        OfficeEmailSettings::upsert($officeId, $data);
        Session::flash('success', 'email_settings_saved');
        $this->redirect('/office/email-settings');
    }

    // ── Notification helper for messages ──────────────

    private function notifyAboutMessage(int $clientId, string $senderType, int $senderId, string $subject, string $eventType): void
    {
        $senderName = Message::getSenderName($senderType, $senderId);
        $recipients = Message::getRecipients($clientId, $senderType, $senderId);

        foreach ($recipients as $r) {
            // System notification (based on prefs)
            if (MessageNotificationPref::shouldNotify($r['user_type'], $r['user_id'], $eventType)) {
                $title = $eventType === 'new_thread'
                    ? "Nowa wiadomość: {$subject}"
                    : "Odpowiedź w wątku: {$subject}";
                $link = $r['user_type'] === 'client' ? '/client/messages' : '/office/messages';
                Notification::create($r['user_type'], $r['user_id'], $title, "Od: {$senderName}", 'info', $link);
            }

            // Email (always for new_thread, pref-based for replies)
            if ($eventType === 'new_thread' || MessageNotificationPref::shouldEmail($r['user_type'], $r['user_id'], $eventType)) {
                try {
                    if (!empty($r['email'])) {
                        $emailSubject = $eventType === 'new_thread'
                            ? "Nowa wiadomość: {$subject}"
                            : "Nowa odpowiedź: {$subject}";
                        $link = $r['user_type'] === 'client' ? '/client/messages' : '/office/messages';
                        $body = "<p><strong>{$senderName}</strong> wysłał(a) wiadomość.</p>"
                            . "<p>Temat: <strong>" . htmlspecialchars($subject) . "</strong></p>"
                            . "<p><a href=\"{$link}\">Przejdź do wiadomości</a></p>";
                        \App\Services\MailQueueService::enqueue($r['email'], $emailSubject, $body, $r['user_type'] === 'client' ? $clientId : null);
                    }
                } catch (\Throwable $e) {
                    error_log("Message email failed for {$r['email']}: " . $e->getMessage());
                }
            }
        }
    }

    // ── Client Edit (contact + tax config) ──────────────────────────

    public function clientEdit(string $id): void
    {
        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clientId = (int) $id;

        $clientData = Client::findById($clientId);
        if (!$clientData || (int)($clientData['office_id'] ?? 0) !== $officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $taxConfig = ClientTaxConfig::findByClientOrDefaults($clientId);

        $this->render('office/client_edit', [
            'clientData' => $clientData,
            'taxConfig'  => $taxConfig,
        ]);
    }

    public function clientEditSave(string $id): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/clients');
            return;
        }

        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clientId = (int) $id;

        $clientData = Client::findById($clientId);
        if (!$clientData || (int)($clientData['office_id'] ?? 0) !== $officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $form = $_POST['_form'] ?? '';

        if ($form === 'contact') {
            $data = [
                'representative_name' => trim($_POST['representative_name'] ?? ''),
                'email'              => trim($_POST['email'] ?? ''),
                'phone'              => trim($_POST['phone'] ?? ''),
                'report_email'       => trim($_POST['report_email'] ?? ''),
            ];

            if ($data['representative_name'] === '' || $data['email'] === '') {
                Session::flash('error', 'fill_required_fields');
                $this->redirect("/office/clients/{$clientId}/edit");
                return;
            }

            Client::update($clientId, $data);
            AuditLog::log('office', (int) Session::get('office_id'), 'client_contact_updated',
                "Client #{$clientId} contact updated", 'client', $clientId);
            Session::flash('success', 'client_updated');
        } elseif ($form === 'tax') {
            ClientTaxConfig::upsert($clientId, [
                'vat_period'       => $_POST['vat_period'] ?? 'monthly',
                'taxation_type'    => $_POST['taxation_type'] ?? 'PIT',
                'tax_form'         => $_POST['tax_form'] ?? 'skala',
                'zus_payer_type'   => $_POST['zus_payer_type'] ?? 'self_employed',
                'jpk_vat_required' => isset($_POST['jpk_vat_required']) ? 1 : 0,
                'alert_days_before' => max(1, min(30, (int) ($_POST['alert_days_before'] ?? 5))),
            ]);
            Session::flash('success', 'tax_config_saved');
        }

        $this->redirect("/office/clients/{$clientId}/edit");
    }

    // ── Client Data Deletion (RODO Art. 17) ──────────────

    public function clientDeleteData(string $id): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/clients');
            return;
        }

        // Only office owners can delete client data, not employees
        if (Auth::isEmployee()) {
            Session::flash('error', 'access_denied');
            $this->redirect('/office/clients');
            return;
        }

        $officeId = (int) Session::get('office_id');
        $clientId = (int) $id;

        $clientData = Client::findById($clientId);
        if (!$clientData || (int)($clientData['office_id'] ?? 0) !== $officeId) {
            Session::flash('error', 'access_denied');
            $this->redirect('/office/clients');
            return;
        }

        // Validate confirmation text
        $confirmText = trim($_POST['confirm_text'] ?? '');
        if ($confirmText !== 'USUN') {
            Session::flash('error', 'rodo_delete_confirm_mismatch');
            $this->redirect("/office/clients/{$clientId}/edit");
            return;
        }

        // Perform deletion
        $result = \App\Services\RodoDeleteService::deleteClientData($clientId, 'office', $officeId);

        if (!$result['success']) {
            Session::flash('error', 'rodo_delete_failed');
            $this->redirect("/office/clients/{$clientId}/edit");
            return;
        }

        Session::flash('success', 'rodo_client_deleted');
        $this->redirect('/office/clients');
    }

    // ── Tax Calendar (F1) ─────────────────────────────────

    public function taxCalendar(): void
    {
        ModuleAccess::requireModule('tax-calendar');
        $officeId = (int) Session::get('office_id');
        $selectedMonth = (int) ($_GET['month'] ?? date('n'));
        $selectedYear = (int) ($_GET['year'] ?? date('Y'));
        $selectedClientId = !empty($_GET['client_id']) ? (int) $_GET['client_id'] : null;

        $clients = Client::findByOffice($officeId, true);

        if ($selectedClientId) {
            $deadlines = TaxCalendarService::getDeadlinesForClient($selectedClientId, $selectedYear, $selectedMonth);
            $calendarGrid = TaxCalendarService::buildMonthCalendar($selectedYear, $selectedMonth, $deadlines);
            $deadlinesList = [];
            $client = Client::findById($selectedClientId);
            foreach ($deadlines as $d) {
                $d['client_name'] = $client['company_name'] ?? '';
                $d['client_id'] = $selectedClientId;
                $deadlinesList[] = $d;
            }
        } else {
            $officeDeadlines = TaxCalendarService::getDeadlinesForOffice($officeId, $selectedYear, $selectedMonth);
            $calendarGrid = [];
            $deadlinesList = [];
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
            for ($d = 1; $d <= $daysInMonth; $d++) $calendarGrid[$d] = [];

            // Group calendar entries by (day, type) instead of per-client
            $grouped = [];
            foreach ($officeDeadlines as $cId => $data) {
                foreach ($data['deadlines'] as $dl) {
                    $key = $dl['day'] . '|' . $dl['type'];
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'day' => $dl['day'],
                            'type' => $dl['type'],
                            'date' => $dl['date'],
                            'tax_type' => $dl['tax_type'] ?? null,
                            'count' => 0,
                            'clients' => [],
                        ];
                    }
                    $grouped[$key]['count']++;
                    $grouped[$key]['clients'][] = [
                        'client_id' => $cId,
                        'client_name' => $data['client']['company_name'],
                    ];

                    // Full per-client list for table below
                    $entry = $dl;
                    $entry['client_name'] = $data['client']['company_name'];
                    $entry['client_id'] = $cId;
                    $deadlinesList[] = $entry;
                }
            }

            foreach ($grouped as $g) {
                $calendarGrid[$g['day']][] = $g;
            }
            usort($deadlinesList, fn($a, $b) => $a['date'] <=> $b['date']);
        }

        // Get tax payment amounts for deadlines list
        foreach ($deadlinesList as &$dl) {
            $dl['amount'] = null;
            if (!empty($dl['tax_type']) && !empty($dl['client_id'])) {
                $payments = \App\Models\TaxPayment::findByClientAndYear((int) $dl['client_id'], $selectedYear);
                $grid = \App\Models\TaxPayment::buildGrid($payments);
                if (isset($grid[$selectedMonth][$dl['tax_type']])) {
                    $p = $grid[$selectedMonth][$dl['tax_type']];
                    if ($p['status'] === 'do_zaplaty' && (float) $p['amount'] > 0) {
                        $dl['amount'] = $p['amount'];
                    }
                }
            }
        }
        unset($dl);

        // Load custom events for this month
        $customEvents = \App\Models\TaxCustomEvent::findByOfficeAndMonth($officeId, $selectedYear, $selectedMonth);
        $customEventsGrid = [];
        foreach ($customEvents as $evt) {
            $evtDay = (int) date('j', strtotime($evt['event_date']));
            $customEventsGrid[$evtDay][] = $evt;
        }

        $this->render('office/tax_calendar', [
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'selectedClientId' => $selectedClientId,
            'clients' => $clients,
            'calendarGrid' => $calendarGrid,
            'deadlinesList' => $deadlinesList,
            'customEvents' => $customEvents,
            'customEventsGrid' => $customEventsGrid,
            'csrf' => Session::generateCsrfToken(),
        ]);
    }

    public function taxCalendarConfig(int $clientId): void
    {
        ModuleAccess::requireModule('tax-calendar');
        $officeId = (int) Session::get('office_id');
        $clientData = Client::findById($clientId);
        if (!$clientData || (int) $clientData['office_id'] !== $officeId) {
            $this->redirect('/office/tax-calendar');
            return;
        }

        $config = ClientTaxConfig::findByClientOrDefaults($clientId);

        $this->render('office/tax_calendar_config', [
            'clientData' => $clientData,
            'config' => $config,
        ]);
    }

    public function taxCalendarConfigSave(int $clientId): void
    {
        ModuleAccess::requireModule('tax-calendar');
        if (!$this->validateCsrf()) { $this->redirect('/office/tax-calendar'); return; }
        $officeId = (int) Session::get('office_id');
        $clientData = Client::findById($clientId);
        if (!$clientData || (int) $clientData['office_id'] !== $officeId) {
            $this->redirect('/office/tax-calendar');
            return;
        }

        ClientTaxConfig::upsert($clientId, [
            'vat_period' => $_POST['vat_period'] ?? 'monthly',
            'taxation_type' => $_POST['taxation_type'] ?? 'PIT',
            'tax_form' => $_POST['tax_form'] ?? 'skala',
            'zus_payer_type' => $_POST['zus_payer_type'] ?? 'self_employed',
            'jpk_vat_required' => isset($_POST['jpk_vat_required']) ? 1 : 0,
            'alert_days_before' => max(1, min(30, (int) ($_POST['alert_days_before'] ?? 5))),
        ]);

        Session::flash('success', 'tax_config_saved');
        $this->redirect("/office/tax-calendar/config/{$clientId}");
    }

    public function taxCalendarAddEvent(): void
    {
        ModuleAccess::requireModule('tax-calendar');
        if (!$this->validateCsrf()) { $this->redirect('/office/tax-calendar'); return; }
        $officeId = (int) Session::get('office_id');

        $target = $_POST['target'] ?? '';
        $eventDate = $_POST['event_date'] ?? '';
        $title = mb_substr(trim($_POST['title'] ?? ''), 0, 100);
        $description = mb_substr(trim($_POST['description'] ?? ''), 0, 500) ?: null;
        $color = $_POST['color'] ?? '#6366f1';
        $redirectMonth = (int) ($_POST['redirect_month'] ?? date('n'));
        $redirectYear = (int) ($_POST['redirect_year'] ?? date('Y'));

        // Validate title not empty and date is valid
        if (empty($title) || empty($target) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            Session::flash('error', 'Uzupełnij odbiorcę, tytuł i datę');
            $this->redirect("/office/tax-calendar?month={$redirectMonth}&year={$redirectYear}");
            return;
        }

        $parsedDate = \DateTime::createFromFormat('Y-m-d', $eventDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $eventDate) {
            Session::flash('error', 'Nieprawidłowa data');
            $this->redirect("/office/tax-calendar?month={$redirectMonth}&year={$redirectYear}");
            return;
        }

        $allowedColors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#64748b'];
        if (!in_array($color, $allowedColors, true)) {
            $color = '#6366f1';
        }

        // Resolve targets: [['client_id' => X, 'employee_id' => Y], ...]
        $targets = [];

        if ($target === 'all_clients') {
            $allClients = Client::findByOffice($officeId, true);
            foreach ($allClients as $c) {
                $targets[] = ['client_id' => (int) $c['id'], 'employee_id' => null];
            }
        } elseif ($target === 'all_employees') {
            $allEmployees = \App\Models\OfficeEmployee::findByOffice($officeId);
            foreach ($allEmployees as $emp) {
                $targets[] = ['client_id' => null, 'employee_id' => (int) $emp['id']];
            }
        } elseif (str_starts_with($target, 'emp_clients_')) {
            // All clients assigned to employee
            $empId = (int) substr($target, 12);
            $clientIds = \App\Models\OfficeEmployee::getAssignedClientIds($empId);
            if (empty($clientIds)) {
                Session::flash('error', 'Pracownik nie ma przypisanych klientów');
                $this->redirect("/office/tax-calendar?month={$redirectMonth}&year={$redirectYear}");
                return;
            }
            foreach ($clientIds as $cid) {
                $targets[] = ['client_id' => (int) $cid, 'employee_id' => null];
            }
        } elseif (str_starts_with($target, 'emp_')) {
            // Single employee
            $empId = (int) substr($target, 4);
            $targets[] = ['client_id' => null, 'employee_id' => $empId];
        } elseif (str_starts_with($target, 'client_')) {
            // Single client
            $clientId = (int) substr($target, 7);
            $client = Client::findById($clientId);
            if (!$client || (int) $client['office_id'] !== $officeId) {
                Session::flash('error', 'Nieprawidłowy klient');
                $this->redirect("/office/tax-calendar?month={$redirectMonth}&year={$redirectYear}");
                return;
            }
            $targets[] = ['client_id' => $clientId, 'employee_id' => null];
        } else {
            Session::flash('error', 'Nieprawidłowy odbiorca');
            $this->redirect("/office/tax-calendar?month={$redirectMonth}&year={$redirectYear}");
            return;
        }

        $created = 0;
        foreach ($targets as $t) {
            \App\Models\TaxCustomEvent::create($officeId, $t['client_id'], $eventDate, $title, $description, $color, $t['employee_id']);
            $created++;
        }

        if ($created > 1) {
            Session::flash('success', "Dodano termin ({$created} wpisów)");
        } else {
            Session::flash('success', 'event_added');
        }
        $evtMonth = (int) date('n', strtotime($eventDate));
        $evtYear = (int) date('Y', strtotime($eventDate));
        $this->redirect("/office/tax-calendar?month={$evtMonth}&year={$evtYear}");
    }

    public function taxCalendarDeleteEvent(int $eventId): void
    {
        ModuleAccess::requireModule('tax-calendar');
        if (!$this->validateCsrf()) { $this->redirect('/office/tax-calendar'); return; }
        $officeId = (int) Session::get('office_id');
        $month = (int) ($_POST['month'] ?? date('n'));
        $year = (int) ($_POST['year'] ?? date('Y'));

        \App\Models\TaxCustomEvent::delete($eventId, $officeId);

        Session::flash('success', 'event_deleted');
        $this->redirect("/office/tax-calendar?month={$month}&year={$year}");
    }

    // ── Calculators ──────────────────────────────────

    public function taxCalculator(): void
    {
        ModuleAccess::requireModule('tax-calculator');
        $officeId = (int) Session::get('office_id');
        $tab = $_GET['tab'] ?? 'tax';

        // Tax profitability calculator
        $taxResults = null;
        $revenue = $_GET['revenue'] ?? '';
        $isGross = (bool) ($_GET['is_gross'] ?? 1);
        $ryczaltRate = (float) ($_GET['ryczalt_rate'] ?? 0.085);
        $costs = (float) ($_GET['costs'] ?? 0);
        $zusVariant = $_GET['zus_variant'] ?? 'full';

        $validVariants = array_keys(\App\Services\TaxCalculatorService::getZusVariants());
        if (!in_array($zusVariant, $validVariants, true)) $zusVariant = 'full';

        if ($tab === 'tax' && $revenue !== '' && (float) $revenue > 0) {
            $taxResults = \App\Services\TaxCalculatorService::calculateAll(
                (float) $revenue, $isGross, $ryczaltRate, $costs, $zusVariant
            );
        }

        // Brutto-netto / VAT
        $vatResult = null;
        if ($tab === 'vat' && !empty($_GET['amount'])) {
            $amountType = $_GET['amount_type'] ?? 'netto';
            if (!in_array($amountType, ['netto', 'brutto', 'vat'], true)) $amountType = 'netto';
            $vatRate = (int) ($_GET['vat_rate'] ?? 23);
            if (!in_array($vatRate, \App\Services\CalculatorService::VAT_RATES, true)) $vatRate = 23;
            $vatResult = \App\Services\CalculatorService::calculateVat(
                max(0, (float) $_GET['amount']), $vatRate, $amountType
            );
        }

        // Margin
        $marginResult = null;
        if ($tab === 'margin' && !empty($_GET['buy_price'])) {
            $calcMode = $_GET['calc_mode'] ?? 'from_prices';
            if (!in_array($calcMode, ['from_prices', 'from_margin', 'from_markup'], true)) $calcMode = 'from_prices';
            $marginResult = \App\Services\CalculatorService::calculateMargin(
                max(0, (float) $_GET['buy_price']), max(0, (float) ($_GET['sell_price'] ?? 0)),
                max(0, (float) ($_GET['margin_percent'] ?? 0)), $calcMode
            );
        }

        // Salary
        $salaryResult = null;
        if ($tab === 'salary' && !empty($_GET['salary_brutto'])) {
            $costType = (int) ($_GET['cost_type'] ?? 1);
            if (!in_array($costType, [1, 2], true)) $costType = 1;
            $salaryResult = \App\Services\CalculatorService::calculateSalary(
                max(0, (float) $_GET['salary_brutto']),
                isset($_GET['under26']),
                isset($_GET['ppk']),
                min(4.0, max(0.5, (float) ($_GET['ppk_employee_rate'] ?? 2.0))),
                min(4.0, max(0.5, (float) ($_GET['ppk_employer_rate'] ?? 1.5))),
                $costType
            );
        }

        // Mileage
        $mileageResult = null;
        if ($tab === 'mileage' && !empty($_GET['km'])) {
            $vehicleType = $_GET['vehicle_type'] ?? 'car_over_900';
            if (!array_key_exists($vehicleType, \App\Services\CalculatorService::VEHICLE_TYPES)) $vehicleType = 'car_over_900';
            $mileageResult = \App\Services\CalculatorService::calculateMileage(
                $vehicleType, max(0, (float) $_GET['km'])
            );
        }

        // Currency
        $currencyResult = null;
        if ($tab === 'currency' && !empty($_GET['curr_amount'])) {
            $fromCur = $_GET['from_currency'] ?? 'EUR';
            $toCur = $_GET['to_currency'] ?? 'PLN';
            if (!in_array($fromCur, \App\Services\CalculatorService::CURRENCIES, true)) $fromCur = 'EUR';
            if (!in_array($toCur, \App\Services\CalculatorService::CURRENCIES, true)) $toCur = 'PLN';
            $currencyResult = \App\Services\CalculatorService::convertCurrency(
                max(0, (float) $_GET['curr_amount']),
                $fromCur,
                $toCur,
                $_GET['rate_date'] ?? null
            );
        }

        // Car allowance
        $carAllowanceResult = null;
        if ($tab === 'car_allowance' && !empty($_GET['monthly_km'])) {
            $caVehicle = $_GET['vehicle_type'] ?? 'car_over_900';
            if (!array_key_exists($caVehicle, \App\Services\CalculatorService::VEHICLE_TYPES)) $caVehicle = 'car_over_900';
            $carAllowanceResult = \App\Services\CalculatorService::calculateCarAllowance(
                $caVehicle, max(0, (float) $_GET['monthly_km']), max(0, min(22, (int) ($_GET['absence_days'] ?? 0)))
            );
        }

        // Business profit
        $profitResult = null;
        if ($tab === 'profit' && !empty($_GET['biz_revenue'])) {
            $profitResult = \App\Services\CalculatorService::calculateProfit(
                (float) $_GET['biz_revenue'], (float) ($_GET['cost_of_sales'] ?? 0), (float) ($_GET['fixed_costs'] ?? 0)
            );
        }

        $clients = Client::findByOffice($officeId, true);
        $filterClientId = !empty($_GET['client_id']) ? (int) $_GET['client_id'] : null;
        $simulations = \App\Models\TaxSimulation::findByOffice($officeId, $filterClientId);

        $this->render('office/tax_calculator', [
            'tab' => $tab,
            'results' => $taxResults,
            'revenue' => $revenue,
            'isGross' => $isGross,
            'ryczaltRate' => $ryczaltRate,
            'costs' => $costs,
            'zusVariant' => $zusVariant,
            'ryczaltRates' => \App\Services\TaxCalculatorService::getAvailableRyczaltRates(),
            'zusVariants' => \App\Services\TaxCalculatorService::getZusVariants(),
            'vatResult' => $vatResult,
            'marginResult' => $marginResult,
            'salaryResult' => $salaryResult,
            'mileageResult' => $mileageResult,
            'currencyResult' => $currencyResult,
            'carAllowanceResult' => $carAllowanceResult,
            'profitResult' => $profitResult,
            'clients' => $clients,
            'filterClientId' => $filterClientId,
            'simulations' => $simulations,
            'csrf' => Session::generateCsrfToken(),
        ]);
    }

    public function taxCalculatorPdf(): void
    {
        ModuleAccess::requireModule('tax-calculator');
        $officeId = (int) Session::get('office_id');
        $revenue = (float) ($_GET['revenue'] ?? 0);
        $isGross = (bool) ($_GET['is_gross'] ?? 1);
        $ryczaltRate = (float) ($_GET['ryczalt_rate'] ?? 0.085);
        $costs = (float) ($_GET['costs'] ?? 0);
        $zusVariant = $_GET['zus_variant'] ?? 'full';

        if ($revenue <= 0) {
            $this->redirect('/office/tax-calculator');
            return;
        }

        // Validate enum parameters
        $validVariants = array_keys(\App\Services\TaxCalculatorService::getZusVariants());
        if (!in_array($zusVariant, $validVariants, true)) $zusVariant = 'full';

        $results = \App\Services\TaxCalculatorService::calculateAll($revenue, $isGross, $ryczaltRate, $costs, $zusVariant);
        $path = \App\Services\TaxCalculatorPdfService::generate($results);

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="kalkulator_podatkowy.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        @unlink($path);
        exit;
    }

    public function taxCalculatorSave(): void
    {
        ModuleAccess::requireModule('tax-calculator');
        if (!$this->validateCsrf()) { $this->redirect('/office/tax-calculator'); return; }
        $officeId = (int) Session::get('office_id');
        $clientId = (int) ($_POST['client_id'] ?? 0);

        $client = Client::findById($clientId);
        if (!$client || (int) $client['office_id'] !== $officeId) {
            Session::flash('error', 'Nieprawidłowy klient');
            $this->redirect('/office/tax-calculator');
            return;
        }

        $revenue = (float) ($_POST['revenue'] ?? 0);
        $isGross = (bool) ($_POST['is_gross'] ?? 0);
        $ryczaltRate = (float) ($_POST['ryczalt_rate'] ?? 0.085);
        $costs = (float) ($_POST['costs'] ?? 0);
        $zusVariant = $_POST['zus_variant'] ?? 'full';

        $results = \App\Services\TaxCalculatorService::calculateAll($revenue, $isGross, $ryczaltRate, $costs, $zusVariant);
        \App\Models\TaxSimulation::create($officeId, $clientId, $results['input'], json_encode($results), $results['best']);

        Session::flash('success', 'simulation_saved');
        $this->redirect('/office/tax-calculator?' . http_build_query([
            'tab' => 'tax', 'revenue' => $revenue, 'is_gross' => (int) $isGross,
            'ryczalt_rate' => $ryczaltRate, 'costs' => $costs, 'zus_variant' => $zusVariant,
        ]));
    }

    public function taxCalculatorDeleteSimulation(int $id): void
    {
        ModuleAccess::requireModule('tax-calculator');
        if (!$this->validateCsrf()) { $this->redirect('/office/tax-calculator'); return; }
        $officeId = (int) Session::get('office_id');
        \App\Models\TaxSimulation::delete($id, $officeId);
        Session::flash('success', 'simulation_deleted');
        $this->redirect('/office/tax-calculator');
    }

    // ── Duplicates Report (F2) ────────────────────────────

    public function duplicatesReport(): void
    {
        ModuleAccess::requireModule('duplicates');
        $officeId = (int) Session::get('office_id');
        $selectedStatus = $_GET['status'] ?? null;
        if ($selectedStatus === '') $selectedStatus = null;

        $candidates = DuplicateCandidate::findAllByOffice($officeId, $selectedStatus);
        $scanResult = Session::getFlash('scan_result');

        $this->render('office/duplicates_report', [
            'candidates' => $candidates,
            'selectedStatus' => $selectedStatus,
            'scanResult' => $scanResult,
        ]);
    }

    public function duplicatesScan(): void
    {
        ModuleAccess::requireModule('duplicates');
        if (!$this->validateCsrf()) { $this->redirect('/office/duplicates'); return; }
        $officeId = (int) Session::get('office_id');

        $result = DuplicateDetectionService::batchScanForOffice($officeId);
        Session::flash('scan_result', $result);
        $this->redirect('/office/duplicates');
    }

    public function duplicateReview(int $id): void
    {
        ModuleAccess::requireModule('duplicates');
        if (!$this->validateCsrf()) { $this->redirect('/office/duplicates'); return; }
        $officeId = (int) Session::get('office_id');
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['dismissed', 'confirmed'])) {
            $this->redirect('/office/duplicates');
            return;
        }

        $candidate = DuplicateCandidate::findById($id);
        if ($candidate) {
            DuplicateCandidate::updateStatus($id, $status, 'office', $officeId);
        }

        $this->redirect('/office/duplicates');
    }

    // ── Client Workflow Status ────────────────────────────────────────────

    public function clientStatusUpdate(string $id): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/clients');
            return;
        }

        $officeId = Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clientId = (int) $id;

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $year = (int) date('Y');
        $month = (int) date('n');
        ClientMonthlyStatus::advanceStatus($clientId, $officeId, $year, $month);

        Session::flash('success', Language::get('workflow_advanced'));
        $this->redirect('/office/clients');
    }

    // ── Client Internal Notes ────────────────────────────────────────────

    public function clientNotes(string $id): void
    {
        $officeId = Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clientId = (int) $id;

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $notes = ClientNote::findByClient($clientId, $officeId);

        $this->render('office/client_notes', [
            'client' => $client,
            'notes'  => $notes,
        ]);
    }

    public function clientNotesSave(string $id): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/clients');
            return;
        }

        $officeId = Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clientId = (int) $id;

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $note = trim($_POST['note'] ?? '');
        if ($note === '' || mb_strlen($note) > 5000) {
            Session::flash('error', Language::get('note_empty'));
            $this->redirect("/office/clients/{$clientId}/notes");
            return;
        }

        $createdBy = Session::get('office_name') ?? 'Office';
        ClientNote::save($clientId, $officeId, $note, $createdBy);

        Session::flash('success', Language::get('note_saved'));
        $this->redirect("/office/clients/{$clientId}/notes");
    }

    public function clientNoteTogglePin(string $id): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/clients');
            return;
        }

        $officeId = Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clientId = (int) $id;

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== (int)$officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        ClientNote::togglePin($clientId, $officeId);
        $this->redirect("/office/clients/{$clientId}/notes");
    }

    // ── VAT Settlement per client ────────────────────────

    public function clientVatSettlement(string $id): void
    {
        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clientId = (int) $id;

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== $officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $month = (int) ($_GET['month'] ?? date('n'));
        $year = (int) ($_GET['year'] ?? date('Y'));
        if ($month < 1 || $month > 12) $month = (int) date('n');
        if ($year < 2020 || $year > (int) date('Y') + 1) $year = (int) date('Y');

        // VAT należny (sprzedaż)
        $vatSalesSummary = IssuedInvoice::getVatSummary($clientId, $month, $year);

        // VAT naliczony (koszty)
        $db = \App\Core\Database::getInstance();
        $costVatRaw = $db->fetchAll(
            "SELECT SUM(net_amount) as net, SUM(vat_amount) as vat, SUM(gross_amount) as gross
             FROM invoices WHERE client_id = ? AND status = 'accepted'
             AND MONTH(issue_date) = ? AND YEAR(issue_date) = ?",
            [$clientId, $month, $year]
        );
        $costVatTotal = $costVatRaw[0] ?? ['net' => 0, 'vat' => 0, 'gross' => 0];

        // Ostatnie 6 miesięcy - trend VAT
        $vatTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $tMonth = (int) date('n', strtotime("-{$i} months", mktime(0, 0, 0, $month, 1, $year)));
            $tYear = (int) date('Y', strtotime("-{$i} months", mktime(0, 0, 0, $month, 1, $year)));

            $salesVat = 0;
            $tVatSales = IssuedInvoice::getVatSummary($clientId, $tMonth, $tYear);
            foreach ($tVatSales as $v) $salesVat += (float) $v['vat'];

            $tCostRaw = $db->fetchAll(
                "SELECT SUM(vat_amount) as vat FROM invoices
                 WHERE client_id = ? AND status = 'accepted'
                 AND MONTH(issue_date) = ? AND YEAR(issue_date) = ?",
                [$clientId, $tMonth, $tYear]
            );
            $costVatT = (float) ($tCostRaw[0]['vat'] ?? 0);

            $vatTrend[] = [
                'month' => $tMonth, 'year' => $tYear,
                'sales_vat' => $salesVat, 'cost_vat' => $costVatT,
                'balance' => $salesVat - $costVatT,
            ];
        }

        $this->render('office/client_vat_settlement', [
            'client'          => $client,
            'month'           => $month,
            'year'            => $year,
            'vatSalesSummary' => $vatSalesSummary,
            'costVatTotal'    => $costVatTotal,
            'vatTrend'        => $vatTrend,
        ]);
    }

    // ── Client Files ─────────���───────────────────────

    public function clientFiles(string $id): void
    {
        ModuleAccess::requireModule('files');
        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $clientId = (int) $id;

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== $officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $category = isset($_GET['category']) && $_GET['category'] !== '' ? trim($_GET['category']) : null;
        $validCategories = ['general', 'invoice', 'contract', 'tax', 'correspondence', 'other'];
        if ($category !== null && !in_array($category, $validCategories, true)) {
            $category = null;
        }

        $files = ClientFile::findByClient($clientId, $category);
        $stats = ClientFile::getStorageStats($clientId);

        $this->render('office/client_files', [
            'client' => $client,
            'files' => $files,
            'stats' => $stats,
            'currentCategory' => $category,
        ]);
    }

    public function clientFileUpload(string $id): void
    {
        ModuleAccess::requireModule('files');
        $clientId = (int) $id;

        if (!$this->validateCsrf()) {
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== $officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'file_required');
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'file_upload_error');
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        // Max 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            Session::flash('error', 'file_too_large');
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        // Extension whitelist
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx', 'doc', 'docx', 'csv', 'xml', 'zip'];
        if (!in_array($ext, $allowedExt, true)) {
            Session::flash('error', 'file_invalid_type');
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        // MIME check
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMime = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/csv',
            'application/csv',
            'text/xml',
            'application/xml',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];
        if (!in_array($mime, $allowedMime, true)) {
            Session::flash('error', 'file_invalid_type');
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        // Determine storage directory
        $nip = $client['nip'] ?? '';
        $sanitizedNip = preg_replace('/[^0-9]/', '', $nip);
        if ($sanitizedNip === '') {
            $sanitizedNip = 'client_' . $clientId;
        }

        $customPath = $client['file_storage_path'] ?? null;
        if (!empty($customPath)) {
            $dir = rtrim($customPath, '/');
        } else {
            $dir = __DIR__ . '/../../storage/client_files/' . $sanitizedNip;
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Safe filename
        $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $safeName = substr($safeName, 0, 100);
        $storedName = time() . '_' . $safeName . '.' . $ext;
        $fullPath = $dir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            Session::flash('error', 'file_upload_error');
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        // Category & description
        $category = $_POST['category'] ?? 'general';
        $validCategories = ['general', 'invoice', 'contract', 'tax', 'correspondence', 'other'];
        if (!in_array($category, $validCategories, true)) {
            $category = 'general';
        }
        $description = isset($_POST['description']) ? mb_substr(trim($_POST['description']), 0, 500) : null;

        // Determine uploader type and id
        $uploadedByType = Auth::isEmployee() ? 'employee' : 'office';
        $uploadedById = Auth::isEmployee() ? (int) Session::get('employee_id') : $officeId;

        ClientFile::create(
            $clientId,
            $uploadedByType,
            $uploadedById,
            $file['name'],
            $storedName,
            $file['size'],
            $mime,
            $category,
            $description ?: null
        );

        Session::flash('success', 'file_uploaded');
        $this->redirect("/office/clients/{$clientId}/files");
    }

    public function clientFileDownload(string $id): void
    {
        ModuleAccess::requireModule('files');
        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();
        $fileId = (int) $id;

        $fileRecord = ClientFile::findById($fileId);
        if (!$fileRecord) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $clientId = (int) $fileRecord['client_id'];
        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== $officeId) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        $fullPath = ClientFile::getFullPath($fileRecord, $client['file_storage_path'] ?? null, $client['nip'] ?? '');

        if (!$fullPath || !file_exists($fullPath)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($fullPath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($fileRecord['original_filename']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }

    public function clientFileDelete(string $id): void
    {
        ModuleAccess::requireModule('files');
        $fileId = (int) $id;
        $fileRecord = ClientFile::findById($fileId);

        if (!$fileRecord) {
            $this->redirect('/office/clients');
            return;
        }

        $clientId = (int) $fileRecord['client_id'];

        if (!$this->validateCsrf()) {
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== $officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $fullPath = ClientFile::getFullPath($fileRecord, $client['file_storage_path'] ?? null, $client['nip'] ?? '');

        if ($fullPath && file_exists($fullPath)) {
            @unlink($fullPath);
        }

        ClientFile::delete($fileId);

        Session::flash('success', 'file_deleted');
        $this->redirect("/office/clients/{$clientId}/files");
    }

    public function clientFileStoragePath(string $id): void
    {
        ModuleAccess::requireModule('files');
        $clientId = (int) $id;

        if (!$this->validateCsrf()) {
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        $officeId = (int) Session::get('office_id');
        $clientFilter = $this->getEmployeeClientFilter();

        $client = Client::findById($clientId);
        if (!$client || (int)($client['office_id'] ?? 0) !== $officeId) {
            $this->redirect('/office/clients');
            return;
        }
        if ($clientFilter !== null && !in_array($clientId, $clientFilter)) {
            $this->redirect('/office/clients');
            return;
        }

        $path = trim($_POST['file_storage_path'] ?? '');

        // Validate: empty (reset to default) or absolute path
        if ($path !== '' && $path[0] !== '/') {
            Session::flash('error', 'file_storage_path_invalid');
            $this->redirect("/office/clients/{$clientId}/files");
            return;
        }

        Client::update($clientId, ['file_storage_path' => $path ?: null]);

        Session::flash('success', 'file_storage_path_saved');
        $this->redirect("/office/clients/{$clientId}/files");
    }

    // ── HR / Kadry i Płace ─────────────────────────────────

    public function hrDashboard(): void
    {
        ModuleAccess::requireModule('hr');
        $officeId = (int)Session::get('office_id');
        $clients = Client::findByOffice($officeId);
        $employeeFilter = $this->getEmployeeClientFilter();
        if ($employeeFilter !== null) {
            $clients = array_values(array_filter($clients, fn($c) => in_array((int)$c['id'], $employeeFilter)));
        }

        $db = \App\Core\Database::getInstance();
        $totals = ['employees' => 0, 'contracts' => 0, 'payrolls_pending' => 0, 'leaves_pending' => 0];

        foreach ($clients as &$c) {
            $cid = (int)$c['id'];
            $empCount = ClientEmployee::countByClient($cid);
            $contractCount = count(EmployeeContract::findActiveByClient($cid));

            $c['employee_count'] = $empCount;
            $c['active_contracts'] = $contractCount;
            $totals['employees'] += $empCount;
            $totals['contracts'] += $contractCount;

            // Last payroll info
            $lastPayroll = $db->fetchOne(
                "SELECT status, CONCAT(LPAD(month,2,'0'), '/', year) as period
                 FROM payroll_lists WHERE client_id = ? ORDER BY year DESC, month DESC LIMIT 1",
                [$cid]
            );
            $c['last_payroll_status'] = $lastPayroll['status'] ?? null;
            $c['last_payroll_period'] = $lastPayroll['period'] ?? null;
        }
        unset($c);

        // Count pending payrolls and leaves across all clients
        $clientIds = array_map(fn($c) => (int)$c['id'], $clients);
        if ($clientIds) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $pp = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM payroll_lists WHERE client_id IN ({$placeholders}) AND status = 'calculated'",
                $clientIds
            );
            $totals['payrolls_pending'] = (int)($pp['cnt'] ?? 0);

            $lp = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM employee_leaves WHERE client_id IN ({$placeholders}) AND status = 'pending'",
                $clientIds
            );
            $totals['leaves_pending'] = (int)($lp['cnt'] ?? 0);
        }

        $this->render('office/hr_dashboard', [
            'clients' => $clients,
            'totals' => $totals,
        ]);
    }

    public function hrEmployees(string $clientId): void
    {
        ModuleAccess::requireModule('hr');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }

        $employees = ClientEmployee::findByClient((int)$clientId, false);
        $this->render('office/hr_employees', [
            'client' => $client,
            'employees' => $employees,
        ]);
    }

    public function hrEmployeeCreate(string $clientId): void
    {
        ModuleAccess::requireModule('hr');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }

        $this->render('office/hr_employee_form', [
            'client' => $client,
            'employee' => null,
        ]);
    }

    public function hrEmployeeStore(string $clientId): void
    {
        ModuleAccess::requireModule('hr');
        if (!$this->validateCsrf()) { $this->redirect("/office/hr/{$clientId}/employees/create"); return; }
        if ($this->requireClientForOffice($clientId, "/office/hr/{$clientId}/employees") === null) { return; }

        $data = [
            'client_id' => (int)$clientId,
            'first_name' => $this->sanitize($_POST['first_name'] ?? ''),
            'last_name' => $this->sanitize($_POST['last_name'] ?? ''),
            'pesel' => $this->sanitize($_POST['pesel'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?: null,
            'email' => $this->sanitize($_POST['email'] ?? ''),
            'phone' => $this->sanitize($_POST['phone'] ?? ''),
            'address_street' => $this->sanitize($_POST['address_street'] ?? ''),
            'address_city' => $this->sanitize($_POST['address_city'] ?? ''),
            'address_postal_code' => $this->sanitize($_POST['address_postal_code'] ?? ''),
            'tax_office' => $this->sanitize($_POST['tax_office'] ?? ''),
            'bank_account' => $this->sanitize($_POST['bank_account'] ?? ''),
            'nfz_branch' => $this->sanitize($_POST['nfz_branch'] ?? ''),
            'hired_at' => $_POST['hired_at'] ?: null,
            'notes' => $this->sanitize($_POST['notes'] ?? ''),
        ];

        ClientEmployee::create($data);
        AuditLog::log(Auth::currentUserType(), Auth::currentUserId(), 'hr_employee_created',
            "Employee created: {$data['first_name']} {$data['last_name']}", 'client', (int)$clientId);
        Session::flash('success', 'hr_employee_saved');
        $this->redirect("/office/hr/{$clientId}/employees");
    }

    public function hrEmployeeEdit(string $clientId, string $employeeId): void
    {
        ModuleAccess::requireModule('hr');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }
        $employee = ClientEmployee::findByIdForClient((int)$employeeId, (int)$clientId);
        if (!$employee) { $this->redirect("/office/hr/{$clientId}/employees"); return; }

        $this->render('office/hr_employee_form', [
            'client' => $client,
            'employee' => $employee,
        ]);
    }

    public function hrEmployeeUpdate(string $clientId, string $employeeId): void
    {
        ModuleAccess::requireModule('hr');
        if (!$this->validateCsrf()) { $this->redirect("/office/hr/{$clientId}/employees/{$employeeId}/edit"); return; }
        if ($this->requireClientForOffice($clientId, "/office/hr/{$clientId}/employees") === null) { return; }
        if (!ClientEmployee::findByIdForClient((int)$employeeId, (int)$clientId)) {
            $this->redirect("/office/hr/{$clientId}/employees");
            return;
        }

        $data = [
            'first_name' => $this->sanitize($_POST['first_name'] ?? ''),
            'last_name' => $this->sanitize($_POST['last_name'] ?? ''),
            'pesel' => $this->sanitize($_POST['pesel'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?: null,
            'email' => $this->sanitize($_POST['email'] ?? ''),
            'phone' => $this->sanitize($_POST['phone'] ?? ''),
            'address_street' => $this->sanitize($_POST['address_street'] ?? ''),
            'address_city' => $this->sanitize($_POST['address_city'] ?? ''),
            'address_postal_code' => $this->sanitize($_POST['address_postal_code'] ?? ''),
            'tax_office' => $this->sanitize($_POST['tax_office'] ?? ''),
            'bank_account' => $this->sanitize($_POST['bank_account'] ?? ''),
            'nfz_branch' => $this->sanitize($_POST['nfz_branch'] ?? ''),
            'hired_at' => $_POST['hired_at'] ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'notes' => $this->sanitize($_POST['notes'] ?? ''),
        ];

        ClientEmployee::update((int)$employeeId, $data);
        Session::flash('success', 'hr_employee_saved');
        $this->redirect("/office/hr/{$clientId}/employees");
    }

    public function hrContracts(string $clientId): void
    {
        ModuleAccess::requireHrModule('payroll-contracts');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }

        $contracts = EmployeeContract::findByClient((int)$clientId);
        $this->render('office/hr_contracts', [
            'client' => $client,
            'contracts' => $contracts,
        ]);
    }

    public function hrContractCreate(string $clientId): void
    {
        ModuleAccess::requireHrModule('payroll-contracts');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }

        $employees = ClientEmployee::findByClient((int)$clientId);
        $this->render('office/hr_contract_form', [
            'client' => $client,
            'contract' => null,
            'employees' => $employees,
        ]);
    }

    public function hrContractStore(string $clientId): void
    {
        ModuleAccess::requireHrModule('payroll-contracts');
        if (!$this->validateCsrf()) { $this->redirect("/office/hr/{$clientId}/contracts/create"); return; }
        if ($this->requireClientForOffice($clientId, "/office/hr/{$clientId}/contracts") === null) { return; }

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        if (!ClientEmployee::findByIdForClient($employeeId, (int)$clientId)) {
            $this->redirect("/office/hr/{$clientId}/contracts/create");
            return;
        }

        $data = [
            'client_id' => (int)$clientId,
            'employee_id' => $employeeId,
            'contract_type' => $_POST['contract_type'] ?? 'umowa_o_prace',
            'work_time_fraction' => (float)($_POST['work_time_fraction'] ?? 1.00),
            'position' => $this->sanitize($_POST['position'] ?? ''),
            'workplace' => $this->sanitize($_POST['workplace'] ?? ''),
            'gross_salary' => (float)($_POST['gross_salary'] ?? 0),
            'salary_type' => $_POST['salary_type'] ?? 'monthly',
            'zus_emerytalna' => isset($_POST['zus_emerytalna']) ? 1 : 0,
            'zus_rentowa' => isset($_POST['zus_rentowa']) ? 1 : 0,
            'zus_chorobowa' => isset($_POST['zus_chorobowa']) ? 1 : 0,
            'zus_wypadkowa' => isset($_POST['zus_wypadkowa']) ? 1 : 0,
            'zus_zdrowotna' => isset($_POST['zus_zdrowotna']) ? 1 : 0,
            'zus_fp' => isset($_POST['zus_fp']) ? 1 : 0,
            'zus_fgsp' => isset($_POST['zus_fgsp']) ? 1 : 0,
            'tax_deductible_costs' => $_POST['tax_deductible_costs'] ?? 'basic',
            'pit_exempt' => isset($_POST['pit_exempt']) ? 1 : 0,
            'uses_kwota_wolna' => isset($_POST['uses_kwota_wolna']) ? 1 : 0,
            'ppk_employee_rate' => (float)($_POST['ppk_employee_rate'] ?? 2.00),
            'ppk_employer_rate' => (float)($_POST['ppk_employer_rate'] ?? 1.50),
            'ppk_active' => isset($_POST['ppk_active']) ? 1 : 0,
            'dzielo_kup_rate' => (float)($_POST['dzielo_kup_rate'] ?? 20.00),
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?: null,
            'status' => $_POST['status'] ?? 'draft',
            'notes' => $this->sanitize($_POST['notes'] ?? ''),
            'created_by_type' => Auth::currentUserType(),
            'created_by_id' => Auth::currentUserId(),
        ];

        // For umowa o prace, force all ZUS to enabled
        if ($data['contract_type'] === 'umowa_o_prace') {
            $data['zus_emerytalna'] = 1;
            $data['zus_rentowa'] = 1;
            $data['zus_chorobowa'] = 1;
            $data['zus_wypadkowa'] = 1;
            $data['zus_zdrowotna'] = 1;
            $data['zus_fp'] = 1;
            $data['zus_fgsp'] = 1;
        }

        EmployeeContract::create($data);
        Session::flash('success', 'hr_contract_saved');
        $this->redirect("/office/hr/{$clientId}/contracts");
    }

    public function hrContractEdit(string $contractId): void
    {
        ModuleAccess::requireHrModule('payroll-contracts');
        $contract = $this->requireRecordForOffice(EmployeeContract::findById((int)$contractId));
        if ($contract === null) { return; }

        $clientId = (int)$contract['client_id'];
        $client = Client::findById($clientId);
        $employees = ClientEmployee::findByClient($clientId);

        $this->render('office/hr_contract_form', [
            'client' => $client,
            'contract' => $contract,
            'employees' => $employees,
        ]);
    }

    public function hrContractUpdate(string $contractId): void
    {
        ModuleAccess::requireHrModule('payroll-contracts');
        $contract = $this->requireRecordForOffice(EmployeeContract::findById((int)$contractId));
        if ($contract === null) { return; }
        if (!$this->validateCsrf()) { $this->redirect("/office/hr/contracts/{$contractId}/edit"); return; }

        $data = [
            'contract_type' => $_POST['contract_type'] ?? $contract['contract_type'],
            'work_time_fraction' => (float)($_POST['work_time_fraction'] ?? 1.00),
            'position' => $this->sanitize($_POST['position'] ?? ''),
            'workplace' => $this->sanitize($_POST['workplace'] ?? ''),
            'gross_salary' => (float)($_POST['gross_salary'] ?? 0),
            'salary_type' => $_POST['salary_type'] ?? 'monthly',
            'zus_emerytalna' => isset($_POST['zus_emerytalna']) ? 1 : 0,
            'zus_rentowa' => isset($_POST['zus_rentowa']) ? 1 : 0,
            'zus_chorobowa' => isset($_POST['zus_chorobowa']) ? 1 : 0,
            'zus_wypadkowa' => isset($_POST['zus_wypadkowa']) ? 1 : 0,
            'zus_zdrowotna' => isset($_POST['zus_zdrowotna']) ? 1 : 0,
            'zus_fp' => isset($_POST['zus_fp']) ? 1 : 0,
            'zus_fgsp' => isset($_POST['zus_fgsp']) ? 1 : 0,
            'tax_deductible_costs' => $_POST['tax_deductible_costs'] ?? 'basic',
            'pit_exempt' => isset($_POST['pit_exempt']) ? 1 : 0,
            'uses_kwota_wolna' => isset($_POST['uses_kwota_wolna']) ? 1 : 0,
            'ppk_employee_rate' => (float)($_POST['ppk_employee_rate'] ?? 2.00),
            'ppk_employer_rate' => (float)($_POST['ppk_employer_rate'] ?? 1.50),
            'ppk_active' => isset($_POST['ppk_active']) ? 1 : 0,
            'dzielo_kup_rate' => (float)($_POST['dzielo_kup_rate'] ?? 20.00),
            'start_date' => $_POST['start_date'] ?? $contract['start_date'],
            'end_date' => $_POST['end_date'] ?: null,
            'status' => $_POST['status'] ?? $contract['status'],
            'notes' => $this->sanitize($_POST['notes'] ?? ''),
        ];

        EmployeeContract::update((int)$contractId, $data);
        Session::flash('success', 'hr_contract_saved');
        $this->redirect("/office/hr/{$contract['client_id']}/contracts");
    }

    public function hrPayrollList(string $clientId): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }

        $lists = PayrollList::findByClient((int)$clientId);
        $this->render('office/hr_payroll_lists', [
            'client' => $client,
            'lists' => $lists,
        ]);
    }

    public function hrPayrollGenerate(string $clientId): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        if (!$this->validateCsrf()) { $this->redirect("/office/hr/{$clientId}/payroll"); return; }
        if ($this->requireClientForOffice($clientId, "/office/hr/{$clientId}/payroll") === null) { return; }

        $year = (int)($_POST['year'] ?? date('Y'));
        $month = (int)($_POST['month'] ?? date('n'));

        $listId = PayrollListService::generateForMonth(
            (int)$clientId, $year, $month,
            Auth::currentUserType(), Auth::currentUserId()
        );

        if ($listId) {
            Session::flash('success', 'hr_payroll_generated');
            $this->redirect("/office/hr/payroll/{$listId}");
        } else {
            Session::flash('error', 'hr_payroll_generate_error');
            $this->redirect("/office/hr/{$clientId}/payroll");
        }
    }

    public function hrPayrollDetail(string $listId): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        $list = $this->requireRecordForOffice(PayrollList::findById((int)$listId));
        if ($list === null) { return; }

        $entries = PayrollEntry::findByPayrollList((int)$listId);
        $client = Client::findById((int)$list['client_id']);

        $this->render('office/hr_payroll_detail', [
            'list' => $list,
            'entries' => $entries,
            'client' => $client,
        ]);
    }

    public function hrPayrollApprove(string $listId): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        if (!$this->validateCsrf()) { $this->redirect("/office/hr/payroll/{$listId}"); return; }
        if ($this->requireRecordForOffice(PayrollList::findById((int)$listId)) === null) { return; }

        PayrollList::approve((int)$listId, Auth::currentUserType(), Auth::currentUserId());
        Session::flash('success', 'hr_payroll_approved');
        $this->redirect("/office/hr/payroll/{$listId}");
    }

    public function hrPayrollPdf(string $listId): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        if ($this->requireRecordForOffice(PayrollList::findById((int)$listId)) === null) { return; }

        $filepath = PayrollPdfService::generatePayrollList((int)$listId);
        if ($filepath && file_exists($filepath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            readfile($filepath);
            exit;
        }
        Session::flash('error', 'hr_pdf_error');
        $this->redirect("/office/hr/payroll/{$listId}");
    }

    public function hrPayslipPdf(string $entryId): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        // Walk entry → payroll_list → client → office. PayrollEntry has employee_id but
        // not client_id, so resolve via payroll_list.
        $entry = PayrollEntry::findById((int)$entryId);
        if (!$entry) { $this->redirect('/office/hr'); return; }
        $list = PayrollList::findById((int)($entry['payroll_list_id'] ?? 0));
        if ($this->requireRecordForOffice($list) === null) { return; }

        $filepath = PayrollPdfService::generatePayslip((int)$entryId);
        if ($filepath && file_exists($filepath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            readfile($filepath);
            exit;
        }
        Session::flash('error', 'hr_pdf_error');
        $this->redirect('/office/hr');
    }

    public function hrPayrollCalculator(): void
    {
        ModuleAccess::requireHrModule('payroll-calc');
        $this->render('office/hr_payroll_calculator', []);
    }

    public function hrLeaves(string $clientId): void
    {
        ModuleAccess::requireHrModule('payroll-leave');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }

        $leaves = EmployeeLeave::findByClient((int)$clientId);
        $this->render('office/hr_leaves', [
            'client' => $client,
            'leaves' => $leaves,
            'leaveTypes' => LeaveService::getLeaveTypes(),
        ]);
    }

    public function hrLeaveCreate(string $clientId): void
    {
        ModuleAccess::requireHrModule('payroll-leave');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }

        $employees = ClientEmployee::findByClient((int)$clientId);
        $contracts = EmployeeContract::findActiveByClient((int)$clientId);
        $this->render('office/hr_leave_form', [
            'client' => $client,
            'employees' => $employees,
            'contracts' => $contracts,
            'leaveTypes' => LeaveService::getLeaveTypes(),
            'leave' => null,
        ]);
    }

    public function hrLeaveStore(string $clientId): void
    {
        ModuleAccess::requireHrModule('payroll-leave');
        if (!$this->validateCsrf()) { $this->redirect("/office/hr/{$clientId}/leaves/create"); return; }
        if ($this->requireClientForOffice($clientId, "/office/hr/{$clientId}/leaves") === null) { return; }

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $contractId = (int)($_POST['contract_id'] ?? 0);
        if (!$this->verifyEmployeeAndContract($employeeId, $contractId, (int)$clientId)) {
            Session::flash('error', 'hr_leave_error');
            $this->redirect("/office/hr/{$clientId}/leaves/create");
            return;
        }

        $leaveId = LeaveService::requestLeave(
            (int)$clientId, $employeeId, $contractId,
            $_POST['leave_type'] ?? 'wypoczynkowy',
            $_POST['start_date'] ?? '',
            $_POST['end_date'] ?? '',
            $this->sanitize($_POST['notes'] ?? '')
        );

        Session::flash($leaveId ? 'success' : 'error', $leaveId ? 'hr_leave_created' : 'hr_leave_error');
        $this->redirect("/office/hr/{$clientId}/leaves");
    }

    public function hrLeaveApprove(string $leaveId): void
    {
        ModuleAccess::requireHrModule('payroll-leave');
        if (!$this->validateCsrf()) { $this->redirect('/office/hr'); return; }

        $leave = $this->requireRecordForOffice(EmployeeLeave::findById((int)$leaveId));
        if ($leave === null) { return; }

        LeaveService::approveLeave((int)$leaveId, Auth::currentUserType(), Auth::currentUserId());
        Session::flash('success', 'hr_leave_approved');
        $this->redirect("/office/hr/{$leave['client_id']}/leaves");
    }

    public function hrLeaveReject(string $leaveId): void
    {
        ModuleAccess::requireHrModule('payroll-leave');
        if (!$this->validateCsrf()) { $this->redirect('/office/hr'); return; }

        $leave = $this->requireRecordForOffice(EmployeeLeave::findById((int)$leaveId));
        if ($leave === null) { return; }

        LeaveService::rejectLeave((int)$leaveId, Auth::currentUserType(), Auth::currentUserId());
        Session::flash('success', 'hr_leave_rejected');
        $this->redirect("/office/hr/{$leave['client_id']}/leaves");
    }

    public function hrDeclarations(string $clientId): void
    {
        ModuleAccess::requireModule('hr');
        $client = $this->requireClientForOffice($clientId);
        if ($client === null) { return; }

        $declarations = PayrollDeclaration::findByClient((int)$clientId);
        $employees = ClientEmployee::findByClient((int)$clientId);
        $this->render('office/hr_declarations', [
            'client' => $client,
            'declarations' => $declarations,
            'employees' => $employees,
        ]);
    }

    /** True iff (employeeId, contractId) form a valid (client_id, employee_id) pair in the DB. */
    private function verifyEmployeeAndContract(int $employeeId, int $contractId, int $clientId): bool
    {
        if (!ClientEmployee::findByIdForClient($employeeId, $clientId)) {
            return false;
        }
        $contract = \App\Core\Database::getInstance()->fetchOne(
            "SELECT id FROM employee_contracts WHERE id = ? AND client_id = ? AND employee_id = ?",
            [$contractId, $clientId, $employeeId]
        );
        return $contract !== null;
    }

    public function hrDeclarationGenerate(string $clientId): void
    {
        ModuleAccess::requireModule('hr');
        if (!$this->validateCsrf()) { $this->redirect("/office/hr/{$clientId}/declarations"); return; }
        if ($this->requireClientForOffice($clientId, "/office/hr/{$clientId}/declarations") === null) { return; }

        $type = $_POST['declaration_type'] ?? '';
        $year = (int)($_POST['year'] ?? date('Y'));
        $month = (int)($_POST['month'] ?? date('n'));
        $employeeId = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
        if ($employeeId !== null && !ClientEmployee::findByIdForClient($employeeId, (int)$clientId)) {
            Session::flash('error', 'hr_declaration_error');
            $this->redirect("/office/hr/{$clientId}/declarations");
            return;
        }

        $id = match ($type) {
            'PIT-11' => $employeeId ? PayrollDeclarationService::generatePit11((int)$clientId, $employeeId, $year) : null,
            'PIT-4R' => PayrollDeclarationService::generatePit4r((int)$clientId, $year),
            'ZUS-DRA' => PayrollDeclarationService::generateZusDra((int)$clientId, $year, $month),
            'ZUS-RCA' => PayrollDeclarationService::generateZusRca((int)$clientId, $year, $month),
            default => null,
        };

        if ($id) {
            Session::flash('success', 'hr_declaration_generated');
        } else {
            Session::flash('error', 'hr_declaration_error');
        }
        $this->redirect("/office/hr/{$clientId}/declarations");
    }

    public function hrDeclarationDownload(string $declarationId): void
    {
        ModuleAccess::requireModule('hr');
        $decl = $this->requireRecordForOffice(PayrollDeclaration::findById((int)$declarationId));
        if ($decl === null || empty($decl['xml_content'])) {
            Session::flash('error', 'hr_declaration_not_found');
            $this->redirect('/office/hr');
            return;
        }

        $filename = strtolower($decl['declaration_type']) . '_' . $decl['year']
            . ($decl['month'] ? '_' . sprintf('%02d', $decl['month']) : '') . '.xml';

        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $decl['xml_content'];
        exit;
    }
}
