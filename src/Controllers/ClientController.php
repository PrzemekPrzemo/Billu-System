<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Language;
use App\Core\ModuleAccess;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Report;
use App\Models\AuditLog;
use App\Models\ClientCostCenter;
use App\Services\ExportService;
use App\Services\PdfService;
use App\Services\MailService;
use App\Services\JpkV3Service;
use App\Services\KsefApiService;
use App\Services\KsefCertificateService;
use App\Services\KsefLogger;
use App\Services\InvoicePdfService;
use App\Services\PurchaseInvoicePdfService;
use App\Services\KsefInvoiceSendService;
use App\Services\ElixirExportService;
use App\Models\Message;
use App\Models\MessageNotificationPref;
use App\Models\ClientTask;
use App\Models\TaxPayment;
use App\Services\WhiteListService;
use App\Services\JpkVat7Service;
use App\Services\SalesReportService;
use App\Models\KsefConfig;
use App\Models\KsefOperationLog;
use App\Models\Setting;
use App\Models\Notification;
use App\Models\CompanyProfile;
use App\Models\BankAccount;
use App\Models\CompanyService;
use App\Models\Contractor;
use App\Models\IssuedInvoice;
use App\Models\Office;
use App\Models\OfficeEmployee;
use App\Models\ClientInvoiceEmailTemplate;
use App\Models\ClientFile;
use App\Services\NbpExchangeRateService;
use App\Models\ClientEmployee;
use App\Models\PayrollList;
use App\Models\PayrollEntry;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeContract;
use App\Models\PayrollDeclaration;
use App\Services\LeaveService;
use App\Services\PayrollPdfService;

class ClientController extends Controller
{
    public function __construct()
    {
        Auth::requireClient();
        $lang = Session::get('client_language', 'pl');
        Language::setLocale($lang);
    }

    public function dashboard(): void
    {
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        $batches = InvoiceBatch::findByClient($clientId);
        $activeBatches = InvoiceBatch::findActiveByClient($clientId);
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');
        $stats = Invoice::countByClientAndPeriod($clientId, $currentMonth, $currentYear);

        $ksefEnabled = false;
        $ksefConnectionStatus = null;
        if ($client && $client['ksef_enabled']) {
            $ksef = KsefApiService::forClient($client);
            $ksefEnabled = $ksef->isConfigured();

            // Check KSeF connection on every login (once per session)
            if ($ksefEnabled) {
                $alreadyChecked = Session::get('ksef_connection_checked');
                if (!$alreadyChecked) {
                    try {
                        $connResult = $ksef->checkConnection();
                        $status = $connResult['ok'] ? 'ok' : 'failed';
                        KsefConfig::updateConnectionStatus($clientId, $status, $connResult['error']);
                        $ksefConnectionStatus = [
                            'status' => $status,
                            'error' => $connResult['error'],
                            'checked_at' => date('Y-m-d H:i:s'),
                            'response_time_ms' => $connResult['response_time_ms'],
                        ];
                        Session::set('ksef_connection_checked', true);
                    } catch (\Exception $e) {
                        KsefConfig::updateConnectionStatus($clientId, 'failed', $e->getMessage());
                        $ksefConnectionStatus = [
                            'status' => 'failed',
                            'error' => $e->getMessage(),
                            'checked_at' => date('Y-m-d H:i:s'),
                            'response_time_ms' => 0,
                        ];
                        Session::set('ksef_connection_checked', true);
                    }
                } else {
                    // Already checked this session — read cached status from DB
                    $cached = KsefConfig::getConnectionStatus($clientId);
                    if ($cached) {
                        $ksefConnectionStatus = [
                            'status' => $cached['ksef_connection_status'] ?? 'unknown',
                            'error' => $cached['ksef_connection_error'] ?? null,
                            'checked_at' => $cached['ksef_connection_checked_at'] ?? null,
                        ];
                    }
                }
            }
        }

        $topSellers = Invoice::getTopSellersByClient($clientId, 5);

        // Password expiry countdown
        $db = \App\Core\Database::getInstance();
        $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'password_expiry_days'");
        $expiryDays = $setting ? (int) $setting['setting_value'] : 90;
        $passwordChangedAt = $client['password_changed_at'] ?? date('Y-m-d H:i:s');
        $expiryDate = (new \DateTime($passwordChangedAt))->modify("+{$expiryDays} days");
        $passwordDaysLeft = max(0, (int) (new \DateTime())->diff($expiryDate)->format('%r%a'));

        $salesCounts = IssuedInvoice::countByClient($clientId);

        // Find latest active batch (regardless of pending count)
        $latestActiveBatchId = null;
        if (!empty($activeBatches)) {
            $latestActiveBatchId = $activeBatches[0]['id'];
        } elseif (!empty($batches)) {
            $latestActiveBatchId = $batches[0]['id'];
        }

        // Assigned employees (all, not just first)
        $assignedEmployees = OfficeEmployee::findAllByClient($clientId);
        $supportContact = [
            'name'  => Setting::get('support_contact_name'),
            'email' => Setting::get('support_contact_email'),
            'phone' => Setting::get('support_contact_phone'),
        ];

        // Tax payments for previous month (last settlement period)
        $prevMonth = $currentMonth - 1;
        $prevYear = $currentYear;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        $taxRows = TaxPayment::findByClientAndYear($clientId, $prevYear);
        $taxGrid = TaxPayment::buildGrid($taxRows);
        $taxPrevMonth = $taxGrid[$prevMonth] ?? [];

        // Office branding (logo + name + contact details)
        $officeBranding = null;
        if ($client && $client['office_id']) {
            $officeForBranding = Office::findById($client['office_id']);
            if ($officeForBranding) {
                $officeBranding = [
                    'name'      => $officeForBranding['name'],
                    'logo_path' => $officeForBranding['logo_path'] ?? null,
                    'nip'       => $officeForBranding['nip'] ?? '',
                    'email'     => $officeForBranding['email'] ?? '',
                    'phone'     => $officeForBranding['phone'] ?? '',
                ];
            }
        }

        // NBP exchange rates for dashboard widget
        $exchangeRates = NbpExchangeRateService::getLatestRates(['EUR', 'USD', 'GBP']);

        $this->render('client/dashboard', [
            'batches'              => $batches,
            'activeBatches'        => $activeBatches,
            'stats'                => $stats,
            'ksefEnabled'          => $ksefEnabled,
            'ksefConnectionStatus' => $ksefConnectionStatus,
            'topSellers'           => $topSellers,
            'passwordDaysLeft'     => $passwordDaysLeft,
            'salesCounts'          => $salesCounts,
            'latestActiveBatchId'  => $latestActiveBatchId,
            'assignedEmployees'    => $assignedEmployees,
            'supportContact'       => $supportContact,
            'officeBranding'       => $officeBranding,
            'taxPrevMonth'         => $taxPrevMonth,
            'taxPrevMonthNum'      => $prevMonth,
            'taxPrevYear'          => $prevYear,
            'exchangeRates'        => $exchangeRates,
        ]);
    }

    public function invoices(string $batchId): void
    {
        $clientId = Session::get('client_id');
        $batch = InvoiceBatch::findById((int) $batchId);

        if (!$batch || (int)$batch['client_id'] !== $clientId) {
            $this->redirect('/client');
            return;
        }

        $client = Client::findById($clientId);

        $filterStatus = $_GET['status'] ?? null;
        $filterSearch = $_GET['search'] ?? null;

        if ($filterStatus || $filterSearch) {
            $invoices = Invoice::findByBatchFiltered((int) $batchId, $filterStatus ?: null, $filterSearch ?: null);
        } else {
            $invoices = Invoice::findByClientAndBatch($clientId, (int) $batchId);
        }
        $stats = Invoice::countByBatchAndStatus((int) $batchId);
        $costCenters = [];
        if ($client['has_cost_centers']) {
            $costCenters = ClientCostCenter::findByClient($clientId, true);
        }

        $commentCounts = \App\Models\InvoiceComment::countByBatch((int) $batchId);

        $ksefEnabled = false;
        if ($client && !empty($client['ksef_enabled'])) {
            $ksef = KsefApiService::forClient($client);
            $ksefEnabled = $ksef->isConfigured();
        }

        $this->render('client/invoices', [
            'batch'         => $batch,
            'client'        => $client,
            'invoices'      => $invoices,
            'stats'         => $stats,
            'costCenters'   => $costCenters,
            'commentCounts' => $commentCounts,
            'ksefEnabled'   => $ksefEnabled,
            'filters'       => ['status' => $filterStatus, 'search' => $filterSearch],
        ]);
    }

