<?php

namespace App\Models;

use App\Core\Database;

class TaxPayment
{
    private static array $taxTypes = ['VAT', 'PIT', 'CIT'];
    private static array $statuses = ['do_zaplaty', 'do_przeniesienia'];

    /**
     * Bulk upsert tax payment entries for a client/year.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE on the unique key (client_id, year, month, tax_type).
     *
     * @param array $data Array of ['month'=>int, 'tax_type'=>string, 'amount'=>string, 'status'=>string]
     */
    public static function bulkUpsert(int $clientId, int $year, array $data, string $byType, int $byId): void
    {
        $db = Database::getInstance();
        foreach ($data as $entry) {
            $month = (int) $entry['month'];
            $taxType = $entry['tax_type'];
            $amount = $entry['amount'];
            $status = $entry['status'];

            if ($month < 1 || $month > 12) {
                continue;
            }
            if (!in_array($taxType, self::$taxTypes, true)) {
                continue;
            }
            if (!in_array($status, self::$statuses, true)) {
                continue;
            }
            if (!is_numeric($amount) || (float) $amount < 0) {
                continue;
            }
            $amount = round((float) $amount, 2);

            $db->query(
                "INSERT INTO tax_payments (client_id, year, month, tax_type, amount, status, created_by_type, created_by_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     amount = VALUES(amount),
                     status = VALUES(status),
                     updated_by_type = VALUES(created_by_type),
                     updated_by_id = VALUES(created_by_id)",
                [$clientId, $year, $month, $taxType, $amount, $status, $byType, $byId]
            );
        }
    }

    /**
     * Find all tax payment entries for a client and year.
     */
    public static function findByClientAndYear(int $clientId, int $year): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM tax_payments WHERE client_id = ? AND year = ? ORDER BY month, FIELD(tax_type, 'VAT', 'PIT', 'CIT')",
            [$clientId, $year]
        );
    }

    /**
     * Build a grid structure: $grid[month][tax_type] = ['amount' => ..., 'status' => ...]
     */
    public static function buildGrid(array $rows): array
    {
        $grid = [];
        foreach ($rows as $row) {
            $grid[(int) $row['month']][$row['tax_type']] = [
                'amount' => $row['amount'],
                'status' => $row['status'],
            ];
        }
        return $grid;
    }

    /**
     * Find entries for all clients of an office.
     */
    public static function findByOffice(int $officeId, ?int $clientId = null, ?int $year = null): array
    {
        $sql = "SELECT t.*, c.company_name AS client_name
                FROM tax_payments t
                JOIN clients c ON t.client_id = c.id
                WHERE c.office_id = ?";
        $params = [$officeId];

        if ($clientId !== null) {
            $sql .= " AND t.client_id = ?";
            $params[] = $clientId;
        }
        if ($year !== null) {
            $sql .= " AND t.year = ?";
            $params[] = $year;
        }

        $sql .= " ORDER BY t.year DESC, t.month, FIELD(t.tax_type, 'VAT', 'PIT', 'CIT')";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Find entries for clients assigned to an employee.
     */
    public static function findByEmployee(int $employeeId, ?int $clientId = null, ?int $year = null): array
    {
        $sql = "SELECT t.*, c.company_name AS client_name
                FROM tax_payments t
                JOIN clients c ON t.client_id = c.id
                JOIN office_employee_clients oec ON c.id = oec.client_id
                WHERE oec.employee_id = ?";
        $params = [$employeeId];

        if ($clientId !== null) {
            $sql .= " AND t.client_id = ?";
            $params[] = $clientId;
        }
        if ($year !== null) {
            $sql .= " AND t.year = ?";
            $params[] = $year;
        }

        $sql .= " ORDER BY t.year DESC, t.month, FIELD(t.tax_type, 'VAT', 'PIT', 'CIT')";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Count pending (do_zaplaty) entries for current/future months for a client.
     */
    public static function countPendingByClient(int $clientId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM tax_payments
             WHERE client_id = ? AND status = 'do_zaplaty'
               AND ((year = YEAR(CURDATE()) AND month >= MONTH(CURDATE())) OR year > YEAR(CURDATE()))",
            [$clientId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get available tax types.
     */
    public static function getTaxTypes(): array
    {
        return self::$taxTypes;
    }

    /**
     * Get current month tax summary for all clients in an office.
     */
    public static function getCurrentMonthSummaryByOffice(int $officeId, ?array $clientFilter = null): array
    {
        $month = (int) date('n');
        $year = (int) date('Y');
        // Check previous month if current month has no data yet
        $sql = "SELECT t.tax_type, SUM(t.amount) as total_amount, COUNT(DISTINCT t.client_id) as client_count
                FROM tax_payments t
                JOIN clients c ON t.client_id = c.id
                WHERE c.office_id = ? AND t.year = ? AND t.month = ? AND c.is_demo = 0";
        $params = [$officeId, $year, $month];

        if ($clientFilter !== null) {
            $placeholders = implode(',', array_fill(0, count($clientFilter), '?'));
            $sql .= " AND t.client_id IN ({$placeholders})";
            $params = array_merge($params, $clientFilter);
        }

        $sql .= " GROUP BY t.tax_type ORDER BY FIELD(t.tax_type, 'VAT', 'PIT', 'CIT')";
        $result = Database::getInstance()->fetchAll($sql, $params);

        if (empty($result)) {
            // Fallback to previous month
            $prevMonth = $month - 1;
            $prevYear = $year;
            if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
            $params[2] = $prevMonth;
            $params[1] = $prevYear;
            $result = Database::getInstance()->fetchAll($sql, $params);
        }

        return $result;
    }
}
