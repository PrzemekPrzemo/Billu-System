<?php

namespace App\Models;

use App\Core\Database;

class ClientTask
{
    public static function create(
        int $clientId,
        string $createdByType,
        int $createdById,
        string $title,
        ?string $description = null,
        string $priority = 'normal',
        ?string $dueDate = null,
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        bool $isBillable = false,
        ?string $taskPrice = null
    ): int {
        $db = Database::getInstance();
        $billingStatus = $isBillable ? 'to_invoice' : 'none';
        $db->query(
            "INSERT INTO client_tasks (client_id, created_by_type, created_by_id, title, description, priority, due_date, attachment_path, attachment_name, is_billable, task_price, billing_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$clientId, $createdByType, $createdById, $title, $description, $priority, $dueDate, $attachmentPath, $attachmentName, $isBillable ? 1 : 0, $taskPrice, $billingStatus]
        );
        return (int) $db->lastInsertId();
    }

    public static function updateAttachment(int $id, string $path, string $name): void
    {
        Database::getInstance()->query(
            "UPDATE client_tasks SET attachment_path = ?, attachment_name = ? WHERE id = ?",
            [$path, $name, $id]
        );
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        $allowed = ['title', 'description', 'priority', 'due_date', 'status', 'is_billable', 'task_price', 'billing_status'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = ?";
                $params[] = $data[$key];
            }
        }
        if (empty($fields)) {
            return;
        }
        $params[] = $id;
        Database::getInstance()->query(
            "UPDATE client_tasks SET " . implode(', ', $fields) . " WHERE id = ?",
            $params
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_tasks WHERE id = ?",
            [$id]
        );
    }

    /**
     * Tasks for a specific client, ordered by priority then due date.
     */
    public static function findByClient(int $clientId, ?string $status = null): array
    {
        $sql = "SELECT * FROM client_tasks WHERE client_id = ?";
        $params = [$clientId];
        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY FIELD(status, 'open', 'in_progress', 'done'), FIELD(priority, 'high', 'normal', 'low'), due_date ASC, created_at DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Tasks across all clients of an office.
     */
    public static function findByOffice(int $officeId, ?string $status = null, ?int $clientId = null): array
    {
        $sql = "SELECT t.*, c.company_name AS client_name
                FROM client_tasks t
                JOIN clients c ON t.client_id = c.id
                WHERE c.office_id = ?";
        $params = [$officeId];
        if ($status !== null) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        if ($clientId !== null) {
            $sql .= " AND t.client_id = ?";
            $params[] = $clientId;
        }
        $sql .= " ORDER BY FIELD(t.status, 'open', 'in_progress', 'done'), FIELD(t.priority, 'high', 'normal', 'low'), t.due_date ASC, t.created_at DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Tasks for clients assigned to an employee.
     */
    public static function findByEmployee(int $employeeId, ?string $status = null, ?int $clientId = null): array
    {
        $sql = "SELECT t.*, c.company_name AS client_name
                FROM client_tasks t
                JOIN clients c ON t.client_id = c.id
                JOIN office_employee_clients oec ON c.id = oec.client_id
                WHERE oec.employee_id = ?";
        $params = [$employeeId];
        if ($status !== null) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        if ($clientId !== null) {
            $sql .= " AND t.client_id = ?";
            $params[] = $clientId;
        }
        $sql .= " ORDER BY FIELD(t.status, 'open', 'in_progress', 'done'), FIELD(t.priority, 'high', 'normal', 'low'), t.due_date ASC, t.created_at DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Count tasks by status for a client.
     */
    public static function countByClientAndStatus(int $clientId): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM client_tasks WHERE client_id = ? GROUP BY status",
            [$clientId]
        );
        $counts = ['open' => 0, 'in_progress' => 0, 'done' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }
        return $counts;
    }

    /**
     * Count overdue tasks for an office.
     */
    public static function countOverdueByOffice(int $officeId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM client_tasks t
             JOIN clients c ON t.client_id = c.id
             WHERE c.office_id = ? AND t.due_date < CURDATE() AND t.status != 'done'",
            [$officeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count overdue tasks for an employee.
     */
    public static function countOverdueByEmployee(int $employeeId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM client_tasks t
             JOIN office_employee_clients oec ON t.client_id = oec.client_id
             WHERE oec.employee_id = ? AND t.due_date < CURDATE() AND t.status != 'done'",
            [$employeeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count all non-completed tasks for an office (open + in_progress).
     */
    public static function countAllOpenByOffice(int $officeId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM client_tasks t
             JOIN clients c ON t.client_id = c.id
             WHERE c.office_id = ? AND t.status != 'done'",
            [$officeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count all non-completed tasks for an employee (open + in_progress).
     */
    public static function countAllOpenByEmployee(int $employeeId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM client_tasks t
             JOIN office_employee_clients oec ON t.client_id = oec.client_id
             WHERE oec.employee_id = ? AND t.status != 'done'",
            [$employeeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Count open tasks for a client (for dashboard badge).
     */
    public static function countOpenByClient(int $clientId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM client_tasks WHERE client_id = ? AND status != 'done'",
            [$clientId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Update task status. If marking as done, record who and when.
     */
    public static function markStatus(int $id, string $status, ?string $completedByType = null, ?int $completedById = null): void
    {
        if ($status === 'done') {
            Database::getInstance()->query(
                "UPDATE client_tasks SET status = 'done', completed_at = NOW(), completed_by_type = ?, completed_by_id = ? WHERE id = ?",
                [$completedByType, $completedById, $id]
            );
        } else {
            Database::getInstance()->query(
                "UPDATE client_tasks SET status = ?, completed_at = NULL, completed_by_type = NULL, completed_by_id = NULL WHERE id = ?",
                [$status, $id]
            );
        }
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM client_tasks WHERE id = ?", [$id]);
    }

    /**
     * Find completed tasks for billing view (office).
     */
    public static function findDoneByOffice(int $officeId, ?string $billingStatus = null, ?int $clientId = null): array
    {
        $sql = "SELECT t.*, c.company_name AS client_name
                FROM client_tasks t
                JOIN clients c ON t.client_id = c.id
                WHERE c.office_id = ? AND t.status = 'done'";
        $params = [$officeId];
        if ($billingStatus !== null && $billingStatus !== 'all') {
            $sql .= " AND t.billing_status = ?";
            $params[] = $billingStatus;
        }
        if ($clientId !== null) {
            $sql .= " AND t.client_id = ?";
            $params[] = $clientId;
        }
        $sql .= " ORDER BY c.company_name ASC, t.completed_at DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Find completed tasks for billing view (employee).
     */
    public static function findDoneByEmployee(int $employeeId, ?string $billingStatus = null, ?int $clientId = null): array
    {
        $sql = "SELECT t.*, c.company_name AS client_name
                FROM client_tasks t
                JOIN clients c ON t.client_id = c.id
                JOIN office_employee_clients oec ON c.id = oec.client_id
                WHERE oec.employee_id = ? AND t.status = 'done'";
        $params = [$employeeId];
        if ($billingStatus !== null && $billingStatus !== 'all') {
            $sql .= " AND t.billing_status = ?";
            $params[] = $billingStatus;
        }
        if ($clientId !== null) {
            $sql .= " AND t.client_id = ?";
            $params[] = $clientId;
        }
        $sql .= " ORDER BY c.company_name ASC, t.completed_at DESC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Resolve creator display name.
     */
    public static function getCreatorName(string $type, int $id): string
    {
        $db = Database::getInstance();
        return match ($type) {
            'admin' => ($db->fetchOne("SELECT username FROM users WHERE id = ?", [$id]))['username'] ?? 'Admin',
            'office' => ($db->fetchOne("SELECT name FROM offices WHERE id = ?", [$id]))['name'] ?? 'Biuro',
            'employee' => ($db->fetchOne("SELECT name FROM office_employees WHERE id = ?", [$id]))['name'] ?? 'Pracownik',
            default => 'Nieznany',
        };
    }
}
