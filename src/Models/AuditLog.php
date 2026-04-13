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
            $sql .= " AND action LIKE ?";
            $params[] = "%{$action}%";
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
            $sql .= " AND action LIKE ?";
            $params[] = "%{$action}%";
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
     * Paginated search.
     */
    public static function searchPaginated(?string $action, ?string $dateFrom, ?string $dateTo, int $offset, int $limit): array
    {
        $sql = "SELECT * FROM audit_log WHERE 1=1";
        $params = [];

        if ($action) {
            $sql .= " AND action LIKE ?";
            $params[] = "%{$action}%";
        }
        if ($dateFrom) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return Database::getInstance()->fetchAll($sql, $params);
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
