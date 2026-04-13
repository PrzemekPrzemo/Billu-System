<?php

namespace App\Models;

use App\Core\Database;

class Message
{
    public static function create(
        int $clientId,
        string $senderType,
        int $senderId,
        string $body,
        ?string $subject = null,
        ?int $invoiceId = null,
        ?int $batchId = null,
        ?int $parentId = null,
        ?string $attachmentPath = null,
        ?string $attachmentName = null
    ): int {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO messages (client_id, invoice_id, batch_id, sender_type, sender_id, subject, body, attachment_path, attachment_name, parent_id, is_read_by_client, is_read_by_office)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $clientId,
                $invoiceId,
                $batchId,
                $senderType,
                $senderId,
                $subject,
                $body,
                $attachmentPath,
                $attachmentName,
                $parentId,
                $senderType === 'client' ? 1 : 0,
                in_array($senderType, ['office', 'employee']) ? 1 : 0,
            ]
        );
        return (int) $db->lastInsertId();
    }

    /**
     * Update attachment info after file upload (used when we need the message ID for filename).
     */
    public static function updateAttachment(int $id, string $path, string $name): void
    {
        Database::getInstance()->query(
            "UPDATE messages SET attachment_path = ?, attachment_name = ? WHERE id = ?",
            [$path, $name, $id]
        );
    }

    /**
     * Get full filesystem path for an attachment.
     */
    public static function getAttachmentFullPath(array $message): ?string
    {
        if (empty($message['attachment_path'])) {
            return null;
        }
        $storageDir = __DIR__ . '/../../storage/messages';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        $storageDir = realpath($storageDir);
        $path = realpath(__DIR__ . '/../../' . $message['attachment_path']);
        if ($path && $storageDir && strpos($path, $storageDir) === 0) {
            return $path;
        }
        return null;
    }

    /**
     * Find root message by ID.
     */
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM messages WHERE id = ?",
            [$id]
        );
    }

    /**
     * Get full thread: root message + all replies, ordered chronologically.
     */
    public static function findThread(int $rootId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM messages WHERE id = ? OR parent_id = ? ORDER BY created_at ASC",
            [$rootId, $rootId]
        );
    }

    /**
     * List root messages (threads) for a client, newest first.
     */
    public static function findByClient(int $clientId, int $limit = 50, int $offset = 0): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT m.*,
                    (SELECT COUNT(*) FROM messages r WHERE r.parent_id = m.id) AS reply_count,
                    (SELECT MAX(r2.created_at) FROM messages r2 WHERE r2.parent_id = m.id) AS last_reply_at
             FROM messages m
             WHERE m.client_id = ? AND m.parent_id IS NULL
             ORDER BY COALESCE(last_reply_at, m.created_at) DESC
             LIMIT ? OFFSET ?",
            [$clientId, $limit, $offset]
        );
    }

    /**
     * List root messages for all clients of an office.
     */
    public static function findByOffice(int $officeId, ?int $clientId = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT m.*,
                       c.company_name AS client_name,
                       (SELECT COUNT(*) FROM messages r WHERE r.parent_id = m.id) AS reply_count,
                       (SELECT MAX(r2.created_at) FROM messages r2 WHERE r2.parent_id = m.id) AS last_reply_at
                FROM messages m
                JOIN clients c ON m.client_id = c.id
                WHERE c.office_id = ? AND m.parent_id IS NULL";
        $params = [$officeId];

        if ($clientId !== null) {
            $sql .= " AND m.client_id = ?";
            $params[] = $clientId;
        }

        $sql .= " ORDER BY COALESCE(last_reply_at, m.created_at) DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * List root messages for clients assigned to an employee.
     */
    public static function findByEmployee(int $employeeId, ?int $clientId = null, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT m.*,
                       c.company_name AS client_name,
                       (SELECT COUNT(*) FROM messages r WHERE r.parent_id = m.id) AS reply_count,
                       (SELECT MAX(r2.created_at) FROM messages r2 WHERE r2.parent_id = m.id) AS last_reply_at
                FROM messages m
                JOIN clients c ON m.client_id = c.id
                JOIN office_employee_clients oec ON c.id = oec.client_id
                WHERE oec.employee_id = ? AND m.parent_id IS NULL";
        $params = [$employeeId];

        if ($clientId !== null) {
            $sql .= " AND m.client_id = ?";
            $params[] = $clientId;
        }

        $sql .= " ORDER BY COALESCE(last_reply_at, m.created_at) DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return Database::getInstance()->fetchAll($sql, $params);
    }

    /**
     * Threads related to a specific invoice.
     */
    public static function findByInvoice(int $invoiceId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM messages WHERE invoice_id = ? AND parent_id IS NULL ORDER BY created_at DESC",
            [$invoiceId]
        );
    }

    /**
     * Unread thread count for a client.
     */
    public static function countUnreadByClient(int $clientId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(DISTINCT COALESCE(m.parent_id, m.id)) AS cnt
             FROM messages m
             WHERE m.client_id = ? AND m.is_read_by_client = 0 AND m.sender_type != 'client'",
            [$clientId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Unread thread count for all clients of an office.
     */
    public static function countAllUnreadForOffice(int $officeId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(DISTINCT COALESCE(m.parent_id, m.id)) AS cnt
             FROM messages m
             JOIN clients c ON m.client_id = c.id
             WHERE c.office_id = ? AND m.is_read_by_office = 0 AND m.sender_type = 'client'",
            [$officeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Unread thread count for an employee's assigned clients.
     */
    public static function countAllUnreadForEmployee(int $employeeId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(DISTINCT COALESCE(m.parent_id, m.id)) AS cnt
             FROM messages m
             JOIN office_employee_clients oec ON m.client_id = oec.client_id
             WHERE oec.employee_id = ? AND m.is_read_by_office = 0 AND m.sender_type = 'client'",
            [$employeeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Mark all messages in a thread as read by client.
     */
    public static function markReadByClient(int $threadId): void
    {
        Database::getInstance()->query(
            "UPDATE messages SET is_read_by_client = 1 WHERE (id = ? OR parent_id = ?) AND is_read_by_client = 0",
            [$threadId, $threadId]
        );
    }

    /**
     * Mark all messages in a thread as read by office.
     */
    public static function markReadByOffice(int $threadId): void
    {
        Database::getInstance()->query(
            "UPDATE messages SET is_read_by_office = 1 WHERE (id = ? OR parent_id = ?) AND is_read_by_office = 0",
            [$threadId, $threadId]
        );
    }

    /**
     * Resolve sender display name (polymorphic).
     */
    public static function getSenderName(string $type, int $id): string
    {
        $db = Database::getInstance();
        return match ($type) {
            'office' => ($db->fetchOne("SELECT name FROM offices WHERE id = ?", [$id]))['name'] ?? 'Biuro',
            'employee' => ($db->fetchOne("SELECT name FROM office_employees WHERE id = ?", [$id]))['name'] ?? 'Pracownik',
            'client' => ($db->fetchOne("SELECT company_name FROM clients WHERE id = ?", [$id]))['company_name'] ?? 'Klient',
            default => 'Nieznany',
        };
    }

    /**
     * Get all recipients for notification about a message in a client's thread.
     * Returns array of ['email' => ..., 'user_type' => ..., 'user_id' => ..., 'name' => ...]
     */
    public static function getRecipients(int $clientId, string $excludeType = '', int $excludeId = 0): array
    {
        $db = Database::getInstance();
        $recipients = [];

        // Client
        $client = $db->fetchOne("SELECT id, email, company_name FROM clients WHERE id = ?", [$clientId]);
        if ($client && !($excludeType === 'client' && (int) $client['id'] === $excludeId)) {
            $recipients[] = [
                'email' => $client['email'],
                'user_type' => 'client',
                'user_id' => (int) $client['id'],
                'name' => $client['company_name'],
            ];
        }

        // Office
        $office = $db->fetchOne(
            "SELECT o.id, o.email, o.name FROM offices o JOIN clients c ON c.office_id = o.id WHERE c.id = ?",
            [$clientId]
        );
        if ($office && !empty($office['email']) && !($excludeType === 'office' && (int) $office['id'] === $excludeId)) {
            $recipients[] = [
                'email' => $office['email'],
                'user_type' => 'office',
                'user_id' => (int) $office['id'],
                'name' => $office['name'],
            ];
        }

        // Assigned employees
        $employees = $db->fetchAll(
            "SELECT oe.id, oe.email, oe.name
             FROM office_employees oe
             JOIN office_employee_clients oec ON oe.id = oec.employee_id
             WHERE oec.client_id = ? AND oe.is_active = 1",
            [$clientId]
        );
        foreach ($employees as $emp) {
            if (!empty($emp['email']) && !($excludeType === 'employee' && (int) $emp['id'] === $excludeId)) {
                $recipients[] = [
                    'email' => $emp['email'],
                    'user_type' => 'employee',
                    'user_id' => (int) $emp['id'],
                    'name' => $emp['name'],
                ];
            }
        }

        return $recipients;
    }
}
