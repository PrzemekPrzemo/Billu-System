<?php

declare(strict_types=1);

/**
 * Background worker for e-US Bramka B submission.
 *
 * Invoked by CronService::processEusJobs() via exec() (twin of
 * scripts/ksef_send_bg.php). Takes a single eus_jobs.id and runs
 * EusSubmissionService::submitNow() under that PID.
 *
 * Why a separate process?
 *   - Long-running cert auth + multipart upload should not hold a
 *     web request thread (FPM workers are precious).
 *   - Crash isolation: a buggy submission cannot kill a cron tick
 *     that's also draining other jobs.
 *
 * Usage:
 *   php scripts/eus_submit_b_bg.php <jobId>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: CLI only.\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

if ($argc < 2 || !ctype_digit((string) $argv[1])) {
    fwrite(STDERR, "Usage: php scripts/eus_submit_b_bg.php <jobId>\n");
    exit(2);
}
$jobId = (int) $argv[1];

// Minimal bootstrap — config + DB.
$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Warsaw');
\App\Core\Cache::init(require __DIR__ . '/../config/cache.php');

try {
    $svc = new \App\Services\EusSubmissionService();
    $svc->submitNow($jobId);
    echo "[OK] eus submit_b job {$jobId} done\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] eus submit_b job {$jobId}: " . $e->getMessage() . "\n");
    error_log("eus_submit_b_bg job {$jobId}: " . $e->getMessage());
    exit(1);
}
