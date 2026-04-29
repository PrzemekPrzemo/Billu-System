<?php

namespace App\Models;

use App\Core\Cache;
use App\Core\Database;

class Invoice
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM invoices WHERE id = ?", [$id]);
    }

    /** Ownership-checked accessor for client-scoped routes. Returns null if not found OR not owned by $clientId. */
    public static function findByIdForClient(int $id, int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM invoices WHERE id = ? AND client_id = ?",
            [$id, $clientId]
        );
    }

    /** Ownership-checked accessor for office-scoped routes. Joins clients to verify office_id. */
    public static function findByIdForOffice(int $id, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT i.* FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = ? AND c.office_id = ?",
            [$id, $officeId]
        );
    }

    public static function findByBatch(int $batchId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM invoices WHERE batch_id = ? ORDER BY issue_date, invoice_number",
            [$batchId]
        );
    }

    public static function findByClient(int $clientId, ?string $status = null): array
    {
        $sql = "SELECT i.*, ib.period_month, ib.period_year, ib.verification_deadline, ib.is_finalized
                FROM invoices i JOIN invoice_batches ib ON i.batch_id = ib.id WHERE i.client_id = ?";
        $params = [$clientId];
        if ($status !== null) {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY ib.period_year DESC, ib.period_month DESC, i.issue_date";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function findByClientAndBatch(int $clientId, int $batchId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM invoices WHERE client_id = ? AND batch_id = ? ORDER BY issue_date, invoice_number",
            [$clientId, $batchId]
        );
    }

    public static function findByKsefReference(string $ksefRef): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM invoices WHERE ksef_reference_number = ? LIMIT 1",
            [$ksefRef]
        );
    }

    public static function findPendingByBatch(int $batchId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM invoices WHERE batch_id = ? AND status = 'pending' ORDER BY issue_date",
            [$batchId]
        );
    }

    public static function create(array $data): int
    {
        $id = Database::getInstance()->insert('invoices', $data);
        self::invalidateAgg(isset($data['client_id']) ? (int)$data['client_id'] : null);
        return $id;
    }

    public static function updateFields(int $id, array $data): void
    {
        Database::getInstance()->update('invoices', $data, 'id = ?', [$id]);
        self::invalidateAgg(self::lookupClientId($id));
    }

    public static function updateStatus(int $id, string $status, ?string $comment = null, ?string $costCenter = null, ?int $costCenterId = null): void
    {
        $data = [
            'status'      => $status,
            'verified_at' => date('Y-m-d H:i:s'),
        ];
        if ($comment !== null) {
            $data['comment'] = $comment;
        }
        if ($costCenter !== null) {
            $data['cost_center'] = $costCenter;
        }
        if ($costCenterId !== null) {
            $data['cost_center_id'] = $costCenterId;
        }
        Database::getInstance()->update('invoices', $data, 'id = ?', [$id]);
        self::invalidateAgg(self::lookupClientId($id));
    }

    /**
     * Accept an invoice on behalf of the client even though whitelist_failed=1.
     * The justification is mandatory (caller must validate non-empty before calling).
     * Records who, when and why on the row, and sets status to 'accepted' in a single
     * write so the cache invalidation below fires once.
     */
    public static function acceptWithWhitelistOverride(int $id, string $reason, string $byType, int $byId): void
    {
        Database::getInstance()->update('invoices', [
            'status'                     => 'accepted',
            'verified_at'                => date('Y-m-d H:i:s'),
            'comment'                    => $reason,
            'whitelist_override_reason'  => $reason,
            'whitelist_override_by_type' => $byType,
            'whitelist_override_by_id'   => $byId,
            'whitelist_override_at'      => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        self::invalidateAgg(self::lookupClientId($id));
    }

    public static function autoRejectWhitelistFailed(int $batchId): int
    {
        $stmt = Database::getInstance()->query(
            "UPDATE invoices SET status = 'rejected', verified_at = NOW(), verified_by_auto = 1,
                    comment = 'Automatycznie odrzucona - numer rachunku nie widnieje na białej liście VAT'
             WHERE batch_id = ? AND status = 'pending' AND whitelist_failed = 1",
            [$batchId]
        );
        $count = $stmt->rowCount();
        if ($count > 0) {
            self::invalidateAgg(); // bulk update -> flush global only
        }
        return $count;
    }

    public static function autoAcceptPending(int $batchId): int
    {
        $stmt = Database::getInstance()->query(
            "UPDATE invoices SET status = 'accepted', verified_at = NOW(), verified_by_auto = 1
             WHERE batch_id = ? AND status = 'pending'",
            [$batchId]
        );
        $count = $stmt->rowCount();
        if ($count > 0) {
            self::invalidateAgg();
        }
        return $count;
    }

    public static function countByBatchAndStatus(int $batchId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT status, COUNT(*) as cnt FROM invoices WHERE batch_id = ? GROUP BY status",
            [$batchId]
        );
    }

    public static function getMonthlyComparison(int $clientId, int $months = 12): array
    {
        $key = "inv:client:{$clientId}:monthlyComparison:{$months}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT YEAR(issue_date) AS year, MONTH(issue_date) AS month,
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    SUM(net_amount) AS net_total,
                    SUM(vat_amount) AS vat_total,
                    SUM(gross_amount) AS gross_total
             FROM invoices
             WHERE client_id = ? AND issue_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY YEAR(issue_date), MONTH(issue_date)
             ORDER BY year ASC, month ASC",
            [$clientId, $months]
        ));
    }

    public static function getVerificationProgressByOffice(int $officeId): array
    {
        $key = "inv:global:verificationProgressByOffice:{$officeId}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT c.id AS client_id, c.company_name, c.nip,
                    COUNT(i.id) AS total_invoices,
                    SUM(CASE WHEN i.status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
                    SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                    SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                    SUM(i.gross_amount) AS total_gross
             FROM clients c
             LEFT JOIN invoices i ON i.client_id = c.id
             WHERE c.office_id = ? AND c.is_active = 1
             GROUP BY c.id, c.company_name, c.nip
             ORDER BY c.company_name",
            [$officeId]
        ));
    }

    public static function bulkUpdateCostCenter(array $invoiceIds, int $costCenterId, string $costCenterName): int
    {
        if (empty($invoiceIds)) return 0;
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $params = array_merge([$costCenterId, $costCenterName], array_map('intval', $invoiceIds));
        $stmt = Database::getInstance()->query(
            "UPDATE invoices SET cost_center_id = ?, cost_center = ? WHERE id IN ($placeholders)",
            $params
        );
        $count = $stmt->rowCount();
        if ($count > 0) {
            self::invalidateAgg(); // multi-row, may span clients -> flush global
        }
        return $count;
    }

    public static function getAcceptedByBatch(int $batchId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM invoices WHERE batch_id = ? AND status = 'accepted' ORDER BY issue_date, invoice_number",
            [$batchId]
        );
    }

    public static function getAcceptedByBatchAndCostCenter(int $batchId, ?int $costCenterId): array
    {
        $sql = "SELECT * FROM invoices WHERE batch_id = ? AND status = 'accepted'";
        $params = [$batchId];
        if ($costCenterId !== null) {
            $sql .= " AND cost_center_id = ?";
            $params[] = $costCenterId;
        } else {
            $sql .= " AND cost_center_id IS NULL";
        }
        $sql .= " ORDER BY issue_date, invoice_number";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function getRejectedByBatch(int $batchId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM invoices WHERE batch_id = ? AND status = 'rejected' ORDER BY issue_date, invoice_number",
            [$batchId]
        );
    }

    public static function findByBatchFiltered(int $batchId, ?string $status = null, ?string $search = null): array
    {
        $sql = "SELECT * FROM invoices WHERE batch_id = ?";
        $params = [$batchId];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        if ($search) {
            $sql .= " AND (invoice_number LIKE ? OR seller_name LIKE ? OR seller_nip LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY issue_date, invoice_number";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function countByClient(int $clientId): array
    {
        $result = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
             FROM invoices WHERE client_id = ?",
            [$clientId]
        );
        return $result ?: ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
    }

    public static function countByClientAndPeriod(int $clientId, int $month, int $year): array
    {
        $result = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as total,
                    SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN i.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN i.whitelist_failed = 1 THEN 1 ELSE 0 END) as whitelist_failed
             FROM invoices i
             JOIN invoice_batches ib ON i.batch_id = ib.id
             WHERE i.client_id = ? AND ib.period_month = ? AND ib.period_year = ?",
            [$clientId, $month, $year]
        );
        return $result ?: ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0, 'whitelist_failed' => 0];
    }

    public static function getMonthlyStats(int $months = 6, bool $excludeDemo = false): array
    {
        $key = "inv:global:monthlyStats:{$months}:" . ($excludeDemo ? '1' : '0');
        return self::rememberAgg($key, function () use ($months, $excludeDemo) {
            $demoFilter = $excludeDemo ? " AND i.client_id NOT IN (SELECT id FROM clients WHERE is_demo = 1)" : "";
            return Database::getInstance()->fetchAll(
                "SELECT DATE_FORMAT(i.created_at, '%Y-%m') as month,
                        COUNT(*) as total,
                        SUM(CASE WHEN i.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                        SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                        SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(i.gross_amount) as total_gross
                 FROM invoices i
                 WHERE i.created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH){$demoFilter}
                 GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
                 ORDER BY month ASC",
                [$months]
            );
        });
    }

    public static function getStatusTotals(bool $excludeDemo = false): array
    {
        $key = "inv:global:statusTotals:" . ($excludeDemo ? '1' : '0');
        return self::rememberAgg($key, function () use ($excludeDemo) {
            $demoFilter = $excludeDemo ? " WHERE client_id NOT IN (SELECT id FROM clients WHERE is_demo = 1)" : "";
            $result = Database::getInstance()->fetchOne(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                 FROM invoices{$demoFilter}"
            );
            return $result ?: ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
        });
    }

    public static function countThisMonth(bool $excludeDemo = false): int
    {
        $key = "inv:global:countThisMonth:" . ($excludeDemo ? '1' : '0');
        return (int) self::rememberAgg($key, function () use ($excludeDemo) {
            $demoFilter = $excludeDemo ? " AND client_id NOT IN (SELECT id FROM clients WHERE is_demo = 1)" : "";
            $result = Database::getInstance()->fetchOne(
                "SELECT COUNT(*) as cnt FROM invoices WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01'){$demoFilter}"
            );
            return (int) ($result['cnt'] ?? 0);
        });
    }

    public static function getMonthlyStatsByOffice(int $officeId, int $months = 6, ?array $clientFilter = null): array
    {
        $filterHash = $clientFilter === null ? 'all' : md5(implode(',', array_map('intval', $clientFilter)));
        $key = "inv:global:monthlyStatsByOffice:{$officeId}:{$months}:{$filterHash}";
        return self::rememberAgg($key, function () use ($officeId, $months, $clientFilter) {
            $params = [$months, $officeId];
            $clientWhere = "";
            if ($clientFilter !== null) {
                $clientFilter = array_map('intval', $clientFilter);
                $placeholders = implode(',', array_fill(0, count($clientFilter), '?'));
                $clientWhere = " AND i.client_id IN ({$placeholders})";
                $params = array_merge($params, $clientFilter);
            }
            return Database::getInstance()->fetchAll(
                "SELECT DATE_FORMAT(i.created_at, '%Y-%m') as month,
                        COUNT(*) as total,
                        SUM(CASE WHEN i.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                        SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                        SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(i.gross_amount) as total_gross
                 FROM invoices i
                 JOIN clients c ON c.id = i.client_id
                 WHERE i.created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                   AND c.office_id = ?{$clientWhere}
                 GROUP BY DATE_FORMAT(i.created_at, '%Y-%m')
                 ORDER BY month ASC",
                $params
            );
        });
    }

    public static function getStatusTotalsByOffice(int $officeId, ?array $clientFilter = null): array
    {
        $filterHash = $clientFilter === null ? 'all' : md5(implode(',', array_map('intval', $clientFilter)));
        $key = "inv:global:statusTotalsByOffice:{$officeId}:{$filterHash}";
        return self::rememberAgg($key, function () use ($officeId, $clientFilter) {
            $params = [$officeId];
            $clientWhere = "";
            if ($clientFilter !== null) {
                $clientFilter = array_map('intval', $clientFilter);
                $placeholders = implode(',', array_fill(0, count($clientFilter), '?'));
                $clientWhere = " AND i.client_id IN ({$placeholders})";
                $params = array_merge($params, $clientFilter);
            }
            $result = Database::getInstance()->fetchOne(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN i.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                        SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) as rejected
                 FROM invoices i
                 JOIN clients c ON c.id = i.client_id
                 WHERE c.office_id = ?{$clientWhere}",
                $params
            );
            return $result ?: ['total' => 0, 'pending' => 0, 'accepted' => 0, 'rejected' => 0];
        });
    }

    public static function getTopSellersByClient(int $clientId, int $limit = 5): array
    {
        $key = "inv:client:{$clientId}:topSellers:{$limit}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT seller_name, seller_nip, COUNT(*) as invoice_count, SUM(gross_amount) as total_gross
             FROM invoices WHERE client_id = ?
             GROUP BY seller_name, seller_nip
             ORDER BY invoice_count DESC LIMIT ?",
            [$clientId, $limit]
        ));
    }

    public static function getSupplierAnalysis(int $clientId, string $dateFrom, string $dateTo, int $limit = 20): array
    {
        $key = "inv:client:{$clientId}:supplierAnalysis:{$dateFrom}:{$dateTo}:{$limit}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT seller_nip, seller_name,
                    COUNT(*) AS invoice_count,
                    SUM(net_amount) AS total_net,
                    SUM(gross_amount) AS total_gross,
                    AVG(gross_amount) AS avg_gross,
                    MIN(gross_amount) AS min_gross,
                    MAX(gross_amount) AS max_gross
             FROM invoices
             WHERE client_id = ? AND issue_date >= ? AND issue_date <= ?
             GROUP BY seller_nip, seller_name
             ORDER BY total_gross DESC
             LIMIT ?",
            [$clientId, $dateFrom, $dateTo, $limit]
        ));
    }

    public static function getSupplierMonthlyTrend(int $clientId, string $sellerNip, int $months = 6): array
    {
        $key = "inv:client:{$clientId}:supplierMonthlyTrend:" . md5($sellerNip) . ":{$months}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT YEAR(issue_date) AS year, MONTH(issue_date) AS month,
                    COUNT(*) AS invoice_count, SUM(gross_amount) AS total_gross
             FROM invoices
             WHERE client_id = ? AND seller_nip = ? AND issue_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY YEAR(issue_date), MONTH(issue_date)
             ORDER BY year ASC, month ASC",
            [$clientId, $sellerNip, $months]
        ));
    }

    public static function getRejectionRateByOffice(int $limit = 15): array
    {
        $key = "inv:global:rejectionRateByOffice:{$limit}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT o.id, o.name,
                    COUNT(i.id) as total,
                    SUM(CASE WHEN i.status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    ROUND(SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) / COUNT(i.id) * 100, 1) as rejection_pct
             FROM offices o
             JOIN clients c ON c.office_id = o.id AND c.is_demo = 0
             JOIN invoices i ON i.client_id = c.id
             GROUP BY o.id, o.name
             HAVING COUNT(i.id) >= 5
             ORDER BY rejection_pct DESC
             LIMIT ?",
            [$limit]
        ));
    }

    public static function getMonthlyCountAll(int $months = 6): array
    {
        $key = "inv:global:monthlyCountAll:{$months}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(gross_amount), 0) as total_gross
             FROM invoices WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC",
            [$months]
        ));
    }

    public static function getAvgVerificationTimeByOffice(int $officeId, int $months = 6): array
    {
        $key = "inv:global:avgVerificationTimeByOffice:{$officeId}:{$months}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(ib.created_at, '%Y-%m') as month,
                    COUNT(DISTINCT ib.id) as batch_count,
                    AVG(TIMESTAMPDIFF(HOUR, ib.created_at, ib.finalized_at)) as avg_hours,
                    SUM(CASE WHEN ib.is_finalized = 1 THEN 1 ELSE 0 END) as finalized,
                    SUM(CASE WHEN ib.is_finalized = 0 THEN 1 ELSE 0 END) as open
             FROM invoice_batches ib
             JOIN clients c ON c.id = ib.client_id
             WHERE c.office_id = ? AND ib.created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(ib.created_at, '%Y-%m')
             ORDER BY month ASC",
            [$officeId, $months]
        ));
    }

    public static function getRejectionRateByClient(int $officeId, int $limit = 10): array
    {
        $key = "inv:global:rejectionRateByClient:{$officeId}:{$limit}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT c.id, c.company_name, c.nip,
                    COUNT(i.id) as total,
                    SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    ROUND(SUM(CASE WHEN i.status = 'rejected' THEN 1 ELSE 0 END) / COUNT(i.id) * 100, 1) as rejection_pct
             FROM clients c
             JOIN invoices i ON i.client_id = c.id
             WHERE c.office_id = ? AND c.is_demo = 0
             GROUP BY c.id, c.company_name, c.nip
             HAVING COUNT(i.id) >= 5
             ORDER BY rejection_pct DESC
             LIMIT ?",
            [$officeId, $limit]
        ));
    }

    public static function getMonthlyGrossByOffice(int $officeId, int $months = 12): array
    {
        $key = "inv:global:monthlyGrossByOffice:{$officeId}:{$months}";
        return self::rememberAgg($key, fn() => Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(i.issue_date, '%Y-%m') as month,
                    COALESCE(SUM(CASE WHEN i.status = 'accepted' THEN i.net_amount ELSE 0 END), 0) as net,
                    COALESCE(SUM(CASE WHEN i.status = 'accepted' THEN i.gross_amount ELSE 0 END), 0) as gross,
                    COUNT(CASE WHEN i.status = 'accepted' THEN 1 END) as count
             FROM invoices i
             JOIN clients c ON c.id = i.client_id
             WHERE c.office_id = ? AND i.issue_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(i.issue_date, '%Y-%m')
             ORDER BY month ASC",
            [$officeId, $months]
        ));
    }

    /**
     * Pomocnik do cachowania agregatów. TTL z bucketu 'aggregate' (domyślnie 30min).
     * Klucz powinien zawierać tag (np. "inv:global:..." lub "inv:client:N:...") -
     * dzięki temu invalidateAgg() może go usunąć przez flushTag().
     */
    private static function rememberAgg(string $key, callable $producer): mixed
    {
        $cache = Cache::getInstance();
        return $cache->remember($key, $cache->ttl('aggregate'), $producer);
    }

    private static function lookupClientId(int $invoiceId): ?int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT client_id FROM invoices WHERE id = ?",
            [$invoiceId]
        );
        return $row ? (int)$row['client_id'] : null;
    }

    /**
     * Invalidacja cache po zapisie faktury. Zawsze flush'uje 'inv:global',
     * dodatkowo 'inv:client:N' jeśli client_id jest znany.
     */
    private static function invalidateAgg(?int $clientId = null): void
    {
        $cache = Cache::getInstance();
        $cache->flushTag('inv:global');
        if ($clientId !== null && $clientId > 0) {
            $cache->flushTag('inv:client:' . $clientId);
        }
    }
}
