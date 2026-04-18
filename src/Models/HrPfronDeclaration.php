<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrPfronDeclaration
{
    public static function findByClient(int $clientId, ?int $year = null): array
    {
        $sql = "SELECT * FROM hr_pfron_declarations WHERE client_id = ?";
        $params = [$clientId];
        if ($year) {
            $sql .= " AND period_year = ?";
            $params[] = $year;
        }
        $sql .= " ORDER BY period_year DESC, period_month DESC";
        return HrDatabase::getInstance()->fetchAll($sql, $params);
    }

    public static function findByClientAndPeriod(int $clientId, int $month, int $year): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_pfron_declarations WHERE client_id = ? AND period_month = ? AND period_year = ?",
            [$clientId, $month, $year]
        );
    }

    public static function upsert(int $clientId, int $month, int $year, array $data): int
    {
        $existing = self::findByClientAndPeriod($clientId, $month, $year);
        $db = HrDatabase::getInstance();

        if ($existing) {
            $db->update('hr_pfron_declarations', $data, 'id = ?', [$existing['id']]);
            return (int) $existing['id'];
        }

        $data['client_id']    = $clientId;
        $data['period_month'] = $month;
        $data['period_year']  = $year;
        return $db->insert('hr_pfron_declarations', $data);
    }

    public static function getYearsForClient(int $clientId): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT DISTINCT period_year FROM hr_pfron_declarations WHERE client_id = ? ORDER BY period_year DESC",
            [$clientId]
        );
        return array_column($rows, 'period_year');
    }
}
