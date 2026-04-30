<?php

namespace App\Services;

use App\Models\InvoiceBatch;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Report;
use App\Models\Setting;
use App\Models\AuditLog;
use App\Services\KsefApiService;
use App\Services\MailQueueService;
use App\Services\ScheduledExportService;
use App\Models\KsefConfig;
use App\Models\IssuedInvoice;
use App\Models\KsefOperationLog;

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
    /**
     * Poll Bramka C for new KAS letters across all due clients.
     *
     * Iterates client_eus_configs.findDueForPolling() (which already
     * applies the throttle on last_poll_at + poll_interval_minutes),
     * spawns one bg poller per client. Each spawned process advances
     * last_poll_at on its own — this method only orchestrates fanout.
     *
     * @return array{spawned:int, errors:string[]}
     */
    public static function pollEusCorrespondence(int $maxPerTick = 20): array
    {
        $result   = ['spawned' => 0, 'errors' => []];
        $configs  = \App\Models\EusConfig::findDueForPolling();
        $configs  = array_slice($configs, 0, $maxPerTick);

        $scriptDir = realpath(__DIR__ . '/../../scripts') ?: (__DIR__ . '/../../scripts');
        $script    = $scriptDir . '/eus_poll_c_bg.php';
        if (!is_file($script)) {
            $result['errors'][] = 'eus_poll_c_bg.php missing';
            return $result;
        }
        $phpBin  = self::detectPhpBinary();
        $logPath = __DIR__ . '/../../storage/logs/eus/cron-spawn.log';

        foreach ($configs as $cfg) {
            $cmd = escapeshellcmd($phpBin) . ' ' . escapeshellarg($script) . ' '
                 . escapeshellarg((string) $cfg['client_id']) . ' >> '
                 . escapeshellarg($logPath) . ' 2>&1 &';
            try {
                @exec($cmd);
                $result['spawned']++;
            } catch (\Throwable $e) {
                $result['errors'][] = "client {$cfg['client_id']}: " . $e->getMessage();
            }
        }
        return $result;
    }

    /**
     * Drain pending eus_jobs by spawning the appropriate bg script.
     *
     * Called every cron tick (~5min on Plesk). Per-job advances are
     * cheap (single SELECT FOR UPDATE on each pending row) — heavy
     * work happens inside the spawned process, so this method
     * returns quickly even when there are dozens of jobs queued.
     *
     * Limits to $maxPerTick spawns per call so a flood of failures
     * does not exhaust PHP-FPM worker pool.
     *
     * @return array{spawned:int, skipped:int, errors:string[]}
     */
    public static function processEusJobs(int $maxPerTick = 10): array
    {
        $result = ['spawned' => 0, 'skipped' => 0, 'errors' => []];
        $db = \App\Core\Database::getInstance();

        $rows = $db->fetchAll(
            "SELECT id, job_type FROM eus_jobs
              WHERE state = 'pending'
                AND (next_run_at IS NULL OR next_run_at <= NOW())
              ORDER BY id ASC
              LIMIT ?",
            [$maxPerTick]
        );

        $scriptDir = realpath(__DIR__ . '/../../scripts') ?: (__DIR__ . '/../../scripts');
        $phpBin    = self::detectPhpBinary();

        foreach ($rows as $row) {
            $script = match ($row['job_type']) {
                'submit_b' => $scriptDir . '/eus_submit_b_bg.php',
                'poll_b'   => $scriptDir . '/eus_poll_b_bg.php',
                default    => null,
            };
            if ($script === null || !is_file($script)) {
                $result['skipped']++;
                continue;
            }
            // Detached spawn — log file captures errors.
            $logPath = __DIR__ . '/../../storage/logs/eus/cron-spawn.log';
            $cmd = escapeshellcmd($phpBin) . ' ' . escapeshellarg($script) . ' '
                 . escapeshellarg((string) $row['id']) . ' >> '
                 . escapeshellarg($logPath) . ' 2>&1 &';
            try {
                @exec($cmd);
                $result['spawned']++;
            } catch (\Throwable $e) {
                $result['errors'][] = 'job ' . $row['id'] . ': ' . $e->getMessage();
            }
        }
        return $result;
    }

    /**
     * Best-effort PHP CLI binary detection. Plesk vhosts run FPM under
     * one PHP version while CLI defaults to a different one — we walk
     * common paths and return the first executable found.
     */
    private static function detectPhpBinary(): string
    {
        $candidates = [
            '/opt/plesk/php/8.4/bin/php',
            '/opt/plesk/php/8.3/bin/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
            PHP_BINARY,
        ];
        foreach ($candidates as $bin) {
            if (is_string($bin) && @is_executable($bin)) {
                return $bin;
            }
        }
        return 'php';
    }

    /**
     * Warn the office about clients whose UPL-1 (pełnomocnictwo) is
     * about to expire. Creates a high-priority ClientTask so the
     * action is actually visible (mail-only would get lost in inbox).
     *
     * Triggers at 30 / 14 / 7 days before upl1_valid_to. Idempotent:
     * checks for an existing 'eus_upl1_expiring' task on the client
     * within the last $days * 86400 seconds before creating a new one.
     */
    public static function checkExpiringEusCredentials(): int
    {
        $created = 0;
        $thresholds = [30, 14, 7];

        foreach ($thresholds as $days) {
            $rows = \App\Models\EusConfig::findExpiringUpl1($days);
            foreach ($rows as $row) {
                $expiryDate = $row['upl1_valid_to'] ?? null;
                if (!$expiryDate) continue;

                $daysLeft = (int) ceil((strtotime($expiryDate) - time()) / 86400);
                // Threshold window: only fire when daysLeft sits in the
                // expected band so a single config triggers once per band.
                $low = $days === 7 ? 0 : ($days - 2);
                if ($daysLeft > $days || $daysLeft < $low) {
                    continue;
                }

                // Idempotency — skip if a recent task already exists
                // for this client + same threshold band.
                $existing = \App\Core\Database::getInstance()->fetchOne(
                    "SELECT id FROM client_tasks
                      WHERE client_id = ?
                        AND title LIKE ?
                        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      LIMIT 1",
                    [(int) $row['client_id'], "e-US: UPL-1%{$days}d%"]
                );
                if ($existing) continue;

                \App\Models\ClientTask::create(
                    (int) $row['client_id'],
                    'system',
                    0,
                    "e-US: UPL-1 dla {$row['company_name']} wygasa za {$days}d ({$expiryDate})",
                    "Pełnomocnictwo UPL-1 wygasa {$expiryDate}. Bez aktywnego UPL-1 system odrzuci każdą wysyłkę do e-US. Skontaktuj się z klientem aby przedłużył pełnomocnictwo.",
                    $days <= 7 ? 'high' : 'normal',
                    $expiryDate
                );
                $created++;
            }
        }
        return $created;
    }

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

        // 5. Delete old audit log entries — retention policy: 37 days.
        // The activity-log view (/admin/activity-log) operates on this
        // window. Anything older is dropped to honor data minimization.
        try {
            $stmt = $db->query("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 37 DAY)");
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

        // 7. Mail queue cleanup (sent > 30d, failed > 90d)
        try {
            $mq = MailQueueService::cleanup();
            $results['mail_queue_cleared'] = $mq['sent_deleted'] + $mq['failed_deleted'];
        } catch (\Exception $e) {
            $results['errors'][] = 'mail_queue cleanup: ' . $e->getMessage();
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

    /**
     * Warmup NBP exchange rates cache for active currencies.
     * Pobiera kursy "na jutro" → wymusza GET dla ostatniego dnia roboczego
     * i wstawia do Redis. Pierwsze logowanie użytkownika rano nie czeka na NBP.
     */
    public static function warmupNbpRates(): array
    {
        $currencies = ['EUR', 'USD', 'GBP', 'CHF', 'CZK'];
        $cached = 0;
        $errors = [];
        foreach ($currencies as $code) {
            try {
                $rate = NbpExchangeRateService::getLatestRates([$code]);
                if (!empty($rate)) {
                    $cached++;
                }
            } catch (\Throwable $e) {
                $errors[] = $code . ': ' . $e->getMessage();
            }
        }
        return ['cached' => $cached, 'errors' => $errors];
    }

    /**
     * Archive audit_log entries older than $monthsToKeep into JSONL.gz file
     * under storage/archive/audit_log/, then delete archived rows.
     * Uruchamiane raz w miesiącu (1. dnia miesiąca).
     */
    public static function archiveAuditLog(int $monthsToKeep = 24): array
    {
        $result = ['archived' => 0, 'deleted' => 0, 'file' => null, 'errors' => []];

        $cutoff = date('Y-m-d 00:00:00', strtotime("-{$monthsToKeep} months"));
        $db = \App\Core\Database::getInstance();

        $rows = $db->fetchAll(
            "SELECT * FROM audit_log WHERE created_at < ? ORDER BY id",
            [$cutoff]
        );

        if (empty($rows)) {
            return $result;
        }

        $archiveDir = dirname(__DIR__, 2) . '/storage/archive/audit_log';
        if (!is_dir($archiveDir) && !@mkdir($archiveDir, 0775, true) && !is_dir($archiveDir)) {
            $result['errors'][] = 'cannot_create_archive_dir';
            return $result;
        }

        $file = $archiveDir . '/audit_log_' . date('Ymd_His') . '.jsonl.gz';
        $gz = @gzopen($file, 'wb9');
        if ($gz === false) {
            $result['errors'][] = 'cannot_open_archive_file';
            return $result;
        }

        $minId = PHP_INT_MAX;
        $maxId = 0;
        foreach ($rows as $row) {
            $line = json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
            if ($line === false || gzwrite($gz, $line) === false) {
                $result['errors'][] = 'write_failed_at_id_' . ($row['id'] ?? '?');
                gzclose($gz);
                @unlink($file);
                return $result;
            }
            $id = (int)$row['id'];
            if ($id < $minId) $minId = $id;
            if ($id > $maxId) $maxId = $id;
            $result['archived']++;
        }
        gzclose($gz);

        // Usuwamy zarchiwizowane wiersze osobnym DELETE z zakresem ID
        // (bezpieczniejsze od WHERE created_at < cutoff bo gwarantuje, że nic
        // nie zostanie zostawione lub usunięte podwójnie).
        $stmt = $db->query(
            "DELETE FROM audit_log WHERE id BETWEEN ? AND ?",
            [$minId, $maxId]
        );
        $result['deleted'] = $stmt->rowCount();
        $result['file'] = $file;

        return $result;
    }
}
