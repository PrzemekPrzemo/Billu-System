<?php
/**
 * KSeF Background Import Worker
 *
 * Launched by the web controller via exec() to avoid HTTP timeout.
 * Reads job parameters from a JSON status file, runs the import,
 * and writes progress/results back to the same file.
 *
 * Usage: php scripts/ksef_import_bg.php <job_id>
 */

declare(strict_types=1);

// No time limit for background processing
set_time_limit(0);
ini_set('max_execution_time', '0');

$projectRoot = dirname(__DIR__);

// Write PID marker immediately so the launcher knows we started
$jobIdRaw = $argv[1] ?? '';
$pidFile = $projectRoot . '/storage/imports/' . $jobIdRaw . '.json.pid';
@file_put_contents($pidFile, (string) getmypid());

// Register shutdown function to catch fatal errors
register_shutdown_function(function () use ($projectRoot) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $jobId = $GLOBALS['argv'][1] ?? '';
        if ($jobId && preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            $statusFile = $projectRoot . '/storage/imports/' . $jobId . '.json';
            if (file_exists($statusFile)) {
                $job = json_decode(file_get_contents($statusFile), true) ?: [];
                $job['status'] = 'error';
                $job['message'] = "Fatal error: {$error['message']} in {$error['file']}:{$error['line']}";
                $job['updated_at'] = date('Y-m-d H:i:s');
                $job['result'] = ['success' => 0, 'total' => 0, 'errors' => [$job['message']]];
                file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            }
        }
        error_log("KSeF BG import fatal: {$error['message']} in {$error['file']}:{$error['line']}");
    }
});

require_once $projectRoot . '/vendor/autoload.php';

use App\Models\Client;
use App\Models\Setting;
use App\Models\AuditLog;
use App\Models\KsefOperationLog;
use App\Services\KsefApiService;

// Load config
$appConfig = require $projectRoot . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Get job ID from CLI argument
$jobId = $argv[1] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
    fwrite(STDERR, "Invalid job ID\n");
    exit(1);
}

$statusDir = $projectRoot . '/storage/imports';
$statusFile = $statusDir . '/' . $jobId . '.json';

if (!file_exists($statusFile)) {
    fwrite(STDERR, "Status file not found: {$statusFile}\n");
    exit(1);
}

$job = json_decode(file_get_contents($statusFile), true);
if (!$job) {
    fwrite(STDERR, "Invalid job file\n");
    exit(1);
}

// Update status: running
updateStatus($statusFile, $job, 'running', 'Łączenie z KSeF...');

try {
    $client = Client::findById($job['client_id']);
    if (!$client) {
        throw new \RuntimeException('Client not found: ' . $job['client_id']);
    }

    // ── DEMO MODE: generate mock invoices instead of calling KSeF API ──
    if (!empty($client['is_demo'])) {
        updateStatus($statusFile, $job, 'running', 'Pobieranie faktur z KSeF (demo)...');
        $result = \App\Services\DemoKsefMockService::mockImport(
            (int) $job['client_id'],
            (int) $job['month'],
            (int) $job['year'],
            (int) $job['imported_by_id'],
            $job['imported_by_type'],
            (int) $job['office_id']
        );
        $job['result'] = $result;
        $message = "Zaimportowano {$result['success']} z {$result['total']} faktur (demo).";
        updateStatus($statusFile, $job, 'completed', $message, $result);
        @unlink($pidFile);
        exit(0);
    }

    $ksef = KsefApiService::forClient($client);
    if (!$ksef->isConfigured()) {
        throw new \RuntimeException('KSeF nie jest skonfigurowane dla tego klienta.');
    }

    updateStatus($statusFile, $job, 'running', 'Autentykacja w KSeF...');

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

    AuditLog::log(
        $job['imported_by_type'],
        $job['imported_by_id'],
        'ksef_import',
        json_encode($result),
        'client',
        $job['client_id']
    );

    $job['result'] = $result;
    $skipped = $result['skipped'] ?? 0;
    $errorCount = count($result['errors'] ?? []);
    if ($result['success'] > 0) {
        $message = "Zaimportowano {$result['success']} z {$result['total']} faktur.";
        if ($skipped > 0) $message .= " Pominięto {$skipped} (duplikaty).";
        if ($errorCount > 0) $message .= " Błędów: {$errorCount}.";
    } elseif ($result['total'] == 0 || $skipped >= $result['total']) {
        $message = 'Brak nowych faktur do pobrania za wybrany okres.';
    } else {
        $errDetails = implode('; ', array_slice($result['errors'] ?? [], 0, 3));
        $message = "Import zakończony z błędami ({$errorCount}): {$errDetails}";
    }
    $status = 'completed';

    updateStatus($statusFile, $job, $status, $message, $result);

    // Log to KSeF operations log for reports
    try {
        KsefOperationLog::log(
            $job['client_id'],
            'import_batch',
            ($result['success'] > 0 || ($result['total'] === $skipped && $result['total'] > 0)) ? 'success' : 'failed',
            $job['imported_by_type'],
            $job['imported_by_id'],
            json_encode(['month' => $job['month'], 'year' => $job['year']]),
            json_encode(['total' => $result['total'], 'success' => $result['success'], 'skipped' => $skipped, 'errors' => $errorCount]),
            $errorCount > 0 ? implode('; ', array_slice($result['errors'] ?? [], 0, 3)) : null,
            null,
            null
        );
    } catch (\Throwable $logErr) {
        error_log("KSeF import log error: " . $logErr->getMessage());
    }

} catch (\Throwable $e) {
    error_log("KSeF BG import error [job {$jobId}]: " . $e->getMessage());

    $result = [
        'success' => 0,
        'total' => 0,
        'errors' => ['KSeF Error: ' . $e->getMessage()],
    ];
    updateStatus($statusFile, $job, 'error', $e->getMessage(), $result);

    // Log failed import to KSeF operations log
    try {
        KsefOperationLog::log(
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

// Clean up PID marker
@unlink($pidFile);

function updateStatus(string $file, array $job, string $status, string $message, ?array $result = null): void
{
    $job['status'] = $status;
    $job['message'] = $message;
    $job['updated_at'] = date('Y-m-d H:i:s');
    if ($result !== null) {
        $job['result'] = $result;
    }
    file_put_contents($file, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
