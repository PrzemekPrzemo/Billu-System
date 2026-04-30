<?php

declare(strict_types=1);

/**
 * Background worker for e-US Bramka B status polling.
 *
 * Invoked by CronService::processEusJobs() for each eus_jobs row
 * with job_type='poll_b' and state='pending' and next_run_at <= NOW.
 *
 * Usage:
 *   php scripts/eus_poll_b_bg.php <jobId>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: CLI only.\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

if ($argc < 2 || !ctype_digit((string) $argv[1])) {
    fwrite(STDERR, "Usage: php scripts/eus_poll_b_bg.php <jobId>\n");
    exit(2);
}
$jobId = (int) $argv[1];

$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Warsaw');
\App\Core\Cache::init(require __DIR__ . '/../config/cache.php');

try {
    $svc = new \App\Services\EusSubmissionService();
    $svc->pollOnce($jobId);
    echo "[OK] eus poll_b job {$jobId} done\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] eus poll_b job {$jobId}: " . $e->getMessage() . "\n");
    error_log("eus_poll_b_bg job {$jobId}: " . $e->getMessage());
    exit(1);
}
