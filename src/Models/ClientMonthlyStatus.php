<?php

namespace App\Models;

use App\Core\Database;

class ClientMonthlyStatus
{
    public const STEPS = ['import', 'weryfikacja', 'jpk', 'zamkniety'];

    public static function findByOfficePeriod(int $officeId, int $year, int $month): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT client_id, status FROM client_monthly_status
             WHERE office_id = ? AND period_year = ? AND period_month = ?",
            [$officeId, $year, $month]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[$r['client_id']] = $r['status'];
        }
        return $map;
    }

    public static function getStatus(int $clientId, int $year, int $month): string
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT status FROM client_monthly_status WHERE client_id = ? AND period_year = ? AND period_month = ?",
            [$clientId, $year, $month]
        );
        return $row['status'] ?? 'import';
    }

    public static function advanceStatus(int $clientId, int $officeId, int $year, int $month): string
    {
        $db = Database::getInstance();

        // Atomic: insert as 'weryfikacja' if no row exists (advancing from default 'import')
        $db->query(
            "INSERT INTO client_monthly_status (client_id, office_id, period_year, period_month, status)
             VALUES (?, ?, ?, ?, 'weryfikacja')
             ON DUPLICATE KEY UPDATE status = CASE
                 WHEN status = 'import' THEN 'weryfikacja'
                 WHEN status = 'weryfikacja' THEN 'jpk'
                 WHEN status = 'jpk' THEN 'zamkniety'
                 ELSE status
             END",
            [$clientId, $officeId, $year, $month]
        );

        return self::getStatus($clientId, $year, $month);
    }
}
