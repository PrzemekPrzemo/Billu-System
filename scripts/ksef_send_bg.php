<?php
/**
 * KSeF Background Invoice Send Worker
 *
 * Launched by the web controller via exec() to avoid HTTP timeout.
 * Handles: authenticate → build XML → submit invoice → download UPO.
 *
 * Usage: php scripts/ksef_send_bg.php <job_id>
 */

declare(strict_types=1);

set_time_limit(0);
ini_set('max_execution_time', '0');

$projectRoot = dirname(__DIR__);

// Write PID marker immediately so the launcher knows we started
$jobIdRaw = $argv[1] ?? '';
$pidFile = $projectRoot . '/storage/ksef_send/' . $jobIdRaw . '.json.pid';
@file_put_contents($pidFile, (string) getmypid());

register_shutdown_function(function () use ($projectRoot) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $jobId = $GLOBALS['argv'][1] ?? '';
        if ($jobId && preg_match('/^[a-f0-9]{32}$/', $jobId)) {
            $statusFile = $projectRoot . '/storage/ksef_send/' . $jobId . '.json';
            if (file_exists($statusFile)) {
                $job = json_decode(file_get_contents($statusFile), true) ?: [];
                $job['status'] = 'error';
                $job['message'] = "Fatal error: {$error['message']} in {$error['file']}:{$error['line']}";
                $job['updated_at'] = date('Y-m-d H:i:s');
                file_put_contents($statusFile, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            }
        }
    }
});

require_once $projectRoot . '/vendor/autoload.php';

use App\Models\Client;
use App\Models\IssuedInvoice;
use App\Services\KsefApiService;
use App\Services\KsefInvoiceSendService;

$appConfig = require $projectRoot . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

$jobId = $argv[1] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $jobId)) {
    fwrite(STDERR, "Invalid job ID\n");
    exit(1);
}

$statusDir = $projectRoot . '/storage/ksef_send';
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

$invoiceId = (int) $job['invoice_id'];

try {
    // Step 1: Load invoice and client
    updateStatus($statusFile, $job, 'running', 'Wczytywanie faktury...');

    $invoice = IssuedInvoice::findById($invoiceId);
    if (!$invoice) {
        throw new \RuntimeException('Faktura nie znaleziona: ' . $invoiceId);
    }

    $client = Client::findById($invoice['client_id']);
    if (!$client) {
        throw new \RuntimeException('Klient nie znaleziony: ' . $invoice['client_id']);
    }

    // Step 2: Authenticate
    updateStatus($statusFile, $job, 'running', 'Autentykacja w KSeF...');

    $ksef = KsefApiService::forClient($client);
    if (!$ksef->isConfigured()) {
        throw new \RuntimeException('KSeF nie jest skonfigurowane dla tego klienta.');
    }

    $ksef->enableLogging();

    if (!$ksef->authenticate()) {
        $logger = $ksef->getLogger();
        $logDetail = $logger ? $logger->getSessionId() : 'brak';
        throw new \RuntimeException('Autentykacja KSeF nie powiodla sie. Log sesji: ' . $logDetail);
    }

    // Step 3: Build XML
    updateStatus($statusFile, $job, 'running', 'Budowanie XML faktury (FA3)...');
    $xml = KsefInvoiceSendService::buildKsefXml($invoice, $client);

    // Save generated XML for debugging
    $xmlDebugDir = $projectRoot . '/storage/ksef_send/xml';
    if (!is_dir($xmlDebugDir)) {
        mkdir($xmlDebugDir, 0775, true);
    }
    $xmlDebugFile = $xmlDebugDir . '/' . $jobId . '.xml';
    file_put_contents($xmlDebugFile, $xml);
    $job['xml_debug_file'] = 'storage/ksef_send/xml/' . $jobId . '.xml';
    error_log("KSeF BG [job {$jobId}]: XML saved to {$xmlDebugFile} (" . strlen($xml) . " bytes)");

    // Step 4: Submit invoice
    updateStatus($statusFile, $job, 'running', 'Wysylanie faktury do KSeF...');
    $result = $ksef->submitInvoice($xml);

    if (!empty($result['referenceNumber'])) {
        IssuedInvoice::updateKsefStatus($invoiceId, 'sent', $result['referenceNumber']);
        IssuedInvoice::updateStatus($invoiceId, 'sent_ksef');

        // Save session reference for later UPO downloads
        $sessionRef = $result['sessionRef'] ?? null;
        if ($sessionRef) {
            IssuedInvoice::updateKsefSessionRef($invoiceId, $sessionRef);
        }

        // Step 5: Try to download UPO (use session ref, not invoice ref)
        $ksefConfig = KsefConfig::findByClientId($clientId);
        if (!($ksefConfig['upo_enabled'] ?? true)) {
            error_log("KSeF UPO: skipping — disabled for client {$clientId}");
            $job['upo_skipped'] = true;
        } else {
        updateStatus($statusFile, $job, 'running', 'Pobieranie UPO z KSeF...');
        try {
            $upoRef = $sessionRef ?? $result['referenceNumber'];
            error_log("KSeF UPO: attempting download for invoice {$invoiceId}, sessionRef={$sessionRef}, ksefRef={$result['referenceNumber']}");
            $upoResult = $ksef->downloadUpo($upoRef, $result['referenceNumber'] ?? null);
            if ($upoResult && !empty($upoResult['content'])) {
                $upoDir = $projectRoot . '/storage/upo';
                if (!is_dir($upoDir)) {
                    mkdir($upoDir, 0775, true);
                }
                $upoFilename = 'UPO_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice['invoice_number']) . '_' . date('Ymd_His') . '.xml';
                $upoPath = $upoDir . '/' . $upoFilename;
                file_put_contents($upoPath, $upoResult['content']);

                // Save UPO path in DB
                IssuedInvoice::updateKsefUpo($invoiceId, 'storage/upo/' . $upoFilename);

                $job['upo_path'] = 'storage/upo/' . $upoFilename;
            } else {
                $upoError = $upoResult['error'] ?? 'downloadUpo() zwrócił pusty content';
                error_log("KSeF UPO download empty for invoice {$invoiceId}: {$upoError}");
                $job['upo_error'] = $upoError;
            }
        } catch (\Throwable $e) {
            // UPO download failure is not critical — invoice was sent
            error_log("KSeF UPO download exception for invoice {$invoiceId}: " . $e->getMessage());
            $job['upo_error'] = $e->getMessage();
        }
        } // end upo_enabled check

        $job['reference'] = $result['referenceNumber'];
        updateStatus($statusFile, $job, 'completed', 'Faktura wyslana do KSeF. Nr ref: ' . $result['referenceNumber']);
    } else {
        $error = $result['error'] ?? 'Nieznany blad';
        IssuedInvoice::updateKsefStatus($invoiceId, 'error', null, $error);
        updateStatus($statusFile, $job, 'error', 'KSeF: ' . $error);
    }

} catch (\Throwable $e) {
    error_log("KSeF BG send error [job {$jobId}]: " . $e->getMessage());
    IssuedInvoice::updateKsefStatus($invoiceId, 'error', null, $e->getMessage());
    updateStatus($statusFile, $job, 'error', $e->getMessage());
}

// Clean up PID marker
@unlink($pidFile);

function updateStatus(string $file, array &$job, string $status, string $message): void
{
    $job['status'] = $status;
    $job['message'] = $message;
    $job['updated_at'] = date('Y-m-d H:i:s');
    file_put_contents($file, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
