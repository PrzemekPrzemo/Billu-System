<?php

namespace App\Models;

use App\Core\Database;

class Office
{
    /** Per-request memoization findById. */
    private static array $memo = [];

    public static function findById(int $id): ?array
    {
        if (array_key_exists($id, self::$memo)) {
            return self::$memo[$id];
        }
        $row = Database::getInstance()->fetchOne("SELECT * FROM offices WHERE id = ?", [$id]);
        self::$memo[$id] = $row;
        return $row;
    }

    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    public static function findByNip(string $nip): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM offices WHERE nip = ?", [$nip]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM offices WHERE email = ?", [$email]);
    }

    public static function findAll(bool $activeOnly = false, bool $excludeDemo = false): array
    {
        $demoFilter = $excludeDemo ? " AND is_demo = 0" : "";
        $sql = "SELECT o.*,
                (SELECT COUNT(*) FROM clients WHERE office_id = o.id{$demoFilter}) as client_count,
                (SELECT COUNT(*) FROM office_employees WHERE office_id = o.id) as employee_count
                FROM offices o";
        $conditions = [];
        if ($activeOnly) $conditions[] = "o.is_active = 1";
        if ($excludeDemo) $conditions[] = "o.is_demo = 0";
        if ($conditions) $sql .= " WHERE " . implode(' AND ', $conditions);
        $sql .= " ORDER BY o.name";
        return Database::getInstance()->fetchAll($sql);
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('offices', $data);
    }

    public static function update(int $id, array $data): int
    {
        $rows = Database::getInstance()->update('offices', $data, 'id = ?', [$id]);
        unset(self::$memo[$id]);
        return $rows;
    }

    public static function updateLastLogin(int $id): void
    {
        Database::getInstance()->update('offices', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public static function updatePassword(int $id, string $hash): void
    {
        Database::getInstance()->update('offices', [
            'password_hash' => $hash,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public static function getClients(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM clients WHERE office_id = ? ORDER BY company_name",
            [$officeId]
        );
    }

    public static function deactivateClients(int $officeId): int
    {
        return Database::getInstance()->update('clients', ['is_active' => 0], 'office_id = ? AND is_active = 1', [$officeId]);
    }

    public static function getEffectiveSetting(int $officeId, string $key): ?string
    {
        $office = self::findById($officeId);
        if (!$office) return null;

        // Map setting keys to office columns
        $officeFields = [
            'verification_deadline_day' => 'verification_deadline_day',
            'auto_accept_on_deadline' => 'auto_accept_on_deadline',
            'notification_days_before' => 'notification_days_before',
        ];

        if (isset($officeFields[$key]) && $office[$officeFields[$key]] !== null) {
            return (string) $office[$officeFields[$key]];
        }

        return Setting::get($key);
    }

    public static function getRankingStats(int $limit = 10): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT o.id, o.name,
                    COUNT(DISTINCT c.id) as client_count,
                    COUNT(DISTINCT CASE WHEN i.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN i.id END) as monthly_invoices,
                    COALESCE(SUM(CASE WHEN i.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN i.net_amount ELSE 0 END), 0) as monthly_net
             FROM offices o
             LEFT JOIN clients c ON c.office_id = o.id AND c.is_demo = 0
             LEFT JOIN invoices i ON i.client_id = c.id
             GROUP BY o.id, o.name
             ORDER BY monthly_invoices DESC
             LIMIT ?",
            [$limit]
        );
    }

    public static function getMonthlyGrowth(int $months = 12): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM offices WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC",
            [$months]
        );
    }
}
