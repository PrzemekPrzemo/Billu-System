<?php

/**
 * Mail queue worker.
 *
 * Crontab entry (every minute):
 *   * * * * * php /path/to/billu/mail-worker.php >> /var/log/billu-mail.log 2>&1
 *
 * Bezpieczne uruchamianie wielokrotne równolegle (FOR UPDATE SKIP LOCKED).
 * Domyślnie przetwarza 20 maili na uruchomienie - jeśli kolejka rośnie,
 * zwiększyć batch lub uruchamiać częściej / w pętli.
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$appConfig = require __DIR__ . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

\App\Core\Cache::init(require __DIR__ . '/config/cache.php');

$batchSize = (int)($argv[1] ?? 20);
$result = \App\Services\MailQueueService::processBatch($batchSize);

if ($result['processed'] > 0 || !empty($result['errors'])) {
    echo "[" . date('Y-m-d H:i:s') . "] mail-worker: ";
    echo "processed={$result['processed']}, sent={$result['sent']}, failed={$result['failed']}\n";
    foreach ($result['errors'] as $err) {
        echo "  ERROR: {$err}\n";
    }
}
