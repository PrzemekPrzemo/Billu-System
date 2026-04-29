<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    /** Per-request memoization findById. */
    private static array $memo = [];

    /** Default mass-assignment whitelist. Sensitive fields (password_hash, office_id, privacy_*, is_demo, last_login_at) require explicit $allowed override (see AdminController). */
    public const FILLABLE = [
        'nip', 'company_name', 'representative_name', 'address',
        'email', 'report_email', 'phone', 'regon',
        'has_cost_centers', 'ksef_api_token', 'ksef_enabled',
        'language', 'is_active',
        'mobile_app_enabled', 'file_storage_path',
        'ip_whitelist', 'can_send_invoices',
        'sftp_push_files', 'sftp_push_messages', 'sftp_push_invoices',
        'sftp_push_exports', 'sftp_push_payslips', 'sftp_subdir',
    ];

    /** Admin-only fields. Combine with FILLABLE via Client::update($id, $data, Client::adminAllowedFields()). */
    public const ADMIN_FILLABLE = [
        'office_id', 'is_demo',
        'password_hash', 'password_changed_at', 'force_password_change',
    ];

    public static function adminAllowedFields(): array
    {
        return array_merge(self::FILLABLE, self::ADMIN_FILLABLE);
    }

    public static function findById(int $id): ?array
    {
        if (array_key_exists($id, self::$memo)) {
            return self::$memo[$id];
        }
        $row = Database::getInstance()->fetchOne("SELECT * FROM clients WHERE id = ?", [$id]);
        self::$memo[$id] = $row;
        return $row;
    }

    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    public static function findByNip(string $nip): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM clients WHERE nip = ?", [$nip]);
    }

    public static function countByOffice(int $officeId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM clients WHERE office_id = ?",
            [$officeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function countAll(bool $excludeDemo = true): int
    {
        $demoFilter = $excludeDemo ? " WHERE is_demo = 0" : "";
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM clients{$demoFilter}"
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function findAll(bool $activeOnly = false, bool $excludeDemo = false): array
    {
        $sql = "SELECT c.*, o.name as office_name FROM clients c LEFT JOIN offices o ON c.office_id = o.id";
        $conditions = [];
        if ($activeOnly) $conditions[] = "c.is_active = 1";
        if ($excludeDemo) $conditions[] = "c.is_demo = 0";
        if ($conditions) $sql .= " WHERE " . implode(' AND ', $conditions);
        $sql .= " ORDER BY c.company_name";
        return Database::getInstance()->fetchAll($sql);
    }

    public static function findByOffice(int $officeId, bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM clients WHERE office_id = ?";
        if ($activeOnly) $sql .= " AND is_active = 1";
        $sql .= " ORDER BY company_name";
        return Database::getInstance()->fetchAll($sql, [$officeId]);
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('clients', $data);
    }

    public static function update(int $id, array $data, ?array $allowed = null): int
    {
        $whitelist = $allowed ?? self::FILLABLE;
        $filtered = array_intersect_key($data, array_flip($whitelist));
        if (empty($filtered)) {
            return 0;
        }
        $rows = Database::getInstance()->update('clients', $filtered, 'id = ?', [$id]);
        unset(self::$memo[$id]);
        return $rows;
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM clients WHERE id = ?", [$id]);
        unset(self::$memo[$id]);
    }

    public static function updateLastLogin(int $id): void
    {
        Database::getInstance()->update('clients', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public static function updatePassword(int $id, string $hash): void
    {
        Database::getInstance()->update('clients', [
            'password_hash' => $hash,
            'password_changed_at' => date('Y-m-d H:i:s'),
            'force_password_change' => 0,
        ], 'id = ?', [$id]);
    }

    public static function acceptPrivacy(int $id): void
    {
        Database::getInstance()->update('clients', [
            'privacy_accepted' => 1,
            'privacy_accepted_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public static function count(): int
    {
        $result = Database::getInstance()->fetchOne("SELECT COUNT(*) as cnt FROM clients");
        return (int) $result['cnt'];
    }

    public static function findAllPaginated(int $offset, int $limit): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT c.*, o.name as office_name FROM clients c LEFT JOIN offices o ON c.office_id = o.id ORDER BY c.company_name LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    private static function buildFilterWhere(array $filters, array &$params): string
    {
        $conditions = [];
        if (!empty($filters['search'])) {
            $conditions[] = "(c.company_name LIKE ? OR c.nip LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (isset($filters['office_id']) && $filters['office_id'] !== '') {
            if ($filters['office_id'] === '0') {
                $conditions[] = "c.office_id IS NULL";
            } else {
                $conditions[] = "c.office_id = ?";
                $params[] = (int) $filters['office_id'];
            }
        }
        if (!empty($filters['status'])) {
            $conditions[] = "c.is_active = ?";
            $params[] = $filters['status'] === 'active' ? 1 : 0;
        }
        return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    }

    public static function countFiltered(array $filters): int
    {
        $params = [];
        $where = self::buildFilterWhere($filters, $params);
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM clients c LEFT JOIN offices o ON c.office_id = o.id{$where}",
            $params
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function findAllFiltered(array $filters, string $sort, string $dir, int $offset, int $limit): array
    {
        $validSorts = ['company_name' => 'c.company_name', 'nip' => 'c.nip', 'office_name' => 'o.name', 'last_login_at' => 'c.last_login_at', 'is_active' => 'c.is_active', 'created_at' => 'c.created_at'];
        $sortCol = $validSorts[$sort] ?? 'c.company_name';
        $sortDir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $params = [];
        $where = self::buildFilterWhere($filters, $params);
        $params[] = $limit;
        $params[] = $offset;

        return Database::getInstance()->fetchAll(
            "SELECT c.*, o.name as office_name FROM clients c LEFT JOIN offices o ON c.office_id = o.id{$where} ORDER BY {$sortCol} {$sortDir}, c.company_name ASC LIMIT ? OFFSET ?",
            $params
        );
    }

    public static function getMonthlyGrowth(int $months = 12): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM clients WHERE is_demo = 0 AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC",
            [$months]
        );
    }

    public static function getActivityBreakdown(): array
    {
        $db = Database::getInstance();
        $active = $db->fetchOne("SELECT COUNT(*) as cnt FROM clients WHERE is_demo = 0 AND last_login_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $dormant = $db->fetchOne("SELECT COUNT(*) as cnt FROM clients WHERE is_demo = 0 AND last_login_at IS NOT NULL AND last_login_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $never = $db->fetchOne("SELECT COUNT(*) as cnt FROM clients WHERE is_demo = 0 AND last_login_at IS NULL");
        return [
            'active_30d' => (int) ($active['cnt'] ?? 0),
            'dormant' => (int) ($dormant['cnt'] ?? 0),
            'never' => (int) ($never['cnt'] ?? 0),
        ];
    }

    public static function getActivityBreakdownByOffice(int $officeId): array
    {
        $db = Database::getInstance();
        $active = $db->fetchOne("SELECT COUNT(*) as cnt FROM clients WHERE office_id = ? AND is_demo = 0 AND last_login_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$officeId]);
        $dormant = $db->fetchOne("SELECT COUNT(*) as cnt FROM clients WHERE office_id = ? AND is_demo = 0 AND last_login_at IS NOT NULL AND last_login_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$officeId]);
        $never = $db->fetchOne("SELECT COUNT(*) as cnt FROM clients WHERE office_id = ? AND is_demo = 0 AND last_login_at IS NULL", [$officeId]);
        return [
            'active_30d' => (int) ($active['cnt'] ?? 0),
            'dormant' => (int) ($dormant['cnt'] ?? 0),
            'never' => (int) ($never['cnt'] ?? 0),
        ];
    }
}
