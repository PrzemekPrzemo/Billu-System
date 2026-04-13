<?php

namespace App\Models;

use App\Core\Database;

class IssuedInvoice
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM issued_invoices WHERE id = ?", [$id]);
    }

    public static function findByClient(int $clientId, ?string $status = null, ?string $search = null): array
    {
        $sql = "SELECT ii.*, c.company_name as contractor_name
                FROM issued_invoices ii
                LEFT JOIN contractors c ON ii.contractor_id = c.id
                WHERE ii.client_id = ?";
        $params = [$clientId];

        if ($status) {
            $sql .= " AND ii.status = ?";
            $params[] = $status;
        }

        if ($search) {
            $sql .= " AND (ii.invoice_number LIKE ? OR ii.buyer_name LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY ii.issue_date DESC, ii.id DESC";

        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function findByClientAndPeriod(int $clientId, int $month, int $year): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM issued_invoices
             WHERE client_id = ? AND MONTH(issue_date) = ? AND YEAR(issue_date) = ?
             AND status != 'cancelled'
             ORDER BY issue_date, id",
            [$clientId, $month, $year]
        );
    }

    public static function create(array $data): int
    {
        self::encodeJsonFields($data);
        return Database::getInstance()->insert('issued_invoices', $data);
    }

    public static function update(int $id, array $data): void
    {
        self::encodeJsonFields($data);
        Database::getInstance()->update('issued_invoices', $data, 'id = ?', [$id]);
    }

    private static function encodeJsonFields(array &$data): void
    {
        foreach (['line_items', 'vat_details', 'original_line_items', 'related_advance_ids'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $encoded = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
                if ($encoded === false) {
                    throw new \RuntimeException("Failed to encode {$field}: " . json_last_error_msg());
                }
                $data[$field] = $encoded;
            }
        }
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::getInstance()->update('issued_invoices', ['status' => $status], 'id = ?', [$id]);
    }

    public static function updateKsefStatus(int $id, string $status, ?string $ref = null, ?string $error = null): void
    {
        $data = ['ksef_status' => $status];
        if ($ref !== null) {
            $data['ksef_reference_number'] = $ref;
        }
        if ($error !== null) {
            $data['ksef_error'] = $error;
        }
        if ($status === 'sent') {
            $data['ksef_sent_at'] = date('Y-m-d H:i:s');
        }

        Database::getInstance()->update('issued_invoices', $data, 'id = ?', [$id]);
    }

    public static function updateKsefUpo(int $id, string $upoPath): void
    {
        Database::getInstance()->update('issued_invoices', ['ksef_upo_path' => $upoPath], 'id = ?', [$id]);
    }

    public static function updateKsefSessionRef(int $id, string $sessionRef): void
    {
        Database::getInstance()->update('issued_invoices', ['ksef_session_ref' => $sessionRef], 'id = ?', [$id]);
    }

    public static function updateKsefElementRef(int $id, string $elementRef): void
    {
        Database::getInstance()->update('issued_invoices', ['ksef_element_ref' => $elementRef], 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query(
            "DELETE FROM issued_invoices WHERE id = ? AND (status = 'draft' OR status = 'issued')",
            [$id]
        );
    }

    public static function countByClient(int $clientId): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT status, COUNT(*) as cnt FROM issued_invoices WHERE client_id = ? GROUP BY status",
            [$clientId]
        );

        $counts = ['draft' => 0, 'issued' => 0, 'sent_ksef' => 0, 'cancelled' => 0, 'total' => 0, 'issued_not_sent' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
            $counts['total'] += (int) $row['cnt'];
        }

        // Count issued invoices not yet sent to KSeF
        $notSent = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM issued_invoices WHERE client_id = ? AND status = 'issued' AND (ksef_status = 'none' OR ksef_status IS NULL)",
            [$clientId]
        );
        $counts['issued_not_sent'] = (int) ($notSent['cnt'] ?? 0);

        return $counts;
    }

    public static function getMonthlySales(int $clientId, int $months = 12): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT YEAR(issue_date) as year, MONTH(issue_date) as month,
                    SUM(net_amount) as net, SUM(vat_amount) as vat, SUM(gross_amount) as gross,
                    COUNT(*) as invoice_count
             FROM issued_invoices
             WHERE client_id = ? AND status != 'cancelled'
             AND issue_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY YEAR(issue_date), MONTH(issue_date)
             ORDER BY year DESC, month DESC",
            [$clientId, $months]
        );
    }

    public static function getTopBuyers(int $clientId, int $limit = 10): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT buyer_name, buyer_nip,
                    SUM(gross_amount) as total_gross,
                    COUNT(*) as invoice_count
             FROM issued_invoices
             WHERE client_id = ? AND status != 'cancelled'
             GROUP BY buyer_name, buyer_nip
             ORDER BY total_gross DESC
             LIMIT ?",
            [$clientId, $limit]
        );
    }

    public static function getVatSummary(int $clientId, int $month, int $year): array
    {
        $invoices = self::findByClientAndPeriod($clientId, $month, $year);
        $summary = [];

        foreach ($invoices as $inv) {
            $vatDetails = $inv['vat_details'] ?? null;
            if (is_string($vatDetails)) {
                $vatDetails = json_decode($vatDetails, true);
            }

            if (is_array($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $rate = $vd['rate'] ?? '23';
                    if (!isset($summary[$rate])) {
                        $summary[$rate] = ['rate' => $rate, 'net' => 0, 'vat' => 0, 'gross' => 0];
                    }
                    $summary[$rate]['net'] += (float) ($vd['net'] ?? 0);
                    $summary[$rate]['vat'] += (float) ($vd['vat'] ?? 0);
                    $summary[$rate]['gross'] += (float) ($vd['net'] ?? 0) + (float) ($vd['vat'] ?? 0);
                }
            }
        }

        // Round accumulated floats to avoid precision drift
        foreach ($summary as &$s) {
            $s['net'] = round($s['net'], 2);
            $s['vat'] = round($s['vat'], 2);
            $s['gross'] = round($s['gross'], 2);
        }
        unset($s);

        return array_values($summary);
    }

    public static function getSalesForJpk(int $clientId, int $month, int $year): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM issued_invoices
             WHERE client_id = ? AND MONTH(issue_date) = ? AND YEAR(issue_date) = ?
             AND status IN ('issued', 'sent_ksef')
             ORDER BY issue_date, id",
            [$clientId, $month, $year]
        );
    }

    /**
     * Get all KSeF reference numbers for a client's issued invoices.
     * Used to filter out own sales invoices during cost invoice import.
     */
    public static function getKsefReferences(int $clientId): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT ksef_reference_number FROM issued_invoices
             WHERE client_id = ? AND ksef_reference_number IS NOT NULL AND ksef_reference_number != ''",
            [$clientId]
        );
        return array_column($rows, 'ksef_reference_number');
    }

    /**
     * Find advance invoices (FV_ZAL) for a given contractor that are not yet linked to a final invoice.
     */
    public static function findUnlinkedAdvanceInvoices(int $clientId, int $contractorId): array
    {
        $db = Database::getInstance();

        // Get all advance invoices for this contractor
        $advances = $db->fetchAll(
            "SELECT id, invoice_number, issue_date, buyer_name, gross_amount, advance_amount
             FROM issued_invoices
             WHERE client_id = ? AND contractor_id = ? AND invoice_type = 'FV_ZAL'
             AND status IN ('issued', 'sent_ksef')
             ORDER BY issue_date DESC, id DESC",
            [$clientId, $contractorId]
        );

        // Get all final invoices and their linked advance IDs
        $finals = $db->fetchAll(
            "SELECT related_advance_ids FROM issued_invoices
             WHERE client_id = ? AND invoice_type = 'FV_KON'
             AND status IN ('issued', 'sent_ksef', 'draft')
             AND related_advance_ids IS NOT NULL",
            [$clientId]
        );

        // Collect all linked advance IDs
        $linkedIds = [];
        foreach ($finals as $final) {
            $ids = $final['related_advance_ids'];
            if (is_string($ids)) {
                $ids = json_decode($ids, true) ?: [];
            }
            if (is_array($ids)) {
                $linkedIds = array_merge($linkedIds, $ids);
            }
        }
        $linkedIds = array_unique(array_map('intval', $linkedIds));

        // Filter out already linked advances
        return array_values(array_filter($advances, function ($adv) use ($linkedIds) {
            return !in_array((int) $adv['id'], $linkedIds, true);
        }));
    }

    public static function getMonthlyCountAll(int $months = 6): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(gross_amount), 0) as total_gross
             FROM issued_invoices WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC",
            [$months]
        );
    }
}
