<?php
/**
 * KSeF Backfill - recover missing KSeF reference numbers for invoices.
 *
 * Usage:
 *   php scripts/ksef_backfill.php <client_id> [--type=sales|purchase|all] [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]
 *
 * Examples:
 *   php scripts/ksef_backfill.php 5                          # both sales + purchase
 *   php scripts/ksef_backfill.php 5 --type=purchase          # only purchase invoices
 *   php scripts/ksef_backfill.php 5 --type=sales --from=2026-01-01
 */

declare(strict_types=1);

set_time_limit(0);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

use App\Models\Client;
use App\Services\KsefApiService;

$appConfig = require $projectRoot . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

// Parse arguments
$clientId = (int) ($argv[1] ?? 0);
if ($clientId <= 0) {
    fwrite(STDERR, "Usage: php scripts/ksef_backfill.php <client_id> [--type=sales|purchase|all] [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]\n");
    exit(1);
}

$dateFrom = null;
$dateTo = null;
$type = 'all';
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $dateFrom = substr($arg, 7);
    } elseif (str_starts_with($arg, '--to=')) {
        $dateTo = substr($arg, 5);
    } elseif (str_starts_with($arg, '--type=')) {
        $type = substr($arg, 7);
    }
}

if (!in_array($type, ['sales', 'purchase', 'all'])) {
    fwrite(STDERR, "Invalid type: {$type}. Use: sales, purchase, or all\n");
    exit(1);
}

$client = Client::findById($clientId);
if (!$client) {
    fwrite(STDERR, "Client not found: {$clientId}\n");
    exit(1);
}

echo "KSeF Backfill for client: {$client['company_name']} (NIP: {$client['nip']})\n";
echo "  Type: {$type}\n";
if ($dateFrom) echo "  From: {$dateFrom}\n";
if ($dateTo) echo "  To:   {$dateTo}\n";
echo "\n";

$ksef = KsefApiService::forClient($client);
if (!$ksef->isConfigured()) {
    fwrite(STDERR, "KSeF is not configured for this client.\n");
    exit(1);
}

$ksef->enableLogging();

echo "Authenticating with KSeF...\n";
if (!$ksef->authenticate()) {
    fwrite(STDERR, "KSeF authentication failed.\n");
    exit(1);
}

$exitCode = 0;

// Sales invoices (issued)
if ($type === 'sales' || $type === 'all') {
    echo "=== Faktury sprzedazowe (issued) ===\n";
    $result = $ksef->backfillKsefNumbers($clientId, $dateFrom, $dateTo);
    echo "  Recovered: {$result['recovered']}\n";
    echo "  Failed:    {$result['failed']}\n";
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $err) echo "    - {$err}\n";
    }
    if ($result['recovered'] === 0 && $result['failed'] === 0) {
        echo "  No invoices with missing KSeF numbers.\n";
    }
    if ($result['failed'] > 0) $exitCode = 1;
    echo "\n";
}

// Purchase invoices (received)
if ($type === 'purchase' || $type === 'all') {
    echo "=== Faktury zakupowe (purchase) ===\n";
    $result = $ksef->backfillPurchaseKsefNumbers($clientId, $dateFrom, $dateTo);
    echo "  Total without KSeF: {$result['total']}\n";
    echo "  Recovered: {$result['recovered']}\n";
    echo "  Failed:    {$result['failed']}\n";
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $err) echo "    - {$err}\n";
    }
    if ($result['total'] === 0) {
        echo "  All purchase invoices already have KSeF numbers.\n";
    }
    if ($result['failed'] > 0) $exitCode = 1;
}

exit($exitCode);
