<?php

namespace App\Core;

use App\Models\Setting;
use App\Models\Notification;

class Controller
{
    protected function render(string $template, array $data = []): void
    {
        extract($data);
        $lang = fn(string $key, array $params = []) => Language::get($key, $params);
        $csrf = Session::generateCsrfToken();
        $flash_success = Session::getFlash('success');
        $flash_error = Session::getFlash('error');
        $flash_info_extra = Session::getFlash('info_extra');

        // Branding
        $branding = $this->getBranding();
        $isImpersonating = Auth::isImpersonating();
        $impersonatorName = Session::get('impersonator_username', '');

        // Notification count for layout bell icon
        $notificationCount = $this->getNotificationCount();

        ob_start();
        require __DIR__ . '/../../templates/' . $template . '.php';
        $content = ob_get_clean();

        require __DIR__ . '/../../templates/layout.php';
    }

    protected function renderWithoutLayout(string $template, array $data = []): void
    {
        extract($data);
        $lang = fn(string $key, array $params = []) => Language::get($key, $params);
        $csrf = Session::generateCsrfToken();
        $branding = $this->getBranding();
        require __DIR__ . '/../../templates/' . $template . '.php';
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function forbidden(): void
    {
        http_response_code(403);
        require __DIR__ . '/../../templates/errors/403.php';
        exit;
    }

    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!Session::validateCsrfToken($token)) {
            Session::flash('error', 'invalid_csrf');
            return false;
        }
        return true;
    }

    protected function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    protected function sanitizeInt(mixed $value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    protected function getNotificationCount(): int
    {
        try {
            $userType = Session::get('user_type');
            $userId = Auth::currentUserId();
            if (!$userType || !$userId) return 0;
            return Notification::countUnread($userType, $userId);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Launch KSeF import as a background PHP process to avoid HTTP timeout.
     * Returns job ID for status polling.
     */
    protected static function launchKsefImportJob(
        int $clientId, int $month, int $year,
        int $importedById, string $importedByType, ?int $officeId = null
    ): string {
        $jobId = md5(uniqid((string)$clientId, true));
        $statusDir = dirname(__DIR__, 2) . '/storage/imports';
        if (!is_dir($statusDir)) {
            mkdir($statusDir, 0775, true);
        }

        $job = [
            'job_id' => $jobId,
            'client_id' => $clientId,
            'month' => $month,
            'year' => $year,
            'imported_by_id' => $importedById,
            'imported_by_type' => $importedByType,
            'office_id' => $officeId,
            'status' => 'queued',
            'message' => 'Uruchamianie importu...',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'result' => null,
        ];

        $statusFile = $statusDir . '/' . $jobId . '.json';
        file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // Detect PHP CLI binary — PHP_BINARY may point to php-fpm which can't run scripts
        $phpBin = self::findPhpCliBinary();
        $scriptPath = dirname(__DIR__, 2) . '/scripts/ksef_import_bg.php';
        $logFile = $statusDir . '/' . $jobId . '.log';

        // Check if exec() is available and we have a valid PHP CLI binary
        $disabledFns = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
        $canExec = function_exists('exec') && !in_array('exec', $disabledFns) && $phpBin !== null;

        if (!$canExec) {
            // Fallback: run import synchronously (blocks the request but still works)
            error_log("KSeF import: exec() unavailable or no PHP CLI binary, running synchronously for job {$jobId}");
            self::runKsefImportSync($statusFile, $job);
            return $jobId;
        }

        // Launch background process with nohup and error logging
        $cmd = sprintf(
            'nohup %s %s %s > %s 2>&1 &',
            escapeshellarg($phpBin),
            escapeshellarg($scriptPath),
            escapeshellarg($jobId),
            escapeshellarg($logFile)
        );
        exec($cmd);

        // Brief wait then verify the background process actually launched
        usleep(200000); // 0.2s
        if (!self::isBackgroundJobRunning($statusFile, $logFile)) {
            error_log("KSeF import: background process failed to start, falling back to sync for job {$jobId}");
            self::runKsefImportSync($statusFile, $job);
        }

        return $jobId;
    }

    /**
     * Check status of a background KSeF import job.
     */
    protected static function checkKsefImportStatus(string $jobId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            return null;
        }
        $statusFile = dirname(__DIR__, 2) . '/storage/imports/' . $jobId . '.json';
        if (!file_exists($statusFile)) {
            return null;
        }
        clearstatcache(true, $statusFile);
        $data = json_decode(file_get_contents($statusFile), true);

        // Clean up completed/errored jobs older than 1 hour
        if (in_array($data['status'] ?? '', ['completed', 'error'])) {
            $age = time() - strtotime($data['updated_at'] ?? $data['created_at']);
            if ($age > 3600) {
                @unlink($statusFile);
            }
        }

        return $data;
    }

    /**
     * Launch KSeF invoice send as a background PHP process to avoid HTTP timeout.
     */
    protected static function launchKsefSendJob(int $invoiceId, int $userId, string $userType): string
    {
        $jobId = md5(uniqid((string)$invoiceId, true));
        $statusDir = dirname(__DIR__, 2) . '/storage/ksef_send';
        if (!is_dir($statusDir)) {
            mkdir($statusDir, 0775, true);
        }

        $job = [
            'job_id' => $jobId,
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'user_type' => $userType,
            'status' => 'queued',
            'message' => 'Uruchamianie wysylki do KSeF...',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'reference' => null,
            'upo_path' => null,
        ];

        $statusFile = $statusDir . '/' . $jobId . '.json';
        file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $phpBin = self::findPhpCliBinary();
        $scriptPath = dirname(__DIR__, 2) . '/scripts/ksef_send_bg.php';
        $logFile = $statusDir . '/' . $jobId . '.log';

        $disabledFns = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
        $canExec = function_exists('exec') && !in_array('exec', $disabledFns) && $phpBin !== null;

        if (!$canExec) {
            // Fallback: run synchronously
            error_log("KSeF send: exec() unavailable, running synchronously for job {$jobId}");
            self::runKsefSendSync($statusFile, $job);
            return $jobId;
        }

        $cmd = sprintf(
            'nohup %s %s %s > %s 2>&1 &',
            escapeshellarg($phpBin),
            escapeshellarg($scriptPath),
            escapeshellarg($jobId),
            escapeshellarg($logFile)
        );
        exec($cmd);

        // Brief wait then verify the background process actually launched
        usleep(200000); // 0.2s
        if (!self::isBackgroundJobRunning($statusFile, $logFile)) {
            error_log("KSeF send: background process failed to start, falling back to sync for job {$jobId}");
            self::runKsefSendSync($statusFile, $job);
        }

        return $jobId;
    }

    /**
     * Check status of a background KSeF send job.
     */
    protected static function checkKsefSendStatus(string $jobId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            return null;
        }
        $statusFile = dirname(__DIR__, 2) . '/storage/ksef_send/' . $jobId . '.json';
        if (!file_exists($statusFile)) {
            return null;
        }
        clearstatcache(true, $statusFile);
        $data = json_decode(file_get_contents($statusFile), true);

        if (in_array($data['status'] ?? '', ['completed', 'error'])) {
            $age = time() - strtotime($data['updated_at'] ?? $data['created_at']);
            if ($age > 3600) {
                @unlink($statusFile);
            }
        }

        return $data;
    }

    /**
     * Run KSeF send synchronously (fallback when exec() unavailable).
     */
    private static function runKsefSendSync(string $statusFile, array $job): void
    {
        try {
            $job['status'] = 'running';
            $job['message'] = 'Wysylanie do KSeF (tryb synchroniczny)...';
            $job['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $result = \App\Services\KsefInvoiceSendService::sendInvoice((int) $job['invoice_id']);

            if ($result['success']) {
                $job['status'] = 'completed';
                $job['message'] = 'Faktura wyslana do KSeF.';
                $job['reference'] = $result['reference'] ?? null;
            } else {
                $job['status'] = 'error';
                $job['message'] = $result['error'] ?? 'Nieznany blad';
            }
        } catch (\Throwable $e) {
            $job['status'] = 'error';
            $job['message'] = $e->getMessage();
        }
        $job['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Launch a batch KSeF send job — ONE background process for multiple invoices.
     * Uses a single KSeF session for all invoices (much faster than individual jobs).
     */
    protected static function launchKsefBatchSendJob(array $invoiceIds, int $clientId): string
    {
        $jobId = md5(uniqid('batch_' . $clientId, true));
        $statusDir = dirname(__DIR__, 2) . '/storage/ksef_send';
        if (!is_dir($statusDir)) {
            mkdir($statusDir, 0775, true);
        }

        $job = [
            'job_id' => $jobId,
            'type' => 'batch',
            'client_id' => $clientId,
            'invoice_ids' => $invoiceIds,
            'status' => 'queued',
            'message' => 'Uruchamianie wysylki...',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'results' => [],
            'success_count' => 0,
            'error_count' => 0,
        ];

        $statusFile = $statusDir . '/' . $jobId . '.json';
        file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $phpBin = self::findPhpCliBinary();
        $scriptPath = dirname(__DIR__, 2) . '/scripts/ksef_send_batch_bg.php';
        $logFile = $statusDir . '/' . $jobId . '.log';

        $disabledFns = array_map('trim', explode(',', ini_get('disable_functions') ?: ''));
        $canExec = function_exists('exec') && !in_array('exec', $disabledFns) && $phpBin !== null;

        if ($canExec) {
            $cmd = sprintf(
                'nohup %s %s %s > %s 2>&1 &',
                escapeshellarg($phpBin),
                escapeshellarg($scriptPath),
                escapeshellarg($jobId),
                escapeshellarg($logFile)
            );
            exec($cmd);

            // Brief wait then verify the background process actually launched
            usleep(200000); // 0.2s
            if (self::isBackgroundJobRunning($statusFile, $logFile)) {
                return $jobId; // Background process started successfully
            }
        }

        // Fallback: run synchronously
        error_log("KSeF batch send: running synchronously for job {$jobId}");
        self::runKsefBatchSendSync($statusFile, $job);
        return $jobId;
    }

    /**
     * Run batch KSeF send synchronously (fallback).
     */
    private static function runKsefBatchSendSync(string $statusFile, array $job): void
    {
        try {
            $job['status'] = 'running';
            $job['message'] = 'Wysylanie (tryb synchroniczny)...';
            $job['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $client = \App\Models\Client::findById($job['client_id']);
            if (!$client) throw new \RuntimeException('Client not found');

            $ksef = \App\Services\KsefApiService::forClient($client);
            if (!$ksef->isConfigured()) throw new \RuntimeException('KSeF not configured');
            $ksef->enableLogging();
            if (!$ksef->authenticate()) throw new \RuntimeException('KSeF auth failed');

            $results = [];
            $success = 0;
            $errors = 0;

            // Phase 1: Build all XMLs
            $xmlList = [];
            $invoiceMap = [];
            $batchIdx = 0;

            foreach ($job['invoice_ids'] as $invoiceId) {
                $invoiceId = (int) $invoiceId;
                try {
                    $invoice = \App\Models\IssuedInvoice::findById($invoiceId);
                    if (!$invoice) {
                        $results[$invoiceId] = ['status' => 'error', 'message' => 'Invoice not found'];
                        $errors++;
                        continue;
                    }
                    $xmlList[$batchIdx] = \App\Services\KsefInvoiceSendService::buildKsefXml($invoice, $client);
                    $invoiceMap[$batchIdx] = ['id' => $invoiceId, 'number' => $invoice['invoice_number']];
                    $batchIdx++;
                } catch (\Throwable $e) {
                    $results[$invoiceId] = ['status' => 'error', 'message' => $e->getMessage()];
                    $errors++;
                }
            }

            // Phase 2: Submit all in ONE session
            if (!empty($xmlList)) {
                $batchResults = $ksef->submitBatch($xmlList);

                foreach ($batchResults as $bIdx => $bResult) {
                    if (!isset($invoiceMap[$bIdx])) continue;
                    $invoiceId = $invoiceMap[$bIdx]['id'];

                    if (!empty($bResult['referenceNumber'])) {
                        $isPartial = !empty($bResult['partial']);
                        \App\Models\IssuedInvoice::updateKsefStatus($invoiceId, 'sent', $bResult['referenceNumber']);
                        \App\Models\IssuedInvoice::updateStatus($invoiceId, 'sent_ksef');
                        if (!empty($bResult['sessionRef'])) {
                            \App\Models\IssuedInvoice::updateKsefSessionRef($invoiceId, $bResult['sessionRef']);
                        }
                        if (!empty($bResult['elementRef'])) {
                            \App\Models\IssuedInvoice::updateKsefElementRef($invoiceId, $bResult['elementRef']);
                        }
                        if (!$isPartial) {
                            try { \App\Services\InvoicePdfService::generate($invoiceId); } catch (\Throwable $pdfErr) {
                                error_log("PDF regen failed for invoice {$invoiceId}: " . $pdfErr->getMessage());
                            }
                        }
                        $results[$invoiceId] = ['status' => 'completed', 'message' => $bResult['referenceNumber']];
                        $success++;
                    } else {
                        $err = $bResult['error'] ?? 'Unknown';
                        \App\Models\IssuedInvoice::updateKsefStatus($invoiceId, 'error', null, $err);
                        $results[$invoiceId] = ['status' => 'error', 'message' => $err];
                        $errors++;
                    }
                }
            }

            $job['results'] = $results;
            $job['success_count'] = $success;
            $job['error_count'] = $errors;
            $job['status'] = 'completed';
            $job['message'] = "Wyslano {$success} z " . count($job['invoice_ids']) . " faktur.";
        } catch (\Throwable $e) {
            $job['status'] = 'error';
            $job['message'] = $e->getMessage();
        }
        $job['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Find PHP CLI binary path. Returns null if only php-fpm is available.
     */
    private static function findPhpCliBinary(): ?string
    {
        // PHP_BINARY often points to php-fpm in web context — check if it's CLI
        $binary = PHP_BINARY;
        if ($binary && !preg_match('/php-fpm|php-cgi/i', $binary)) {
            return $binary;
        }

        // Try to find php CLI in common locations
        $candidates = [
            // Try replacing php-fpm with php in the same directory
            $binary ? preg_replace('/php-fpm[\d.]*$/i', 'php', $binary) : null,
            '/usr/bin/php',
            '/usr/local/bin/php',
            '/opt/plesk/php/8.3/bin/php',
            '/opt/plesk/php/8.2/bin/php',
            '/opt/plesk/php/8.1/bin/php',
            '/opt/alt/php83/usr/bin/php',
            '/opt/alt/php82/usr/bin/php',
            '/opt/cpanel/ea-php83/root/usr/bin/php',
            '/opt/cpanel/ea-php82/root/usr/bin/php',
        ];

        foreach (array_filter($candidates) as $path) {
            if ($path !== $binary && is_executable($path)) {
                return $path;
            }
        }

        // Last resort: try 'php' from PATH via which
        $which = @exec('which php 2>/dev/null');
        if ($which && is_executable($which) && !preg_match('/php-fpm|php-cgi/i', $which)) {
            return $which;
        }

        return null;
    }

    /**
     * Check if a background job actually started running.
     *
     * Strategy: look for a ".pid" marker file that the background script creates
     * immediately on startup (before loading autoloader). If the marker exists,
     * the process launched. If not after 200ms, check if log file contains a
     * fatal PHP error.
     */
    private static function isBackgroundJobRunning(string $statusFile, string $logFile): bool
    {
        $pidFile = $statusFile . '.pid';

        // The background script creates a .pid file immediately on startup
        if (file_exists($pidFile)) {
            return true;
        }

        // Check if status was updated (process already started processing)
        clearstatcache(true, $statusFile);
        $currentJob = json_decode(file_get_contents($statusFile), true);
        if (($currentJob['status'] ?? 'queued') !== 'queued') {
            return true;
        }

        // Check log file for fatal errors that prevented startup
        if (file_exists($logFile)) {
            clearstatcache(true, $logFile);
            if (filesize($logFile) > 0) {
                $content = file_get_contents($logFile);
                if (str_contains($content, 'Fatal error') || str_contains($content, 'Parse error')) {
                    error_log("KSeF BG process error: " . $content);
                    return false;
                }
            }
            // Log file exists but no errors — process is likely loading
            return true;
        }

        return false;
    }

    /**
     * Run KSeF import synchronously (fallback when exec() is unavailable).
     */
    private static function runKsefImportSync(string $statusFile, array $job): void
    {
        try {
            $job['status'] = 'running';
            $job['message'] = 'Łączenie z KSeF...';
            $job['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $client = \App\Models\Client::findById($job['client_id']);
            if (!$client) {
                throw new \RuntimeException('Client not found: ' . $job['client_id']);
            }

            $ksef = \App\Services\KsefApiService::forClient($client);
            if (!$ksef->isConfigured()) {
                throw new \RuntimeException('KSeF nie jest skonfigurowane dla tego klienta.');
            }

            $ksef->enableLogging();
            $result = $ksef->importInvoicesToBatch(
                $job['client_id'],
                $client['nip'],
                $job['month'],
                $job['year'],
                $job['imported_by_id'],
                $job['imported_by_type'],
                $job['office_id']
            );

            $job['result'] = $result;
            $skipped = $result['skipped'] ?? 0;
            if ($result['success'] > 0) {
                $message = "Zaimportowano {$result['success']} z {$result['total']} faktur.";
            } elseif ($result['total'] == 0 || $skipped >= $result['total']) {
                $message = 'Brak nowych faktur do pobrania za wybrany okres.';
            } else {
                $message = 'Import zakończony z błędami.';
            }
            $job['status'] = 'completed';
            $job['message'] = $message;
            $job['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            \App\Models\AuditLog::log(
                $job['imported_by_type'],
                $job['imported_by_id'],
                'ksef_import',
                json_encode($result),
                'client',
                $job['client_id']
            );

            // Log to KSeF operations log for reports
            try {
                \App\Models\KsefOperationLog::log(
                    $job['client_id'],
                    'import_batch',
                    ($result['success'] > 0 || ($result['total'] === $skipped && $result['total'] > 0)) ? 'success' : 'failed',
                    $job['imported_by_type'],
                    $job['imported_by_id'],
                    json_encode(['month' => $job['month'], 'year' => $job['year']]),
                    json_encode(['total' => $result['total'], 'success' => $result['success'], 'skipped' => $skipped, 'errors' => count($result['errors'] ?? [])]),
                    !empty($result['errors']) ? implode('; ', array_slice($result['errors'], 0, 3)) : null,
                    null,
                    null
                );
            } catch (\Throwable $logErr) {
                error_log("KSeF import log error: " . $logErr->getMessage());
            }
        } catch (\Throwable $e) {
            error_log("KSeF sync import error: " . $e->getMessage());
            $job['status'] = 'error';
            $job['message'] = $e->getMessage();
            $job['result'] = ['success' => 0, 'total' => 0, 'errors' => [$e->getMessage()]];
            $job['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            // Log failed import to KSeF operations log
            try {
                \App\Models\KsefOperationLog::log(
                    $job['client_id'],
                    'import_batch',
                    'failed',
                    $job['imported_by_type'],
                    $job['imported_by_id'],
                    json_encode(['month' => $job['month'], 'year' => $job['year']]),
                    null,
                    $e->getMessage(),
                    null,
                    null
                );
            } catch (\Throwable $logErr) {
                error_log("KSeF import log error: " . $logErr->getMessage());
            }
        }
    }

    protected function getBranding(): array
    {
        static $branding = null;
        if ($branding !== null) return $branding;

        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN (
                'system_name', 'system_description', 'primary_color', 'secondary_color',
                'accent_color', 'logo_path', 'logo_path_dark', 'logo_path_login',
                'privacy_policy_enabled', 'privacy_policy_text'
            )");
            $branding = [];
            foreach ($rows as $row) {
                $branding[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Exception $e) {
            $branding = [];
        }

        return $branding + [
            'system_name' => 'BiLLU Financial Solutions',
            'system_description' => '',
            'primary_color' => '#008F8F',
            'secondary_color' => '#0B2430',
            'accent_color' => '#22C55E',
            'logo_path' => '/assets/img/logo.svg',
            'logo_path_dark' => '/assets/img/logo.svg',
            'logo_path_login' => '/assets/img/logo.svg',
            'privacy_policy_enabled' => '0',
            'privacy_policy_text' => '',
        ];
    }
}
