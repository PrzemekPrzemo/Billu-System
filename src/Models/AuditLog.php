<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Session;

class AuditLog
{
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
                'old_values'      => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values'      => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'impersonated_by' => Session::get('impersonator_id'),
            ]);
        } catch (\Exception $e) {
            error_log("AuditLog error: " . $e->getMessage());
        }
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
