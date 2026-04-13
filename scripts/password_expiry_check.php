<?php
/**
 * Password Expiry Warning — Cron Script
 *
 * Checks all active clients whose passwords expire within 14 days
 * and sends a warning email once.
 *
 * Usage:  php scripts/password_expiry_check.php
 * Cron:   0 8 * * * php /path/to/scripts/password_expiry_check.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\MailService;

$db = Database::getInstance();

// Get password expiry setting (default 90 days)
$setting = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'password_expiry_days'");
$expiryDays = $setting ? (int) $setting['setting_value'] : 90;

$warningDays = 14;
$thresholdDays = $expiryDays - $warningDays;

// Find active clients whose password was changed exactly (expiryDays - warningDays) ago
// or up to expiryDays ago (so we catch the whole 14-day window but only send once per day range)
$now = new DateTime();
$warningDate = (clone $now)->modify("-{$thresholdDays} days")->format('Y-m-d');

// Select clients whose password_changed_at falls on the warning date (send once)
$clients = $db->fetchAll(
    "SELECT id, company_name, email, language, password_changed_at
     FROM clients
     WHERE is_active = 1
       AND DATE(password_changed_at) = ?",
    [$warningDate]
);

if (empty($clients)) {
    echo "No clients need password expiry warnings today.\n";
    exit(0);
}

// Determine login URL from app config or default
$appUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$configPath = __DIR__ . '/../config/app.php';
if (file_exists($configPath)) {
    $appConfig = require $configPath;
    if (!empty($appConfig['url'])) {
        $appUrl = rtrim($appConfig['url'], '/');
    }
}
$loginUrl = $appUrl . '/login';

$sent = 0;
$failed = 0;

foreach ($clients as $client) {
    $daysLeft = $warningDays;
    $lang = $client['language'] ?? 'pl';

    $ok = MailService::sendPasswordExpiryWarning(
        $client['email'],
        $client['company_name'],
        $daysLeft,
        $loginUrl,
        $lang
    );

    if ($ok) {
        $sent++;
        echo "Sent warning to: {$client['company_name']} ({$client['email']})\n";
    } else {
        $failed++;
        echo "FAILED to send to: {$client['company_name']} ({$client['email']})\n";
    }
}

echo "\nDone. Sent: {$sent}, Failed: {$failed}\n";
