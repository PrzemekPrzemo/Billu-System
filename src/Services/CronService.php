<?php

namespace App\Services;

use App\Models\InvoiceBatch;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Report;
use App\Models\Setting;
use App\Models\AuditLog;
use App\Services\KsefApiService;
use App\Services\ScheduledExportService;
use App\Models\KsefConfig;
use App\Models\IssuedInvoice;
use App\Models\KsefOperationLog;
use App\Models\HrDocument;
use App\Models\Notification;
use App\Services\HrLeaveService;
use App\Services\HrTaxCalendarService;

class CronService
{
    /**
     * Auto-accept expired batches and generate reports.
     * Should be run daily via cron: php cron.php
     */
    public static function processExpiredBatches(): array
    {
        $results = ['processed' => 0, 'auto_accepted' => 0, 'reports_sent' => 0, 'errors' => []];

        if (!Setting::getAutoAcceptOnDeadline()) {
            return $results;
        }

        $expiredBatches = InvoiceBatch::findExpiredUnfinalized();

        foreach ($expiredBatches as $batch) {
            try {
                // Auto-reject pending invoices that failed whitelist verification
                $rejectedWl = Invoice::autoRejectWhitelistFailed($batch['id']);
                $results['auto_rejected_whitelist'] = ($results['auto_rejected_whitelist'] ?? 0) + $rejectedWl;

                // Auto-accept remaining pending invoices
                $count = Invoice::autoAcceptPending($batch['id']);
                $results['auto_accepted'] += $count;

                // Finalize batch
                InvoiceBatch::finalize($batch['id']);

                $client = Client::findById($batch['client_id']);
                $periodLabel = sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']);
                $attachmentPaths = [];
                $isKsef = ($batch['source'] === 'ksef_api');
                $reportFormat = $isKsef ? 'jpk_xml' : 'excel';

                // Handle cost centers if enabled
                if ($client && $client['has_cost_centers']) {
                    $costCenters = \App\Models\ClientCostCenter::findByClient($batch['client_id'], true);
                    foreach ($costCenters as $cc) {
                        $acceptedInvoices = Invoice::getAcceptedByBatchAndCostCenter($batch['id'], (int)$cc['id']);
                        if (!empty($acceptedInvoices)) {
                            $pdfPath = PdfService::generateCostCenterPdf($batch['id'], $cc['name'], $acceptedInvoices);
                            $attachmentPaths[] = $pdfPath;
                            $reportData = [
                                'client_id' => $batch['client_id'], 'batch_id' => $batch['id'],
                                'report_type' => 'accepted', 'pdf_path' => $pdfPath,
                                'cost_center_name' => $cc['name'], 'report_format' => $reportFormat,
                            ];

                            if ($isKsef) {
                                $xmlPath = JpkV3Service::generateCostCenterJpk($batch['id'], $cc['name'], $acceptedInvoices);
                                $attachmentPaths[] = $xmlPath;
                                $reportData['xml_path'] = $xmlPath;
                            } else {
                                $xlsPath = ExportService::generateCostCenterXls($batch['id'], $cc['name'], $acceptedInvoices);
                                $attachmentPaths[] = $xlsPath;
                                $reportData['xls_path'] = $xlsPath;
                            }

                            Report::create($reportData);
                        }
                    }
                } else {
                    $pdfPath = PdfService::generateAcceptedPdf($batch['id']);
                    $attachmentPaths[] = $pdfPath;
                    $reportData = [
                        'client_id' => $batch['client_id'], 'batch_id' => $batch['id'],
                        'report_type' => 'accepted', 'pdf_path' => $pdfPath,
                        'report_format' => $reportFormat,
                    ];

                    if ($isKsef) {
                        $xmlPath = JpkV3Service::generateAcceptedJpk($batch['id']);
                        $attachmentPaths[] = $xmlPath;
                        $reportData['xml_path'] = $xmlPath;
                    } else {
                        $xlsPath = ExportService::generateAcceptedXls($batch['id']);
                        $attachmentPaths[] = $xlsPath;
                        $reportData['xls_path'] = $xlsPath;
                    }

                    Report::create($reportData);
                }

                // Generate rejected invoices report
                $rejectedXls = ExportService::generateRejectedXls($batch['id']);
                $rejectedPdf = PdfService::generateRejectedPdf($batch['id']);
                $attachmentPaths[] = $rejectedXls;
                $attachmentPaths[] = $rejectedPdf;

                // Send email with all attachments
                $sent = MailService::sendReportMultiple(
                    $batch['report_email'],
                    $batch['company_name'],
                    $batch['nip'],
                    $periodLabel,
                    $attachmentPaths
                );

                if ($sent) {
                    $results['reports_sent']++;
                }

                AuditLog::log('admin', 0, 'auto_finalize_batch', "Batch {$batch['id']}, auto-accepted: {$count}");

                $results['processed']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Batch {$batch['id']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Send notifications about new invoices to verify.
     */
    public static function sendNewInvoiceNotifications(): int
    {
        $batches = InvoiceBatch::findPendingNotification();
        $sent = 0;

        foreach ($batches as $batch) {
            $invoiceCount = count(Invoice::findByBatch($batch['id']));
            $periodLabel = sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']);

            $success = MailService::sendNewInvoicesNotification(
                $batch['email'],
                $batch['company_name'],
                $invoiceCount,
                $periodLabel,
                $batch['verification_deadline'],
                $batch['language'],
                (int) $batch['client_id']
            );

            if ($success) {
                InvoiceBatch::update($batch['id'], [
                    'notification_sent'    => 1,
                    'notification_sent_at' => date('Y-m-d H:i:s'),
                ]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send reminders N days before deadline.
     */
    public static function sendDeadlineReminders(): int
    {
        $daysBefore = (int) Setting::get('notification_days_before', '3');
        $reminderDate = date('Y-m-d', strtotime("+{$daysBefore} days"));
        $sent = 0;

        $batches = \App\Core\Database::getInstance()->fetchAll(
            "SELECT ib.*, c.company_name, c.email, c.language
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             WHERE ib.is_finalized = 0
               AND ib.verification_deadline = ?
               AND EXISTS (SELECT 1 FROM invoices WHERE batch_id = ib.id AND status = 'pending')",
            [$reminderDate]
        );

        foreach ($batches as $batch) {
            $pendingCount = count(Invoice::findPendingByBatch($batch['id']));
            if ($pendingCount === 0) continue;

            $success = MailService::sendDeadlineReminder(
                $batch['email'],
                $batch['company_name'],
                $pendingCount,
                $batch['verification_deadline'],
                $batch['language'],
                (int) $batch['client_id']
            );

            if ($success) $sent++;
        }

        return $sent;
    }

    /**
     * Auto-import invoices from KSeF for all eligible clients.
     * Runs on the configured day of month (default: last day).
     */
    public static function autoImportFromKsef(): array
    {
        $results = ['clients' => 0, 'invoices' => 0, 'errors' => []];

        $importDay = Setting::get('ksef_auto_import_day', '0');
        $today = (int) date('j');
        $lastDayOfMonth = (int) date('t');

        // importDay=0 means last day of month
        $targetDay = (int) $importDay === 0 ? $lastDayOfMonth : (int) $importDay;

        if ($today !== $targetDay) {
            return $results;
        }

        $month = (int) date('n');
        $year = (int) date('Y');

        // Find all active clients with KSeF enabled
        $clients = \App\Core\Database::getInstance()->fetchAll(
            "SELECT c.*, o.id as office_id FROM clients c
             LEFT JOIN offices o ON c.office_id = o.id
             WHERE c.is_active = 1 AND c.ksef_enabled = 1"
        );

        foreach ($clients as $client) {
            try {
                $ksef = KsefApiService::forClient($client);
                if (!$ksef->isConfigured()) {
                    continue;
                }

                $result = $ksef->importInvoicesToBatch(
                    $client['id'],
                    $client['nip'],
                    $month,
                    $year,
                    0,
                    'cron',
                    $client['office_id'] ?: null
                );

                if ($result['success'] > 0) {
                    $results['clients']++;
                    $results['invoices'] += $result['success'];
                }

                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $err) {
                        $results['errors'][] = "Client {$client['company_name']}: {$err}";
                    }
                }

                AuditLog::log('cron', 0, 'ksef_auto_import', "Client: {$client['company_name']}, imported: {$result['success']}", 'client', $client['id']);
            } catch (\Exception $e) {
                $results['errors'][] = "Client {$client['company_name']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Send email warnings about expiring KSeF certificates.
     * Checks at 30, 14, and 7 days before expiry.
     */
    public static function checkExpiringCertificates(): int
    {
        $sent = 0;
        $thresholds = [30, 14, 7];

        foreach ($thresholds as $days) {
            $expiring = KsefConfig::findExpiringCertificates($days);

            foreach ($expiring as $config) {
                $expiryDate = $config['cert_valid_to'] ?? $config['cert_ksef_valid_to'] ?? null;
                if (!$expiryDate) continue;

                $daysLeft = (int) ceil((strtotime($expiryDate) - time()) / 86400);

                // Only send for the exact threshold window to avoid duplicates
                // e.g. 30-day check: send only if 28-30 days left
                if ($daysLeft > $days || $daysLeft < ($days === 7 ? 0 : $days - 2)) {
                    continue;
                }

                $email = $config['email'] ?? null;
                if (empty($email)) continue;

                $certType = !empty($config['cert_ksef_valid_to']) ? 'ksef_cert' : 'certificate';
                $language = $config['language'] ?? 'pl';

                $success = MailService::sendCertificateExpiryWarning(
                    $email,
                    $config['company_name'],
                    $certType,
                    date('d.m.Y', strtotime($expiryDate)),
                    $daysLeft,
                    $language
                );

                if ($success) $sent++;
            }
        }

        return $sent;
    }

    /**
     * Process due scheduled exports.
     */
    public static function processScheduledExports(): array
    {
        return ScheduledExportService::processDueExports();
    }

    /**
     * Clean up old temporary files, expired sessions, and stale data.
     */
    public static function cleanupOldData(): array
    {
        $results = ['files_deleted' => 0, 'sessions_cleared' => 0, 'tokens_cleared' => 0, 'logs_cleared' => 0, 'errors' => []];
        $db = \App\Core\Database::getInstance();

        // 1. Delete temporary import files older than 7 days
        $importDir = __DIR__ . '/../../storage/imports';
        if (is_dir($importDir)) {
            $cutoff = time() - (7 * 86400);
            foreach (glob($importDir . '/*') as $file) {
                if (is_file($file) && filemtime($file) < $cutoff && basename($file) !== '.gitkeep') {
                    if (unlink($file)) {
                        $results['files_deleted']++;
                    }
                }
            }
        }

        // 2. Delete expired password reset tokens
        try {
            $stmt = $db->query("DELETE FROM password_resets WHERE expires_at < NOW()");
            $results['tokens_cleared'] = (int) $stmt->rowCount();
        } catch (\Exception $e) {
            $results['errors'][] = 'password_resets cleanup: ' . $e->getMessage();
        }

        // 3. Delete expired user sessions (older than 24h)
        try {
            $stmt = $db->query("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $results['sessions_cleared'] = (int) $stmt->rowCount();
        } catch (\Exception $e) {
            $results['errors'][] = 'sessions cleanup: ' . $e->getMessage();
        }

        // 4. Delete old login history entries (older than 90 days)
        try {
            $stmt = $db->query("DELETE FROM login_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $results['logs_cleared'] = (int) $stmt->rowCount();
        } catch (\Exception $e) {
            $results['errors'][] = 'login_history cleanup: ' . $e->getMessage();
        }

        // 5. Delete old audit log entries (older than 365 days)
        try {
            $stmt = $db->query("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)");
            $results['logs_cleared'] += (int) $stmt->rowCount();
        } catch (\Exception $e) {
            $results['errors'][] = 'audit_log cleanup: ' . $e->getMessage();
        }

        // 6. Delete UPO files older than 30 days
        $upoDir = __DIR__ . '/../../storage/upo';
        $results['upo_deleted'] = 0;
        if (is_dir($upoDir)) {
            $cutoff = time() - (30 * 86400);
            foreach (glob($upoDir . '/*') as $file) {
                if (is_file($file) && filemtime($file) < $cutoff && basename($file) !== '.gitkeep') {
                    if (unlink($file)) {
                        $results['upo_deleted']++;
                    }
                }
            }
        }

        // Clear ksef_upo_path for invoices where UPO was sent more than 30 days ago
        try {
            $db->query("UPDATE issued_invoices SET ksef_upo_path = NULL WHERE ksef_upo_path IS NOT NULL AND ksef_sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        } catch (\Exception $e) {
            $results['errors'][] = 'UPO path cleanup: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Retry KSeF connections for clients with failed status (within last 24h).
     * For clients with ksef_cert auth: re-authenticate and batch-send pending invoices.
     * Should be run hourly via cron.
     */
    public static function retryKsefConnections(): array
    {
        $results = ['clients_checked' => 0, 'connections_restored' => 0, 'invoices_sent' => 0, 'errors' => []];

        $failedClients = KsefConfig::findFailedConnections(24);

        foreach ($failedClients as $config) {
            $results['clients_checked']++;
            $clientId = (int) $config['client_id'];

            try {
                $client = Client::findById($clientId);
                if (!$client) continue;

                $ksef = KsefApiService::forClient($client);
                if (!$ksef->isConfigured()) continue;

                // 1. Health check
                $connResult = $ksef->checkConnection();
                if (!$connResult['ok']) {
                    // Still down — update timestamp so we know we tried
                    KsefConfig::updateConnectionStatus($clientId, 'failed', $connResult['error']);
                    continue;
                }

                // 2. Connection restored — try full authentication
                $ksef->setPerformer('system', 0);
                if (!$ksef->authenticate()) {
                    KsefConfig::updateConnectionStatus($clientId, 'failed', 'Połączenie OK, ale autentykacja nie powiodła się');
                    $results['errors'][] = "{$config['company_name']}: auth failed after connection restored";
                    continue;
                }

                KsefConfig::updateConnectionStatus($clientId, 'ok', null);
                $results['connections_restored']++;

                // 3. Batch-send pending invoices (issued but not sent to KSeF)
                $pendingInvoices = \App\Core\Database::getInstance()->fetchAll(
                    "SELECT id FROM issued_invoices
                     WHERE client_id = ? AND status = 'issued'
                       AND (ksef_status = 'none' OR ksef_status = 'error')
                     ORDER BY issue_date ASC",
                    [$clientId]
                );

                if (empty($pendingInvoices)) continue;

                $sentCount = 0;
                foreach ($pendingInvoices as $inv) {
                    try {
                        $invoice = IssuedInvoice::findById((int) $inv['id']);
                        if (!$invoice) continue;

                        $xml = KsefInvoiceSendService::buildKsefXml($invoice, $client);
                        $result = $ksef->submitInvoice($xml);

                        if (!empty($result['referenceNumber'])) {
                            IssuedInvoice::updateKsefStatus((int) $inv['id'], 'sent', $result['referenceNumber']);
                            IssuedInvoice::updateStatus((int) $inv['id'], 'sent_ksef');
                            // Regenerate PDF with KSeF QR code
                            try { InvoicePdfService::generate((int) $inv['id']); } catch (\Exception $pdfErr) {
                                error_log("PDF regen failed for invoice {$inv['id']}: " . $pdfErr->getMessage());
                            }
                            $sentCount++;
                        } else {
                            $err = $result['error'] ?? 'Unknown';
                            IssuedInvoice::updateKsefStatus((int) $inv['id'], 'error', null, $err);
                        }
                    } catch (\Exception $e) {
                        IssuedInvoice::updateKsefStatus((int) $inv['id'], 'error', null, $e->getMessage());
                    }
                }

                if ($sentCount > 0) {
                    $results['invoices_sent'] += $sentCount;
                    AuditLog::log('cron', 0, 'ksef_auto_send', "Client: {$config['company_name']}, sent: {$sentCount}", 'client', $clientId);
                    KsefOperationLog::log(
                        $clientId, 'invoice_submit', 'success',
                        "Auto-send: {$sentCount} invoices",
                        null, null, 'system', 0
                    );
                }
            } catch (\Exception $e) {
                $results['errors'][] = "{$config['company_name']}: " . $e->getMessage();
                KsefConfig::updateConnectionStatus($clientId, 'failed', $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Process tax calendar alerts - notify clients and offices about upcoming deadlines.
     */
    public static function processTaxCalendarAlerts(): array
    {
        return TaxCalendarService::processAlerts();
    }

    /**
     * Scan for duplicate invoices (weekly).
     */
    public static function scanForDuplicates(): array
    {
        $result = DuplicateDetectionService::batchScanAll();
        \App\Models\DuplicateCandidate::deleteOld(365);
        return $result;
    }

    public static function processHrTaxDeadlines(): array
    {
        $month     = (int) date('n');
        $year      = (int) date('Y');
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear  = $month === 1 ? $year - 1 : $year;

        $count = HrTaxCalendarService::generateDeadlinesForMonth($prevYear, $prevMonth);

        if ($month === 1) {
            $count += HrTaxCalendarService::generateAnnualDeadlines($prevYear);
        }

        AuditLog::log('system', 0, 'hr_tax_deadlines', json_encode([
            'period'      => "{$prevYear}-{$prevMonth}",
            'count'       => $count,
            'annual_pass' => $month === 1,
        ]));

        return ['deadlines_added' => $count];
    }

    public static function rolloverLeaveBalances(): array
    {
        $today = date('m-d');
        if ($today !== '12-31' && $today !== '01-01') {
            return ['skipped' => true, 'reason' => 'Not year-end or year-start'];
        }

        $newYear = (int) date('Y') + ($today === '12-31' ? 1 : 0);
        $count   = HrLeaveService::rolloverBalances($newYear);

        AuditLog::log('system', 0, 'hr_leave_rollover', json_encode([
            'new_year'        => $newYear,
            'employees_count' => $count,
        ]));

        return ['rolled_over' => $count, 'new_year' => $newYear];
    }

    public static function processHrDocumentExpiryAlerts(): array
    {
        $alertsSent = 0;

        foreach ([30, 14, 7] as $days) {
            $docs = HrDocument::findExpiringSoon($days);

            foreach ($docs as $doc) {
                $employeeId = (int) $doc['employee_id'];
                $empName    = $doc['employee_name'] ?? "pracownik #{$employeeId}";
                $docName    = $doc['original_name'] ?? 'Dokument';
                $expiry     = $doc['expiry_date'] ?? '';
                $clientId   = (int) $doc['client_id'];

                $title   = "Dokument wygasa za {$days}d: {$docName}";
                $message = "Dokument pracownika {$empName} ({$doc['category']}) wygasa {$expiry}. Prosimy o przedłużenie.";
                $link    = "/office/hr/{$clientId}/employees/{$employeeId}/documents";

                Notification::create('office', 0, $title, $message, 'warning', $link);

                HrDocument::markAlertSent($doc['id'], $days);
                $alertsSent++;
            }
        }

        return ['alerts_sent' => $alertsSent];
    }
}
