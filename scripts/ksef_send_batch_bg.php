<?php
/**
 * KSeF Batch Send Worker - sends multiple invoices in ONE KSeF session.
 *
 * Usage: php scripts/ksef_send_batch_bg.php <batch_job_id>
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
use App\Services\InvoicePdfService;

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
    fwrite(STDERR, "Status file not found\n");
    exit(1);
}

$job = json_decode(file_get_contents($statusFile), true);
if (!$job || empty($job['invoice_ids'])) {
    fwrite(STDERR, "Invalid job file or no invoice IDs\n");
    exit(1);
}

$invoiceIds = $job['invoice_ids'];
$clientId = (int) $job['client_id'];

try {
    updateBatchStatus($statusFile, $job, 'running', 'Wczytywanie faktur...', []);

    $client = Client::findById($clientId);
    if (!$client) {
        throw new \RuntimeException('Klient nie znaleziony: ' . $clientId);
    }

    // ── DEMO MODE: simulate KSeF send without real API ──
    if (!empty($client['is_demo'])) {
        $demoResult = \App\Services\DemoKsefMockService::mockBatchSend($invoiceIds, $clientId);
        $job['success_count'] = $demoResult['success_count'];
        $job['error_count'] = $demoResult['error_count'];
        $msg = "Wysylka zakonczona (demo): {$demoResult['success_count']} wyslanych z " . count($invoiceIds) . " faktur.";
        updateBatchStatus($statusFile, $job, 'completed', $msg, $demoResult['results']);
        @unlink($pidFile);
        exit(0);
    }

    // Authenticate ONCE
    updateBatchStatus($statusFile, $job, 'running', 'Autentykacja w KSeF...', []);

    $ksef = KsefApiService::forClient($client);
    if (!$ksef->isConfigured()) {
        throw new \RuntimeException('KSeF nie jest skonfigurowane dla tego klienta.');
    }

    $ksef->enableLogging();

    if (!$ksef->authenticate()) {
        throw new \RuntimeException('Autentykacja KSeF nie powiodla sie.');
    }

    $xmlDebugDir = $projectRoot . '/storage/ksef_send/xml';
    if (!is_dir($xmlDebugDir)) {
        mkdir($xmlDebugDir, 0775, true);
    }

    // ── Phase 1: Build all XMLs upfront ──
    updateBatchStatus($statusFile, $job, 'running', 'Budowanie XML faktur...', []);

    $xmlList = [];       // batch index => xml string
    $invoiceMap = [];    // batch index => ['id' => int, 'number' => string]
    $results = [];
    $errorCount = 0;
    $batchIdx = 0;

    foreach ($invoiceIds as $invoiceId) {
        $invoiceId = (int) $invoiceId;
        try {
            $invoice = IssuedInvoice::findById($invoiceId);
            if (!$invoice) {
                $results[$invoiceId] = ['status' => 'error', 'message' => 'Faktura nie znaleziona'];
                $errorCount++;
                continue;
            }

            $xml = KsefInvoiceSendService::buildKsefXml($invoice, $client);
            file_put_contents($xmlDebugDir . '/' . $jobId . '_' . $invoiceId . '.xml', $xml);

            $xmlList[$batchIdx] = $xml;
            $invoiceMap[$batchIdx] = ['id' => $invoiceId, 'number' => $invoice['invoice_number']];
            $batchIdx++;
        } catch (\Throwable $e) {
            $results[$invoiceId] = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'invoice_number' => $invoice['invoice_number'] ?? "#{$invoiceId}",
            ];
            $errorCount++;
        }
    }

    if (empty($xmlList)) {
        throw new \RuntimeException('Nie udalo sie zbudowac zadnego XML.');
    }

    // ── Phase 2: Submit all in ONE KSeF session ──
    updateBatchStatus($statusFile, $job, 'running',
        'Wysylanie ' . count($xmlList) . ' faktur w jednej sesji KSeF...', $results);

    $onProgress = function (int $index, int $total, string $phase) use ($statusFile, &$job, &$results, $invoiceMap) {
        if ($phase === 'sending' && isset($invoiceMap[$index])) {
            $inv = $invoiceMap[$index];
            updateBatchStatus($statusFile, $job, 'running',
                'Wysylanie ' . ($index + 1) . '/' . $total . ': ' . $inv['number'] . '...', $results);
        } elseif ($phase === 'polling') {
            updateBatchStatus($statusFile, $job, 'running',
                'Oczekiwanie na numery KSeF...', $results);
        }
    };

    $batchResults = $ksef->submitBatch($xmlList, $onProgress);

    // ── Phase 3: Process results ──
    $successCount = 0;

    foreach ($batchResults as $bIdx => $bResult) {
        if (!isset($invoiceMap[$bIdx])) continue;
        $inv = $invoiceMap[$bIdx];
        $invoiceId = $inv['id'];
        $invoiceNumber = $inv['number'];

        if (!empty($bResult['referenceNumber'])) {
            $isPartial = !empty($bResult['partial']);
            IssuedInvoice::updateKsefStatus($invoiceId, 'sent', $bResult['referenceNumber']);
            IssuedInvoice::updateStatus($invoiceId, 'sent_ksef');

            if (!empty($bResult['sessionRef'])) {
                IssuedInvoice::updateKsefSessionRef($invoiceId, $bResult['sessionRef']);
            }
            if (!empty($bResult['elementRef'])) {
                IssuedInvoice::updateKsefElementRef($invoiceId, $bResult['elementRef']);
            }

            // Regenerate PDF with KSeF QR code (skip if partial — no full KSeF number for QR)
            if (!$isPartial) {
                try { InvoicePdfService::generate($invoiceId); } catch (\Throwable $pdfErr) {
                    error_log("PDF regen failed for invoice {$invoiceId}: " . $pdfErr->getMessage());
                }
            }

            $msg = $bResult['referenceNumber'];
            if ($isPartial) {
                $msg .= ' (numer tymczasowy — pełny numer KSeF zostanie pobrany później)';
            }

            $results[$invoiceId] = [
                'status' => 'completed',
                'message' => $msg,
                'invoice_number' => $invoiceNumber,
            ];
            $successCount++;
        } else {
            $err = $bResult['error'] ?? 'Nieznany blad';
            IssuedInvoice::updateKsefStatus($invoiceId, 'error', null, $err);
            $results[$invoiceId] = [
                'status' => 'error',
                'message' => $err,
                'invoice_number' => $invoiceNumber,
            ];
            $errorCount++;
        }
    }

    $finalMessage = "Wysylka zakonczona: {$successCount} wyslanych";
    if ($errorCount > 0) $finalMessage .= ", {$errorCount} bledow";
    $finalMessage .= " z " . count($invoiceIds) . " faktur.";

    $job['success_count'] = $successCount;
    $job['error_count'] = $errorCount;
    updateBatchStatus($statusFile, $job, 'completed', $finalMessage, $results);

} catch (\Throwable $e) {
    error_log("KSeF batch send error [job {$jobId}]: " . $e->getMessage());
    updateBatchStatus($statusFile, $job, 'error', $e->getMessage(), $results ?? []);
}

// Clean up PID marker
@unlink($pidFile);

function updateBatchStatus(string $file, array &$job, string $status, string $message, array $results): void
{
    $job['status'] = $status;
    $job['message'] = $message;
    $job['updated_at'] = date('Y-m-d H:i:s');
    $job['results'] = $results;
    file_put_contents($file, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
