<?php

declare(strict_types=1);

/**
 * Background poller for e-US Bramka C — single-client scope.
 *
 * Invoked by CronService::pollEusCorrespondence() per each client
 * config that's due (last_poll_at older than poll_interval_minutes).
 *
 * Usage:
 *   php scripts/eus_poll_c_bg.php <clientId>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: CLI only.\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

if ($argc < 2 || !ctype_digit((string) $argv[1])) {
    fwrite(STDERR, "Usage: php scripts/eus_poll_c_bg.php <clientId>\n");
    exit(2);
}
$clientId = (int) $argv[1];

$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Warsaw');
\App\Core\Cache::init(require __DIR__ . '/../config/cache.php');

try {
    $svc = new \App\Services\EusCorrespondenceService();
    $count = $svc->pollIncoming($clientId);
    echo "[OK] eus poll_c client {$clientId}: {$count} letter(s) ingested\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] eus poll_c client {$clientId}: " . $e->getMessage() . "\n");
    error_log("eus_poll_c_bg client {$clientId}: " . $e->getMessage());
    exit(1);
}
