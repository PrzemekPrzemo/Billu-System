<?php

namespace App\Models;

use App\Core\Database;

class DuplicateCandidate
{
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('duplicate_candidates', $data);
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM duplicate_candidates WHERE id = ?",
            [$id]
        );
    }

    public static function findByInvoice(string $invoiceType, int $invoiceId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM duplicate_candidates
             WHERE invoice_type = ? AND (invoice_id = ? OR duplicate_of_id = ?)
             ORDER BY created_at DESC",
            [$invoiceType, $invoiceId, $invoiceId]
        );
    }

    public static function existsPair(string $invoiceType, int $invoiceId, int $duplicateOfId): bool
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT id FROM duplicate_candidates
             WHERE invoice_type = ?
               AND ((invoice_id = ? AND duplicate_of_id = ?) OR (invoice_id = ? AND duplicate_of_id = ?))
             LIMIT 1",
            [$invoiceType, $invoiceId, $duplicateOfId, $duplicateOfId, $invoiceId]
        );
        return $row !== null;
    }

    public static function findPendingByOffice(int $officeId, int $limit = 100): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT dc.*,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.invoice_number ELSE ii1.invoice_number END AS invoice_number,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.gross_amount ELSE ii1.gross_amount END AS gross_amount,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.seller_nip ELSE ii1.buyer_nip END AS nip,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.issue_date ELSE ii1.issue_date END AS issue_date,
                    c.company_name AS client_name
             FROM duplicate_candidates dc
             LEFT JOIN invoices i1 ON dc.invoice_type = 'purchase' AND dc.invoice_id = i1.id
             LEFT JOIN issued_invoices ii1 ON dc.invoice_type = 'sales' AND dc.invoice_id = ii1.id
             LEFT JOIN clients c ON (
                 (dc.invoice_type = 'purchase' AND i1.client_id = c.id) OR
                 (dc.invoice_type = 'sales' AND ii1.client_id = c.id)
             )
             WHERE dc.status = 'pending' AND c.office_id = ?
             ORDER BY dc.created_at DESC
             LIMIT ?",
            [$officeId, $limit]
        );
    }

    public static function findPendingByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT dc.*,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.invoice_number ELSE ii1.invoice_number END AS invoice_number,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.gross_amount ELSE ii1.gross_amount END AS gross_amount,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.seller_nip ELSE ii1.buyer_nip END AS nip
             FROM duplicate_candidates dc
             LEFT JOIN invoices i1 ON dc.invoice_type = 'purchase' AND dc.invoice_id = i1.id
             LEFT JOIN issued_invoices ii1 ON dc.invoice_type = 'sales' AND dc.invoice_id = ii1.id
             WHERE dc.status = 'pending'
               AND ((dc.invoice_type = 'purchase' AND i1.client_id = ?)
                    OR (dc.invoice_type = 'sales' AND ii1.client_id = ?))
             ORDER BY dc.created_at DESC",
            [$clientId, $clientId]
        );
    }

    public static function findAllByOffice(int $officeId, ?string $status = null, int $limit = 200): array
    {
        $sql = "SELECT dc.*,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.invoice_number ELSE ii1.invoice_number END AS invoice_number,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.gross_amount ELSE ii1.gross_amount END AS gross_amount,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.seller_nip ELSE ii1.buyer_nip END AS nip,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.issue_date ELSE ii1.issue_date END AS issue_date,
                    c.company_name AS client_name
                FROM duplicate_candidates dc
                LEFT JOIN invoices i1 ON dc.invoice_type = 'purchase' AND dc.invoice_id = i1.id
                LEFT JOIN issued_invoices ii1 ON dc.invoice_type = 'sales' AND dc.invoice_id = ii1.id
                LEFT JOIN clients c ON (
                    (dc.invoice_type = 'purchase' AND i1.client_id = c.id) OR
                    (dc.invoice_type = 'sales' AND ii1.client_id = c.id)
                )
                WHERE c.office_id = ?";
        $params = [$officeId];

        if ($status !== null) {
            $sql .= " AND dc.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY dc.created_at DESC LIMIT ?";
        $params[] = $limit;

        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function findAllGlobal(?string $status = null, int $limit = 200): array
    {
        $sql = "SELECT dc.*,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.invoice_number ELSE ii1.invoice_number END AS invoice_number,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.gross_amount ELSE ii1.gross_amount END AS gross_amount,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.seller_nip ELSE ii1.buyer_nip END AS nip,
                    CASE WHEN dc.invoice_type = 'purchase'
                         THEN i1.issue_date ELSE ii1.issue_date END AS issue_date,
                    c.company_name AS client_name
                FROM duplicate_candidates dc
                LEFT JOIN invoices i1 ON dc.invoice_type = 'purchase' AND dc.invoice_id = i1.id
                LEFT JOIN issued_invoices ii1 ON dc.invoice_type = 'sales' AND dc.invoice_id = ii1.id
                LEFT JOIN clients c ON (
                    (dc.invoice_type = 'purchase' AND i1.client_id = c.id) OR
                    (dc.invoice_type = 'sales' AND ii1.client_id = c.id)
                )";
        $params = [];

        if ($status !== null) {
            $sql .= " WHERE dc.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY dc.created_at DESC LIMIT ?";
        $params[] = $limit;

        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function updateStatus(int $id, string $status, string $reviewerType, int $reviewerId): void
    {
        Database::getInstance()->update('duplicate_candidates', [
            'status' => $status,
            'reviewed_by_type' => $reviewerType,
            'reviewed_by_id' => $reviewerId,
        ], 'id = ?', [$id]);
    }

    public static function deleteOld(int $days = 365): int
    {
        $stmt = Database::getInstance()->query(
            "DELETE FROM duplicate_candidates WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND status != 'pending'",
            [$days]
        );
        return $stmt->rowCount();
    }
}