    public function verifyInvoice(): void
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!$this->validateCsrf()) {
            if ($isAjax) { $this->json(['error' => 'invalid_csrf'], 403); return; }
            $this->redirect('/client'); return;
        }

        $clientId = Session::get('client_id');
        $invoiceId = $this->sanitizeInt($_POST['invoice_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $comment = trim($_POST['comment'] ?? '');
        $costCenterId = $this->sanitizeInt($_POST['cost_center_id'] ?? 0);
        $costCenter = trim($_POST['cost_center'] ?? '');

        $invoice = Invoice::findById($invoiceId);
        if (!$invoice || (int)$invoice['client_id'] !== $clientId) {
            if ($isAjax) { $this->json(['error' => 'invoice_not_found'], 404); return; }
            Session::flash('error', 'invoice_not_found');
            $this->redirect('/client');
            return;
        }

        $batch = InvoiceBatch::findById($invoice['batch_id']);
        if ($batch['is_finalized']) {
            if ($isAjax) { $this->json(['error' => 'batch_finalized'], 400); return; }
            Session::flash('error', 'batch_finalized');
            $this->redirect("/client/invoices/{$invoice['batch_id']}");
            return;
        }

        if ($invoice['status'] !== 'pending') {
            if ($isAjax) { $this->json(['error' => 'invoice_already_verified'], 400); return; }
            Session::flash('error', 'invoice_already_verified');
            $this->redirect("/client/invoices/{$invoice['batch_id']}");
            return;
        }

        $client = Client::findById($clientId);

        if ($action === 'accept') {
            if ($client['has_cost_centers'] && !$costCenterId) {
                if ($isAjax) { $this->json(['error' => 'cost_center_required'], 400); return; }
                Session::flash('error', 'cost_center_required');
                $this->redirect("/client/invoices/{$invoice['batch_id']}");
                return;
            }

            if ($costCenterId) {
                $cc = ClientCostCenter::findById($costCenterId);
                if (!$cc || (int)$cc['client_id'] !== $clientId) {
                    $costCenterId = 0;
                } else {
                    $costCenter = $cc['name'];
                }
            }

            // White list verification before acceptance
            $whitelistBlock = self::checkWhitelistForInvoice($invoice);
            if ($whitelistBlock) {
                if ($isAjax) { $this->json(['error' => $whitelistBlock], 400); return; }
                Session::flash('error', $whitelistBlock);
                $this->redirect("/client/invoices/{$invoice['batch_id']}");
                return;
            }

            Invoice::updateStatus($invoiceId, 'accepted', $comment ?: null, $costCenter ?: null, $costCenterId ?: null);
            AuditLog::log('client', $clientId, 'invoice_accepted', "Invoice: {$invoice['invoice_number']}, MPK: {$costCenter}", 'invoice', $invoiceId);
        } elseif ($action === 'reject') {
            if ($costCenterId) {
                $cc = ClientCostCenter::findById($costCenterId);
                if ($cc && (int)$cc['client_id'] === $clientId) {
                    $costCenter = $cc['name'];
                }
            }

            Invoice::updateStatus($invoiceId, 'rejected', $comment ?: null, $costCenter ?: null, $costCenterId ?: null);
            AuditLog::log('client', $clientId, 'invoice_rejected', "Invoice: {$invoice['invoice_number']}, Comment: {$comment}", 'invoice', $invoiceId);
        }

        $pending = Invoice::findPendingByBatch($invoice['batch_id']);

        if ($isAjax) {
            $this->json([
                'success' => true,
                'status' => $action === 'accept' ? 'accepted' : 'rejected',
                'cost_center' => $costCenter,
                'comment' => $comment,
                'all_verified' => empty($pending),
            ]);
            return;
        }

        if (empty($pending)) {
            Session::flash('success', 'all_invoices_verified');
        }

        $this->redirect("/client/invoices/{$invoice['batch_id']}");
    }

    public function bulkVerify(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/client'); return; }

        $clientId = Session::get('client_id');
        $batchId = $this->sanitizeInt($_POST['batch_id'] ?? 0);
        $action = $_POST['bulk_action'] ?? '';
        $invoiceIds = $_POST['invoice_ids'] ?? [];
        $bulkComment = trim($_POST['bulk_comment'] ?? '');

        $batch = InvoiceBatch::findById($batchId);
        if (!$batch || (int)$batch['client_id'] !== $clientId || $batch['is_finalized']) {
            $this->redirect('/client');
            return;
        }

        if (!is_array($invoiceIds) || empty($invoiceIds)) {
            Session::flash('error', 'no_invoices_selected');
            $this->redirect("/client/invoices/{$batchId}");
            return;
        }

        $client = Client::findById($clientId);

        // Bulk accept with MPK requires using the MPK assignment bar instead
        if ($action === 'accept' && !empty($client['has_cost_centers'])) {
            Session::flash('error', 'bulk_accept_use_mpk');
            $this->redirect("/client/invoices/{$batchId}");
            return;
        }

        $count = 0;
        $whitelistSkipped = 0;
        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::findById((int) $invoiceId);
            if (!$invoice || (int)$invoice['client_id'] !== $clientId || $invoice['status'] !== 'pending') continue;

            if ($action === 'accept') {
                $whitelistBlock = self::checkWhitelistForInvoice($invoice);
                if ($whitelistBlock) {
                    $whitelistSkipped++;
                    continue;
                }
                Invoice::updateStatus((int) $invoiceId, 'accepted');
            } elseif ($action === 'reject') {
                Invoice::updateStatus((int) $invoiceId, 'rejected', $bulkComment ?: null);
            }
            $count++;
        }

        AuditLog::log('client', $clientId, 'bulk_verify', "Batch: {$batchId}, action: {$action}, count: {$count}, whitelist_skipped: {$whitelistSkipped}", 'batch', $batchId);

        $pending = Invoice::findPendingByBatch($batchId);
        if ($whitelistSkipped > 0) {
            Session::flash('error', "Pominięto {$whitelistSkipped} faktur(y) - numer rachunku nie widnieje na białej liście VAT.");
        }
        if (empty($pending)) {
            Session::flash('success', 'all_invoices_verified');
        } else {
            Session::flash('success', 'bulk_verify_success');
        }
        $this->redirect("/client/invoices/{$batchId}");
    }

    private static function checkWhitelistForInvoice(array $invoice): ?string
    {
        // Skip if no KSeF XML (e.g. CSV import)
        if (empty($invoice['ksef_xml'])) {
            return null;
        }

        $parsed = KsefApiService::parseKsefFaXml($invoice['ksef_xml']);
        if (!empty($parsed['error'])) {
            return null;
        }

        // Skip if payment is not bank transfer (code "6" = przelew)
        $formCode = $parsed['payment']['form_code'] ?? '';
        if ($formCode !== '' && $formCode !== '6') {
            return null;
        }

        $bankAccount = $parsed['payment']['bank_account'] ?? '';
        if (empty($bankAccount)) {
            return null;
        }

        $sellerNip = $invoice['seller_nip'] ?? '';
        if (empty($sellerNip)) {
            return null;
        }

        $result = WhiteListService::verifyNipBankAccount($sellerNip, $bankAccount);
        if (!$result['verified']) {
            return 'Numer rachunku nie widnieje na białej liście VAT - brak możliwości akceptacji';
        }

        return null;
    }

    private function finalizeBatchByClient(int $batchId): void
    {
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        InvoiceBatch::finalize($batchId);

        $periodLabel = sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']);
        $attachmentPaths = [];
        $isKsef = ($batch['source'] === 'ksef_api');
        $reportFormat = $isKsef ? 'jpk_xml' : 'excel';

        // Handle cost centers if enabled
        if ($client['has_cost_centers']) {
            $costCenters = ClientCostCenter::findByClient($batch['client_id'], true);
            foreach ($costCenters as $cc) {
                $acceptedInvoices = Invoice::getAcceptedByBatchAndCostCenter($batchId, (int)$cc['id']);
                if (!empty($acceptedInvoices)) {
                    $pdfPath = PdfService::generateCostCenterPdf($batchId, $cc['name'], $acceptedInvoices);
                    $attachmentPaths[] = $pdfPath;
                    $reportData = [
                        'client_id' => $batch['client_id'], 'batch_id' => $batchId,
                        'report_type' => 'accepted', 'pdf_path' => $pdfPath,
                        'cost_center_name' => $cc['name'], 'report_format' => $reportFormat,
                    ];

                    if ($isKsef) {
                        $xmlPath = JpkV3Service::generateCostCenterJpk($batchId, $cc['name'], $acceptedInvoices);
                        $attachmentPaths[] = $xmlPath;
                        $reportData['xml_path'] = $xmlPath;
                    } else {
                        $xlsPath = ExportService::generateCostCenterXls($batchId, $cc['name'], $acceptedInvoices);
                        $attachmentPaths[] = $xlsPath;
                        $reportData['xls_path'] = $xlsPath;
                    }

                    Report::create($reportData);
                }
            }
        } else {
            $pdfPath = PdfService::generateAcceptedPdf($batchId);
            $attachmentPaths[] = $pdfPath;
            $reportData = [
                'client_id' => $batch['client_id'], 'batch_id' => $batchId,
                'report_type' => 'accepted', 'pdf_path' => $pdfPath,
                'report_format' => $reportFormat,
            ];

            if ($isKsef) {
                $xmlPath = JpkV3Service::generateAcceptedJpk($batchId);
                $attachmentPaths[] = $xmlPath;
                $reportData['xml_path'] = $xmlPath;
            } else {
                $xlsPath = ExportService::generateAcceptedXls($batchId);
                $attachmentPaths[] = $xlsPath;
                $reportData['xls_path'] = $xlsPath;
            }

            Report::create($reportData);
        }

        // Generate rejected invoices report (always Excel + PDF)
        $rejectedXls = ExportService::generateRejectedXls($batchId);
        $rejectedPdf = PdfService::generateRejectedPdf($batchId);
        $attachmentPaths[] = $rejectedXls;
        $attachmentPaths[] = $rejectedPdf;

        // Send email with all attachments
        MailService::sendReportMultiple($client['report_email'], $client['company_name'], $client['nip'], $periodLabel, $attachmentPaths);
        AuditLog::log('client', $batch['client_id'], 'batch_finalized_by_client', "Batch: {$batchId}", 'batch', $batchId);
        Session::flash('success', 'all_invoices_verified');
    }

    public function reports(): void
    {
        $clientId = Session::get('client_id');
        $reports = \App\Models\Report::findByClient($clientId);

        // Analytics data for reports page
        $monthlySales = IssuedInvoice::getMonthlySales($clientId, 12);
        $monthlyCosts = Invoice::getMonthlyComparison($clientId, 12);
        $topBuyers = IssuedInvoice::getTopBuyers($clientId, 10);
        $topSellers = Invoice::getTopSellersByClient($clientId, 10);

        $curMonth = (int) date('n');
        $curYear = (int) date('Y');
        $db = \App\Core\Database::getInstance();

        // KSeF operation logs for report
        $ksefLogs = KsefOperationLog::findByClient($clientId, 100);
        $ksefSummary = KsefOperationLog::getImportSendSummary($clientId);

        // Overdue invoices (issued, not paid, past due_date)
        $overdueInvoices = $db->fetchAll(
            "SELECT invoice_number, buyer_name, gross_amount, due_date, DATEDIFF(CURDATE(), due_date) as days_overdue
             FROM issued_invoices
             WHERE client_id = ? AND status != 'cancelled' AND due_date < CURDATE()
             AND due_date IS NOT NULL
             ORDER BY due_date ASC
             LIMIT 50",
            [$clientId]
        );

        $this->render('client/reports', [
            'reports'           => $reports,
            'monthlySales'      => $monthlySales,
            'monthlyCosts'      => $monthlyCosts,
            'topBuyers'         => $topBuyers,
            'topSellers'        => $topSellers,
            'overdueInvoices'   => $overdueInvoices,
            'ksefLogs'          => $ksefLogs,
            'ksefSummary'       => $ksefSummary,
            'currentMonth'      => $curMonth,
            'currentYear'       => $curYear,
        ]);
    }

    public function downloadReport(string $id): void
    {
        $clientId = Session::get('client_id');
        $report = Report::findById((int) $id);
        if (!$report || (int)$report['client_id'] !== $clientId) { $this->redirect('/client/reports'); return; }

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
        $this->redirect('/client/reports');
    }

    public function downloadRejected(string $batchId): void
    {
        $clientId = Session::get('client_id');
        $batch = InvoiceBatch::findById((int) $batchId);
        if (!$batch || (int)$batch['client_id'] !== $clientId) { $this->redirect('/client'); return; }

        $type = $_GET['type'] ?? 'pdf';
        $path = $type === 'xls' ? ExportService::generateRejectedXls((int)$batchId) : PdfService::generateRejectedPdf((int)$batchId);
        $ct = $type === 'xls' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf';

        header('Content-Type: ' . $ct);
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function importKsef(): void
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!$this->validateCsrf()) {
            if ($isAjax) { $this->json(['error' => 'invalid_csrf'], 403); return; }
            $this->redirect('/client'); return;
        }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        $month = $this->sanitizeInt($_POST['month'] ?? date('n'));
        $year = $this->sanitizeInt($_POST['year'] ?? date('Y'));

        $ksef = KsefApiService::forClient($client);
        if (!$ksef->isConfigured()) {
            if ($isAjax) { $this->json(['error' => 'KSeF nie jest skonfigurowane'], 400); return; }
            Session::flash('error', 'ksef_not_configured');
            $this->redirect('/client');
            return;
        }

        $jobId = self::launchKsefImportJob($clientId, $month, $year, $clientId, 'client', $client['office_id'] ?: null);

        if ($isAjax) {
            $this->json(['success' => true, 'job_id' => $jobId]);
            return;
        }

        Session::set('ksef_import_job_id', $jobId);
        $this->redirect('/client');
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

    // ─── KSeF Configuration ───────────────────────────

    public function ksefConfig(): void
    {
        ModuleAccess::requireModule('ksef');
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        $config = KsefConfig::findByClientId($clientId);
        $recentOps = KsefOperationLog::findByClient($clientId, 20);

        $certWarning = null;
        if ($config && $config['auth_method'] === 'certificate' && !empty($config['cert_valid_to'])) {
            if (KsefCertificateService::isExpiringSoon($config['cert_valid_to'])) {
                $daysLeft = max(0, (int)((strtotime($config['cert_valid_to']) - time()) / 86400));
                $certWarning = "Certyfikat wygasa za {$daysLeft} dni. Załaduj nowy certyfikat.";
            }
        }

        // Check for KSeF cert expiry warning too
        if (!$certWarning && $config && $config['auth_method'] === 'ksef_cert' && !empty($config['cert_ksef_valid_to'])) {
            if (KsefCertificateService::isExpiringSoon($config['cert_ksef_valid_to'])) {
                $daysLeft = max(0, (int)((strtotime($config['cert_ksef_valid_to']) - time()) / 86400));
                $certWarning = "Certyfikat KSeF wygasa za {$daysLeft} dni. Wygeneruj nowy certyfikat.";
            }
        }

        $configuredAuthMethod = $config['auth_method'] ?? null;

        $this->render('client/ksef_config', [
            'config' => $config,
            'certUploadEnabled' => (bool) Setting::get('ksef_cert_upload_enabled', '1'),
            'enrollmentEnabled' => false, // Clients cannot enroll certs - must use KSeF system
            'certWarning' => $certWarning,
            'recentOps' => $recentOps,
            'configuredAuthMethod' => $configuredAuthMethod,
        ]);
    }

    public function ksefUploadCert(): void
    {
        ModuleAccess::requireModule('ksef');
        if (!$this->validateCsrf()) { $this->redirect('/client/ksef'); return; }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);

        if (!Setting::get('ksef_cert_upload_enabled', '1')) {
            Session::flash('error', 'ksef_cert_upload_disabled');
            $this->redirect('/client/ksef');
            return;
        }

        if (!isset($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'ksef_cert_missing');
            $this->redirect('/client/ksef');
            return;
        }

        $password = $_POST['cert_password'] ?? '';
        // Use client's configured environment
        $existingConfig = KsefConfig::findByClientId($clientId);
        $environment = $existingConfig['ksef_environment'] ?? 'test';

        // Validate certificate
        $errors = KsefCertificateService::validateUpload($_FILES['certificate'], $password);
        $hasRealError = false;
        foreach ($errors as $err) {
            if (strpos($err, 'UWAGA:') === false) {
                $hasRealError = true;
                break;
            }
        }

        if ($hasRealError) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/client/ksef');
            return;
        }

        // Parse and store
        try {
            $pfxData = file_get_contents($_FILES['certificate']['tmp_name']);
            $certInfo = KsefCertificateService::parseCertificate($pfxData, $password);

            // Encrypt the PFX with our master key
            $encryptedPfx = KsefCertificateService::encryptCertificate($pfxData);

            // Clear raw data from memory
            $pfxData = str_repeat("\0", strlen($pfxData));

            KsefConfig::upsert($clientId, [
                'auth_method' => 'certificate',
                'is_active' => 1,
                'cert_pfx_encrypted' => $encryptedPfx,
                'cert_fingerprint' => $certInfo['fingerprint'],
                'cert_subject_cn' => $certInfo['subject_cn'],
                'cert_subject_nip' => $certInfo['subject_nip'],
                'cert_issuer' => $certInfo['issuer'],
                'cert_valid_from' => $certInfo['valid_from'],
                'cert_valid_to' => $certInfo['valid_to'],
                'cert_type' => $certInfo['cert_type'],
                'cert_serial_number' => $certInfo['serial_number'],
                'ksef_environment' => $environment,
                'ksef_context_nip' => $certInfo['subject_nip'] ?: $client['nip'],
                'configured_by_type' => 'client',
                'configured_by_id' => $clientId,
            ]);

            // Also mark client as ksef_enabled
            Client::update($clientId, ['ksef_enabled' => 1]);

            AuditLog::log('client', $clientId, 'ksef_cert_uploaded', "Fingerprint: {$certInfo['fingerprint']}, Type: {$certInfo['cert_type']}", 'client', $clientId);
            Session::flash('success', 'ksef_cert_uploaded');
        } catch (\Exception $e) {
            Session::flash('error', 'ksef_cert_error: ' . $e->getMessage());
        }

        $this->redirect('/client/ksef');
    }

    public function ksefUploadPem(): void
    {
        ModuleAccess::requireModule('ksef');
        if (!$this->validateCsrf()) { $this->redirect('/client/ksef'); return; }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        $existingConfig = KsefConfig::findByClientId($clientId);

        if (!Setting::get('ksef_cert_upload_enabled', '1')) {
            Session::flash('error', 'ksef_cert_upload_disabled');
            $this->redirect('/client/ksef');
            return;
        }

        if (!isset($_FILES['cert_crt']) || $_FILES['cert_crt']['error'] !== UPLOAD_ERR_OK
            || !isset($_FILES['cert_key']) || $_FILES['cert_key']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'ksef_pem_missing_files');
            $this->redirect('/client/ksef');
            return;
        }

        // Validate file types and sizes
        $errors = KsefCertificateService::validatePemUpload($_FILES['cert_crt'], $_FILES['cert_key']);
        if (!empty($errors)) {
            Session::flash('error', implode(' ', $errors));
            $this->redirect('/client/ksef');
            return;
        }

        $password = !empty($_POST['cert_password']) ? $_POST['cert_password'] : null;

        try {
            $certPem = file_get_contents($_FILES['cert_crt']['tmp_name']);
            $keyPem = file_get_contents($_FILES['cert_key']['tmp_name']);

            if (!$certPem || !$keyPem) {
                throw new \RuntimeException('Nie można odczytać przesłanych plików.');
            }

            // Parse and validate cert+key pair
            $certInfo = KsefCertificateService::parsePemCertificate($certPem, $keyPem, $password);

            // Export private key without password before encrypting for storage
            // (the key may be password-protected, but we store it encrypted with AES-256-GCM)
            $privKeyResource = openssl_pkey_get_private($keyPem, $password ?? '');
            $unprotectedKeyPem = '';
            openssl_pkey_export($privKeyResource, $unprotectedKeyPem);

            // Encrypt the unprotected key for storage
            $encryptedKey = KsefCertificateService::encrypt($unprotectedKeyPem);

            // Clear sensitive data from memory
            $unprotectedKeyPem = str_repeat("\0", strlen($unprotectedKeyPem));

            // Clear raw key from memory
            $keyPem = str_repeat("\0", strlen($keyPem));

            KsefConfig::upsert($clientId, [
                'auth_method' => 'ksef_cert',
                'is_active' => 1,
                'cert_ksef_pem' => $certPem,
                'cert_ksef_private_key_encrypted' => $encryptedKey,
                'cert_ksef_status' => 'active',
                'cert_ksef_name' => $certInfo['subject_cn'],
                'cert_ksef_serial_number' => $certInfo['serial_number'],
                'cert_ksef_valid_from' => $certInfo['valid_from'],
                'cert_ksef_valid_to' => $certInfo['valid_to'],
                'cert_ksef_type' => $certInfo['cert_type'],
                'cert_fingerprint' => $certInfo['fingerprint'],
                'cert_subject_cn' => $certInfo['subject_cn'],
                'cert_subject_nip' => $certInfo['subject_nip'],
                'cert_issuer' => $certInfo['issuer'],
                'cert_valid_from' => $certInfo['valid_from'],
                'cert_valid_to' => $certInfo['valid_to'],
                'cert_type' => $certInfo['cert_type'],
                'cert_serial_number' => $certInfo['serial_number'],
                'ksef_environment' => $existingConfig['ksef_environment'] ?? 'test',
                'ksef_context_nip' => $certInfo['subject_nip'] ?: $client['nip'],
                'configured_by_type' => 'client',
                'configured_by_id' => $clientId,
            ]);

            Client::update($clientId, ['ksef_enabled' => 1]);

            AuditLog::log('client', $clientId, 'ksef_pem_cert_uploaded', "PEM cert uploaded. Fingerprint: {$certInfo['fingerprint']}, Type: {$certInfo['cert_type']}", 'client', $clientId);
            Session::flash('success', 'ksef_cert_uploaded');
        } catch (\Exception $e) {
            Session::flash('error', 'ksef_cert_error: ' . $e->getMessage());
        }

        $this->redirect('/client/ksef');
    }

    public function ksefDeleteCert(): void
    {
        ModuleAccess::requireModule('ksef');
        if (!$this->validateCsrf()) { $this->redirect('/client/ksef'); return; }

        $clientId = Session::get('client_id');
        $config = KsefConfig::findByClientId($clientId);

        if ($config) {
            KsefConfig::update($clientId, [
                'auth_method' => 'none',
                'is_active' => 0,
                'cert_pfx_encrypted' => null,
                'cert_fingerprint' => null,
                'cert_subject_cn' => null,
                'cert_subject_nip' => null,
                'cert_issuer' => null,
                'cert_valid_from' => null,
                'cert_valid_to' => null,
                'cert_type' => null,
                'cert_serial_number' => null,
                'access_token' => null,
                'refresh_token' => null,
            ]);

            Client::update($clientId, ['ksef_enabled' => 0]);
            AuditLog::log('client', $clientId, 'ksef_cert_deleted', '', 'client', $clientId);
        }

        Session::flash('success', 'ksef_cert_deleted');
        $this->redirect('/client/ksef');
    }

    public function ksefSaveToken(): void
    {
        ModuleAccess::requireModule('ksef');
        if (!$this->validateCsrf()) { $this->redirect('/client/ksef'); return; }

        $clientId = Session::get('client_id');

        // Handle switching to KSeF cert as auth method
        if (!empty($_POST['use_ksef_cert'])) {
            $config = KsefConfig::findByClientId($clientId);
            if ($config && $config['cert_ksef_status'] === 'active' && !empty($config['cert_ksef_pem'])) {
                KsefConfig::update($clientId, [
                    'auth_method' => 'ksef_cert',
                    'is_active' => 1,
                    'access_token' => null,
                    'refresh_token' => null,
                ]);
                Client::update($clientId, ['ksef_enabled' => 1]);
                Session::flash('success', 'ksef_auth_method_changed');
            } else {
                Session::flash('error', 'ksef_no_active_ksef_cert');
            }
            $this->redirect('/client/ksef');
            return;
        }

        // Token auth removed — only certificate auth is supported
        Session::flash('error', 'ksef_token_auth_removed');
        $this->redirect('/client/ksef');
    }

    public function ksefSaveEnvironment(): void
    {
        ModuleAccess::requireModule('ksef');
        if (!$this->validateCsrf()) { $this->redirect('/client/ksef'); return; }

        // Only admin impersonating can change environment
        if (!Auth::isImpersonating()) {
            $this->redirect('/client/ksef');
            return;
        }

        $clientId = Session::get('client_id');

        $env = $_POST['ksef_environment'] ?? 'test';
        if (!in_array($env, ['test', 'demo', 'production'])) {
            $env = 'test';
        }

        $config = KsefConfig::findByClientId($clientId);
        if ($config) {
            KsefConfig::update($clientId, ['ksef_environment' => $env]);
        } else {
            KsefConfig::upsert($clientId, ['ksef_environment' => $env]);
        }

        Session::flash('success', 'settings_updated');
        $this->redirect('/client/ksef');
    }

    public function ksefToggleUpo(): void
    {
        ModuleAccess::requireModule('ksef');
        if (!$this->validateCsrf()) { $this->redirect('/client/ksef'); return; }
        if (!Auth::isImpersonating()) { $this->redirect('/client/ksef'); return; }
        $clientId = (int) Session::get('client_id');

        $upoEnabled = isset($_POST['upo_enabled']) ? 1 : 0;
        KsefConfig::upsert($clientId, ['upo_enabled' => $upoEnabled]);

        Session::flash('success', 'settings_updated');
        $this->redirect('/client/ksef');
    }

    public function ksefTestConnection(): void
    {
        ModuleAccess::requireModule('ksef');
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);

        $ksef = KsefApiService::forClient($client);
        $ksef->enableLogging();
        $ksef->setPerformer('client', $clientId);

        $result = $ksef->testConnection();

        $this->render('client/ksef_test', [
            'result' => $result,
            'authMethod' => $ksef->getAuthMethod(),
        ]);
    }

    /**
     * Full KSeF diagnostic: connectivity + auth + fetch invoices + full log output.
     */
    public function ksefDiagnostic(): void
    {
        ModuleAccess::requireModule('ksef');
        // Increase time limit for this diagnostic page
        set_time_limit(120);

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        $config = KsefConfig::findByClientId($clientId);

        $ksef = KsefApiService::forClient($client);
        $ksef->enableLogging();
        $ksef->setPerformer('client', $clientId);

        $steps = [];
        $month = (int) ($_GET['month'] ?? date('n'));
        $year = (int) ($_GET['year'] ?? date('Y'));

        // Step 1: Configuration check
        $steps[] = [
            'name' => 'Konfiguracja',
            'ok' => $ksef->isConfigured(),
            'details' => [
                'auth_method' => $config['auth_method'] ?? 'brak',
                'environment' => $ksef->getEnvironment(),
                'api_url' => $ksef->getApiUrl(),
                'nip' => $client['nip'] ?? 'brak',
                'ksef_context_nip' => $config['ksef_context_nip'] ?? $client['nip'] ?? 'brak',
                'is_active' => ($config['is_active'] ?? 0) ? 'TAK' : 'NIE',
                'cert_fingerprint' => $config['cert_fingerprint'] ?? 'brak',
                'cert_valid_to' => $config['cert_valid_to'] ?? $config['cert_ksef_valid_to'] ?? 'brak',
            ],
        ];

        // Step 2: Connectivity (challenge)
        $connectOk = false;
        try {
            $testResult = $ksef->testConnection();
            $connectOk = $testResult['success'] ?? false;
            $steps[] = [
                'name' => 'Połączenie z API (challenge)',
                'ok' => $connectOk,
                'details' => $testResult,
            ];
        } catch (\Throwable $e) {
            $steps[] = [
                'name' => 'Połączenie z API (challenge)',
                'ok' => false,
                'details' => ['error' => $e->getMessage()],
            ];
        }

        // Step 3: Full authentication
        $authOk = false;
        if ($connectOk) {
            try {
                $authOk = $ksef->authenticate();
                $steps[] = [
                    'name' => 'Autentykacja (' . ($config['auth_method'] ?? 'token') . ')',
                    'ok' => $authOk,
                    'details' => $authOk ? ['status' => 'Zalogowano pomyślnie'] : ['error' => 'Autentykacja nie powiodła się — sprawdź log poniżej'],
                ];
            } catch (\Throwable $e) {
                $steps[] = [
                    'name' => 'Autentykacja',
                    'ok' => false,
                    'details' => ['error' => $e->getMessage()],
                ];
            }
        }

        // Step 4: Try fetching invoices
        $invoiceCount = 0;
        if ($authOk) {
            $nip = $config['ksef_context_nip'] ?? $client['nip'];
            $dateFrom = sprintf('%04d-%02d-01', $year, $month);
            $dateTo = sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));

            try {
                $invoices = $ksef->fetchInvoices($nip, $dateFrom, $dateTo, 'Subject2');
                $invoiceCount = count($invoices);
                $steps[] = [
                    'name' => "Pobieranie faktur (kupujący, {$dateFrom} — {$dateTo})",
                    'ok' => true,
                    'details' => [
                        'znaleziono_faktur' => $invoiceCount,
                        'pierwsze_3' => array_map(function($inv) {
                            return ($inv['invoice_number'] ?? '?') . ' | ' . ($inv['seller_name'] ?? '?') . ' | ' . ($inv['gross_amount'] ?? '?') . ' ' . ($inv['currency'] ?? 'PLN');
                        }, array_slice($invoices, 0, 3)),
                    ],
                ];

                // Also try as seller
                $invoicesSeller = $ksef->fetchInvoices($nip, $dateFrom, $dateTo, 'Subject1');
                $steps[] = [
                    'name' => "Pobieranie faktur (sprzedawca, {$dateFrom} — {$dateTo})",
                    'ok' => true,
                    'details' => ['znaleziono_faktur' => count($invoicesSeller)],
                ];
            } catch (\Throwable $e) {
                $steps[] = [
                    'name' => 'Pobieranie faktur',
                    'ok' => false,
                    'details' => ['error' => $e->getMessage()],
                ];
            }
        }

        // Read the full log
        $logger = $ksef->getLogger();
        $logContent = null;
        if ($logger) {
            $sessionId = $logger->getSessionId();
            $logContent = KsefLogger::readSession($sessionId);
        }

        $this->render('client/ksef_diagnostic', [
            'steps' => $steps,
            'logContent' => $logContent,
            'month' => $month,
            'year' => $year,
            'client' => $client,
        ]);
    }

    // ─── KSeF Certificate Enrollment ──────────────────

    /**
     * Certificate enrollment disabled for clients.
     * Clients must generate certificates themselves via the KSeF system.
     */
    public function ksefEnrollCert(): void
    {
        Session::flash('error', 'ksef_enrollment_disabled');
        $this->redirect('/client/ksef');
        return;

        $certName = trim($_POST['cert_name'] ?? ('BiLLU-' . $client['company_name']));
        $certName = substr($certName, 0, 100);

        $ksef = KsefApiService::forClient($client);
        if (!$ksef->isConfigured()) {
            Session::flash('error', 'ksef_enroll_auth_required');
            $this->redirect('/client/ksef');
            return;
        }

        $ksef->enableLogging();
        $ksef->setPerformer('client', $clientId);

        try {
            $result = $ksef->enrollKsefCertificate($certName, 'ec');

            if (!$result) {
                Session::flash('error', 'ksef_enrollment_failed');
                $this->redirect('/client/ksef');
                return;
            }

            // Store enrollment data
            KsefConfig::update($clientId, [
                'cert_ksef_private_key_encrypted' => $result['encryptedPrivateKey'],
                'cert_ksef_name' => $certName,
                'cert_ksef_status' => 'enrolling',
                'cert_ksef_enrollment_ref' => $result['referenceNumber'],
                'cert_ksef_type' => 'Authentication',
            ]);

            AuditLog::log('client', $clientId, 'ksef_cert_enrollment_started',
                "Name: {$certName}, Ref: {$result['referenceNumber']}", 'client', $clientId);
            Session::flash('success', 'ksef_enrollment_started');
        } catch (\Exception $e) {
            error_log("KSeF enrollment error: " . $e->getMessage());
            Session::flash('error', 'ksef_enrollment_error: ' . $e->getMessage());
        }

        $this->redirect('/client/ksef');
    }

    /**
     * Certificate enrollment disabled for clients.
     * Clients must generate certificates themselves via the KSeF system.
     */
    public function ksefCheckEnrollment(): void
    {
        Session::flash('error', 'ksef_enrollment_disabled');
        $this->redirect('/client/ksef');
        return;

        $ksef->enableLogging();
        $ksef->setPerformer('client', $clientId);

        try {
            $status = $ksef->getCertificateEnrollmentStatus($config['cert_ksef_enrollment_ref']);

            if (!$status) {
                Session::flash('error', 'ksef_enrollment_status_error');
                $this->redirect('/client/ksef');
                return;
            }

            $enrollmentStatus = $status['status'] ?? $status['processingCode'] ?? 'unknown';
            $serialNumber = $status['certificateSerialNumber'] ?? null;

            if ($serialNumber) {
                // Certificate issued! Retrieve it.
                $certs = $ksef->retrieveCertificates([$serialNumber]);
                $certPem = null;
                if ($certs && !empty($certs['certificates'])) {
                    $certData = $certs['certificates'][0];
                    // Certificate is Base64-encoded DER - wrap in PEM
                    $certDer64 = $certData['certificate'] ?? '';
                    $certPem = "-----BEGIN CERTIFICATE-----\n"
                        . chunk_split($certDer64, 64, "\n")
                        . "-----END CERTIFICATE-----";

                    // Parse certificate info
                    $certInfo = KsefCertificateService::parseCertPem($certPem);

                    KsefConfig::update($clientId, [
                        'auth_method' => 'ksef_cert',
                        'is_active' => 1,
                        'cert_ksef_pem' => $certPem,
                        'cert_ksef_serial_number' => $serialNumber,
                        'cert_ksef_valid_from' => $certInfo['valid_from'],
                        'cert_ksef_valid_to' => $certInfo['valid_to'],
                        'cert_ksef_status' => 'active',
                        'cert_ksef_enrollment_ref' => null,
                        'configured_by_type' => 'client',
                        'configured_by_id' => $clientId,
                    ]);

                    Client::update($clientId, ['ksef_enabled' => 1]);
                    AuditLog::log('client', $clientId, 'ksef_cert_enrolled',
                        "Serial: {$serialNumber}, Name: {$config['cert_ksef_name']}", 'client', $clientId);
                    Session::flash('success', 'ksef_enrollment_completed');
                } else {
                    Session::flash('info', 'ksef_enrollment_cert_retrieval_failed');
                }
            } else {
                // Still processing
                Session::flash('info', 'ksef_enrollment_in_progress');
            }
        } catch (\Exception $e) {
            error_log("KSeF enrollment check error: " . $e->getMessage());
            Session::flash('error', 'ksef_enrollment_check_error: ' . $e->getMessage());
        }

        $this->redirect('/client/ksef');
    }

    /**
     * Delete/revoke KSeF certificate.
     */
    public function ksefDeleteKsefCert(): void
    {
        ModuleAccess::requireModule('ksef');
        if (!$this->validateCsrf()) { $this->redirect('/client/ksef'); return; }

        $clientId = Session::get('client_id');
        $config = KsefConfig::findByClientId($clientId);

        if ($config && !empty($config['cert_ksef_serial_number'])) {
            // Try to revoke on KSeF side
            $client = Client::findById($clientId);
            $ksef = KsefApiService::forClient($client);
            if ($ksef->isConfigured()) {
                $ksef->enableLogging();
                $ksef->setPerformer('client', $clientId);
                $ksef->revokeCertificate($config['cert_ksef_serial_number']);
            }
        }

        if ($config) {
            $updateData = [
                'cert_ksef_private_key_encrypted' => null,
                'cert_ksef_pem' => null,
                'cert_ksef_serial_number' => null,
                'cert_ksef_name' => null,
                'cert_ksef_valid_from' => null,
                'cert_ksef_valid_to' => null,
                'cert_ksef_status' => 'none',
                'cert_ksef_enrollment_ref' => null,
            ];

            // If this was the active auth method, disable KSeF
            if ($config['auth_method'] === 'ksef_cert') {
                $updateData['auth_method'] = 'none';
                $updateData['is_active'] = 0;
                $updateData['access_token'] = null;
                $updateData['refresh_token'] = null;
                Client::update($clientId, ['ksef_enabled' => 0]);
            }

            KsefConfig::update($clientId, $updateData);
            AuditLog::log('client', $clientId, 'ksef_cert_ksef_deleted', '', 'client', $clientId);
        }

        Session::flash('success', 'ksef_cert_deleted');
        $this->redirect('/client/ksef');
    }

    /**
     * View KSeF certificates list.
     */
    public function ksefCertificates(): void
    {
        ModuleAccess::requireModule('ksef');
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);

        $ksef = KsefApiService::forClient($client);
        $certs = null;

        if ($ksef->isConfigured()) {
            $ksef->enableLogging();
            $ksef->setPerformer('client', $clientId);
            $certs = $ksef->queryCertificates(['pageSize' => 50, 'pageOffset' => 0]);
        }

        $this->render('client/ksef_certificates', [
            'certificates' => $certs,
            'config' => KsefConfig::findByClientId($clientId),
        ]);
    }

    public function switchLanguage(): void
    {
        $lang = $_GET['lang'] ?? 'pl';
        if (!in_array($lang, ['pl', 'en'])) $lang = 'pl';

        $clientId = Session::get('client_id');
        Client::update($clientId, ['language' => $lang]);
        Session::set('client_language', $lang);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/client';
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $this->redirect(($refererHost && $refererHost !== $currentHost) ? '/client' : $referer);
    }

    // ── RODO Data Export ──────────────────────────────

    public function rodoExport(): void
    {
        if (!Auth::isClient()) { $this->redirect('/login'); return; }

        $clientId = Session::get('client_id');
        if (!$clientId) { $this->redirect('/login'); return; }

        $zipPath = \App\Services\RodoExportService::exportClientData($clientId);

        if (!$zipPath || !file_exists($zipPath)) {
            Session::flash('error', 'export_failed');
            $this->redirect('/client');
            return;
        }

        $client = Client::findById($clientId);
        $filename = 'eksport_danych_' . ($client['nip'] ?? 'klient') . '_' . date('Ymd') . '.zip';

        AuditLog::log('client', $clientId, 'rodo_data_export', 'Client requested RODO data export', 'client', $clientId);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($zipPath);

        // Clean up temp file
        unlink($zipPath);
        exit;
    }

    // ── Notifications ──────────────────────────────

    public function notifications(): void
    {
        $clientId = Session::get('client_id');
        if ($_GET['format'] ?? '' === 'json') {
            $notifications = Notification::getUnread('client', $clientId);
            $this->json(['notifications' => $notifications, 'count' => count($notifications)]);
            return;
        }
        $notifications = Notification::getAll('client', $clientId, 50);
        $this->render('client/notifications', ['notifications' => $notifications]);
    }

    public function notificationsMarkRead(): void
    {
        $clientId = Session::get('client_id');
        $id = $this->sanitizeInt($_POST['id'] ?? 0);
        if ($id > 0) {
            Notification::markAsRead($id, 'client', $clientId);
        } else {
            Notification::markAllAsRead('client', $clientId);
        }
        if ($_POST['ajax'] ?? false) {
            $this->json(['ok' => true]);
            return;
        }
        $this->redirect('/client/notifications');
    }

    public function security(): void
    {
        Auth::requireClient();
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);

        $db = \App\Core\Database::getInstance();
        $setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'password_expiry_days'");
        $expiryDays = $setting ? (int) $setting['setting_value'] : 90;

        $passwordChangedAt = $client['password_changed_at'] ?? date('Y-m-d H:i:s');
        $expiryDate = (new \DateTime($passwordChangedAt))->modify("+{$expiryDays} days");
        $now = new \DateTime();
        $daysLeft = max(0, (int) $now->diff($expiryDate)->format('%r%a'));

        $this->render('client/security', [
            'twoFactorEnabled' => !empty($client['two_factor_enabled']),
            'twoFactorAllowed' => Auth::is2faEnabled(),
            'passwordChangedAt' => $passwordChangedAt,
            'passwordExpiryDate' => $expiryDate->format('Y-m-d'),
            'passwordDaysLeft' => $daysLeft,
        ]);
    }

    // ── Account Deletion (RODO Art. 17) ───────────

    public function accountDelete(): void
    {
        Auth::requireClient();

        if (!$this->validateCsrf()) {
            $this->redirect('/client/security');
            return;
        }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);

        if (!$client) {
            $this->redirect('/login');
            return;
        }

        // Validate confirmation text
        $confirmText = trim($_POST['confirm_text'] ?? '');
        if ($confirmText !== 'USUN') {
            Session::flash('error', 'rodo_delete_confirm_mismatch');
            $this->redirect('/client/security');
            return;
        }

        // Validate password
        $password = $_POST['password'] ?? '';
        if (!password_verify($password, $client['password_hash'] ?? '')) {
            Session::flash('error', 'invalid_password');
            $this->redirect('/client/security');
            return;
        }

        // Perform deletion
        $result = \App\Services\RodoDeleteService::deleteClientData($clientId, 'client', $clientId);

        if (!$result['success']) {
            Session::flash('error', 'rodo_delete_failed');
            $this->redirect('/client/security');
            return;
        }

        // Destroy session and redirect to login
        Session::destroy();
        Session::start();
        Session::flash('success', 'rodo_account_deleted');
        $this->redirect('/login');
    }

    // ── Bulk MPK Assignment ────────────────────────

    public function bulkAssignCostCenter(): void
    {
        if (!$this->validateCsrf()) { $this->redirect('/client'); return; }

        $clientId = Session::get('client_id');
        $batchId = $this->sanitizeInt($_POST['batch_id'] ?? 0);
        $costCenterId = $this->sanitizeInt($_POST['cost_center_id'] ?? 0);
        $invoiceIds = $_POST['invoice_ids'] ?? [];

        $batch = InvoiceBatch::findById($batchId);
        if (!$batch || (int) $batch['client_id'] !== $clientId) {
            $this->redirect('/client');
            return;
        }

        if (!$costCenterId || empty($invoiceIds)) {
            Session::flash('error', 'bulk_mpk_missing');
            $this->redirect("/client/invoices/{$batchId}");
            return;
        }

        $cc = ClientCostCenter::findById($costCenterId);
        if (!$cc || (int) $cc['client_id'] !== $clientId) {
            Session::flash('error', 'access_denied');
            $this->redirect("/client/invoices/{$batchId}");
            return;
        }

        // Filter to only invoices that belong to this client
        $validIds = [];
        foreach ($invoiceIds as $id) {
            $inv = Invoice::findById((int) $id);
            if ($inv && (int) $inv['client_id'] === $clientId) {
                $validIds[] = (int) $id;
            }
        }

        $count = Invoice::bulkUpdateCostCenter($validIds, $costCenterId, $cc['name']);

        AuditLog::log('client', $clientId, 'bulk_mpk_assigned', "MPK: {$cc['name']}, Invoices: {$count}", 'batch', $batchId);
        Session::flash('success', 'bulk_mpk_success');
        $this->redirect("/client/invoices/{$batchId}");
    }

    // ── Invoice Detail (AJAX) ────────────────────────────

    public function getInvoiceDetail(): void
    {
        Auth::requireClient();
        $clientId = Session::get('client_id');
        $invoiceId = (int) ($_GET['invoice_id'] ?? 0);

        $invoice = Invoice::findById($invoiceId);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            $this->json(['error' => 'access_denied'], 403);
            return;
        }

        $batch = InvoiceBatch::findById($invoice['batch_id']);
        $client = Client::findById($clientId);

        // Decode JSON fields
        $lineItems = $invoice['line_items'] ?? null;
        if (is_string($lineItems)) {
            $lineItems = json_decode($lineItems, true);
        }
        $vatDetails = $invoice['vat_details'] ?? null;
        if (is_string($vatDetails)) {
            $vatDetails = json_decode($vatDetails, true);
        }

        // Comments
        $comments = \App\Models\InvoiceComment::findByInvoice($invoiceId);
        foreach ($comments as &$c) {
            $c['user_name'] = \App\Models\InvoiceComment::getUserName($c['user_type'], (int) $c['user_id']);
        }

        // Cost centers
        $costCenters = [];
        if (!empty($client['has_cost_centers'])) {
            $costCenters = \App\Models\ClientCostCenter::findByClient($clientId, true);
        }

        $this->json([
            'id' => (int) $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'ksef_reference_number' => $invoice['ksef_reference_number'] ?? '',
            'issue_date' => $invoice['issue_date'],
            'sale_date' => $invoice['sale_date'] ?? '',
            'seller_name' => $invoice['seller_name'],
            'seller_nip' => $invoice['seller_nip'],
            'seller_address' => $invoice['seller_address'] ?? '',
            'buyer_name' => $invoice['buyer_name'] ?? $client['name'] ?? '',
            'buyer_nip' => $invoice['buyer_nip'] ?? $client['nip'] ?? '',
            'buyer_address' => $invoice['buyer_address'] ?? $client['address'] ?? '',
            'net_amount' => $invoice['net_amount'],
            'vat_amount' => $invoice['vat_amount'],
            'gross_amount' => $invoice['gross_amount'],
            'currency' => $invoice['currency'] ?? 'PLN',
            'line_items' => $lineItems,
            'vat_details' => $vatDetails,
            'status' => $invoice['status'],
            'comment' => $invoice['comment'] ?? '',
            'cost_center' => $invoice['cost_center'] ?? '',
            'cost_center_id' => $invoice['cost_center_id'] ? (int) $invoice['cost_center_id'] : null,
            'notes' => $invoice['notes'] ?? $invoice['description'] ?? '',
            'amount_to_pay' => $invoice['amount_to_pay'] ?? '',
            'is_paid' => (int) ($invoice['is_paid'] ?? 0),
            'whitelist_failed' => (int) ($invoice['whitelist_failed'] ?? 0),
            'due_date' => $invoice['payment_due_date'] ?? $invoice['due_date'] ?? '',
            'payment_method_detected' => $invoice['payment_method_detected'] ?? '',
            'invoice_type' => $invoice['invoice_type'] ?? 'VAT',
            'corrected_invoice_number' => $invoice['corrected_invoice_number'] ?? '',
            'corrected_invoice_date' => $invoice['corrected_invoice_date'] ?? '',
            'corrected_ksef_number' => $invoice['corrected_ksef_number'] ?? '',
            'correction_reason' => $invoice['correction_reason'] ?? '',
            'is_finalized' => (bool) ($batch['is_finalized'] ?? false),
            'comments' => $comments,
            'cost_centers' => $costCenters,
        ]);
    }

    // ── Invoice Visualization ────────────────────────────

    public function getInvoiceVisualization(): void
    {
        Auth::requireClient();
        $clientId = Session::get('client_id');
        $invoiceId = (int) ($_GET['invoice_id'] ?? 0);

        $invoice = Invoice::findById($invoiceId);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            http_response_code(403);
            echo 'Brak dostępu';
            return;
        }

        $xml = $invoice['ksef_xml'] ?? null;

        // If no cached XML and we have a KSeF reference, try to download
        if (!$xml && !empty($invoice['ksef_reference_number'])) {
            $client = \App\Models\Client::findById($clientId);

            if ($client) {
                try {
                    $ksefService = \App\Services\KsefApiService::forClient($client);
                    $xml = $ksefService->downloadInvoiceRaw($invoice['ksef_reference_number']);

                    if ($xml) {
                        // Cache the XML
                        Invoice::updateFields($invoiceId, ['ksef_xml' => $xml]);

                        // Also enrich invoice data from XML
                        $parsed = \App\Services\KsefApiService::parseKsefFaXml($xml);
                        $enrichData = [];
                        if (!empty($parsed['line_items'])) {
                            $enrichData['line_items'] = json_encode($parsed['line_items']);
                        }
                        if (!empty($parsed['invoice']['sale_date_from']) && empty($invoice['sale_date'])) {
                            $enrichData['sale_date'] = $parsed['invoice']['sale_date_from'];
                        }
                        if (!empty($parsed['payment']['due_date']) && empty($invoice['payment_due_date'])) {
                            $enrichData['payment_due_date'] = $parsed['payment']['due_date'];
                        }
                        if (!empty($parsed['payment']['form_name']) && empty($invoice['payment_method_detected'])) {
                            $enrichData['payment_method_detected'] = $parsed['payment']['form_name'];
                        }
                        if (!empty($parsed['seller']['address_l1']) && empty($invoice['seller_address'])) {
                            $addr = $parsed['seller']['address_l1'];
                            if (!empty($parsed['seller']['address_l2'])) $addr .= ', ' . $parsed['seller']['address_l2'];
                            $enrichData['seller_address'] = $addr;
                        }
                        if (!empty($parsed['buyer']['address_l1']) && empty($invoice['buyer_address'])) {
                            $addr = $parsed['buyer']['address_l1'];
                            if (!empty($parsed['buyer']['address_l2'])) $addr .= ', ' . $parsed['buyer']['address_l2'];
                            $enrichData['buyer_address'] = $addr;
                        }
                        if (!empty($parsed['vat_rates'])) {
                            $enrichData['vat_details'] = json_encode($parsed['vat_rates']);
                        }
                        if (!empty($enrichData)) {
                            Invoice::updateFields($invoiceId, $enrichData);
                        }
                    }
                } catch (\Exception $e) {
                    // Log but continue - we'll show what we have
                }
            }
        }

        if (!$xml) {
            // No XML available — render visualization from DB fields
            $this->renderVisualizationFromDb($invoice);
            return;
        }

        $parsed = \App\Services\KsefApiService::parseKsefFaXml($xml);
        $this->renderVisualizationHtml($parsed, $invoice);
    }

    private function renderVisualizationHtml(array $parsed, array $invoice): void
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $m = function ($v) {
            $n = (float) $v;
            return number_format($n, 2, ',', ' ');
        };

        $seller = $parsed['seller'] ?? [];
        $buyer = $parsed['buyer'] ?? [];
        $inv = $parsed['invoice'] ?? [];
        $payment = $parsed['payment'] ?? [];
        $items = $parsed['line_items'] ?? [];
        $notes = $parsed['notes'] ?? '';
        $annotations = $parsed['annotations'] ?? [];
        $ksefRef = $invoice['ksef_reference_number'] ?? '';

        $saleDate = $inv['sale_date_from'] ?? '';
        if (!empty($inv['sale_date_to']) && $inv['sale_date_to'] !== $saleDate) {
            $saleDate .= ' - ' . $inv['sale_date_to'];
        }

        $annotationTexts = [];
        if (!empty($annotations['split_payment'])) $annotationTexts[] = 'Mechanizm podzielonej płatności';
        if (!empty($annotations['reverse_charge'])) $annotationTexts[] = 'Odwrotne obciążenie';
        if (!empty($annotations['self_invoicing'])) $annotationTexts[] = 'Samofakturowanie';
        if (!empty($annotations['margin'])) $annotationTexts[] = 'Procedura marży';

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Faktura ' . $e($inv['invoice_number'] ?? $invoice['invoice_number']) . '</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:13px;color:#333;background:#f5f5f5;padding:20px}
.invoice-page{max-width:800px;margin:0 auto;background:#fff;padding:40px;box-shadow:0 2px 10px rgba(0,0,0,.1);border-radius:4px}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #2563eb}
.header h1{font-size:22px;color:#2563eb}
.header .meta{text-align:right;font-size:12px;color:#666}
.ksef-ref{background:#e0f2fe;padding:6px 12px;border-radius:4px;font-size:11px;color:#0369a1;margin-bottom:16px;word-break:break-all}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px}
.party{padding:12px;border:1px solid #e5e7eb;border-radius:6px}
.party h3{font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:.5px;margin-bottom:6px}
.party .name{font-weight:700;font-size:14px;margin-bottom:4px}
.party .nip{color:#2563eb;font-size:12px;margin-bottom:4px}
.dates{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.date-box{padding:8px 12px;background:#f9fafb;border-radius:4px;text-align:center}
.date-box .label{font-size:10px;text-transform:uppercase;color:#6b7280;letter-spacing:.5px}
.date-box .value{font-weight:600;font-size:14px;margin-top:2px}
table.items{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:12px}
table.items th{background:#f3f4f6;padding:8px 6px;text-align:left;font-weight:600;font-size:11px;text-transform:uppercase;color:#4b5563;border-bottom:2px solid #e5e7eb}
table.items td{padding:7px 6px;border-bottom:1px solid #f3f4f6}
table.items tr:hover td{background:#fafafa}
.text-right{text-align:right}
.totals{display:flex;justify-content:flex-end;margin-bottom:20px}
.totals table{border-collapse:collapse;min-width:280px}
.totals td{padding:6px 12px;font-size:13px}
.totals tr.grand td{font-weight:700;font-size:16px;border-top:2px solid #333;padding-top:8px}
.payment-box{background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;border-radius:6px;margin-bottom:16px;font-size:12px}
.payment-box h3{font-size:11px;text-transform:uppercase;color:#166534;margin-bottom:6px}
.payment-box .bank-nr{font-family:monospace;font-size:13px;letter-spacing:1px;color:#166534}
.notes-box{background:#fffbeb;border:1px solid #fde68a;padding:12px;border-radius:6px;margin-bottom:16px;font-size:12px}
.annotations{margin-bottom:16px;font-size:12px}
.annotation-badge{display:inline-block;padding:3px 8px;background:#fef3c7;border:1px solid #f59e0b;border-radius:3px;font-size:11px;margin-right:4px;color:#92400e}
.footer{text-align:center;color:#9ca3af;font-size:10px;padding-top:16px;border-top:1px solid #e5e7eb}
.vat-summary{margin-bottom:16px}
.vat-summary table{width:100%;border-collapse:collapse;font-size:12px}
.vat-summary th{background:#f3f4f6;padding:6px;text-align:right;font-size:11px;border-bottom:1px solid #e5e7eb}
.vat-summary td{padding:6px;text-align:right;border-bottom:1px solid #f3f4f6}
.print-btn{position:fixed;top:16px;right:16px;padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;z-index:100}
.print-btn:hover{background:#1d4ed8}
@media print{.print-btn{display:none}body{background:#fff;padding:0}.invoice-page{box-shadow:none;padding:20px}}
</style></head><body>
<button class="print-btn" onclick="window.print()">Drukuj</button>
<div class="invoice-page">';

        // Header
        echo '<div class="header"><div><h1>Faktura ' . $e($inv['invoice_number'] ?? $invoice['invoice_number']) . '</h1>';
        if (!empty($inv['invoice_type'])) echo '<div style="font-size:12px;color:#666;">Typ: ' . $e($inv['invoice_type']) . '</div>';
        echo '</div><div class="meta">';
        if (!empty($inv['issue_place'])) echo 'Miejsce wystawienia: ' . $e($inv['issue_place']) . '<br>';
        echo 'Waluta: ' . $e($inv['currency'] ?? 'PLN') . '</div></div>';

        // KSeF reference
        if ($ksefRef) {
            echo '<div class="ksef-ref">Nr KSeF: <strong>' . $e($ksefRef) . '</strong></div>';
        }

        // Dates
        echo '<div class="dates">';
        echo '<div class="date-box"><div class="label">Data wystawienia</div><div class="value">' . $e($inv['issue_date'] ?? $invoice['issue_date']) . '</div></div>';
        echo '<div class="date-box"><div class="label">Data sprzedaży</div><div class="value">' . $e($saleDate ?: $invoice['sale_date'] ?? '-') . '</div></div>';
        echo '<div class="date-box"><div class="label">Termin płatności</div><div class="value">' . $e($payment['due_date'] ?: $invoice['payment_due_date'] ?? '-') . '</div></div>';
        echo '</div>';

        // Parties
        echo '<div class="parties">';
        echo '<div class="party"><h3>Sprzedawca</h3>';
        echo '<div class="name">' . $e($seller['name'] ?: $invoice['seller_name']) . '</div>';
        if (!empty($seller['trade_name'])) echo '<div style="font-size:12px;color:#666;">' . $e($seller['trade_name']) . '</div>';
        echo '<div class="nip">NIP: ' . $e($seller['nip'] ?: $invoice['seller_nip']) . '</div>';
        $selAddr = trim(($seller['address_l1'] ?? '') . ' ' . ($seller['address_l2'] ?? ''));
        if ($selAddr) echo '<div>' . $e($selAddr) . '</div>';
        if (!empty($seller['email'])) echo '<div style="font-size:11px;">Email: ' . $e($seller['email']) . '</div>';
        if (!empty($seller['phone'])) echo '<div style="font-size:11px;">Tel: ' . $e($seller['phone']) . '</div>';
        echo '</div>';

        echo '<div class="party"><h3>Nabywca</h3>';
        echo '<div class="name">' . $e($buyer['name'] ?: $invoice['buyer_name'] ?? '') . '</div>';
        echo '<div class="nip">NIP: ' . $e($buyer['nip'] ?: $invoice['buyer_nip'] ?? '') . '</div>';
        $buyAddr = trim(($buyer['address_l1'] ?? '') . ' ' . ($buyer['address_l2'] ?? ''));
        if ($buyAddr) echo '<div>' . $e($buyAddr) . '</div>';
        echo '</div></div>';

        // Line items
        if (!empty($items)) {
            echo '<table class="items"><thead><tr>';
            echo '<th>Lp.</th><th>Nazwa towaru/usługi</th><th>PKWiU/CN</th><th class="text-right">Ilość</th><th>Jedn.</th>';
            echo '<th class="text-right">Cena netto</th><th class="text-right">Wartość netto</th><th class="text-right">Stawka VAT</th>';
            echo '</tr></thead><tbody>';
            foreach ($items as $idx => $item) {
                $pkwiu = $item['pkwiu'] ?: ($item['cn'] ?? '');
                echo '<tr>';
                echo '<td>' . ($item['lp'] ?: ($idx + 1)) . '</td>';
                echo '<td>' . $e($item['name']) . '</td>';
                echo '<td>' . $e($pkwiu) . '</td>';
                echo '<td class="text-right">' . $e($item['quantity']) . '</td>';
                echo '<td>' . $e($item['unit']) . '</td>';
                echo '<td class="text-right">' . ($item['unit_price_net'] ? $m($item['unit_price_net']) : '') . '</td>';
                echo '<td class="text-right">' . ($item['net_value'] ? $m($item['net_value']) : '') . '</td>';
                echo '<td class="text-right">' . $e($item['vat_rate']) . '%</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        // VAT summary
        if (!empty($parsed['vat_rates'])) {
            echo '<div class="vat-summary"><table><thead><tr><th>Stawka VAT</th><th>Netto</th><th>VAT</th></tr></thead><tbody>';
            foreach ($parsed['vat_rates'] as $vr) {
                echo '<tr><td>' . $e($vr['rate']) . '</td><td>' . $m($vr['net'] ?? 0) . '</td><td>' . $m($vr['vat'] ?? 0) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        // Totals
        $netTotal = $inv['net_amount'] ?: $invoice['net_amount'];
        $vatTotal = $inv['vat_amount'] ?: $invoice['vat_amount'];
        $grossTotal = $inv['gross_amount'] ?: $invoice['gross_amount'];
        $cur = $inv['currency'] ?? 'PLN';
        echo '<div class="totals"><table>';
        echo '<tr><td>Razem netto:</td><td class="text-right">' . $m($netTotal) . ' ' . $e($cur) . '</td></tr>';
        echo '<tr><td>Razem VAT:</td><td class="text-right">' . $m($vatTotal) . ' ' . $e($cur) . '</td></tr>';
        echo '<tr class="grand"><td>Do zapłaty:</td><td class="text-right">' . $m($grossTotal) . ' ' . $e($cur) . '</td></tr>';
        echo '</table></div>';

        // Annotations
        if (!empty($annotationTexts)) {
            echo '<div class="annotations">';
            foreach ($annotationTexts as $at) {
                echo '<span class="annotation-badge">' . $e($at) . '</span>';
            }
            echo '</div>';
        }

        // Payment
        if (!empty($payment['form_name']) || !empty($payment['bank_account'])) {
            echo '<div class="payment-box"><h3>Płatność</h3>';
            if (!empty($payment['form_name'])) echo '<div>Forma: <strong>' . $e($payment['form_name']) . '</strong></div>';
            if (!empty($payment['bank_account'])) echo '<div>Nr rachunku: <span class="bank-nr">' . $e($payment['bank_account']) . '</span></div>';
            if (!empty($payment['bank_name'])) echo '<div>Bank: ' . $e($payment['bank_name']) . '</div>';
            if (!empty($payment['description'])) echo '<div>Opis: ' . $e($payment['description']) . '</div>';
            echo '</div>';
        }

        // Notes
        if ($notes) {
            echo '<div class="notes-box"><strong>Uwagi:</strong> ' . $e($notes) . '</div>';
        }
        if (!empty($parsed['additional_info'])) {
            echo '<div class="notes-box"><strong>Informacje dodatkowe:</strong> ' . $e($parsed['additional_info']) . '</div>';
        }

        echo '<div class="footer">Wizualizacja faktury z KSeF &mdash; BiLLU</div>';
        echo '</div></body></html>';
    }

    private function renderVisualizationFromDb(array $invoice): void
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $m = function ($v) {
            return number_format((float) $v, 2, ',', ' ');
        };

        $lineItems = $invoice['line_items'] ?? null;
        if (is_string($lineItems)) $lineItems = json_decode($lineItems, true);

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Faktura ' . $e($invoice['invoice_number']) . '</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:13px;color:#333;background:#f5f5f5;padding:20px}
.invoice-page{max-width:800px;margin:0 auto;background:#fff;padding:40px;box-shadow:0 2px 10px rgba(0,0,0,.1);border-radius:4px}
.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid #2563eb}
.header h1{font-size:22px;color:#2563eb}
.ksef-ref{background:#e0f2fe;padding:6px 12px;border-radius:4px;font-size:11px;color:#0369a1;margin-bottom:16px;word-break:break-all}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px}
.party{padding:12px;border:1px solid #e5e7eb;border-radius:6px}
.party h3{font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:.5px;margin-bottom:6px}
.party .name{font-weight:700;font-size:14px;margin-bottom:4px}
.party .nip{color:#2563eb;font-size:12px;margin-bottom:4px}
.dates{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.date-box{padding:8px 12px;background:#f9fafb;border-radius:4px;text-align:center}
.date-box .label{font-size:10px;text-transform:uppercase;color:#6b7280}
.date-box .value{font-weight:600;font-size:14px;margin-top:2px}
table.items{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:12px}
table.items th{background:#f3f4f6;padding:8px 6px;text-align:left;font-weight:600;font-size:11px;border-bottom:2px solid #e5e7eb}
table.items td{padding:7px 6px;border-bottom:1px solid #f3f4f6}
.text-right{text-align:right}
.totals{display:flex;justify-content:flex-end;margin-bottom:20px}
.totals table{border-collapse:collapse;min-width:280px}
.totals td{padding:6px 12px;font-size:13px}
.totals tr.grand td{font-weight:700;font-size:16px;border-top:2px solid #333;padding-top:8px}
.no-xml-notice{background:#fef3c7;border:1px solid #f59e0b;padding:12px;border-radius:6px;margin-bottom:16px;font-size:12px;color:#92400e}
.footer{text-align:center;color:#9ca3af;font-size:10px;padding-top:16px;border-top:1px solid #e5e7eb}
.print-btn{position:fixed;top:16px;right:16px;padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px}
@media print{.print-btn,.no-xml-notice{display:none}body{background:#fff;padding:0}.invoice-page{box-shadow:none;padding:20px}}
</style></head><body>
<button class="print-btn" onclick="window.print()">Drukuj</button>
<div class="invoice-page">';

        echo '<div class="header"><div><h1>Faktura ' . $e($invoice['invoice_number']) . '</h1></div><div style="text-align:right;font-size:12px;color:#666;">Waluta: ' . $e($invoice['currency'] ?? 'PLN') . '</div></div>';

        if (!empty($invoice['ksef_reference_number'])) {
            echo '<div class="ksef-ref">Nr KSeF: <strong>' . $e($invoice['ksef_reference_number']) . '</strong></div>';
        }

        echo '<div class="no-xml-notice">Brak pełnych danych XML z KSeF. Wyświetlane są dane dostępne w systemie. Kliknij "Uzupełnij nr KSeF" aby pobrać pełne dane.</div>';

        echo '<div class="dates">';
        echo '<div class="date-box"><div class="label">Data wystawienia</div><div class="value">' . $e($invoice['issue_date']) . '</div></div>';
        echo '<div class="date-box"><div class="label">Data sprzedaży</div><div class="value">' . $e($invoice['sale_date'] ?? '-') . '</div></div>';
        echo '<div class="date-box"><div class="label">Termin płatności</div><div class="value">' . $e($invoice['payment_due_date'] ?? '-') . '</div></div>';
        echo '</div>';

        echo '<div class="parties">';
        echo '<div class="party"><h3>Sprzedawca</h3><div class="name">' . $e($invoice['seller_name']) . '</div><div class="nip">NIP: ' . $e($invoice['seller_nip']) . '</div>';
        if (!empty($invoice['seller_address'])) echo '<div>' . $e($invoice['seller_address']) . '</div>';
        echo '</div>';
        echo '<div class="party"><h3>Nabywca</h3><div class="name">' . $e($invoice['buyer_name'] ?? '') . '</div><div class="nip">NIP: ' . $e($invoice['buyer_nip'] ?? '') . '</div>';
        if (!empty($invoice['buyer_address'])) echo '<div>' . $e($invoice['buyer_address']) . '</div>';
        echo '</div></div>';

        if (!empty($lineItems)) {
            echo '<table class="items"><thead><tr><th>Lp.</th><th>Nazwa</th><th class="text-right">Ilość</th><th>Jedn.</th><th class="text-right">Cena netto</th><th class="text-right">Wartość netto</th><th class="text-right">VAT</th></tr></thead><tbody>';
            foreach ($lineItems as $idx => $item) {
                echo '<tr><td>' . ($idx + 1) . '</td>';
                echo '<td>' . $e($item['name'] ?? $item['nazwa'] ?? $item['P_7'] ?? '') . '</td>';
                echo '<td class="text-right">' . $e($item['quantity'] ?? $item['ilosc'] ?? $item['P_8B'] ?? '') . '</td>';
                echo '<td>' . $e($item['unit'] ?? $item['jednostka'] ?? $item['P_8A'] ?? '') . '</td>';
                echo '<td class="text-right">' . ($item['unit_price_net'] ?? $item['cena_netto'] ?? $item['P_9A'] ?? '' ? $m($item['unit_price_net'] ?? $item['cena_netto'] ?? $item['P_9A'] ?? 0) : '') . '</td>';
                echo '<td class="text-right">' . ($item['net_value'] ?? $item['wartosc_netto'] ?? $item['P_11'] ?? '' ? $m($item['net_value'] ?? $item['wartosc_netto'] ?? $item['P_11'] ?? 0) : '') . '</td>';
                echo '<td class="text-right">' . $e($item['vat_rate'] ?? $item['stawka_vat'] ?? $item['P_12'] ?? '') . '%</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        $cur = $invoice['currency'] ?? 'PLN';
        echo '<div class="totals"><table>';
        echo '<tr><td>Razem netto:</td><td class="text-right">' . $m($invoice['net_amount']) . ' ' . $e($cur) . '</td></tr>';
        echo '<tr><td>Razem VAT:</td><td class="text-right">' . $m($invoice['vat_amount']) . ' ' . $e($cur) . '</td></tr>';
        echo '<tr class="grand"><td>Do zapłaty:</td><td class="text-right">' . $m($invoice['gross_amount']) . ' ' . $e($cur) . '</td></tr>';
        echo '</table></div>';

        echo '<div class="footer">Wizualizacja faktury &mdash; BiLLU</div>';
        echo '</div></body></html>';
    }

    // ── Invoice Payment Status ────────────────────────────

    public function toggleInvoicePaid(): void
    {
        header('Content-Type: application/json');
        $clientId = Session::get('client_id');
        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);

        if (!$invoiceId) {
            echo json_encode(['error' => 'Missing invoice_id']); exit;
        }

        $invoice = Invoice::findById($invoiceId);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            echo json_encode(['error' => 'Forbidden']); exit;
        }

        // 3-state toggle: 0 (unpaid) → 1 (paid), 2 (transfer in progress) → 1 (paid), 1 (paid) → 0 (unpaid)
        $current = (int) ($invoice['is_paid'] ?? 0);
        $newStatus = ($current === 1) ? 0 : 1;
        $db = \App\Core\Database::getInstance();
        $db->query("UPDATE invoices SET is_paid = ? WHERE id = ?", [$newStatus, $invoiceId]);

        echo json_encode(['success' => true, 'is_paid' => $newStatus]);
        exit;
    }

    // ── Invoice Comments ────────────────────────────

    public function addComment(): void
    {
        Auth::requireClient();
        if (!$this->validateCsrf()) { $this->redirect('/client'); return; }

        $clientId = Session::get('client_id');
        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        $invoice = Invoice::findById($invoiceId);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            Session::flash('error', 'access_denied');
            $this->redirect('/client');
            return;
        }

        if ($message === '') {
            Session::flash('error', 'comment_empty');
            $this->redirect('/client/invoices/' . $invoice['batch_id']);
            return;
        }

        \App\Models\InvoiceComment::create($invoiceId, 'client', $clientId, $message);

        Session::flash('success', 'comment_added');
        $this->redirect('/client/invoices/' . $invoice['batch_id']);
    }

    public function getComments(): void
    {
        Auth::requireClient();
        $clientId = Session::get('client_id');
        $invoiceId = (int) ($_GET['invoice_id'] ?? 0);

        $invoice = Invoice::findById($invoiceId);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
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

    // ── Company Profile ────────────────────────────────

    public function companyProfile(): void
    {
        ModuleAccess::requireModule('company-profile');
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        $profile = CompanyProfile::findByClient($clientId);
        $bankAccounts = BankAccount::findByClient($clientId);
        $services = CompanyService::findByClient($clientId, false);

        $this->render('client/company_profile', [
            'client' => $client,
            'profile' => $profile ?? [],
            'bankAccounts' => $bankAccounts,
            'services' => $services,
        ]);
    }

    public function companyGusLookup(): void
    {
        ModuleAccess::requireModule('company-profile');
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);

        header('Content-Type: application/json');

        // Rate limiting: max 1 request per 3 seconds
        $lastLookup = Session::get('gus_last_lookup', 0);
        if (time() - $lastLookup < 3) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests']);
            exit;
        }
        Session::set('gus_last_lookup', time());

        $nip = preg_replace('/[^0-9]/', '', $client['nip'] ?? '');
        if (strlen($nip) !== 10) {
            echo json_encode(['error' => Language::get('gus_not_found')]);
            exit;
        }

        try {
            $data = \App\Services\CompanyLookupService::findByNip($nip);
            if (!$data || empty($data['company_name'])) {
                echo json_encode(['error' => Language::get('gus_not_found')]);
                exit;
            }

            $street = $data['street'] ?? '';
            if ($street) {
                $num = $data['building_no'] ?? '';
                if (!empty($data['apartment_no'])) {
                    $num .= '/' . $data['apartment_no'];
                }
                $street = "ul. {$street} {$num}";
            }

            echo json_encode([
                'company_name' => $data['company_name'],
                'street' => trim($street),
                'postal' => $data['postal_code'] ?? '',
                'city' => $data['city'] ?? '',
                'regon' => $data['regon'] ?? '',
                'source' => $data['source'] ?? 'gus',
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    public function companyProfileUpdate(): void
    {
        ModuleAccess::requireModule('company-profile');
        if (!$this->validateCsrf()) { $this->redirect('/client/company'); return; }
        $clientId = Session::get('client_id');

        $data = [
            'trade_name' => trim($_POST['trade_name'] ?? ''),
            'address_street' => trim($_POST['address_street'] ?? ''),
            'address_city' => trim($_POST['address_city'] ?? ''),
            'address_postal' => trim($_POST['address_postal'] ?? ''),
            'regon' => trim($_POST['regon'] ?? ''),
            'krs' => trim($_POST['krs'] ?? ''),
            'bdo' => trim($_POST['bdo'] ?? ''),
            'default_payment_method' => $_POST['default_payment_method'] ?? 'przelew',
            'default_payment_days' => (int) ($_POST['default_payment_days'] ?? 14),
            'invoice_number_pattern' => trim($_POST['invoice_number_pattern'] ?? 'FV/{NR}/{MM}/{RRRR}'),
            'next_invoice_number' => max(1, (int) ($_POST['next_invoice_number'] ?? 1)),
            'numbering_reset_mode' => in_array($_POST['numbering_reset_mode'] ?? '', ['monthly', 'yearly', 'continuous']) ? $_POST['numbering_reset_mode'] : 'monthly',
            'invoice_notes' => trim($_POST['invoice_notes'] ?? ''),
        ];

        CompanyProfile::upsert($clientId, $data);

        Session::flash('success', Language::get('company_profile_saved'));
        header('Location: /client/company');
        exit;
    }

    public function companyLogoUpload(): void
    {
        ModuleAccess::requireModule('company-profile');
        if (!$this->validateCsrf()) { $this->redirect('/client/company'); return; }
        $clientId = Session::get('client_id');

        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'No file uploaded.');
            header('Location: /client/company');
            exit;
        }

        $file = $_FILES['logo'];

        // File size limit: 2MB
        if ($file['size'] > 2 * 1024 * 1024) {
            Session::flash('error', Language::get('file_too_large'));
            header('Location: /client/company');
            exit;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            Session::flash('error', 'Invalid file format.');
            header('Location: /client/company');
            exit;
        }

        // Validate actual MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/png', 'image/jpeg', 'image/gif'];
        if (!in_array($mime, $allowedMimes)) {
            Session::flash('error', 'Invalid file format.');
            header('Location: /client/company');
            exit;
        }

        $dir = __DIR__ . '/../../storage/logos';
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $filename = 'logo_' . $clientId . '_' . time() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . '/' . $filename);

        CompanyProfile::upsert($clientId, ['logo_path' => 'storage/logos/' . $filename]);

        Session::flash('success', Language::get('logo_uploaded'));
        header('Location: /client/company');
        exit;
    }

    public function companyLogoDelete(): void
    {
        ModuleAccess::requireModule('company-profile');
        if (!$this->validateCsrf()) { $this->redirect('/client/company'); return; }
        $clientId = Session::get('client_id');

        $profile = CompanyProfile::findByClient($clientId);
        if ($profile && !empty($profile['logo_path'])) {
            $fullPath = realpath(__DIR__ . '/../../' . $profile['logo_path']);
            $safeDir = realpath(__DIR__ . '/../../storage/logos');
            if ($fullPath && $safeDir && str_starts_with($fullPath, $safeDir) && file_exists($fullPath)) {
                unlink($fullPath);
            }
            CompanyProfile::upsert($clientId, ['logo_path' => null]);
        }

        Session::flash('success', Language::get('logo_deleted'));
        header('Location: /client/company');
        exit;
    }

    // ── Bank Accounts ──────────────────────────────────

    public function bankAccountCreate(): void
    {
        ModuleAccess::requireModule('company-profile');
        if (!$this->validateCsrf()) { $this->redirect('/client/company'); return; }
        $clientId = Session::get('client_id');

        $bankName = trim($_POST['bank_name'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');

        // Auto-detect bank name if empty
        if ($bankName === '' && $accountNumber !== '') {
            $detected = \App\Services\BankIdentService::identifyBank($accountNumber);
            if ($detected !== null) {
                $bankName = $detected;
            }
        }

        $data = [
            'account_name' => trim($_POST['account_name'] ?? ''),
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'swift' => trim($_POST['swift'] ?? ''),
            'currency' => $_POST['currency'] ?? 'PLN',
            'is_default_receiving' => isset($_POST['is_default_receiving']) ? 1 : 0,
            'is_default_outgoing' => isset($_POST['is_default_outgoing']) ? 1 : 0,
        ];

        BankAccount::create($clientId, $data);

        Session::flash('success', Language::get('bank_account_added'));
        header('Location: /client/company');
        exit;
    }

    public function bankAccountDelete(int $id): void
    {
        ModuleAccess::requireModule('company-profile');
        if (!$this->validateCsrf()) { $this->redirect('/client/company'); return; }
        $clientId = Session::get('client_id');

        $account = BankAccount::findById($id);
        if ($account && (int) $account['client_id'] === $clientId) {
            BankAccount::delete($id);
            Session::flash('success', Language::get('bank_account_deleted'));
        }

        header('Location: /client/company');
        exit;
    }

    public function bankAccountSetDefault(int $id): void
    {
        ModuleAccess::requireModule('company-profile');
        if (!$this->validateCsrf()) { $this->redirect('/client/company'); return; }
        $clientId = (int) Session::get('client_id');
        $type = $_POST['type'] ?? '';

        $account = BankAccount::findById($id);
        if ($account && (int) $account['client_id'] === $clientId) {
            if ($type === 'receiving') {
                BankAccount::clearDefaultReceiving($clientId);
                BankAccount::update($id, ['is_default_receiving' => 1]);
            } elseif ($type === 'outgoing') {
                BankAccount::clearDefaultOutgoing($clientId);
                BankAccount::update($id, ['is_default_outgoing' => 1]);
            }
            Session::flash('success', Language::get('default_account_set'));
        }

        header('Location: /client/company');
        exit;
    }

    // ── Bank Account: Auto-detect bank from IBAN (F5) ─────

    public function bankAccountIdentifyBank(): void
    {
        ModuleAccess::requireModule('company-profile');
        if (!$this->validateCsrf()) { http_response_code(403); echo json_encode(['error' => 'csrf']); return; }

        header('Content-Type: application/json');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $bankName = \App\Services\BankIdentService::identifyBank($accountNumber);

        echo json_encode(['bank_name' => $bankName]);
    }

    // ── Bank Account: Whitelist check (F4) ──────────────

    public function bankAccountWhitelistCheck(): void
    {
        ModuleAccess::requireModule('company-profile');
        if (!$this->validateCsrf()) { http_response_code(403); echo json_encode(['error' => 'csrf']); return; }

        header('Content-Type: application/json');
        $clientId = (int) Session::get('client_id');
        $accountId = (int) ($_POST['bank_account_id'] ?? 0);

        if (!$accountId) {
            echo json_encode(['verified' => false, 'status' => 'error', 'message' => 'Missing account ID']);
            return;
        }

        $account = BankAccount::findById($accountId);
        if (!$account || (int) $account['client_id'] !== $clientId) {
            echo json_encode(['verified' => false, 'status' => 'error', 'message' => 'Account not found']);
            return;
        }

        $client = Client::findById($clientId);
        if (!$client || empty($client['nip'])) {
            echo json_encode(['verified' => false, 'status' => 'error', 'message' => 'Client NIP not found']);
            return;
        }

        try {
            $result = \App\Services\WhiteListService::verifyNipBankAccount($client['nip'], $account['account_number']);
            echo json_encode($result);
        } catch (\Exception $e) {
            echo json_encode(['verified' => false, 'status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ── Services Catalog ───────────────────────────────

    public function services(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $services = CompanyService::findByClient($clientId, false);

        $this->render('client/services_catalog', [
            'services' => $services,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function serviceCreate(): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/services'); return; }
        $clientId = Session::get('client_id');

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'unit' => $_POST['unit'] ?? 'szt.',
            'default_price' => !empty($_POST['default_price']) ? (float) $_POST['default_price'] : null,
            'vat_rate' => $_POST['vat_rate'] ?? '23',
            'pkwiu' => trim($_POST['pkwiu'] ?? ''),
        ];

        if (empty($data['name'])) {
            Session::flash('error', Language::get('required'));
            header('Location: /client/services');
            exit;
        }

        CompanyService::create($clientId, $data);

        Session::flash('success', Language::get('service_added'));
        header('Location: /client/services');
        exit;
    }

    public function serviceUpdate(int $id): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/services'); return; }
        $clientId = Session::get('client_id');

        $service = CompanyService::findById($id);
        if (!$service || (int) $service['client_id'] !== $clientId) {
            header('Location: /client/services');
            exit;
        }

        $data = [
            'name' => trim($_POST['name'] ?? $service['name']),
            'unit' => $_POST['unit'] ?? $service['unit'],
            'default_price' => isset($_POST['default_price']) ? (float) $_POST['default_price'] : $service['default_price'],
            'vat_rate' => $_POST['vat_rate'] ?? $service['vat_rate'],
            'pkwiu' => trim($_POST['pkwiu'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        CompanyService::update($id, $data);

        Session::flash('success', Language::get('service_updated'));
        header('Location: /client/services');
        exit;
    }

    public function serviceDelete(int $id): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/services'); return; }
        $clientId = Session::get('client_id');

        $service = CompanyService::findById($id);
        if ($service && (int) $service['client_id'] === $clientId) {
            CompanyService::delete($id);
            Session::flash('success', Language::get('service_deleted'));
        }

        header('Location: /client/services');
        exit;
    }

    // ── Contractors ────────────────────────────────────

    public function contractors(): void
    {
        ModuleAccess::requireModule('contractors');
        $clientId = Session::get('client_id');
        $search = trim($_GET['q'] ?? '');

        $contractors = $search
            ? Contractor::search($clientId, $search)
            : Contractor::findByClient($clientId);

        $this->render('client/contractors', [
            'contractors' => $contractors,
            'search' => $search,
            'success' => Session::getFlash('success'),
        ]);
    }

    public function contractorCreateForm(): void
    {
        ModuleAccess::requireModule('contractors');
        $this->render('client/contractor_form', [
            'isEdit' => false,
            'contractor' => [],
            'error' => Session::getFlash('error'),
        ]);
    }

    public function contractorCreate(): void
    {
        ModuleAccess::requireModule('contractors');
        if (!$this->validateCsrf()) { $this->redirect('/client/contractors'); return; }
        $clientId = Session::get('client_id');

        $data = $this->getContractorData();
        if (empty($data['company_name'])) {
            Session::flash('error', Language::get('required'));
            header('Location: /client/contractors/create');
            exit;
        }

        Contractor::create($clientId, $data);

        Session::flash('success', Language::get('contractor_added'));
        header('Location: /client/contractors');
        exit;
    }

    public function contractorEditForm(int $id): void
    {
        ModuleAccess::requireModule('contractors');
        $clientId = Session::get('client_id');
        $contractor = Contractor::findById($id);

        if (!$contractor || (int) $contractor['client_id'] !== $clientId) {
            header('Location: /client/contractors');
            exit;
        }

        $this->render('client/contractor_form', [
            'isEdit' => true,
            'contractor' => $contractor,
            'error' => Session::getFlash('error'),
        ]);
    }

    public function contractorUpdate(int $id): void
    {
        ModuleAccess::requireModule('contractors');
        if (!$this->validateCsrf()) { $this->redirect('/client/contractors'); return; }
        $clientId = Session::get('client_id');

        $contractor = Contractor::findById($id);
        if (!$contractor || (int) $contractor['client_id'] !== $clientId) {
            header('Location: /client/contractors');
            exit;
        }

        $data = $this->getContractorData();
        Contractor::update($id, $data);

        Session::flash('success', Language::get('contractor_updated'));
        header('Location: /client/contractors');
        exit;
    }

    public function contractorDelete(int $id): void
    {
        ModuleAccess::requireModule('contractors');
        if (!$this->validateCsrf()) { $this->redirect('/client/contractors'); return; }
        $clientId = Session::get('client_id');

        $contractor = Contractor::findById($id);
        if ($contractor && (int) $contractor['client_id'] === $clientId) {
            Contractor::delete($id);
            Session::flash('success', Language::get('contractor_deleted'));
        }

        header('Location: /client/contractors');
        exit;
    }

    public function contractorSearch(): void
    {
        ModuleAccess::requireModule('contractors');
        $clientId = Session::get('client_id');
        $q = trim($_GET['q'] ?? '');

        $results = $q ? Contractor::search($clientId, $q) : [];

        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }

    public function contractorGusLookup(): void
    {
        ModuleAccess::requireModule('contractors');
        $nip = preg_replace('/[^0-9]/', '', $_GET['nip'] ?? '');

        header('Content-Type: application/json');

        // Rate limiting: max 1 request per 3 seconds
        $lastLookup = Session::get('contractor_last_lookup', 0);
        if (time() - $lastLookup < 3) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests. Odczekaj 3 sekundy.']);
            exit;
        }
        Session::set('contractor_last_lookup', time());

        if (strlen($nip) !== 10) {
            echo json_encode(['error' => Language::get('gus_not_found')]);
            exit;
        }

        $result = null;
        $source = null;

        // 1. Try White List API (MF) — has name and address
        try {
            $url = 'https://wl-api.mf.gov.pl/api/search/nip/' . $nip . '?date=' . date('Y-m-d');
            $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
            $response = file_get_contents($url, false, $ctx);

            if ($response !== false) {
                $data = json_decode($response, true);
                $subject = $data['result']['subject'] ?? null;

                if ($subject && !empty($subject['name'])) {
                    $address = $subject['workingAddress'] ?? $subject['residenceAddress'] ?? '';
                    $parts = $this->parsePolishAddress($address);
                    $result = [
                        'company_name' => $subject['name'] ?? '',
                        'street' => $parts['street'] ?? '',
                        'postal' => $parts['postal'] ?? '',
                        'city' => $parts['city'] ?? '',
                        'nip' => $nip,
                        'source' => 'whitelist',
                    ];
                    $source = 'whitelist';
                }
            }
        } catch (\Throwable $e) {
            // White List failed, try fallback
        }

        // 2. Fallback: CompanyLookupService (GUS → CEIDG)
        if (!$result) {
            try {
                $lookupData = \App\Services\CompanyLookupService::findByNip($nip);
                if ($lookupData && !empty($lookupData['company_name'])) {
                    $street = $lookupData['street'] ?? '';
                    if ($street) {
                        $num = $lookupData['building_no'] ?? '';
                        if (!empty($lookupData['apartment_no'])) {
                            $num .= '/' . $lookupData['apartment_no'];
                        }
                        $street = "ul. {$street} {$num}";
                    }
                    $result = [
                        'company_name' => $lookupData['company_name'],
                        'street' => trim($street),
                        'postal' => $lookupData['postal_code'] ?? '',
                        'city' => $lookupData['city'] ?? '',
                        'nip' => $nip,
                        'source' => $lookupData['source'] ?? 'gus',
                    ];
                    $source = $lookupData['source'] ?? 'gus';
                }
            } catch (\Throwable $e) {
                // Both failed
            }
        }

        if (!$result) {
            echo json_encode(['error' => Language::get('gus_not_found')]);
            exit;
        }

        echo json_encode($result);
        exit;
    }

    public function contractorImportForm(): void
    {
        ModuleAccess::requireModule('contractors');
        $this->render('client/contractor_import', [
            'error' => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ]);
    }

    public function contractorImport(): void
    {
        ModuleAccess::requireModule('contractors');
        if (!$this->validateCsrf()) { $this->redirect('/client/contractors/import'); return; }
        $clientId = Session::get('client_id');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', Language::get('import_missing_data'));
            $this->redirect('/client/contractors/import');
            return;
        }

        $file = $_FILES['file'];
        if ($file['size'] > 10 * 1024 * 1024) {
            Session::flash('error', Language::get('file_too_large'));
            $this->redirect('/client/contractors/import');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            Session::flash('error', Language::get('import_invalid_format'));
            $this->redirect('/client/contractors/import');
            return;
        }

        $uploadDir = __DIR__ . '/../../storage/imports/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
        $uploadPath = $uploadDir . uniqid('contractor_') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadPath);

        $result = \App\Services\ContractorImportService::import($uploadPath, $clientId);
        @unlink($uploadPath);

        $this->render('client/contractor_import', [
            'importResult' => $result,
            'success' => $result['success'] > 0 ? sprintf(Language::get('contractors_imported'), $result['success']) : null,
            'error' => !empty($result['errors']) && $result['success'] === 0 ? $result['errors'][0] : null,
        ]);
    }

    public function contractorImportTemplate(): void
    {
        ModuleAccess::requireModule('contractors');
        $spreadsheet = \App\Services\ContractorImportService::generateTemplate();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="kontrahenci_szablon.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    public function contractorLogoUpload(int $id): void
    {
        ModuleAccess::requireModule('contractors');
        if (!$this->validateCsrf()) { $this->redirect('/client/contractors/' . $id . '/edit'); return; }
        $clientId = Session::get('client_id');
        $contractor = Contractor::findById($id);
        if (!$contractor || (int) $contractor['client_id'] !== $clientId) {
            $this->redirect('/client/contractors');
            return;
        }

        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'No file uploaded.');
            $this->redirect('/client/contractors/' . $id . '/edit');
            return;
        }

        $file = $_FILES['logo'];
        if ($file['size'] > 2 * 1024 * 1024) {
            Session::flash('error', Language::get('file_too_large'));
            $this->redirect('/client/contractors/' . $id . '/edit');
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            Session::flash('error', 'Invalid file format.');
            $this->redirect('/client/contractors/' . $id . '/edit');
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif'])) {
            Session::flash('error', 'Invalid file format.');
            $this->redirect('/client/contractors/' . $id . '/edit');
            return;
        }

        $dir = __DIR__ . '/../../storage/contractor_logos';
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $filename = 'contractor_' . $id . '_' . time() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . '/' . $filename);

        Contractor::update($id, ['logo_path' => 'storage/contractor_logos/' . $filename]);

        Session::flash('success', Language::get('logo_uploaded'));
        $this->redirect('/client/contractors/' . $id . '/edit');
    }

    public function contractorLogoDelete(int $id): void
    {
        ModuleAccess::requireModule('contractors');
        if (!$this->validateCsrf()) { $this->redirect('/client/contractors/' . $id . '/edit'); return; }
        $clientId = Session::get('client_id');
        $contractor = Contractor::findById($id);
        if (!$contractor || (int) $contractor['client_id'] !== $clientId) {
            $this->redirect('/client/contractors');
            return;
        }

        if (!empty($contractor['logo_path'])) {
            $fullPath = realpath(__DIR__ . '/../../' . $contractor['logo_path']);
            $safeDir = realpath(__DIR__ . '/../../storage/contractor_logos');
            if ($fullPath && $safeDir && str_starts_with($fullPath, $safeDir) && file_exists($fullPath)) {
                unlink($fullPath);
            }
            Contractor::update($id, ['logo_path' => null]);
        }

        Session::flash('success', Language::get('logo_deleted'));
        $this->redirect('/client/contractors/' . $id . '/edit');
    }

    private function getContractorData(): array
    {
        return [
            'nip' => trim($_POST['nip'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'short_name' => trim($_POST['short_name'] ?? '') ?: null,
            'address_street' => trim($_POST['address_street'] ?? ''),
            'address_city' => trim($_POST['address_city'] ?? ''),
            'address_postal' => trim($_POST['address_postal'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'default_payment_days' => !empty($_POST['default_payment_days']) ? (int) $_POST['default_payment_days'] : null,
            'notes' => trim($_POST['notes'] ?? ''),
        ];
    }

    private function parsePolishAddress(string $address): array
    {
        $result = ['street' => '', 'postal' => '', 'city' => ''];
        if (empty($address)) return $result;

        // Format: "ul. Street 1, 00-000 City" or "Street 1, 00-000 City"
        if (preg_match('/^(.+?),\s*(\d{2}-\d{3})\s+(.+)$/', $address, $m)) {
            $result['street'] = trim($m[1]);
            $result['postal'] = $m[2];
            $result['city'] = trim($m[3]);
        }

        return $result;
    }

    // ── Issued Invoices (Sales) ────────────────────────

    public function issuedInvoices(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $status = $_GET['status'] ?? null;
        $search = trim($_GET['search'] ?? '');

        $invoices = IssuedInvoice::findByClient($clientId, $status ?: null, $search ?: null);
        $counts = IssuedInvoice::countByClient($clientId);

        $this->render('client/sales_list', [
            'invoices' => $invoices,
            'counts' => $counts,
            'filterStatus' => $status,
            'filterSearch' => $search,
            'success' => Session::getFlash('success'),
        ]);
    }

    public function issuedInvoiceCreate(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        $profile = CompanyProfile::findByClient($clientId) ?? [];
        $bankAccounts = BankAccount::findByClient($clientId);
        $services = CompanyService::findByClient($clientId);

        $this->render('client/sales_form', [
            'isEdit' => false,
            'invoice' => [],
            'lineItems' => [],
            'profile' => $profile,
            'bankAccounts' => $bankAccounts,
            'services' => $services,
            'client' => $client,
            'error' => Session::getFlash('error'),
        ]);
    }

    public function issuedInvoiceStore(): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/sales'); return; }
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        $profile = CompanyProfile::findByClient($clientId);

        $action = $_POST['action'] ?? 'draft';
        $items = $this->parseLineItems();
        $vatDetails = $this->calculateVatDetails($items);
        $totals = $this->calculateTotals($items);

        // Build seller address from profile
        $sellerAddress = implode(', ', array_filter([
            $profile['address_street'] ?? '',
            $profile['address_postal'] ?? '',
            $profile['address_city'] ?? '',
        ]));

        // Validate currency
        $allowedCurrencies = ['PLN', 'EUR', 'USD'];
        $currency = strtoupper(trim($_POST['currency'] ?? 'PLN'));
        if (!in_array($currency, $allowedCurrencies, true)) {
            $currency = 'PLN';
        }

        // Get bank account details
        $bankAccountNumber = '';
        $bankName = '';
        $bankAccountCurrency = 'PLN';
        $bankAccountId = !empty($_POST['bank_account_id']) ? (int) $_POST['bank_account_id'] : null;
        if ($bankAccountId) {
            $ba = BankAccount::findById($bankAccountId);
            if ($ba && (int) $ba['client_id'] === $clientId) {
                $bankAccountNumber = $ba['account_number'] ?? '';
                $bankName = $ba['bank_name'] ?? '';
                $bankAccountCurrency = $ba['currency'] ?? 'PLN';
            }
        }

        // Block foreign currency invoices without matching bank account
        if ($currency !== 'PLN') {
            if (!$bankAccountId || $bankAccountCurrency !== $currency) {
                Session::flash('error', Language::get('currency_mismatch'));
                $this->redirect('/client/sales/create');
                return;
            }
        }

        // Validate contractor ownership
        $contractorId = !empty($_POST['contractor_id']) ? (int) $_POST['contractor_id'] : null;
        if ($contractorId) {
            $contractor = Contractor::findById($contractorId);
            if (!$contractor || (int) $contractor['client_id'] !== $clientId) {
                $contractorId = null;
            }
        }

        // Save as new contractor if requested
        if (!empty($_POST['save_contractor']) && !$contractorId) {
            $buyerNip = trim($_POST['buyer_nip'] ?? '');
            $buyerName = trim($_POST['buyer_name'] ?? '');
            if ($buyerName !== '') {
                $existing = $buyerNip ? Contractor::findByClientAndNip($clientId, $buyerNip) : null;
                if ($existing) {
                    $contractorId = (int) $existing['id'];
                } else {
                    $addrParts = array_map('trim', explode(',', trim($_POST['buyer_address'] ?? '')));
                    $contractorId = Contractor::create($clientId, [
                        'company_name' => $buyerName,
                        'nip' => $buyerNip,
                        'address_street' => $addrParts[0] ?? '',
                        'address_postal' => $addrParts[1] ?? '',
                        'address_city' => $addrParts[2] ?? '',
                    ]);
                }
            }
        }

        $allowedInvoiceTypes = ['FV', 'FV_KOR', 'FP', 'FV_ZAL', 'FV_KON'];
        $invoiceType = in_array($_POST['invoice_type'] ?? '', $allowedInvoiceTypes, true) ? $_POST['invoice_type'] : 'FV';

        // Validate issue_date: only today or yesterday allowed
        $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        if ($issueDate < $yesterday || $issueDate > $today) {
            Session::flash('error', 'Data wystawienia może być tylko z dnia dzisiejszego lub wczorajszego.');
            $this->redirect('/client/sales/create');
            return;
        }

        $invoiceNumber = '';
        $status = 'draft';
        if ($action === 'issue') {
            $invoiceNumber = CompanyProfile::getAndIncrementInvoiceNumber($clientId, $invoiceType);
            $status = 'issued';
        }

        $data = [
            'client_id' => $clientId,
            'contractor_id' => $contractorId,
            'invoice_type' => $invoiceType,
            'invoice_number' => $invoiceNumber ?: ('DRAFT-' . date('YmdHis')),
            'issue_date' => $issueDate,
            'sale_date' => $_POST['sale_date'] ?? date('Y-m-d'),
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'seller_nip' => $client['nip'] ?? '',
            'seller_name' => $client['company_name'] ?? '',
            'seller_address' => $sellerAddress,
            'buyer_nip' => trim($_POST['buyer_nip'] ?? ''),
            'buyer_name' => trim($_POST['buyer_name'] ?? ''),
            'buyer_address' => trim($_POST['buyer_address'] ?? ''),
            'currency' => $currency,
            'exchange_rate' => null,
            'exchange_rate_date' => null,
            'exchange_rate_table' => null,
            'net_amount' => $totals['net'],
            'vat_amount' => $totals['vat'],
            'gross_amount' => $totals['gross'],
            'line_items' => $items,
            'vat_details' => $vatDetails,
            'payment_method' => $_POST['payment_method'] ?? 'przelew',
            'bank_account_id' => $bankAccountId,
            'bank_account_number' => $bankAccountNumber,
            'bank_name' => $bankName,
            'notes' => trim($_POST['notes'] ?? ''),
            'internal_notes' => trim($_POST['internal_notes'] ?? ''),
            'corrected_invoice_id' => !empty($_POST['corrected_invoice_id']) ? (int) $_POST['corrected_invoice_id'] : null,
            'correction_reason' => trim($_POST['correction_reason'] ?? ''),
            'is_split_payment' => (!empty($_POST['is_split_payment']) && ($data['payment_method'] ?? '') !== 'gotowka') ? 1 : 0,
            'payer_data' => self::buildPayerData(),
            'status' => $status,
        ];

        // Server-side exchange rate fetch for foreign currency invoices
        // Per art. 31a ustawy o VAT: use the last business day before the issue date
        if ($currency !== 'PLN') {
            $rateRefDate = $data['issue_date'] ?? date('Y-m-d');
            $nbpData = \App\Services\NbpExchangeRateService::getRate($currency, $rateRefDate);

            if ($nbpData) {
                $data['exchange_rate'] = round($nbpData['rate'], 6);
                $data['exchange_rate_date'] = $nbpData['date'];
                $data['exchange_rate_table'] = $nbpData['table'];
            } else {
                // Fallback: validate client-submitted rate if NBP is unreachable
                $exRate = isset($_POST['exchange_rate']) ? (float) $_POST['exchange_rate'] : 0;
                $exDate = $_POST['exchange_rate_date'] ?? '';
                $exTable = trim($_POST['exchange_rate_table'] ?? '');

                if ($exRate > 0 && $exRate < 100
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $exDate)
                    && preg_match('/^[A-Za-z0-9\/]{0,20}$/', $exTable)
                ) {
                    $data['exchange_rate'] = round($exRate, 6);
                    $data['exchange_rate_date'] = $exDate;
                    $data['exchange_rate_table'] = $exTable;
                }
            }

            // Calculate VAT and net in PLN (art. 106e ust. 11 ustawy o VAT)
            $rate = (float)($data['exchange_rate'] ?? 0);
            if ($rate > 0) {
                $data['vat_amount_pln'] = round((float)($data['vat_amount'] ?? 0) * $rate, 2);
                $data['net_amount_pln'] = round((float)($data['net_amount'] ?? 0) * $rate, 2);
            }
        }

        // For corrections: save original invoice data for StanPrzed/StanPo comparison
        if ($invoiceType === 'FV_KOR' && !empty($data['corrected_invoice_id'])) {
            $originalInvoice = IssuedInvoice::findById($data['corrected_invoice_id']);
            if ($originalInvoice) {
                $origItems = $originalInvoice['line_items'] ?? '[]';
                if (is_string($origItems)) $origItems = json_decode($origItems, true) ?: [];
                $data['original_line_items'] = $origItems;
                $data['original_net_amount'] = (float) ($originalInvoice['net_amount'] ?? 0);
                $data['original_vat_amount'] = (float) ($originalInvoice['vat_amount'] ?? 0);
                $data['original_gross_amount'] = (float) ($originalInvoice['gross_amount'] ?? 0);
            }
            $data['correction_type'] = (int) ($_POST['correction_type'] ?? 1);
        }

        // For advance invoices (FV_ZAL): save advance-specific fields
        if ($invoiceType === 'FV_ZAL') {
            $advAmt = !empty($_POST['advance_amount']) ? round((float) $_POST['advance_amount'], 2) : null;
            $data['advance_amount'] = ($advAmt !== null && $advAmt > 0 && $advAmt <= 999999999.99) ? $advAmt : null;
            $data['advance_order_description'] = trim($_POST['advance_order_description'] ?? '');
        }

        // For final invoices (FV_KON): save related advance invoice IDs
        if ($invoiceType === 'FV_KON') {
            $relatedIds = $_POST['related_advance_ids'] ?? '[]';
            if (is_string($relatedIds)) {
                $relatedIds = json_decode($relatedIds, true) ?: [];
            }
            $data['related_advance_ids'] = array_map('intval', $relatedIds);
        }

        // Block self-invoicing: seller NIP must differ from buyer NIP
        $buyerNipClean = preg_replace('/[^0-9]/', '', $data['buyer_nip']);
        $sellerNipClean = preg_replace('/[^0-9]/', '', $data['seller_nip']);
        if ($buyerNipClean !== '' && $sellerNipClean !== '' && $buyerNipClean === $sellerNipClean) {
            Session::flash('error', 'Nie można wystawić faktury na samego siebie — NIP nabywcy jest taki sam jak NIP sprzedawcy.');
            $this->redirect('/client/sales/create');
            return;
        }

        // Block invoice without bank account when payment is bank transfer
        $paymentMethod = $data['payment_method'] ?? 'przelew';
        if ($paymentMethod === 'przelew' && empty($bankAccountNumber)) {
            Session::flash('error', 'Brak wskazanego rachunku bankowego — uzupełnij w panelu klienta.');
            $this->redirect('/client/sales/create');
            return;
        }

        // Duplicate detection (F2): warn but allow override
        if ($action === 'issue' || $action === 'draft') {
            $dupCheck = \App\Services\DuplicateDetectionService::checkSalesInvoice(
                $clientId,
                $data['invoice_number'],
                $data['buyer_nip'],
                (float) $data['gross_amount']
            );
            if ($dupCheck['is_duplicate'] && empty($_POST['duplicate_acknowledged'])) {
                Session::flash('error', Language::get('duplicate_warning'));
                // Store form data in session for re-display
                Session::set('duplicate_form_data', $_POST);
                Session::set('duplicate_candidates', $dupCheck['candidates']);
                $this->redirect('/client/sales/create');
                return;
            }
            if (!empty($_POST['duplicate_acknowledged'])) {
                $data['duplicate_acknowledged'] = 1;
            }
        }

        $id = IssuedInvoice::create($data);

        Session::flash('success', Language::get($action === 'issue' ? 'invoice_issued' : 'invoice_saved'));
        header('Location: /client/sales/' . $id);
        exit;
    }

    public function issuedInvoiceEdit(int $id): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            header('Location: /client/sales');
            exit;
        }

        // Allow editing drafts and issued invoices not yet sent to KSeF
        $ksefStatus = $invoice['ksef_status'] ?? 'none';
        $sentToKsef = !empty($ksefStatus) && !in_array($ksefStatus, ['none', 'error']);
        $canEdit = $invoice['status'] === 'draft'
            || ($invoice['status'] === 'issued' && !$sentToKsef);

        if (!$canEdit) {
            Session::flash('error', 'Faktura wysłana do KSeF nie może być edytowana. Użyj korekty.');
            header('Location: /client/sales/' . $id);
            exit;
        }

        $profile = CompanyProfile::findByClient($clientId) ?? [];
        $bankAccounts = BankAccount::findByClient($clientId);
        $services = CompanyService::findByClient($clientId);

        $lineItems = $invoice['line_items'] ?? '[]';
        if (is_string($lineItems)) $lineItems = json_decode($lineItems, true) ?: [];

        $this->render('client/sales_form', [
            'isEdit' => true,
            'invoice' => $invoice,
            'lineItems' => $lineItems,
            'profile' => $profile,
            'bankAccounts' => $bankAccounts,
            'services' => $services,
            'client' => $client,
            'error' => Session::getFlash('error'),
        ]);
    }

    public function issuedInvoiceUpdate(int $id): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/sales'); return; }
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            header('Location: /client/sales');
            exit;
        }

        // Allow updating drafts and issued invoices not yet sent to KSeF
        $ksefStatus = $invoice['ksef_status'] ?? 'none';
        $sentToKsef = !empty($ksefStatus) && !in_array($ksefStatus, ['none', 'error']);
        $canEdit = $invoice['status'] === 'draft'
            || ($invoice['status'] === 'issued' && !$sentToKsef);

        if (!$canEdit) {
            Session::flash('error', 'Faktura wysłana do KSeF nie może być edytowana. Użyj korekty.');
            header('Location: /client/sales/' . $id);
            exit;
        }

        $client = Client::findById($clientId);
        $profile = CompanyProfile::findByClient($clientId);
        $action = $_POST['action'] ?? 'draft';
        $items = $this->parseLineItems();
        $vatDetails = $this->calculateVatDetails($items);
        $totals = $this->calculateTotals($items);

        $sellerAddress = implode(', ', array_filter([
            $profile['address_street'] ?? '',
            $profile['address_postal'] ?? '',
            $profile['address_city'] ?? '',
        ]));

        // Validate currency
        $allowedCurrencies = ['PLN', 'EUR', 'USD'];
        $currency = strtoupper(trim($_POST['currency'] ?? ($invoice['currency'] ?? 'PLN')));
        if (!in_array($currency, $allowedCurrencies, true)) {
            $currency = 'PLN';
        }

        $bankAccountNumber = '';
        $bankName = '';
        $bankAccountCurrency = 'PLN';
        $bankAccountId = !empty($_POST['bank_account_id']) ? (int) $_POST['bank_account_id'] : null;
        if ($bankAccountId) {
            $ba = BankAccount::findById($bankAccountId);
            if ($ba && (int) $ba['client_id'] === $clientId) {
                $bankAccountNumber = $ba['account_number'] ?? '';
                $bankName = $ba['bank_name'] ?? '';
                $bankAccountCurrency = $ba['currency'] ?? 'PLN';
            }
        }

        // Block foreign currency invoices without matching bank account
        if ($currency !== 'PLN') {
            if (!$bankAccountId || $bankAccountCurrency !== $currency) {
                Session::flash('error', Language::get('currency_mismatch'));
                $this->redirect("/client/sales/{$id}/edit");
                return;
            }
        }

        // Validate contractor ownership
        $contractorId = !empty($_POST['contractor_id']) ? (int) $_POST['contractor_id'] : null;
        if ($contractorId) {
            $contractor = Contractor::findById($contractorId);
            if (!$contractor || (int) $contractor['client_id'] !== $clientId) {
                $contractorId = null;
            }
        }

        // Save as new contractor if requested
        if (!empty($_POST['save_contractor']) && !$contractorId) {
            $buyerNip = trim($_POST['buyer_nip'] ?? '');
            $buyerName = trim($_POST['buyer_name'] ?? '');
            if ($buyerName !== '') {
                $existing = $buyerNip ? Contractor::findByClientAndNip($clientId, $buyerNip) : null;
                if ($existing) {
                    $contractorId = (int) $existing['id'];
                } else {
                    $addrParts = array_map('trim', explode(',', trim($_POST['buyer_address'] ?? '')));
                    $contractorId = Contractor::create($clientId, [
                        'company_name' => $buyerName,
                        'nip' => $buyerNip,
                        'address_street' => $addrParts[0] ?? '',
                        'address_postal' => $addrParts[1] ?? '',
                        'address_city' => $addrParts[2] ?? '',
                    ]);
                }
            }
        }

        $invoiceNumber = $invoice['invoice_number'];
        $invoiceType = $invoice['invoice_type'] ?? 'FV';

        // Validate issue_date: only today or yesterday allowed
        $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $today = date('Y-m-d');
        if ($issueDate < $yesterday || $issueDate > $today) {
            Session::flash('error', 'Data wystawienia może być tylko z dnia dzisiejszego lub wczorajszego.');
            $this->redirect("/client/sales/{$id}/edit");
            return;
        }

        $status = 'draft';
        if ($action === 'issue') {
            $invoiceNumber = CompanyProfile::getAndIncrementInvoiceNumber($clientId, $invoiceType);
            $status = 'issued';
        }

        $data = [
            'contractor_id' => $contractorId,
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'sale_date' => $_POST['sale_date'] ?? date('Y-m-d'),
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'seller_nip' => $client['nip'] ?? '',
            'seller_name' => $client['company_name'] ?? '',
            'seller_address' => $sellerAddress,
            'buyer_nip' => trim($_POST['buyer_nip'] ?? ''),
            'buyer_name' => trim($_POST['buyer_name'] ?? ''),
            'buyer_address' => trim($_POST['buyer_address'] ?? ''),
            'currency' => $currency,
            'exchange_rate' => null,
            'exchange_rate_date' => null,
            'exchange_rate_table' => null,
            'net_amount' => $totals['net'],
            'vat_amount' => $totals['vat'],
            'gross_amount' => $totals['gross'],
            'line_items' => $items,
            'vat_details' => $vatDetails,
            'payment_method' => $_POST['payment_method'] ?? 'przelew',
            'bank_account_id' => $bankAccountId,
            'bank_account_number' => $bankAccountNumber,
            'bank_name' => $bankName,
            'notes' => trim($_POST['notes'] ?? ''),
            'internal_notes' => trim($_POST['internal_notes'] ?? ''),
            'is_split_payment' => (!empty($_POST['is_split_payment']) && ($_POST['payment_method'] ?? '') !== 'gotowka') ? 1 : 0,
            'payer_data' => self::buildPayerData(),
            'status' => $status,
        ];

        // Server-side exchange rate fetch for foreign currency invoices
        // Per art. 31a ustawy o VAT: use the last business day before the issue date
        if ($currency !== 'PLN') {
            $rateRefDate = $data['issue_date'] ?? date('Y-m-d');
            $nbpData = \App\Services\NbpExchangeRateService::getRate($currency, $rateRefDate);

            if ($nbpData) {
                $data['exchange_rate'] = round($nbpData['rate'], 6);
                $data['exchange_rate_date'] = $nbpData['date'];
                $data['exchange_rate_table'] = $nbpData['table'];
            } else {
                // Fallback: validate client-submitted rate if NBP is unreachable
                $exRate = isset($_POST['exchange_rate']) ? (float) $_POST['exchange_rate'] : 0;
                $exDate = $_POST['exchange_rate_date'] ?? '';
                $exTable = trim($_POST['exchange_rate_table'] ?? '');

                if ($exRate > 0 && $exRate < 100
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $exDate)
                    && preg_match('/^[A-Za-z0-9\/]{0,20}$/', $exTable)
                ) {
                    $data['exchange_rate'] = round($exRate, 6);
                    $data['exchange_rate_date'] = $exDate;
                    $data['exchange_rate_table'] = $exTable;
                }
            }

            // Calculate VAT and net in PLN (art. 106e ust. 11 ustawy o VAT)
            $rate = (float)($data['exchange_rate'] ?? 0);
            if ($rate > 0) {
                $data['vat_amount_pln'] = round((float)($data['vat_amount'] ?? 0) * $rate, 2);
                $data['net_amount_pln'] = round((float)($data['net_amount'] ?? 0) * $rate, 2);
            }
        }

        // For advance invoices (FV_ZAL): save advance-specific fields
        if ($invoiceType === 'FV_ZAL') {
            $advAmt = !empty($_POST['advance_amount']) ? round((float) $_POST['advance_amount'], 2) : null;
            $data['advance_amount'] = ($advAmt !== null && $advAmt > 0 && $advAmt <= 999999999.99) ? $advAmt : null;
            $data['advance_order_description'] = trim($_POST['advance_order_description'] ?? '');
        }

        // For final invoices (FV_KON): save related advance invoice IDs
        if ($invoiceType === 'FV_KON') {
            $relatedIds = $_POST['related_advance_ids'] ?? '[]';
            if (is_string($relatedIds)) {
                $relatedIds = json_decode($relatedIds, true) ?: [];
            }
            $data['related_advance_ids'] = array_map('intval', $relatedIds);
        }

        // Block self-invoicing: seller NIP must differ from buyer NIP
        $buyerNipClean = preg_replace('/[^0-9]/', '', $data['buyer_nip']);
        $sellerNipClean = preg_replace('/[^0-9]/', '', $data['seller_nip']);
        if ($buyerNipClean !== '' && $sellerNipClean !== '' && $buyerNipClean === $sellerNipClean) {
            Session::flash('error', 'Nie można wystawić faktury na samego siebie — NIP nabywcy jest taki sam jak NIP sprzedawcy.');
            $this->redirect("/client/sales/{$id}/edit");
            return;
        }

        // Block invoice without bank account when payment is bank transfer
        $paymentMethod = $data['payment_method'] ?? 'przelew';
        if ($paymentMethod === 'przelew' && empty($bankAccountNumber)) {
            Session::flash('error', 'Brak wskazanego rachunku bankowego — uzupełnij w panelu klienta.');
            $this->redirect("/client/sales/{$id}/edit");
            return;
        }
        IssuedInvoice::update($id, $data, IssuedInvoice::FILLABLE);

        Session::flash('success', Language::get($action === 'issue' ? 'invoice_issued' : 'invoice_saved'));
        header('Location: /client/sales/' . $id);
        exit;
    }

    public function issuedInvoiceUpo(int $id): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = (int) Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            Session::flash('error', 'Faktura nie znaleziona');
            header('Location: /client/sales'); exit;
        }

        // Check if UPO download is enabled
        $ksefConfig = KsefConfig::findByClientId($clientId);
        if (!($ksefConfig['upo_enabled'] ?? true)) {
            Session::flash('error', 'Pobieranie UPO jest wyłączone w konfiguracji KSeF');
            header('Location: /client/sales/' . $id); exit;
        }

        $ksefRef = $invoice['ksef_reference_number'] ?? '';
        if (empty($ksefRef)) {
            Session::flash('error', 'Faktura nie została wysłana do KSeF — brak numeru referencyjnego');
            header('Location: /client/sales/' . $id); exit;
        }

        // Check if UPO already saved locally
        $upoPath = $invoice['ksef_upo_path'] ?? '';
        if (!empty($upoPath)) {
            // Try both absolute and relative paths
            $absPath = $upoPath;
            if (!str_starts_with($upoPath, '/')) {
                $absPath = __DIR__ . '/../../' . $upoPath;
            }
            if (file_exists($absPath)) {
                \App\Models\KsefOperationLog::log($clientId, 'upo_download', 'success', 'client', $clientId,
                    "UPO from cache: {$upoPath}", null, null, $ksefRef);
                while (ob_get_level()) ob_end_clean();
                header('Content-Type: application/xml');
                header('Content-Disposition: attachment; filename="UPO_' . str_replace('/', '_', $invoice['invoice_number']) . '.xml"');
                readfile($absPath);
                exit;
            }
            // Cached path is stale — clear it and re-download
            IssuedInvoice::updateKsefUpo($id, '');
        }

        // Download UPO from KSeF
        $client = Client::findById($clientId);
        $ksef = KsefApiService::forClient($client);
        $ksef->enableLogging('upo_' . $id . '_' . date('Ymd_His'));
        $ksef->setPerformer('client', $clientId);

        if (!$ksef->isConfigured()) {
            \App\Models\KsefOperationLog::log($clientId, 'upo_download', 'failed', 'client', $clientId,
                "Invoice #{$id}, KSeF ref: {$ksefRef}", null, 'KSeF nie jest skonfigurowany', $ksefRef);
            Session::flash('error', 'KSeF nie jest skonfigurowany — sprawdź konfigurację w ustawieniach');
            header('Location: /client/sales/' . $id); exit;
        }

        $sessionRef = $invoice['ksef_session_ref'] ?? '';
        $startTime = microtime(true);

        // Diagnose: what reference do we have?
        $diagInfo = [
            'invoice_id' => $id,
            'ksef_reference_number' => $ksefRef,
            'ksef_session_ref' => $sessionRef,
            'ksef_status' => $invoice['ksef_status'] ?? 'unknown',
            'ksef_sent_at' => $invoice['ksef_sent_at'] ?? 'unknown',
            'ksef_upo_path' => $invoice['ksef_upo_path'] ?? 'empty',
        ];
        $requestSummary = json_encode($diagInfo, JSON_UNESCAPED_UNICODE);

        // Pass both refs — downloadUpo will try session ref first, then ksef ref
        $primaryRef = !empty($sessionRef) ? $sessionRef : $ksefRef;

        if (empty($primaryRef)) {
            $errMsg = 'Brak session_ref i ksef_reference — nie można pobrać UPO';
            \App\Models\KsefOperationLog::log($clientId, 'upo_download', 'failed', 'client', $clientId,
                $requestSummary, null, $errMsg, $ksefRef);
            Session::flash('error', $errMsg);
            header('Location: /client/sales/' . $id); exit;
        }

        $upoResult = $ksef->downloadUpo($primaryRef, $ksefRef);
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $logSessionId = $ksef->getLogger() ? $ksef->getLogger()->getSessionId() : null;

        if (!$upoResult || empty($upoResult['content'])) {
            $errMsg = 'Nie udało się pobrać UPO z KSeF';
            $details = [];
            if (empty($sessionRef)) {
                $details[] = 'Brak ksef_session_ref w bazie — użyto ksef_reference_number jako fallback';
            }
            if (!empty($upoResult['error'])) {
                $details[] = 'Błąd API: ' . $upoResult['error'];
            }
            if ($logSessionId) {
                $details[] = 'Log sesji: ' . $logSessionId;
            }
            $responseSummary = !empty($details) ? implode('; ', $details) : 'downloadUpo() zwróciło null';

            \App\Models\KsefOperationLog::log($clientId, 'upo_download', 'failed', 'client', $clientId,
                $requestSummary, $responseSummary, $errMsg, $ksefRef, $durationMs);

            $flashMsg = $errMsg . '. ';
            if (empty($sessionRef)) {
                $flashMsg .= 'Brak identyfikatora sesji KSeF (session_ref). ';
            }
            if ($logSessionId) {
                $flashMsg .= 'Szczegóły w logach debugowania KSeF (sesja: ' . $logSessionId . ')';
            } else {
                $flashMsg .= 'Sprawdź logi debugowania KSeF.';
            }
            Session::flash('error', $flashMsg);
            header('Location: /client/sales/' . $id); exit;
        }

        // Save locally for future downloads
        $upoDir = __DIR__ . '/../../storage/ksef_upo';
        @mkdir($upoDir, 0755, true);
        $filename = 'upo_' . $id . '_' . date('Ymd_His') . '.xml';
        $savePath = $upoDir . '/' . $filename;
        file_put_contents($savePath, $upoResult['content']);
        IssuedInvoice::updateKsefUpo($id, $savePath);

        \App\Models\KsefOperationLog::log($clientId, 'upo_download', 'success', 'client', $clientId,
            $requestSummary, 'UPO saved: ' . $filename . ' (' . strlen($upoResult['content']) . ' bytes)',
            null, $ksefRef, $durationMs);

        // Serve download
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="UPO_' . str_replace('/', '_', $invoice['invoice_number']) . '.xml"');
        echo $upoResult['content'];
        exit;
    }

    public function issuedInvoiceDelete(int $id): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/sales'); return; }
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            header('Location: /client/sales');
            exit;
        }

        // Allow deletion if not sent to KSeF (draft or issued but not yet sent)
        $ksefStatus = $invoice['ksef_status'] ?? 'none';
        $sentToKsef = !empty($ksefStatus) && $ksefStatus !== 'none' && $ksefStatus !== 'error';
        if ($sentToKsef) {
            Session::flash('error', Language::get('cannot_delete_ksef_sent') ?: 'Nie mozna usunac faktury wyslanej do KSeF');
            header('Location: /client/sales');
            exit;
        }

        IssuedInvoice::delete($id);
        Session::flash('success', Language::get('invoice_deleted'));
        header('Location: /client/sales');
        exit;
    }

    private function parseLineItems(): array
    {
        $items = [];
        $rawItems = $_POST['items'] ?? [];
        if (count($rawItems) > 200) {
            $rawItems = array_slice($rawItems, 0, 200);
        }
        foreach ($rawItems as $item) {
            $name = trim($item['name'] ?? '');
            if (empty($name)) continue;

            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $vatRate = $item['vat_rate'] ?? '23';
            $net = round($qty * $price, 2);
            $vatPercent = is_numeric($vatRate) ? (int) $vatRate / 100 : 0;
            $vat = round($net * $vatPercent, 2);

            $lineItem = [
                'name' => $name,
                'quantity' => $qty,
                'unit' => $item['unit'] ?? 'szt.',
                'unit_price' => $price,
                'vat_rate' => $vatRate,
                'net' => $net,
                'vat' => $vat,
                'gross' => $net + $vat,
            ];
            if (!empty($item['gtu'])) {
                $lineItem['gtu'] = $item['gtu'];
            }
            $items[] = $lineItem;
        }
        return $items;
    }

    private function calculateVatDetails(array $items): array
    {
        $details = [];
        foreach ($items as $item) {
            $rate = $item['vat_rate'];
            if (!isset($details[$rate])) {
                $details[$rate] = ['rate' => $rate, 'net' => 0, 'vat' => 0];
            }
            $details[$rate]['net'] += $item['net'];
            $details[$rate]['vat'] += $item['vat'];
        }
        return array_values($details);
    }

    private function calculateTotals(array $items): array
    {
        $net = 0;
        $vat = 0;
        foreach ($items as $item) {
            $net += $item['net'];
            $vat += $item['vat'];
        }
        return ['net' => round($net, 2), 'vat' => round($vat, 2), 'gross' => round($net + $vat, 2)];
    }

    private static function buildPayerData(): ?string
    {
        $payerName = trim($_POST['payer_name'] ?? '');
        $payerNip = trim($_POST['payer_nip'] ?? '');
        $payerAddress = trim($_POST['payer_address'] ?? '');
        if ($payerName === '' && $payerNip === '' && $payerAddress === '') {
            return null;
        }
        return json_encode([
            'payer_name' => $payerName,
            'payer_nip' => $payerNip,
            'payer_address' => $payerAddress,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * AJAX endpoint: get unlinked advance invoices (FV_ZAL) for a contractor.
     */
    public function getAdvanceInvoices(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $contractorId = (int) ($_GET['contractor_id'] ?? 0);

        if (!$contractorId) {
            $this->json([]);
            return;
        }

        // Verify contractor belongs to client
        $contractor = Contractor::findById($contractorId);
        if (!$contractor || (int) $contractor['client_id'] !== $clientId) {
            $this->json([]);
            return;
        }

        $invoices = IssuedInvoice::findUnlinkedAdvanceInvoices($clientId, $contractorId);
        $this->json($invoices);
    }

    public function issuedInvoiceView(int $id): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            header('Location: /client/sales');
            exit;
        }

        $client = Client::findById($clientId);
        $contractor = !empty($invoice['contractor_id']) ? Contractor::findById((int) $invoice['contractor_id']) : null;
        $ksefConfig = KsefConfig::findByClientId($clientId);

        $this->render('client/sales_view', [
            'invoice' => $invoice,
            'canSendInvoices' => (bool) ($client['can_send_invoices'] ?? 0),
            'contractorEmail' => $contractor['email'] ?? '',
            'upoEnabled' => (bool) ($ksefConfig['upo_enabled'] ?? true),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function issuedInvoiceIssue(int $id): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/sales'); return; }
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId || $invoice['status'] !== 'draft') {
            header('Location: /client/sales');
            exit;
        }

        $invoiceType = $invoice['invoice_type'] ?? 'FV';
        $invoiceNumber = CompanyProfile::getAndIncrementInvoiceNumber($clientId, $invoiceType);
        IssuedInvoice::update($id, [
            'invoice_number' => $invoiceNumber,
            'status' => 'issued',
        ]);

        Session::flash('success', Language::get('invoice_issued'));
        header('Location: /client/sales/' . $id);
        exit;
    }

    public function issuedInvoiceSendKsef(int $id): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
            return;
        }
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            $this->json(['error' => 'Invoice not found'], 404);
            return;
        }

        if ($invoice['status'] === 'draft') {
            $this->json(['error' => 'Cannot send draft invoice'], 400);
            return;
        }

        // Proforma invoices cannot be sent to KSeF
        if (($invoice['invoice_type'] ?? 'FV') === 'FP') {
            $this->json(['error' => 'Faktura proforma nie jest wysyłana do KSeF'], 400);
            return;
        }

        if ($invoice['ksef_status'] === 'sent' || $invoice['ksef_status'] === 'pending') {
            $this->json(['error' => 'Already sent or pending'], 400);
            return;
        }

        IssuedInvoice::updateKsefStatus($id, 'pending');

        $jobId = self::launchKsefBatchSendJob([$id], $clientId);

        $this->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => 'Wysylanie faktury do KSeF w tle...',
        ]);
    }

    public function bulkSendKsef(): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $clientId = Session::get('client_id');

        // Get selected invoice IDs from POST, or send all eligible
        $selectedIds = $_POST['invoice_ids'] ?? [];
        if (is_string($selectedIds)) {
            $selectedIds = json_decode($selectedIds, true) ?: [];
        }

        if (empty($selectedIds)) {
            // Send all issued invoices not yet sent to KSeF (excluding proforma)
            $allInvoices = IssuedInvoice::findByClient($clientId, 'issued');
            $selectedIds = [];
            foreach ($allInvoices as $inv) {
                if (($inv['invoice_type'] ?? 'FV') === 'FP') continue; // Proforma — skip KSeF
                if (empty($inv['ksef_status']) || $inv['ksef_status'] === 'none' || $inv['ksef_status'] === 'error') {
                    $selectedIds[] = (int)$inv['id'];
                }
            }
        }

        if (empty($selectedIds)) {
            $this->json(['error' => 'Brak faktur do wyslania', 'jobs' => []]);
            return;
        }

        // Verify ownership and eligibility, collect valid IDs
        $validIds = [];
        $invoiceNumbers = [];
        foreach ($selectedIds as $invoiceId) {
            $invoiceId = (int)$invoiceId;
            $invoice = IssuedInvoice::findById($invoiceId);

            if (!$invoice || (int)$invoice['client_id'] !== $clientId) continue;
            if ($invoice['status'] === 'draft') continue;
            if (($invoice['invoice_type'] ?? 'FV') === 'FP') continue; // Proforma — skip KSeF
            if ($invoice['ksef_status'] === 'sent' || $invoice['ksef_status'] === 'pending') continue;

            IssuedInvoice::updateKsefStatus($invoiceId, 'pending');
            $validIds[] = $invoiceId;
            $invoiceNumbers[$invoiceId] = $invoice['invoice_number'];
        }

        if (empty($validIds)) {
            $this->json(['error' => 'Brak faktur do wyslania', 'jobs' => []]);
            return;
        }

        // Launch ONE batch job for all invoices (single KSeF session)
        $jobId = self::launchKsefBatchSendJob($validIds, $clientId);

        $this->json([
            'success' => true,
            'message' => 'Wysylka ' . count($validIds) . ' faktur do KSeF uruchomiona w tle',
            'batch_job_id' => $jobId,
            'invoice_count' => count($validIds),
            'invoice_numbers' => $invoiceNumbers,
        ]);
    }

    public function ksefSendStatus(): void
    {
        ModuleAccess::requireModule('sales');
        $jobId = $_GET['job_id'] ?? '';

        // Support multiple job IDs (comma-separated)
        if (str_contains($jobId, ',')) {
            $jobIds = explode(',', $jobId);
            $results = [];
            foreach ($jobIds as $jid) {
                $jid = trim($jid);
                $status = self::checkKsefSendStatus($jid);
                if ($status) {
                    $results[$jid] = $status;
                }
            }
            $this->json($results);
            return;
        }

        $result = self::checkKsefSendStatus($jobId);
        if ($result === null) {
            $this->json(['error' => 'Job not found'], 404);
            return;
        }
        $this->json($result);
    }

    public function ksefBackfill(): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        if (!$client) {
            $this->json(['error' => 'Klient nie znaleziony'], 404);
            return;
        }

        $ksef = KsefApiService::forClient($client);
        if (!$ksef->isConfigured()) {
            $this->json(['error' => 'KSeF nie jest skonfigurowane dla tego klienta'], 400);
            return;
        }

        $ksef->enableLogging();

        if (!$ksef->authenticate()) {
            $this->json(['error' => 'Autentykacja KSeF nie powiodła się'], 500);
            return;
        }

        $dateFrom = $_POST['date_from'] ?? null;
        $dateTo = $_POST['date_to'] ?? null;

        // Phase 1: Recover missing KSeF reference numbers
        $result = $ksef->backfillKsefNumbers($clientId, $dateFrom ?: null, $dateTo ?: null);

        // Phase 2: For invoices with status 'sent' — try to confirm + download UPO
        // Limit to 10 per request to avoid gateway timeout
        $db = \App\Core\Database::getInstance();
        $startTime = microtime(true);
        $maxSeconds = 25; // stay well under 30s gateway timeout

        // First: bulk-update invoices that already have UPO but status still 'sent'
        $bulkStmt = $db->query(
            "UPDATE issued_invoices SET ksef_status = 'accepted'
             WHERE client_id = ? AND ksef_status = 'sent'
               AND ksef_upo_path IS NOT NULL AND ksef_upo_path != ''",
            [$clientId]
        );
        $bulkUpdated = $bulkStmt->rowCount();

        // Then: find invoices needing UPO download (limit 10)
        $needUpo = $db->fetchAll(
            "SELECT id, ksef_reference_number, ksef_session_ref
             FROM issued_invoices
             WHERE client_id = ?
               AND ksef_status IN ('sent', 'pending')
               AND ksef_reference_number IS NOT NULL AND ksef_reference_number != ''
               AND (ksef_upo_path IS NULL OR ksef_upo_path = '')
             ORDER BY issue_date ASC
             LIMIT 10",
            [$clientId]
        );

        $upoDownloaded = 0;
        $upoFailed = 0;
        $upoErrors = [];
        $upoDir = __DIR__ . '/../../storage/ksef_send/upo';

        foreach ($needUpo as $inv) {
            // Time guard — stop if approaching timeout
            if ((microtime(true) - $startTime) > $maxSeconds) break;

            $ksefRef = $inv['ksef_reference_number'];
            $invId = (int)$inv['id'];

            $upoResult = $ksef->downloadUpo($inv['ksef_session_ref'] ?? '', $ksefRef);
            if (!empty($upoResult['content'])) {
                if (!is_dir($upoDir)) @mkdir($upoDir, 0755, true);
                $upoFile = $upoDir . '/upo_' . $invId . '_' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $ksefRef) . '.xml';
                file_put_contents($upoFile, $upoResult['content']);

                $relPath = 'storage/ksef_send/upo/' . basename($upoFile);
                $db->query("UPDATE issued_invoices SET ksef_upo_path = ?, ksef_status = 'accepted' WHERE id = ?", [$relPath, $invId]);
                $upoDownloaded++;
            } else {
                $upoFailed++;
                // UPO not available — if invoice has valid KSeF ref, mark as accepted anyway
                // (KSeF accepted it if it assigned a reference number)
                if (!empty($ksefRef) && strlen($ksefRef) > 20) {
                    $db->query("UPDATE issued_invoices SET ksef_status = 'accepted' WHERE id = ? AND ksef_status = 'sent'", [$invId]);
                    $upoDownloaded++; // count as status updated
                }
                if ($upoFailed <= 3) {
                    $upoErrors[] = "FV #{$invId}: UPO niedostępne" . (!empty($upoResult['error']) ? ' — ' . $upoResult['error'] : '');
                }
            }
        }

        $statusUpdated = $bulkUpdated + $upoDownloaded;

        // Also: mark ALL invoices with valid KSeF ref (>20 chars) as accepted
        // A valid full KSeF reference number means KSeF accepted the invoice
        $acceptStmt = $db->query(
            "UPDATE issued_invoices SET ksef_status = 'accepted'
             WHERE client_id = ? AND ksef_status = 'sent'
               AND ksef_reference_number IS NOT NULL
               AND LENGTH(ksef_reference_number) > 20",
            [$clientId]
        );
        $autoAccepted = $acceptStmt->rowCount();
        $statusUpdated += $autoAccepted;

        $remaining = $db->fetchOne(
            "SELECT COUNT(*) as cnt FROM issued_invoices
             WHERE client_id = ? AND ksef_status IN ('sent','pending')
               AND ksef_reference_number IS NOT NULL AND ksef_reference_number != ''",
            [$clientId]
        )['cnt'] ?? 0;

        $msgs = [];
        if ($result['recovered'] > 0) $msgs[] = "Odzyskano numery KSeF: {$result['recovered']}";
        if ($upoDownloaded > 0) $msgs[] = "Pobrano UPO / potwierdzono: {$upoDownloaded}";
        if ($autoAccepted > 0) $msgs[] = "Auto-potwierdzono (numer KSeF): {$autoAccepted}";
        if ($statusUpdated > 0) $msgs[] = "Zaktualizowano statusy: {$statusUpdated}";
        if ($remaining > 0) $msgs[] = "Pozostało: {$remaining}";
        if (empty($msgs)) $msgs[] = 'Wszystkie faktury mają aktualne statusy KSeF.';

        $allErrors = array_merge($result['errors'], $upoErrors);

        $this->json([
            'success' => true,
            'recovered' => $result['recovered'],
            'upo_downloaded' => $upoDownloaded,
            'status_updated' => $statusUpdated,
            'auto_accepted' => $autoAccepted,
            'remaining' => (int)$remaining,
            'failed' => $result['failed'],
            'errors' => $allErrors,
            'message' => implode("\n", $msgs),
        ]);
    }

    public function ksefBackfillPurchase(): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        if (!$client) {
            $this->json(['error' => 'Klient nie znaleziony'], 404);
            return;
        }

        $ksef = KsefApiService::forClient($client);
        if (!$ksef->isConfigured()) {
            $this->json(['error' => 'KSeF nie jest skonfigurowane dla tego klienta'], 400);
            return;
        }

        $ksef->enableLogging();

        if (!$ksef->authenticate()) {
            $this->json(['error' => 'Autentykacja KSeF nie powiodła się'], 500);
            return;
        }

        $dateFrom = $_POST['date_from'] ?? null;
        $dateTo = $_POST['date_to'] ?? null;

        $result = $ksef->backfillPurchaseKsefNumbers($clientId, $dateFrom ?: null, $dateTo ?: null);

        $this->json([
            'success' => true,
            'recovered' => $result['recovered'],
            'failed' => $result['failed'],
            'total' => $result['total'],
            'errors' => $result['errors'],
            'message' => $result['recovered'] > 0
                ? "Uzupełniono numery KSeF dla {$result['recovered']} z {$result['total']} faktur zakupowych."
                : ($result['total'] > 0
                    ? "Nie udało się dopasować numerów KSeF dla {$result['total']} faktur."
                    : 'Wszystkie faktury zakupowe mają już numery KSeF.'),
        ]);
    }

    public function whitelistRecheck(): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $clientId = Session::get('client_id');
        $batchId = $this->sanitizeInt($_POST['batch_id'] ?? 0);

        $batch = InvoiceBatch::findById($batchId);
        if (!$batch || (int) $batch['client_id'] !== $clientId) {
            $this->json(['error' => 'Brak dostępu'], 403);
            return;
        }

        $invoices = Invoice::findByBatch($batchId);
        $checked = 0;
        $failed = 0;

        foreach ($invoices as $inv) {
            if (empty($inv['ksef_xml']) || empty($inv['seller_nip'])) {
                continue;
            }

            $parsed = KsefApiService::parseKsefFaXml($inv['ksef_xml']);
            if (!empty($parsed['error'])) {
                continue;
            }

            $bankAccount = $parsed['payment']['bank_account'] ?? '';
            if (empty($bankAccount)) {
                continue;
            }

            $formCode = $parsed['payment']['form_code'] ?? '';
            if ($formCode !== '' && $formCode !== '6') {
                continue;
            }

            $checked++;
            try {
                $result = WhiteListService::verifyNipBankAccount($inv['seller_nip'], $bankAccount);
                $newFlag = $result['verified'] ? 0 : 1;
                if ((int) ($inv['whitelist_failed'] ?? 0) !== $newFlag) {
                    Invoice::updateFields((int) $inv['id'], ['whitelist_failed' => $newFlag]);
                    if ($newFlag === 1) {
                        $failed++;
                    }
                } elseif ($newFlag === 1) {
                    $failed++;
                }
            } catch (\Exception $e) {
                // skip on API error
            }
        }

        $this->json([
            'success' => true,
            'checked' => $checked,
            'failed' => $failed,
            'message' => "Sprawdzono {$checked} faktur. Brak na białej liście: {$failed}.",
        ]);
    }

    public function issuedInvoiceDuplicate(int $id): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/sales'); return; }
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            header('Location: /client/sales');
            exit;
        }

        $newData = [
            'client_id' => $clientId,
            'contractor_id' => $invoice['contractor_id'],
            'invoice_type' => 'FV',
            'invoice_number' => 'DRAFT-' . date('YmdHis'),
            'issue_date' => date('Y-m-d'),
            'sale_date' => date('Y-m-d'),
            'due_date' => $invoice['due_date'] ? date('Y-m-d', strtotime('+' . max(0, (int) ((strtotime($invoice['due_date']) - strtotime($invoice['issue_date'])) / 86400)) . ' days')) : null,
            'seller_nip' => $invoice['seller_nip'],
            'seller_name' => $invoice['seller_name'],
            'seller_address' => $invoice['seller_address'],
            'buyer_nip' => $invoice['buyer_nip'],
            'buyer_name' => $invoice['buyer_name'],
            'buyer_address' => $invoice['buyer_address'],
            'currency' => $invoice['currency'],
            'net_amount' => $invoice['net_amount'],
            'vat_amount' => $invoice['vat_amount'],
            'gross_amount' => $invoice['gross_amount'],
            'line_items' => $invoice['line_items'],
            'vat_details' => $invoice['vat_details'],
            'payment_method' => $invoice['payment_method'],
            'bank_account_id' => $invoice['bank_account_id'],
            'bank_account_number' => $invoice['bank_account_number'],
            'bank_name' => $invoice['bank_name'],
            'notes' => $invoice['notes'],
            'is_split_payment' => $invoice['is_split_payment'] ?? 0,
            'payer_data' => $invoice['payer_data'] ?? null,
            'status' => 'draft',
        ];

        $newId = IssuedInvoice::create($newData);

        Session::flash('success', Language::get('invoice_duplicated'));
        header('Location: /client/sales/' . $newId . '/edit');
        exit;
    }

    public function issuedInvoiceCorrection(int $id): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId || $invoice['status'] === 'draft') {
            header('Location: /client/sales');
            exit;
        }

        // Correction only for invoices sent to KSeF with a KSeF reference number
        $ksefStatus = $invoice['ksef_status'] ?? 'none';
        $hasKsefNumber = !empty($invoice['ksef_reference_number']);
        $sentToKsef = in_array($ksefStatus, ['sent', 'accepted']);

        if (!$sentToKsef || !$hasKsefNumber) {
            Session::flash('error', 'Korekta może być wystawiona tylko do faktury wysłanej do KSeF z numerem KSeF. Faktury niewysłane do KSeF można edytować bezpośrednio.');
            header('Location: /client/sales/' . $id);
            exit;
        }

        $profile = CompanyProfile::findByClient($clientId) ?? [];
        $bankAccounts = BankAccount::findByClient($clientId);
        $services = CompanyService::findByClient($clientId);

        $lineItems = $invoice['line_items'] ?? '[]';
        if (is_string($lineItems)) $lineItems = json_decode($lineItems, true) ?: [];

        // Pre-fill correction data
        $correctionInvoice = $invoice;
        $correctionInvoice['id'] = null;
        $correctionInvoice['invoice_type'] = 'FV_KOR';
        $correctionInvoice['invoice_number'] = '';
        $correctionInvoice['issue_date'] = date('Y-m-d');
        $correctionInvoice['status'] = 'draft';
        $correctionInvoice['corrected_invoice_id'] = $id;
        $correctionInvoice['correction_reason'] = '';
        $correctionInvoice['ksef_reference_number'] = '';
        $correctionInvoice['ksef_status'] = 'none';

        $this->render('client/sales_correction', [
            'isEdit' => false,
            'invoice' => $correctionInvoice,
            'originalInvoice' => $invoice,
            'lineItems' => $lineItems,
            'profile' => $profile,
            'bankAccounts' => $bankAccounts,
            'services' => $services,
            'error' => Session::getFlash('error'),
        ]);
    }

    public function issuedInvoicePdf(int $id): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $invoice = IssuedInvoice::findById($id);

        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            header('Location: /client/sales');
            exit;
        }

        try {
            $path = InvoicePdfService::generate($id);

            while (ob_get_level()) ob_end_clean();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            header('Location: /client/sales/' . $id);
            exit;
        }
    }

    public function salesDashboard(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $counts = IssuedInvoice::countByClient($clientId);
        $monthlySales = IssuedInvoice::getMonthlySales($clientId, 12);
        $topBuyers = IssuedInvoice::getTopBuyers($clientId, 10);
        $vatSummary = IssuedInvoice::getVatSummary($clientId, (int) date('n'), (int) date('Y'));

        // Total revenue
        $totalRevenue = 0;
        foreach ($monthlySales as $ms) {
            $totalRevenue += (float) $ms['gross'];
        }

        // This month's revenue
        $monthlyRevenue = 0;
        foreach ($monthlySales as $ms) {
            if ((int) $ms['month'] === (int) date('n') && (int) $ms['year'] === (int) date('Y')) {
                $monthlyRevenue = (float) $ms['gross'];
                break;
            }
        }

        $this->render('client/sales_dashboard', [
            'counts' => $counts,
            'monthlySales' => $monthlySales,
            'topBuyers' => $topBuyers,
            'vatSummary' => $vatSummary,
            'totalRevenue' => $totalRevenue,
            'monthlyRevenue' => $monthlyRevenue,
        ]);
    }

    public function salesReport(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $month = (int) ($_GET['month'] ?? date('n'));
        $year = (int) ($_GET['year'] ?? date('Y'));

        try {
            $path = SalesReportService::generateMonthlyReport($clientId, $month, $year);

            while (ob_get_level()) ob_end_clean();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            header('Location: /client/sales/dashboard');
            exit;
        }
    }

    public function salesJpk(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $month = (int) ($_GET['month'] ?? date('n'));
        $year = (int) ($_GET['year'] ?? date('Y'));

        try {
            $path = JpkVat7Service::generateForSales($clientId, $year, $month);

            while (ob_get_level()) ob_end_clean();

            header('Content-Type: text/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            header('Location: /client/sales');
            exit;
        }
    }

    // =========================================================================
    // PDF Export endpoints
    // =========================================================================

    public function purchaseInvoicePdf(): void
    {
        $invoiceId = (int) ($_GET['id'] ?? 0);
        if (!$invoiceId) {
            http_response_code(400);
            echo 'Missing invoice ID';
            exit;
        }

        $clientId = Session::get('client_id');
        $invoice = Invoice::findById($invoiceId);
        if (!$invoice || (int)$invoice['client_id'] !== $clientId) {
            http_response_code(404);
            echo 'Invoice not found';
            exit;
        }

        try {
            $path = PurchaseInvoicePdfService::generate($invoiceId);

            while (ob_get_level()) ob_end_clean();

            $filename = 'FZ_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice['invoice_number']) . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'PDF generation error: ' . $e->getMessage();
            exit;
        }
    }

    public function purchaseInvoicesBulkPdf(): void
    {
        $clientId = Session::get('client_id');
        $ids = $_POST['invoice_ids'] ?? '';
        $layout = $_POST['layout'] ?? 'vertical';

        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?: [];
        }
        $ids = array_map('intval', array_filter($ids));

        if (empty($ids)) {
            Session::flash('error', 'Nie wybrano faktur');
            header('Location: ' . ($_POST['return_url'] ?? '/client/invoices'));
            exit;
        }

        // Verify all invoices belong to this client
        $validIds = [];
        foreach ($ids as $id) {
            $inv = Invoice::findById($id);
            if ($inv && (int)$inv['client_id'] === $clientId) {
                $validIds[] = $id;
            }
        }

        if (empty($validIds)) {
            Session::flash('error', 'Brak uprawnień do wybranych faktur');
            header('Location: ' . ($_POST['return_url'] ?? '/client/invoices'));
            exit;
        }

        try {
            if ($layout === 'horizontal') {
                $path = PurchaseInvoicePdfService::generateSummaryTable($validIds);
                $filename = 'zestawienie_zakup_' . date('Ymd_Hi') . '.pdf';
            } else {
                $path = PurchaseInvoicePdfService::generateBulk($validIds);
                $filename = 'faktury_zakup_' . date('Ymd_Hi') . '.pdf';
            }

            while (ob_get_level()) ob_end_clean();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'Błąd generowania PDF: ' . $e->getMessage());
            header('Location: ' . ($_POST['return_url'] ?? '/client/invoices'));
            exit;
        }
    }

    public function salesBulkPdf(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $ids = $_POST['invoice_ids'] ?? '';
        $layout = $_POST['layout'] ?? 'vertical';

        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?: [];
        }
        $ids = array_map('intval', array_filter($ids));

        if (empty($ids)) {
            Session::flash('error', 'Nie wybrano faktur');
            header('Location: /client/sales');
            exit;
        }

        // Verify all invoices belong to this client
        $validIds = [];
        foreach ($ids as $id) {
            $inv = IssuedInvoice::findById($id);
            if ($inv && (int)$inv['client_id'] === $clientId) {
                $validIds[] = $id;
            }
        }

        if (empty($validIds)) {
            Session::flash('error', 'Brak uprawnień do wybranych faktur');
            header('Location: /client/sales');
            exit;
        }

        try {
            if ($layout === 'horizontal') {
                $path = InvoicePdfService::generateSummaryTable($validIds);
                $filename = 'zestawienie_sprzedaz_' . date('Ymd_Hi') . '.pdf';
            } else {
                $path = InvoicePdfService::generateBulk($validIds);
                $filename = 'faktury_sprzedaz_' . date('Ymd_Hi') . '.pdf';
            }

            while (ob_get_level()) ob_end_clean();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'Błąd generowania PDF: ' . $e->getMessage());
            header('Location: /client/sales');
            exit;
        }
    }

    // ── Bank Export (Elixir-O) ────────────────────────

    public function bankExport(int $batchId): void
    {
        $clientId = (int) Session::get('client_id');
        $batch = InvoiceBatch::findById($batchId);
        if (!$batch || (int) $batch['client_id'] !== $clientId) {
            Session::flash('error', 'batch_not_found');
            $this->redirect('/client');
            return;
        }

        // Get unpaid accepted invoices
        $db = \App\Core\Database::getInstance();
        $invoices = $db->fetchAll(
            "SELECT id, invoice_number, seller_name, seller_nip, gross_amount, currency, payment_due_date,
                    ksef_reference_number, ksef_xml, is_paid, payment_method_detected
             FROM invoices
             WHERE batch_id = ? AND client_id = ? AND status = 'accepted' AND is_paid IN (0, 2)
             ORDER BY currency, issue_date, invoice_number",
            [$batchId, $clientId]
        );

        // Parse XML to check bank account availability
        foreach ($invoices as &$inv) {
            $inv['has_bank_account'] = false;
            $inv['has_ksef_ref'] = !empty($inv['ksef_reference_number']);
            if (!empty($inv['ksef_xml'])) {
                $parsed = KsefApiService::parseKsefFaXml($inv['ksef_xml']);
                $inv['has_bank_account'] = !empty($parsed['payment']['bank_account']);
                $inv['bank_account_preview'] = $parsed['payment']['bank_account'] ?? '';
            }
            unset($inv['ksef_xml']);
        }
        unset($inv);

        $bankAccounts = BankAccount::findByClient($clientId);

        // Detect currencies present in invoices
        $currencies = array_unique(array_column($invoices, 'currency'));
        $accountsByCurrency = [];
        foreach ($bankAccounts as $ba) {
            $cur = strtoupper($ba['currency'] ?? 'PLN');
            if (!isset($accountsByCurrency[$cur])) {
                $accountsByCurrency[$cur] = $ba;
            }
        }

        $this->render('client/bank_export', [
            'batch' => $batch,
            'invoices' => $invoices,
            'bankAccounts' => $bankAccounts,
            'currencies' => $currencies,
            'accountsByCurrency' => $accountsByCurrency,
            'results' => null,
            'csrf' => Session::generateCsrfToken(),
        ]);
    }

    public function bankExportGenerate(): void
    {
        Session::validateCsrfToken($_POST['csrf_token'] ?? '');
        $clientId = (int) Session::get('client_id');

        $invoiceIds = $_POST['invoice_ids'] ?? [];
        if (is_string($invoiceIds)) {
            $invoiceIds = json_decode($invoiceIds, true) ?: [];
        }
        $invoiceIds = array_map('intval', array_filter($invoiceIds));

        $bankAccountId = (int) ($_POST['bank_account_id'] ?? 0);
        $executionDate = $_POST['execution_date'] ?? date('Y-m-d');
        $batchId = (int) ($_POST['batch_id'] ?? 0);

        if (empty($invoiceIds)) {
            Session::flash('error', 'Nie wybrano żadnych faktur');
            $this->redirect('/client/bank-export/' . $batchId);
            return;
        }

        if ($bankAccountId <= 0) {
            Session::flash('error', 'Nie wybrano konta bankowego zleceniodawcy');
            $this->redirect('/client/bank-export/' . $batchId);
            return;
        }

        // Validate execution date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $executionDate)) {
            $executionDate = date('Y-m-d');
        }

        // Generate export (multi-currency)
        $results = ElixirExportService::generate($clientId, $invoiceIds, $bankAccountId, $executionDate);

        // Log the export
        $pkgSummary = [];
        foreach ($results['packages'] as $cur => $pkg) {
            $pkgSummary[] = $cur . ': ' . count($pkg['verified']) . ' przelewów (' . $pkg['format'] . ')';
        }
        if (!empty($results['manual'])) {
            foreach ($results['manual'] as $cur => $m) {
                $pkgSummary[] = $cur . ': ' . count($m['invoices']) . ' (ręczny przelew)';
            }
        }
        AuditLog::log('client', $clientId, 'bank_export',
            'Eksport bankowy: ' . implode(', ', $pkgSummary) . '. Odrzuconych: ' . count($results['failed']),
            'invoice_batch', $batchId
        );

        // Re-fetch data for the form
        $batch = InvoiceBatch::findById($batchId);
        $db = \App\Core\Database::getInstance();
        $invoices = $db->fetchAll(
            "SELECT id, invoice_number, seller_name, seller_nip, gross_amount, currency, payment_due_date,
                    ksef_reference_number, ksef_xml, is_paid, payment_method_detected
             FROM invoices
             WHERE batch_id = ? AND client_id = ? AND status = 'accepted' AND is_paid IN (0, 2)
             ORDER BY currency, issue_date, invoice_number",
            [$batchId, $clientId]
        );

        foreach ($invoices as &$inv) {
            $inv['has_bank_account'] = false;
            $inv['has_ksef_ref'] = !empty($inv['ksef_reference_number']);
            if (!empty($inv['ksef_xml'])) {
                $parsed = KsefApiService::parseKsefFaXml($inv['ksef_xml']);
                $inv['has_bank_account'] = !empty($parsed['payment']['bank_account']);
                $inv['bank_account_preview'] = $parsed['payment']['bank_account'] ?? '';
            }
            unset($inv['ksef_xml']);
        }
        unset($inv);

        $bankAccounts = BankAccount::findByClient($clientId);
        $currencies = array_unique(array_column($invoices, 'currency'));
        $accountsByCurrency = [];
        foreach ($bankAccounts as $ba) {
            $cur = strtoupper($ba['currency'] ?? 'PLN');
            if (!isset($accountsByCurrency[$cur])) {
                $accountsByCurrency[$cur] = $ba;
            }
        }

        $this->render('client/bank_export', [
            'batch' => $batch,
            'invoices' => $invoices,
            'bankAccounts' => $bankAccounts,
            'currencies' => $currencies,
            'accountsByCurrency' => $accountsByCurrency,
            'results' => $results,
            'csrf' => Session::generateCsrfToken(),
        ]);
    }

    public function bankExportDownload(string $filename): void
    {
        $clientId = (int) Session::get('client_id');
        $client = Client::findById($clientId);
        $nip = $client['nip'] ?? '';

        // Security: filename must contain client NIP and have valid prefix/extension
        $filename = basename($filename);
        $validElixir = str_starts_with($filename, 'elixir_' . $nip . '_') && str_ends_with($filename, '.pli');
        $validSepa = str_starts_with($filename, 'sepa_EUR_' . $nip . '_') && str_ends_with($filename, '.xml');
        if (!$validElixir && !$validSepa) {
            Session::flash('error', 'Nieprawidłowy plik');
            $this->redirect('/client');
            return;
        }

        $filePath = dirname(__DIR__, 2) . '/storage/exports/' . $filename;
        if (!file_exists($filePath)) {
            Session::flash('error', 'Plik nie istnieje');
            $this->redirect('/client');
            return;
        }

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        readfile($filePath);
        flush();
        exit;
    }

    // ── Messages ──────────────────────────────────────

    public function messages(): void
    {
        ModuleAccess::requireModule('messages');
        $clientId = Session::get('client_id');
        $threads = Message::findByClient($clientId);

        foreach ($threads as &$t) {
            $t['sender_name'] = Message::getSenderName($t['sender_type'], (int) $t['sender_id']);
        }
        unset($t);

        $this->render('client/messages', [
            'threads' => $threads,
        ]);
    }

    public function messageThread(int $id): void
    {
        ModuleAccess::requireModule('messages');
        $clientId = Session::get('client_id');
        $root = Message::findById($id);

        if (!$root || $root['parent_id'] !== null || (int) $root['client_id'] !== (int) $clientId) {
            $this->redirect('/client/messages');
            return;
        }

        Message::markReadByClient($id);

        $messages = Message::findThread($id);
        foreach ($messages as &$m) {
            $m['sender_name'] = Message::getSenderName($m['sender_type'], (int) $m['sender_id']);
        }
        unset($m);

        $this->render('client/message_thread', [
            'thread' => $root,
            'messages' => $messages,
        ]);
    }

    public function messageCreate(): void
    {
        ModuleAccess::requireModule('messages');
        if (!$this->validateCsrf()) { $this->redirect('/client/messages'); return; }
        $clientId = Session::get('client_id');

        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $invoiceId = !empty($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : null;

        if ($subject === '' || $body === '') {
            Session::flash('error', 'fill_required_fields');
            $this->redirect('/client/messages');
            return;
        }

        $msgId = Message::create($clientId, 'client', $clientId, $body, $subject, $invoiceId);

        // Handle attachment
        $client = Client::findById($clientId);
        $this->handleMessageAttachment($msgId, $client['nip'] ?? '');

        // Notify office + assigned employees (always email for new thread)
        $this->notifyOfficeAboutMessage($clientId, $subject, 'new_thread');

        Session::flash('success', 'message_sent');
        $this->redirect("/client/messages/{$msgId}");
    }

    public function messageReply(int $id): void
    {
        ModuleAccess::requireModule('messages');
        if (!$this->validateCsrf()) { $this->redirect('/client/messages'); return; }
        $clientId = Session::get('client_id');

        $root = Message::findById($id);
        if (!$root || $root['parent_id'] !== null || (int) $root['client_id'] !== (int) $clientId) {
            $this->redirect('/client/messages');
            return;
        }

        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            $this->redirect("/client/messages/{$id}");
            return;
        }

        $replyId = Message::create($clientId, 'client', $clientId, $body, null, null, null, $id);

        // Handle attachment
        $client = Client::findById($clientId);
        $this->handleMessageAttachment($replyId, $client['nip'] ?? '');

        // Notify office about reply
        $this->notifyOfficeAboutMessage($clientId, $root['subject'] ?? '', 'new_reply');

        $this->redirect("/client/messages/{$id}");
    }

    public function messageNotificationPrefs(): void
    {
        ModuleAccess::requireModule('messages');
        $clientId = Session::get('client_id');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            MessageNotificationPref::savePrefs('client', $clientId, [
                'notify_new_thread' => isset($_POST['notify_new_thread']) ? 1 : 0,
                'notify_new_reply' => isset($_POST['notify_new_reply']) ? 1 : 0,
                'notify_email' => isset($_POST['notify_email']) ? 1 : 0,
            ]);
            Session::flash('success', 'settings_saved');
            $this->redirect('/client/messages/preferences');
            return;
        }

        $prefs = MessageNotificationPref::getPrefs('client', $clientId);
        $this->render('client/message_prefs', ['prefs' => $prefs]);
    }

    // ── Tasks ─────────────────────────────────────────

    public function tasks(): void
    {
        ModuleAccess::requireModule('tasks');
        $clientId = Session::get('client_id');
        $tasks = ClientTask::findByClient($clientId);
        $counts = ClientTask::countByClientAndStatus($clientId);

        $this->render('client/tasks', [
            'tasks' => $tasks,
            'counts' => $counts,
        ]);
    }

    public function taskUpdateStatus(int $id): void
    {
        ModuleAccess::requireModule('tasks');
        $this->validateCsrf();
        $clientId = Session::get('client_id');

        $task = ClientTask::findById($id);
        if (!$task || (int) $task['client_id'] !== (int) $clientId) {
            $this->redirect('/client/tasks');
            return;
        }

        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['open', 'in_progress', 'done'])) {
            $this->redirect('/client/tasks');
            return;
        }

        ClientTask::markStatus($id, $status, 'client', $clientId);

        Session::flash('success', 'task_updated');
        $this->redirect('/client/tasks');
    }

    // ── Task attachment download ──────────────

    public function taskAttachment(int $id): void
    {
        ModuleAccess::requireModule('tasks');
        $clientId = Session::get('client_id');
        $task = ClientTask::findById($id);
        if (!$task || (int) $task['client_id'] !== (int) $clientId || empty($task['attachment_path'])) {
            http_response_code(404);
            echo 'Not found';
            return;
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

    // ── Tax Payments ──────────────

    public function taxPayments(): void
    {
        ModuleAccess::requireModule('tax-payments');
        $clientId = Session::get('client_id');
        $filterYear = !empty($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        if ($filterYear < 2000 || $filterYear > 2100) {
            $filterYear = (int) date('Y');
        }

        $rows = TaxPayment::findByClientAndYear($clientId, $filterYear);
        $grid = TaxPayment::buildGrid($rows);

        $this->render('client/tax_payments', [
            'filterYear' => $filterYear,
            'grid' => $grid,
            'taxTypes' => TaxPayment::getTaxTypes(),
        ]);
    }

    // ── Attachment download ──────────────

    public function messageAttachment(int $id): void
    {
        ModuleAccess::requireModule('messages');
        $clientId = Session::get('client_id');
        $msg = \App\Core\Database::getInstance()->fetchOne("SELECT * FROM messages WHERE id = ?", [$id]);
        if (!$msg || empty($msg['attachment_path']) || (int) $msg['client_id'] !== (int) $clientId) {
            http_response_code(404);
            echo 'Not found';
            return;
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

    // ── Invoice Email Sending ─────────────────────────

    public function invoiceEmailForm(int $id): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        if (!$client || !$client['can_send_invoices']) {
            $this->redirect('/client/sales'); return;
        }

        $invoice = IssuedInvoice::findById($id);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId || $invoice['status'] === 'draft') {
            $this->redirect('/client/sales'); return;
        }

        $contractor = !empty($invoice['contractor_id']) ? Contractor::findById((int) $invoice['contractor_id']) : null;

        // Load email template or defaults
        $tpl = ClientInvoiceEmailTemplate::findByClientId($clientId);
        $vars = [
            'invoice_number' => $invoice['invoice_number'],
            'gross_amount'   => number_format((float) $invoice['gross_amount'], 2, ',', ' '),
            'net_amount'     => number_format((float) $invoice['net_amount'], 2, ',', ' '),
            'currency'       => $invoice['currency'] ?? 'PLN',
            'due_date'       => $invoice['due_date'] ?? '',
            'issue_date'     => $invoice['issue_date'] ?? '',
            'sale_date'      => $invoice['sale_date'] ?? '',
            'contractor_name' => $invoice['buyer_name'] ?? ($contractor['company_name'] ?? ''),
            'seller_name'    => $invoice['seller_name'] ?? $client['company_name'],
        ];

        // Render both PL and EN versions
        $clientLang = Session::get('client_language', 'pl');
        $tplData = ClientInvoiceEmailTemplate::getTemplate($clientId, 'pl');
        $subjectPl = ClientInvoiceEmailTemplate::renderSubject($tplData['subject'], $vars);
        $bodyPl = ClientInvoiceEmailTemplate::renderBody($tplData['body'], $vars);

        $tplDataEn = ClientInvoiceEmailTemplate::getTemplate($clientId, 'en');
        $subjectEn = ClientInvoiceEmailTemplate::renderSubject($tplDataEn['subject'], $vars);
        $bodyEn = ClientInvoiceEmailTemplate::renderBody($tplDataEn['body'], $vars);

        $defaultSubject = $clientLang === 'en' ? $subjectEn : $subjectPl;
        $defaultBody = $clientLang === 'en' ? $bodyEn : $bodyPl;

        $this->render('client/invoice_send_email', [
            'invoice' => $invoice,
            'contractorEmail' => $contractor['email'] ?? '',
            'contractorName' => $contractor['company_name'] ?? $invoice['buyer_name'] ?? '',
            'defaultSubject' => $defaultSubject,
            'defaultBody' => $defaultBody,
            'defaultLang' => $clientLang,
            'subjectPl' => $subjectPl,
            'subjectEn' => $subjectEn,
            'bodyPl' => $bodyPl,
            'bodyEn' => $bodyEn,
        ]);
    }

    public function invoiceEmailSend(int $id): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/sales'); return; }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        if (!$client || !$client['can_send_invoices']) {
            $this->redirect('/client/sales'); return;
        }

        $invoice = IssuedInvoice::findById($id);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId || $invoice['status'] === 'draft') {
            Session::flash('error', 'invoice_email_not_allowed');
            $this->redirect('/client/sales'); return;
        }

        $toEmail = trim($_POST['to_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';

        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'invalid_email_address');
            $this->redirect("/client/sales/{$id}/send-email"); return;
        }

        try {
            $pdfPath = InvoicePdfService::generate($id);
            $success = MailService::sendInvoiceEmail($clientId, $id, $toEmail, $subject, $body, $pdfPath);

            if ($success) {
                Session::flash('success', 'invoice_email_sent');
            } else {
                Session::flash('error', 'invoice_email_failed');
            }
        } catch (\Throwable $e) {
            error_log("Invoice email error: " . $e->getMessage());
            Session::flash('error', 'invoice_email_failed');
        }

        $this->redirect("/client/sales/{$id}");
    }

    public function invoiceBulkSendEmail(): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/sales'); return; }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        if (!$client || !$client['can_send_invoices']) {
            $this->redirect('/client/sales'); return;
        }

        $ids = $_POST['invoice_ids'] ?? '';
        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?: [];
        }
        $ids = array_map('intval', array_filter($ids));

        if (empty($ids)) {
            Session::flash('error', 'no_invoices_selected');
            $this->redirect('/client/sales'); return;
        }

        // Use client's language preference for bulk sends
        $emailLang = $client['language'] ?? 'pl';

        $sent = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($ids as $invId) {
            $invoice = IssuedInvoice::findById($invId);
            if (!$invoice || (int) $invoice['client_id'] !== $clientId || $invoice['status'] === 'draft') {
                $skipped++;
                continue;
            }

            $contractor = !empty($invoice['contractor_id']) ? Contractor::findById((int) $invoice['contractor_id']) : null;
            $toEmail = $contractor['email'] ?? '';

            if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $vars = [
                'invoice_number' => $invoice['invoice_number'],
                'gross_amount'   => number_format((float) $invoice['gross_amount'], 2, ',', ' '),
                'net_amount'     => number_format((float) $invoice['net_amount'], 2, ',', ' '),
                'currency'       => $invoice['currency'] ?? 'PLN',
                'due_date'       => $invoice['due_date'] ?? '',
                'issue_date'     => $invoice['issue_date'] ?? '',
                'sale_date'      => $invoice['sale_date'] ?? '',
                'contractor_name' => $invoice['buyer_name'] ?? ($contractor['company_name'] ?? ''),
                'seller_name'    => $invoice['seller_name'] ?? $client['company_name'],
            ];

            $tplData = ClientInvoiceEmailTemplate::getTemplate($clientId, $emailLang);
            $subject = ClientInvoiceEmailTemplate::renderSubject($tplData['subject'], $vars);
            $body = ClientInvoiceEmailTemplate::renderBody($tplData['body'], $vars);

            try {
                $pdfPath = InvoicePdfService::generate($invId);
                if (MailService::sendInvoiceEmail($clientId, $invId, $toEmail, $subject, $body, $pdfPath)) {
                    $sent++;
                } else {
                    $errors++;
                }
            } catch (\Throwable $e) {
                error_log("Bulk email error for invoice {$invId}: " . $e->getMessage());
                $errors++;
            }
        }

        $msg = "Wysłano: {$sent}";
        if ($skipped > 0) $msg .= ", pominięto: {$skipped}";
        if ($errors > 0) $msg .= ", błędy: {$errors}";
        Session::flash($errors > 0 ? 'error' : 'success', $msg);
        $this->redirect('/client/sales');
    }

    public function invoiceEmailSettings(): void
    {
        ModuleAccess::requireModule('sales');
        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        if (!$client || !$client['can_send_invoices']) {
            $this->redirect('/client/sales'); return;
        }

        $tpl = ClientInvoiceEmailTemplate::findByClientId($clientId);
        $this->render('client/invoice_email_settings', [
            'emailTemplate' => $tpl,
            'defaultBodyPl' => ClientInvoiceEmailTemplate::getDefaultBody('pl'),
            'defaultBodyEn' => ClientInvoiceEmailTemplate::getDefaultBody('en'),
        ]);
    }

    public function invoiceEmailSettingsUpdate(): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { $this->redirect('/client/sales/email-settings'); return; }

        $clientId = Session::get('client_id');
        $client = Client::findById($clientId);
        if (!$client || !$client['can_send_invoices']) {
            $this->redirect('/client/sales'); return;
        }

        $section = $_POST['_section'] ?? 'template';

        if ($section === 'branding') {
            // Save branding settings
            $data = [
                'header_color'   => preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['header_color'] ?? '') ? $_POST['header_color'] : '#008F8F',
                'logo_in_emails' => isset($_POST['logo_in_emails']) ? 1 : 0,
                'footer_text'    => $_POST['footer_text'] ?? '',
            ];

            // Handle logo removal
            if (!empty($_POST['remove_logo'])) {
                $existing = ClientInvoiceEmailTemplate::findByClientId($clientId);
                if ($existing && !empty($existing['logo_path'])) {
                    $filePath = dirname(__DIR__, 2) . '/public' . $existing['logo_path'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
                $data['logo_path'] = null;
            }

            // Handle logo upload
            if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['logo'];

                // Validate size (max 2MB)
                if ($file['size'] > 2 * 1024 * 1024) {
                    Session::flash('error', 'logo_too_large');
                    $this->redirect('/client/sales/email-settings');
                    return;
                }

                // Validate type
                $allowedMime = ['image/png', 'image/jpeg', 'image/webp'];
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                if (!in_array($mime, $allowedMime, true)) {
                    Session::flash('error', 'logo_invalid_format');
                    $this->redirect('/client/sales/email-settings');
                    return;
                }

                // Validate dimensions
                $imgInfo = @getimagesize($file['tmp_name']);
                if (!$imgInfo || $imgInfo[0] < 50 || $imgInfo[1] < 50) {
                    Session::flash('error', 'logo_too_small');
                    $this->redirect('/client/sales/email-settings');
                    return;
                }
                if ($imgInfo[0] > 2000 || $imgInfo[1] > 2000) {
                    Session::flash('error', 'logo_too_large_dimensions');
                    $this->redirect('/client/sales/email-settings');
                    return;
                }

                // Save file
                $ext = match($mime) {
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg',
                };
                $uploadDir = dirname(__DIR__, 2) . '/public/assets/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                // Remove old logo if exists
                $existing = ClientInvoiceEmailTemplate::findByClientId($clientId);
                if ($existing && !empty($existing['logo_path'])) {
                    $oldPath = dirname(__DIR__, 2) . '/public' . $existing['logo_path'];
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $filename = 'client_' . $clientId . '_logo.' . $ext;
                move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename);
                $data['logo_path'] = '/assets/uploads/' . $filename;
            }

            ClientInvoiceEmailTemplate::upsert($clientId, $data);
            Session::flash('success', 'email_branding_saved');
        } else {
            // Save template content (PL + EN)
            $data = [
                'subject_template_pl' => trim($_POST['subject_template_pl'] ?? 'Faktura {{invoice_number}}'),
                'body_template_pl'    => $_POST['body_template_pl'] ?? ClientInvoiceEmailTemplate::getDefaultBody('pl'),
                'subject_template_en' => trim($_POST['subject_template_en'] ?? 'Invoice {{invoice_number}}'),
                'body_template_en'    => $_POST['body_template_en'] ?? ClientInvoiceEmailTemplate::getDefaultBody('en'),
            ];
            ClientInvoiceEmailTemplate::upsert($clientId, $data);
            Session::flash('success', 'invoice_email_template_saved');
        }

        $this->redirect('/client/sales/email-settings');
    }

    // ── Notification helper for messages ──────────────

    private function notifyOfficeAboutMessage(int $clientId, string $subject, string $eventType): void
    {
        $clientName = Session::get('client_name', 'Klient');
        $recipients = Message::getRecipients($clientId, 'client', (int) Session::get('client_id'));

        foreach ($recipients as $r) {
            // System notification
            if (MessageNotificationPref::shouldNotify($r['user_type'], $r['user_id'], $eventType)) {
                $title = $eventType === 'new_thread'
                    ? "Nowa wiadomość od {$clientName}: {$subject}"
                    : "Odpowiedź od {$clientName}: {$subject}";
                $link = '/office/messages';
                Notification::create($r['user_type'], $r['user_id'], $title, null, 'info', $link);
            }

            // Email
            if ($eventType === 'new_thread' || MessageNotificationPref::shouldEmail($r['user_type'], $r['user_id'], $eventType)) {
                try {
                    if (!empty($r['email'])) {
                        $emailSubject = $eventType === 'new_thread'
                            ? "Nowa wiadomość od {$clientName}"
                            : "Nowa odpowiedź od {$clientName}";
                        $body = "<p><strong>{$clientName}</strong> wysłał(a) wiadomość.</p>"
                            . "<p>Temat: <strong>" . htmlspecialchars($subject) . "</strong></p>"
                            . "<p><a href=\"/office/messages\">Przejdź do wiadomości</a></p>";
                        \App\Services\MailQueueService::enqueue($r['email'], $emailSubject, $body);
                    }
                } catch (\Throwable $e) {
                    error_log("Message email failed for {$r['email']}: " . $e->getMessage());
                }
            }
        }
    }

    // ── Tax Calendar (F1) ─────────────────────────────────

    public function taxCalendar(): void
    {
        ModuleAccess::requireModule('tax-calendar');
        $clientId = (int) Session::get('client_id');
        $selectedMonth = (int) ($_GET['month'] ?? date('n'));
        $selectedYear = (int) ($_GET['year'] ?? date('Y'));

        $deadlines = \App\Services\TaxCalendarService::getDeadlinesForClient($clientId, $selectedYear, $selectedMonth);
        $calendarGrid = \App\Services\TaxCalendarService::buildMonthCalendar($selectedYear, $selectedMonth, $deadlines);
        $upcomingDeadlines = \App\Services\TaxCalendarService::getUpcomingDeadlines($clientId, 30);

        $this->render('client/tax_calendar', [
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'calendarGrid' => $calendarGrid,
            'upcomingDeadlines' => $upcomingDeadlines,
        ]);
    }

    // ── Calculators ──────────────────────────────────────

    public function calculators(): void
    {
        ModuleAccess::requireModule('tax-calculator');
        $tab = $_GET['tab'] ?? 'vat';
        $validTabs = ['vat', 'margin', 'currency', 'profit'];
        if (!in_array($tab, $validTabs, true)) $tab = 'vat';

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

        $marginResult = null;
        if ($tab === 'margin' && !empty($_GET['buy_price'])) {
            $calcMode = $_GET['calc_mode'] ?? 'from_prices';
            if (!in_array($calcMode, ['from_prices', 'from_margin', 'from_markup'], true)) $calcMode = 'from_prices';
            $marginResult = \App\Services\CalculatorService::calculateMargin(
                max(0, (float) $_GET['buy_price']), max(0, (float) ($_GET['sell_price'] ?? 0)),
                max(0, (float) ($_GET['margin_percent'] ?? 0)), $calcMode
            );
        }

        $currencyResult = null;
        if ($tab === 'currency' && !empty($_GET['curr_amount'])) {
            $fromCur = $_GET['from_currency'] ?? 'EUR';
            $toCur = $_GET['to_currency'] ?? 'PLN';
            if (!in_array($fromCur, \App\Services\CalculatorService::CURRENCIES, true)) $fromCur = 'EUR';
            if (!in_array($toCur, \App\Services\CalculatorService::CURRENCIES, true)) $toCur = 'PLN';
            $currencyResult = \App\Services\CalculatorService::convertCurrency(
                max(0, (float) $_GET['curr_amount']), $fromCur, $toCur, $_GET['rate_date'] ?? null
            );
        }

        $profitResult = null;
        if ($tab === 'profit' && !empty($_GET['biz_revenue'])) {
            $profitResult = \App\Services\CalculatorService::calculateProfit(
                max(0, (float) $_GET['biz_revenue']),
                max(0, (float) ($_GET['cost_of_sales'] ?? 0)),
                max(0, (float) ($_GET['fixed_costs'] ?? 0))
            );
        }

        $this->render('client/calculators', [
            'tab' => $tab,
            'vatResult' => $vatResult,
            'marginResult' => $marginResult,
            'currencyResult' => $currencyResult,
            'profitResult' => $profitResult,
        ]);
    }

    // ── Duplicate Check AJAX (F2) ─────────────────────────

    public function checkSalesInvoiceDuplicate(): void
    {
        ModuleAccess::requireModule('sales');
        if (!$this->validateCsrf()) { http_response_code(403); echo json_encode(['error' => 'csrf']); return; }

        header('Content-Type: application/json');
        $clientId = (int) Session::get('client_id');
        $invoiceNumber = trim($_POST['invoice_number'] ?? '');
        $buyerNip = trim($_POST['buyer_nip'] ?? '');
        $grossAmount = (float) ($_POST['gross_amount'] ?? 0);
        $excludeId = !empty($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : null;

        $result = \App\Services\DuplicateDetectionService::checkSalesInvoice(
            $clientId, $invoiceNumber, $buyerNip, $grossAmount, $excludeId
        );

        echo json_encode($result);
    }

    // ── Client Files ─────────────────────────────────

    public function files(): void
    {
        ModuleAccess::requireModule('files');
        $clientId = (int) Session::get('client_id');
        $client = Client::findById($clientId);

        $category = isset($_GET['category']) && $_GET['category'] !== '' ? trim($_GET['category']) : null;
        $validCategories = ['general', 'invoice', 'contract', 'tax', 'correspondence', 'other'];
        if ($category !== null && !in_array($category, $validCategories, true)) {
            $category = null;
        }

        $files = ClientFile::findByClient($clientId, $category);

        $this->render('client/files', [
            'client' => $client,
            'files' => $files,
            'currentCategory' => $category,
        ]);
    }

    public function fileUpload(): void
    {
        ModuleAccess::requireModule('files');
        if (!$this->validateCsrf()) {
            $this->redirect('/client/files');
            return;
        }

        $clientId = (int) Session::get('client_id');
        $client = Client::findById($clientId);

        if (empty($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'file_required');
            $this->redirect('/client/files');
            return;
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'file_upload_error');
            $this->redirect('/client/files');
            return;
        }

        // Max 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            Session::flash('error', 'file_too_large');
            $this->redirect('/client/files');
            return;
        }

        // Extension whitelist
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx', 'doc', 'docx', 'csv', 'xml', 'zip'];
        if (!in_array($ext, $allowedExt, true)) {
            Session::flash('error', 'file_invalid_type');
            $this->redirect('/client/files');
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
            $this->redirect('/client/files');
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

        // Safe filename: {timestamp}_{sanitized_original_name}.{ext}
        $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $safeName = substr($safeName, 0, 100);
        $storedName = time() . '_' . $safeName . '.' . $ext;
        $fullPath = $dir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            Session::flash('error', 'file_upload_error');
            $this->redirect('/client/files');
            return;
        }

        // Category & description
        $category = $_POST['category'] ?? 'general';
        $validCategories = ['general', 'invoice', 'contract', 'tax', 'correspondence', 'other'];
        if (!in_array($category, $validCategories, true)) {
            $category = 'general';
        }
        $description = isset($_POST['description']) ? mb_substr(trim($_POST['description']), 0, 500) : null;

        ClientFile::create(
            $clientId,
            'client',
            $clientId,
            $file['name'],
            $storedName,
            $file['size'],
            $mime,
            $category,
            $description ?: null
        );

        Session::flash('success', 'file_uploaded');
        $this->redirect('/client/files');
    }

    public function fileDownload(int $id): void
    {
        ModuleAccess::requireModule('files');
        $clientId = (int) Session::get('client_id');
        $fileRecord = ClientFile::findById($id);

        if (!$fileRecord || (int) $fileRecord['client_id'] !== $clientId) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $client = Client::findById($clientId);
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

    public function fileDelete(int $id): void
    {
        ModuleAccess::requireModule('files');
        if (!$this->validateCsrf()) {
            $this->redirect('/client/files');
            return;
        }

        $clientId = (int) Session::get('client_id');
        $fileRecord = ClientFile::findById($id);

        if (!$fileRecord || (int) $fileRecord['client_id'] !== $clientId) {
            $this->redirect('/client/files');
            return;
        }

        // Clients can only delete their own uploads
        if ($fileRecord['uploaded_by_type'] !== 'client' || (int) $fileRecord['uploaded_by_id'] !== $clientId) {
            Session::flash('error', 'file_delete_forbidden');
            $this->redirect('/client/files');
            return;
        }

        $client = Client::findById($clientId);
        $fullPath = ClientFile::getFullPath($fileRecord, $client['file_storage_path'] ?? null, $client['nip'] ?? '');

        if ($fullPath && file_exists($fullPath)) {
            @unlink($fullPath);
        }

        ClientFile::delete($id);

        Session::flash('success', 'file_deleted');
        $this->redirect('/client/files');
    }

    // ── HR / Kadry i Płace (read-only + leave requests) ────

    public function hrEmployees(): void
    {
        ModuleAccess::requireModule('hr');
        $clientId = (int)Session::get('client_id');
        $employees = ClientEmployee::findByClient($clientId, false);
        $this->render('client/hr_employees', [
            'employees' => $employees,
        ]);
    }

    public function hrEmployeeCreateForm(): void
    {
        ModuleAccess::requireModule('hr');
        $this->render('client/hr_employee_form', [
            'employee' => null,
        ]);
    }

    public function hrEmployeeStore(): void
    {
        ModuleAccess::requireModule('hr');
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/employees/create'); return; }
        $clientId = (int)Session::get('client_id');

        $data = $this->buildEmployeeData($_POST);
        $loginEmail = trim($_POST['login_email'] ?? '');
        $canLogin   = !empty($_POST['can_login']) && $loginEmail !== '';

        $data['client_id']   = $clientId;
        $data['login_email'] = $canLogin ? $loginEmail : null;
        $data['can_login']   = $canLogin ? 1 : 0;

        $employeeId = ClientEmployee::create($data);

        if ($canLogin) {
            $this->sendEmployeeInvitation($employeeId, $loginEmail, $data['first_name'], $data['last_name']);
            Session::flash('success', 'hr_employee_saved_invitation_sent');
        } else {
            Session::flash('success', 'hr_employee_saved');
        }

        AuditLog::log('client', $clientId, 'hr_employee_created',
            "Employee {$data['first_name']} {$data['last_name']} added (login=" . ($canLogin ? 'yes' : 'no') . ')',
            'client_employee', $employeeId);

        $this->redirect('/client/hr/employees');
    }

    public function hrEmployeeEditForm(int $id): void
    {
        ModuleAccess::requireModule('hr');
        $clientId = (int)Session::get('client_id');
        $employee = ClientEmployee::findByIdForClient($id, $clientId);
        if (!$employee) { $this->redirect('/client/hr/employees'); return; }
        $this->render('client/hr_employee_form', [
            'employee' => $employee,
        ]);
    }

    public function hrEmployeeUpdate(int $id): void
    {
        ModuleAccess::requireModule('hr');
        if (!$this->validateCsrf()) { $this->redirect("/client/hr/employees/{$id}/edit"); return; }
        $clientId = (int)Session::get('client_id');

        $employee = ClientEmployee::findByIdForClient($id, $clientId);
        if (!$employee) { $this->redirect('/client/hr/employees'); return; }

        $data = $this->buildEmployeeData($_POST);
        $loginEmail = trim($_POST['login_email'] ?? '');
        $canLogin   = !empty($_POST['can_login']) && $loginEmail !== '';
        $data['login_email'] = $canLogin ? $loginEmail : null;
        $data['can_login']   = $canLogin ? 1 : 0;

        ClientEmployee::update($id, $data, ClientEmployee::clientAllowedFields());

        // If we just turned login on AND the employee has no password yet — send invitation.
        $needsInvite = $canLogin && empty($employee['password_hash'])
                       && ($loginEmail !== ($employee['login_email'] ?? '') || empty($employee['can_login']));
        if ($needsInvite) {
            $this->sendEmployeeInvitation($id, $loginEmail, $data['first_name'], $data['last_name']);
            Session::flash('success', 'hr_employee_saved_invitation_sent');
        } else {
            Session::flash('success', 'hr_employee_saved');
        }

        AuditLog::log('client', $clientId, 'hr_employee_updated',
            "Employee #{$id} updated", 'client_employee', $id);

        $this->redirect('/client/hr/employees');
    }

    public function hrEmployeeDelete(int $id): void
    {
        ModuleAccess::requireModule('hr');
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/employees'); return; }
        $clientId = (int)Session::get('client_id');

        $employee = ClientEmployee::findByIdForClient($id, $clientId);
        if (!$employee) { $this->redirect('/client/hr/employees'); return; }

        // Soft delete — preserves payroll history and FK integrity.
        ClientEmployee::update($id, ['is_active' => 0]);

        AuditLog::log('client', $clientId, 'hr_employee_deactivated',
            "Employee #{$id} deactivated", 'client_employee', $id);
        Session::flash('success', 'hr_employee_deactivated');
        $this->redirect('/client/hr/employees');
    }

    public function hrEmployeeResendInvitation(int $id): void
    {
        ModuleAccess::requireModule('hr');
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/employees'); return; }
        $clientId = (int)Session::get('client_id');

        $employee = ClientEmployee::findByIdForClient($id, $clientId);
        if (!$employee || empty($employee['login_email']) || empty($employee['can_login'])) {
            Session::flash('error', 'hr_employee_invitation_unavailable');
            $this->redirect('/client/hr/employees');
            return;
        }

        $this->sendEmployeeInvitation($id, $employee['login_email'], $employee['first_name'], $employee['last_name']);
        AuditLog::log('client', $clientId, 'hr_employee_invitation_resent',
            "Invitation resent to {$employee['login_email']}", 'client_employee', $id);
        Session::flash('success', 'hr_employee_invitation_sent');
        $this->redirect('/client/hr/employees');
    }

    /** Build $_POST → DB-shape array, only fields exposed to the form. */
    private function buildEmployeeData(array $post): array
    {
        return [
            'first_name'          => $this->sanitize($post['first_name'] ?? ''),
            'last_name'           => $this->sanitize($post['last_name'] ?? ''),
            'pesel'               => $this->sanitize($post['pesel'] ?? ''),
            'date_of_birth'       => !empty($post['date_of_birth']) ? $post['date_of_birth'] : null,
            'email'               => $this->sanitize($post['email'] ?? ''),
            'phone'               => $this->sanitize($post['phone'] ?? ''),
            'address_street'      => $this->sanitize($post['address_street'] ?? ''),
            'address_city'        => $this->sanitize($post['address_city'] ?? ''),
            'address_postal_code' => $this->sanitize($post['address_postal_code'] ?? ''),
            'tax_office'          => $this->sanitize($post['tax_office'] ?? ''),
            'bank_account'        => $this->sanitize($post['bank_account'] ?? ''),
            'nfz_branch'          => $this->sanitize($post['nfz_branch'] ?? ''),
            'hired_at'            => !empty($post['hired_at']) ? $post['hired_at'] : null,
            'terminated_at'       => !empty($post['terminated_at']) ? $post['terminated_at'] : null,
            'notes'                => $this->sanitize($post['notes'] ?? ''),
            'is_active'            => isset($post['is_active']) ? 1 : 0,
        ];
    }

    /** Issue a fresh activation token and email the employee a link to set their password. */
    private function sendEmployeeInvitation(int $employeeId, string $email, string $firstName, string $lastName): void
    {
        $token = ClientEmployee::issueActivationToken($employeeId);
        $appConfig = require __DIR__ . '/../../config/app.php';
        $baseUrl = rtrim($appConfig['url'] ?? 'https://portal.billu.pl', '/');
        $activateUrl = $baseUrl . '/employee/activate?token=' . $token;

        $subject = 'Aktywacja konta pracowniczego BiLLU';
        $html = '<p>Witaj ' . htmlspecialchars(trim($firstName . ' ' . $lastName)) . ',</p>'
              . '<p>Twój pracodawca utworzył dla Ciebie konto w panelu pracowniczym BiLLU.</p>'
              . '<p>Aby ustawić hasło i aktywować konto, kliknij poniższy link (ważny 72 godziny):</p>'
              . '<p><a href="' . htmlspecialchars($activateUrl) . '">' . htmlspecialchars($activateUrl) . '</a></p>'
              . '<p>Po aktywacji będziesz mógł logować się na: <a href="' . htmlspecialchars($baseUrl) . '/employee/login">' . htmlspecialchars($baseUrl) . '/employee/login</a></p>';

        try {
            MailService::createSimpleMail($email, $subject, $html, (int) Session::get('client_id'));
        } catch (\Throwable $e) {
            error_log("Employee invitation email failed: " . $e->getMessage());
        }
    }

    public function hrPayrollLists(): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        $clientId = (int)Session::get('client_id');
        $lists = PayrollList::findByClient($clientId);
        $this->render('client/hr_payroll_lists', [
            'lists' => $lists,
        ]);
    }

    public function hrPayrollDetail(string $listId): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        $clientId = (int)Session::get('client_id');
        $list = PayrollList::findById((int)$listId);
        if (!$list || (int)$list['client_id'] !== $clientId) {
            $this->redirect('/client/hr/payroll');
            return;
        }

        $entries = PayrollEntry::findByPayrollList((int)$listId);
        $this->render('client/hr_payroll_detail', [
            'list' => $list,
            'entries' => $entries,
        ]);
    }

    public function hrPayrollPdf(string $listId): void
    {
        ModuleAccess::requireHrModule('payroll-lists');
        $clientId = (int)Session::get('client_id');
        $list = PayrollList::findById((int)$listId);
        if (!$list || (int)$list['client_id'] !== $clientId) {
            $this->redirect('/client/hr/payroll');
            return;
        }

        $filepath = PayrollPdfService::generatePayrollList((int)$listId);
        if ($filepath && file_exists($filepath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            readfile($filepath);
            exit;
        }
        Session::flash('error', 'hr_pdf_error');
        $this->redirect("/client/hr/payroll/{$listId}");
    }

    public function hrLeaves(): void
    {
        ModuleAccess::requireHrModule('payroll-leave');
        $clientId = (int)Session::get('client_id');
        $leaves = EmployeeLeave::findByClient($clientId);
        $this->render('client/hr_leaves', [
            'leaves' => $leaves,
            'leaveTypes' => LeaveService::getLeaveTypes(),
        ]);
    }

    public function hrLeaveRequest(): void
    {
        ModuleAccess::requireHrModule('payroll-leave');
        if (!$this->validateCsrf()) { $this->redirect('/client/hr/leaves'); return; }

        $clientId   = (int)Session::get('client_id');
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $contractId = (int)($_POST['contract_id'] ?? 0);

        // Verify employee + contract both belong to this client — never trust POST IDs.
        if (!ClientEmployee::findByIdForClient($employeeId, $clientId)) {
            Session::flash('error', 'hr_leave_error');
            $this->redirect('/client/hr/leaves');
            return;
        }
        $contract = \App\Core\Database::getInstance()->fetchOne(
            "SELECT id FROM employee_contracts WHERE id = ? AND client_id = ? AND employee_id = ?",
            [$contractId, $clientId, $employeeId]
        );
        if (!$contract) {
            Session::flash('error', 'hr_leave_error');
            $this->redirect('/client/hr/leaves');
            return;
        }

        $leaveId = LeaveService::requestLeave(
            $clientId, $employeeId, $contractId,
            $_POST['leave_type'] ?? 'wypoczynkowy',
            $_POST['start_date'] ?? '',
            $_POST['end_date'] ?? '',
            $this->sanitize($_POST['notes'] ?? '')
        );

        Session::flash($leaveId ? 'success' : 'error', $leaveId ? 'hr_leave_created' : 'hr_leave_error');
        $this->redirect('/client/hr/leaves');
    }

    public function hrDeclarations(): void
    {
        ModuleAccess::requireModule('hr');
        $clientId = (int)Session::get('client_id');
        $declarations = PayrollDeclaration::findByClient($clientId);
        $this->render('client/hr_declarations', [
            'declarations' => $declarations,
        ]);
    }

    public function hrDeclarationDownload(string $declarationId): void
    {
        ModuleAccess::requireModule('hr');
        $clientId = (int)Session::get('client_id');
        $decl = PayrollDeclaration::findById((int)$declarationId);
        if (!$decl || (int)$decl['client_id'] !== $clientId || empty($decl['xml_content'])) {
            $this->redirect('/client/hr/declarations');
            return;
        }

        $filename = strtolower($decl['declaration_type']) . '_' . $decl['year']
            . ($decl['month'] ? '_' . sprintf('%02d', $decl['month']) : '') . '.xml';
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $decl['xml_content'];
        exit;
    }

    // ── Contracts module — read-only listing for the client ──

    public function contractsIndex(): void
    {
        ModuleAccess::requireModule('contracts');
        Auth::requireClient();
        $clientId = (int) Session::get('client_id');
        $forms = \App\Models\ContractForm::findByClient($clientId);
        $this->render('client/contracts_index', ['forms' => $forms]);
    }
}
