<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Session;

class AuditLog
{
    /** Field names whose values must never be persisted to audit_log in plaintext. */
    private const REDACTED_KEYS = [
        'password', 'password_hash', 'password_changed_at',
        'totp_secret', 'two_factor_secret', 'recovery_codes',
        'smtp_password', 'ksef_token', 'ksef_api_token',
        'api_key', 'api_token', 'jwt_secret',
    ];

    public static function log(
        string $userType,
        int $userId,
        string $action,
        ?string $details = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            Database::getInstance()->insert('audit_log', [
                'user_type'       => $userType,
                'user_id'         => $userId,
                'action'          => $action,
                'entity_type'     => $entityType,
                'entity_id'       => $entityId,
                'details'         => $details,
                'old_values'      => $oldValues ? json_encode(self::redact($oldValues), JSON_UNESCAPED_UNICODE) : null,
                'new_values'      => $newValues ? json_encode(self::redact($newValues), JSON_UNESCAPED_UNICODE) : null,
                'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'impersonated_by' => Session::get('impersonator_id'),
            ]);
        } catch (\Exception $e) {
            error_log("AuditLog error: " . $e->getMessage());
        }
    }

    /** Replace values of sensitive keys with [REDACTED]. Recurses into nested arrays. */
    private static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $values[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $values[$key] = self::redact($value);
            }
        }
        return $values;
    }

    public static function getRecent(int $limit = 50, ?string $entityType = null, ?int $entityId = null): array
    {
        $sql = "SELECT * FROM audit_log";
        $params = [];

        if ($entityType !== null) {
            $sql .= " WHERE entity_type = ?";
            $params[] = $entityType;
            if ($entityId !== null) {
                $sql .= " AND entity_id = ?";
                $params[] = $entityId;
            }
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function getByUser(string $userType, int $userId, int $limit = 50): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM audit_log WHERE user_type = ? AND user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userType, $userId, $limit]
        );
    }

    public static function search(?string $action = null, ?string $dateFrom = null, ?string $dateTo = null, int $limit = 100): array
    {
        $sql = "SELECT * FROM audit_log WHERE 1=1";
        $params = [];

        if ($action) {
            // prefix-LIKE wykorzystuje idx_audit_action_created (left-anchored)
            $sql .= " AND action LIKE ?";
            $params[] = $action . '%';
        }
        if ($dateFrom) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Count audit log entries matching filters.
     */
    public static function searchCount(?string $action = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM audit_log WHERE 1=1";
        $params = [];

        if ($action) {
            // prefix-LIKE wykorzystuje idx_audit_action_created (left-anchored)
            $sql .= " AND action LIKE ?";
            $params[] = $action . '%';
        }
        if ($dateFrom) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $result = Database::getInstance()->fetchOne($sql, $params);
        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Paginated search z deferred join: inner query wybiera tylko ID
     * korzystając z covering index (action, created_at, id) - tania nawet
     * przy dużym OFFSET. Outer query pobiera pełne wiersze (z dużą
     * kolumną details) tylko dla N wynikowych wierszy po PK.
     */
    public static function searchPaginated(?string $action, ?string $dateFrom, ?string $dateTo, int $offset, int $limit): array
    {
        $where = " WHERE 1=1";
        $params = [];

        if ($action) {
            // prefix-LIKE wykorzystuje idx_audit_action_created (left-anchored)
            $where .= " AND action LIKE ?";
            $params[] = $action . '%';
        }
        if ($dateFrom) {
            $where .= " AND created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $where .= " AND created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT a.* FROM audit_log a
                JOIN (
                    SELECT id FROM audit_log{$where}
                    ORDER BY created_at DESC, id DESC
                    LIMIT ? OFFSET ?
                ) sub ON sub.id = a.id
                ORDER BY a.created_at DESC, a.id DESC";

        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Keyset (cursor-based) pagination - O(LIMIT) niezależnie od głębokości.
     * Cursor to ostatnia widziana para (created_at, id) z poprzedniej strony.
     * Pierwsze wywołanie: $cursor = null. Kolejne: przekaż wartości z ostatniego
     * wiersza poprzedniej odpowiedzi.
     *
     * Zwraca ['rows' => [...], 'next_cursor' => ['created_at' => ..., 'id' => ...]|null].
     *
     * @param array{action?:?string,date_from?:?string,date_to?:?string} $filters
     * @param array{created_at:string,id:int}|null $cursor
     */
    public static function searchKeyset(array $filters, ?array $cursor, int $limit = 50): array
    {
        $where = " WHERE 1=1";
        $params = [];

        if (!empty($filters['action'])) {
            $where .= " AND action LIKE ?";
            $params[] = $filters['action'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where .= " AND created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        // Cursor: weź wiersze STARSZE niż ostatnio widziany.
        // Tie-break po id zapewnia deterministyczność przy identycznym created_at.
        if ($cursor !== null && isset($cursor['created_at'], $cursor['id'])) {
            $where .= " AND (created_at < ? OR (created_at = ? AND id < ?))";
            $params[] = $cursor['created_at'];
            $params[] = $cursor['created_at'];
            $params[] = (int)$cursor['id'];
        }

        // +1 wiersz żeby wykryć czy jest następna strona
        $params[] = $limit + 1;

        $rows = Database::getInstance()->fetchAll(
            "SELECT * FROM audit_log{$where} ORDER BY created_at DESC, id DESC LIMIT ?",
            $params
        );

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $nextCursor = null;
        if ($hasMore && !empty($rows)) {
            $last = end($rows);
            $nextCursor = [
                'created_at' => $last['created_at'],
                'id'         => (int)$last['id'],
            ];
        }

        return ['rows' => $rows, 'next_cursor' => $nextCursor];
    }

    /**
     * Encode keyset cursor to URL-safe string. Decoder w decodeCursor().
     */
    public static function encodeCursor(?array $cursor): ?string
    {
        if ($cursor === null) {
            return null;
        }
        return rtrim(strtr(base64_encode(json_encode($cursor)), '+/', '-_'), '=');
    }

    public static function decodeCursor(?string $encoded): ?array
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }
        $padded = strtr($encoded, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $raw = base64_decode($padded, true);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['created_at'], $decoded['id'])) {
            return null;
        }
        return $decoded;
    }

    public static function findLast(string $action): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM audit_log WHERE action = ? ORDER BY created_at DESC LIMIT 1",
            [$action]
        );
    }

    /**
     * Master-admin activity-log query — superset of searchPaginated with
     * user_type / entity_type / keyword filters. Returns ['rows' => [...],
     * 'total' => N, 'page' => N, 'pages' => N] for the standard paginator.
     *
     * Filters (all optional):
     *   user_type   — 'admin' | 'office' | 'employee' | 'client' | 'client_employee' | 'system'
     *   action      — prefix LIKE (uses idx_audit_action_created)
     *   entity_type — exact match
     *   date_from / date_to — 'YYYY-MM-DD'
     *   keyword     — substring search in details / action
     *
     * @param array<string,mixed> $filters
     */
    public static function searchActivityLog(array $filters, int $page = 1, int $pageSize = 50): array
    {
        $where  = ' WHERE 1=1';
        $params = [];

        if (!empty($filters['user_type'])) {
            $where .= ' AND user_type = ?';
            $params[] = (string) $filters['user_type'];
        }
        if (!empty($filters['action'])) {
            $where .= ' AND action LIKE ?';
            $params[] = ((string) $filters['action']) . '%';
        }
        if (!empty($filters['entity_type'])) {
            $where .= ' AND entity_type = ?';
            $params[] = (string) $filters['entity_type'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND created_at >= ?';
            $params[] = ((string) $filters['date_from']) . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND created_at <= ?';
            $params[] = ((string) $filters['date_to']) . ' 23:59:59';
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (details LIKE ? OR action LIKE ?)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['keyword']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $page     = max(1, (int) $page);
        $pageSize = max(10, min(200, (int) $pageSize));
        $offset   = ($page - 1) * $pageSize;

        $db = Database::getInstance();
        $countRow = $db->fetchOne("SELECT COUNT(*) AS cnt FROM audit_log{$where}", $params);
        $total = (int) ($countRow['cnt'] ?? 0);

        $rowParams = $params;
        $rowParams[] = $pageSize;
        $rowParams[] = $offset;

        // Two-step (covering subquery) — same trick as searchPaginated for
        // deep offsets to use idx_audit_action_created when filtered by action.
        $rows = $db->fetchAll(
            "SELECT a.* FROM audit_log a
                 JOIN (
                     SELECT id FROM audit_log{$where}
                     ORDER BY created_at DESC, id DESC
                     LIMIT ? OFFSET ?
                 ) sub ON sub.id = a.id
              ORDER BY a.created_at DESC, a.id DESC",
            $rowParams
        );

        return [
            'rows'  => $rows,
            'total' => $total,
            'page'  => $page,
            'pages' => max(1, (int) ceil($total / $pageSize)),
            'page_size' => $pageSize,
        ];
    }

    /**
     * Resolves (user_type, user_id) tuples to display names by batching
     * queries per role table. Returns a map keyed by "{type}:{id}".
     * Used by the activity-log view so 200 audit rows produce at most
     * 5 SELECTs (one per role) instead of N+1 lookups.
     *
     * @param array<int,array{user_type:string,user_id:int}> $rows audit-log rows
     * @return array<string,string>
     */
    public static function resolveActorNames(array $rows): array
    {
        $byType = [];
        foreach ($rows as $r) {
            $t = (string) ($r['user_type'] ?? '');
            $i = (int)    ($r['user_id']   ?? 0);
            if ($t === '' || $i === 0) {
                continue;
            }
            $byType[$t][$i] = true;
        }

        $names = [];
        $db = Database::getInstance();
        $tables = [
            'admin'           => ['users',             'name',          'email'],
            'office'          => ['offices',           'name',          'email'],
            'employee'        => ['office_employees',  'name',          'email'],
            'client'          => ['clients',           'company_name',  'email'],
            'client_employee' => ['client_employees',  null,            'email'], // composite name
        ];

        foreach ($byType as $type => $idsMap) {
            if (!isset($tables[$type])) {
                continue;
            }
            [$table, $nameCol, $emailCol] = $tables[$type];
            $ids = array_keys($idsMap);
            if (empty($ids)) {
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $select = $nameCol
                ? "id, {$nameCol} AS display_name, {$emailCol} AS email"
                : "id, CONCAT(first_name, ' ', last_name) AS display_name, {$emailCol} AS email";
            try {
                $found = $db->fetchAll(
                    "SELECT {$select} FROM {$table} WHERE id IN ({$placeholders})",
                    $ids
                );
                foreach ($found as $row) {
                    $key = $type . ':' . (int) $row['id'];
                    $label = trim((string) ($row['display_name'] ?? '')) ?: (string) ($row['email'] ?? '');
                    $names[$key] = $label !== '' ? $label : '(bez nazwy)';
                }
            } catch (\Throwable) {
                // Table missing or schema mismatch — fall through, label will be "{type} #id".
            }
        }
        return $names;
    }

    /**
     * Distinct values currently present in audit_log for filter dropdowns.
     * Cheap query thanks to idx_audit_action_created (action) + small
     * cardinality (user_type / entity_type usually <20 distinct values).
     */
    public static function distinctValues(): array
    {
        $db = Database::getInstance();
        try {
            return [
                'user_types'   => array_column($db->fetchAll(
                    "SELECT DISTINCT user_type FROM audit_log
                      WHERE user_type IS NOT NULL AND user_type <> ''
                      ORDER BY user_type"
                ), 'user_type'),
                'entity_types' => array_column($db->fetchAll(
                    "SELECT DISTINCT entity_type FROM audit_log
                      WHERE entity_type IS NOT NULL AND entity_type <> ''
                      ORDER BY entity_type"
                ), 'entity_type'),
            ];
        } catch (\Throwable) {
            return ['user_types' => [], 'entity_types' => []];
        }
    }

    public static function getLoginHistory(int $limit = 100): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM login_history ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }

    public static function getDailyActivityStats(int $days = 30): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT DATE(created_at) as day, COUNT(*) as login_count
             FROM audit_log WHERE action = 'login'
             AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY day ASC",
            [$days]
        );
    }
}
