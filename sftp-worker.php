<?php

/**
 * SFTP queue worker.
 *
 * Crontab entry (every minute):
 *   * * * * * /opt/plesk/php/8.4/bin/php /var/www/vhosts/portal.billu.pl/httpdocs/sftp-worker.php >> /var/log/billu-sftp.log 2>&1
 *
 * Two parallel runs are safe: SftpUploadService::processQueue uses an
 * UPDATE-with-WHERE-status='pending' pattern + per-row attempts counter
 * that converges. For multi-host setups consider switching to
 * 'FOR UPDATE SKIP LOCKED' inside the model.
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$appConfig = require __DIR__ . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Europe/Warsaw');

\App\Core\Cache::init(require __DIR__ . '/config/cache.php');

$start = microtime(true);
$result = \App\Services\SftpUploadService::processQueue(20);
$elapsed = number_format(microtime(true) - $start, 2);

echo sprintf(
    "[%s] sftp-worker: processed=%d sent=%d failed=%d (%ss)\n",
    date('Y-m-d H:i:s'),
    $result['processed'], $result['sent'], $result['failed'], $elapsed
);
