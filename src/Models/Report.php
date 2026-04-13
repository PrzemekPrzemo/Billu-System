<?php

namespace App\Models;

use App\Core\Database;

class Report
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT r.*, c.company_name, c.nip
             FROM reports r
             JOIN clients c ON r.client_id = c.id
             WHERE r.id = ?",
            [$id]
        );
    }

    public static function findByClientAndBatch(int $clientId, int $batchId, string $type = 'accepted'): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM reports WHERE client_id = ? AND batch_id = ? AND report_type = ?",
            [$clientId, $batchId, $type]
        );
    }

    public static function findByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT r.*, ib.period_month, ib.period_year
             FROM reports r
             JOIN invoice_batches ib ON r.batch_id = ib.id
             WHERE r.client_id = ?
             ORDER BY ib.period_year DESC, ib.period_month DESC",
            [$clientId]
        );
    }

    public static function findByOffice(int $officeId, ?int $clientId = null): array
    {
        $sql = "SELECT r.*, ib.period_month, ib.period_year, c.company_name, c.nip
                FROM reports r
                JOIN invoice_batches ib ON r.batch_id = ib.id
                JOIN clients c ON r.client_id = c.id
                WHERE c.office_id = ?";
        $params = [$officeId];

        if ($clientId) {
            $sql .= " AND r.client_id = ?";
            $params[] = $clientId;
        }

        $sql .= " ORDER BY ib.period_year DESC, ib.period_month DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('reports', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::getInstance()->update('reports', $data, 'id = ?', [$id]);
    }
}
