<?php

declare(strict_types=1);

/**
 * Defensive retention purge — soft-purges eus_documents rows whose
 * retain_until has passed. Tombstone-style: keeps the row id so FK
 * integrity from messages/client_tasks holds, but nulls payload_path
 * + upo_path and stamps purged_at.
 *
 * Idempotent (skips already-purged rows).
 *
 * Manual override: master admin can pass --client=N to scope to one
 * client (used by RODO export-then-delete flow).
 *
 * Usage:
 *   php scripts/eus_retention_purge.php
 *   php scripts/eus_retention_purge.php --client=42
 *   php scripts/eus_retention_purge.php --dry-run
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: CLI only.\n");
}

require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Warsaw');

$opts = getopt('', ['client::', 'dry-run']);
$clientId = isset($opts['client']) && ctype_digit((string) $opts['client'])
    ? (int) $opts['client']
    : null;
$dryRun = array_key_exists('dry-run', $opts);

$db = \App\Core\Database::getInstance();
$where = " WHERE retain_until IS NOT NULL
            AND retain_until < CURDATE()
            AND purged_at IS NULL ";
$params = [];
if ($clientId !== null) {
    $where .= " AND client_id = ? ";
    $params[] = $clientId;
}

$rows = $db->fetchAll("SELECT id FROM eus_documents{$where} ORDER BY id ASC LIMIT 1000", $params);
$count = count($rows);
echo "Found {$count} expired retention rows" . ($clientId ? " for client #{$clientId}" : '')
    . ($dryRun ? ' (dry-run, no changes will be made)' : '') . "\n";

if ($dryRun || $count === 0) {
    exit(0);
}

$purged = 0;
foreach ($rows as $row) {
    try {
        \App\Models\EusDocument::softPurge((int) $row['id']);
        $purged++;
    } catch (\Throwable $e) {
        fwrite(STDERR, "Failed to purge document {$row['id']}: " . $e->getMessage() . "\n");
    }
}
echo "Soft-purged {$purged} of {$count} rows.\n";
exit(0);
