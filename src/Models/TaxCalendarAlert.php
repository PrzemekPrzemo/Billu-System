<?php

namespace App\Models;

use App\Core\Database;

class TaxCalendarAlert
{
    public static function wasAlertSent(int $clientId, string $obligationType, string $deadlineDate): bool
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT id FROM tax_calendar_alerts
             WHERE client_id = ? AND obligation_type = ? AND deadline_date = ?",
            [$clientId, $obligationType, $deadlineDate]
        );
        return $row !== null;
    }

    public static function markSent(int $clientId, string $obligationType, string $deadlineDate): void
    {
        Database::getInstance()->query(
            "INSERT IGNORE INTO tax_calendar_alerts (client_id, obligation_type, deadline_date)
             VALUES (?, ?, ?)",
            [$clientId, $obligationType, $deadlineDate]
        );
    }

    public static function cleanOld(int $days = 180): int
    {
        $stmt = Database::getInstance()->query(
            "DELETE FROM tax_calendar_alerts WHERE deadline_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)",
            [$days]
        );
        return $stmt->rowCount();
    }
}
