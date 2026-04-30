<?php

/**
 * Cron job script - run daily.
 *
 * Crontab entry:
 * 0 8 * * * php /path/to/billu/cron.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$appConfig = require __DIR__ . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

use App\Services\CronService;

echo "[" . date('Y-m-d H:i:s') . "] Starting cron job...\n";

// 1. Auto-accept expired batches
echo "Processing expired batches...\n";
$result = CronService::processExpiredBatches();
echo "  Processed: {$result['processed']}, Auto-accepted: {$result['auto_accepted']}, Reports sent: {$result['reports_sent']}\n";
if (!empty($result['errors'])) {
    foreach ($result['errors'] as $err) {
        echo "  ERROR: {$err}\n";
    }
}

// 2. Send notifications about new invoices
echo "Sending new invoice notifications...\n";
$sent = CronService::sendNewInvoiceNotifications();
echo "  Notifications sent: {$sent}\n";

// 3. Send deadline reminders
echo "Sending deadline reminders...\n";
$reminders = CronService::sendDeadlineReminders();
echo "  Reminders sent: {$reminders}\n";

// 4. Auto-import from KSeF (runs on configured day of month)
echo "Checking KSeF auto-import...\n";
$ksefResult = CronService::autoImportFromKsef();
echo "  Clients processed: {$ksefResult['clients']}, Invoices imported: {$ksefResult['invoices']}\n";
if (!empty($ksefResult['errors'])) {
    foreach ($ksefResult['errors'] as $err) {
        echo "  ERROR: {$err}\n";
    }
}

// 5. Retry failed KSeF connections + auto-send pending invoices
echo "Retrying failed KSeF connections...\n";
$retryResult = CronService::retryKsefConnections();
echo "  Clients checked: {$retryResult['clients_checked']}, Connections restored: {$retryResult['connections_restored']}, Invoices sent: {$retryResult['invoices_sent']}\n";
if (!empty($retryResult['errors'])) {
    foreach ($retryResult['errors'] as $err) {
        echo "  ERROR: {$err}\n";
    }
}

// 6. Check expiring KSeF certificates
echo "Checking expiring KSeF certificates...\n";
$certWarnings = CronService::checkExpiringCertificates();
echo "  Certificate expiry warnings sent: {$certWarnings}\n";

// 6b. Check expiring e-US UPL-1 (pełnomocnictwo) — creates office tasks
echo "Checking expiring e-US UPL-1 (pełnomocnictwo)...\n";
$eusWarnings = CronService::checkExpiringEusCredentials();
echo "  e-US UPL-1 expiry tasks created: {$eusWarnings}\n";

// 6c. Drain e-US Bramka B job queue — spawns bg workers
echo "Draining e-US Bramka B job queue...\n";
$eusJobs = CronService::processEusJobs();
echo "  Spawned: {$eusJobs['spawned']}, skipped: {$eusJobs['skipped']}\n";
foreach ($eusJobs['errors'] as $err) {
    echo "  ERROR: {$err}\n";
}

// 7. Process scheduled exports
echo "Processing scheduled exports...\n";
$schedResult = CronService::processScheduledExports();
echo "  Processed: {$schedResult['processed']}, Sent: {$schedResult['sent']}\n";
if (!empty($schedResult['errors'])) {
    foreach ($schedResult['errors'] as $err) {
        echo "  ERROR: {$err}\n";
    }
}

// 8. Cleanup old data (temp files, expired sessions, old logs)
echo "Cleaning up old data...\n";
$cleanup = CronService::cleanupOldData();
echo "  Files deleted: {$cleanup['files_deleted']}, Sessions cleared: {$cleanup['sessions_cleared']}, Tokens cleared: {$cleanup['tokens_cleared']}, Logs cleared: {$cleanup['logs_cleared']}\n";
if (!empty($cleanup['errors'])) {
    foreach ($cleanup['errors'] as $err) {
        echo "  ERROR: {$err}\n";
    }
}

// 9. Tax calendar alerts
echo "Processing tax calendar alerts...\n";
$taxAlerts = CronService::processTaxCalendarAlerts();
echo "  Checked: {$taxAlerts['checked']}, Alerts sent: {$taxAlerts['alerts_sent']}\n";
if (!empty($taxAlerts['errors'])) {
    foreach ($taxAlerts['errors'] as $err) {
        echo "  ERROR: {$err}\n";
    }
}

// 10. Weekly duplicate scan (only on Mondays)
if (date('N') == 1) {
    echo "Running weekly duplicate scan...\n";
    $dupResult = CronService::scanForDuplicates();
    echo "  Clients scanned: {$dupResult['clients_scanned']}, New duplicates: {$dupResult['new_duplicates']}\n";
}

// 11. Warmup NBP exchange rates (cache hit dla pierwszego logowania rano)
echo "Warming NBP rates cache...\n";
$nbp = CronService::warmupNbpRates();
echo "  Cached: {$nbp['cached']}\n";
foreach ($nbp['errors'] as $err) {
    echo "  ERROR: {$err}\n";
}

// 12. (Retired) Monthly audit_log archive to JSONL.gz.
//     Retention is now 37 days (enforced daily by step 5 in cleanupOldData),
//     so the older "archive everything past 24 months" pass has nothing
//     to do. Kept the CronService::archiveAuditLog method available for
//     ad-hoc operator use, but no longer scheduled.

// 13. Cleanup expired trusted-device tokens
echo "Cleaning up expired trusted devices...\n";
$removed = \App\Models\TrustedDevice::cleanupExpired();
echo "  Removed: {$removed} row(s)\n";

// 14. Expire pending contract forms past their TTL
echo "Expiring overdue contract forms...\n";
$expired = \App\Models\ContractForm::expireOverdue();
echo "  Expired: {$expired} form(s)\n";

echo "[" . date('Y-m-d H:i:s') . "] Cron job completed.\n";
