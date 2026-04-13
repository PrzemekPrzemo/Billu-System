<?php

namespace App\Models;

use App\Core\Database;

class TaxSimulation
{
    public static function create(int $officeId, int $clientId, array $input, string $resultsJson, string $bestOption): int
    {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO tax_simulations (office_id, client_id, revenue, is_gross, ryczalt_rate, costs, zus_variant, results_json, best_option)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $officeId, $clientId,
                $input['annualRevenue'] ?? $input['annual_revenue'] ?? 0,
                $input['isGross'] ?? $input['is_gross'] ?? 0,
                $input['ryczaltRate'] ?? $input['ryczalt_rate'] ?? 0.085,
                $input['costs'] ?? 0,
                $input['zusVariant'] ?? $input['zus_variant'] ?? 'full',
                $resultsJson, $bestOption,
            ]
        );
        return (int) $db->lastInsertId();
    }

    public static function findByOffice(int $officeId, ?int $clientId = null, int $limit = 30): array
    {
        $db = Database::getInstance();
        $sql = "SELECT s.*, c.company_name, c.nip
                FROM tax_simulations s
                JOIN clients c ON c.id = s.client_id
                WHERE s.office_id = ?";
        $params = [$officeId];

        if ($clientId) {
            $sql .= " AND s.client_id = ?";
            $params[] = $clientId;
        }

        $sql .= " ORDER BY s.created_at DESC LIMIT ?";
        $params[] = $limit;

        return $db->fetchAll($sql, $params);
    }

    public static function delete(int $id, int $officeId): bool
    {
        $db = Database::getInstance();
        return $db->query("DELETE FROM tax_simulations WHERE id = ? AND office_id = ?", [$id, $officeId]) !== false;
    }
}
