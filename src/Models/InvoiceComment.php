<?php

namespace App\Models;

use App\Core\Database;

class InvoiceComment
{
    public static function findByInvoice(int $invoiceId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM invoice_comments WHERE invoice_id = ? ORDER BY created_at ASC",
            [$invoiceId]
        );
    }

    public static function countByInvoice(int $invoiceId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM invoice_comments WHERE invoice_id = ?",
            [$invoiceId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function countByBatch(int $batchId): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT ic.invoice_id, COUNT(*) AS cnt
             FROM invoice_comments ic
             JOIN invoices i ON ic.invoice_id = i.id
             WHERE i.batch_id = ?
             GROUP BY ic.invoice_id",
            [$batchId]
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['invoice_id']] = (int) $row['cnt'];
        }
        return $counts;
    }

    public static function create(int $invoiceId, string $userType, int $userId, string $message): int
    {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO invoice_comments (invoice_id, user_type, user_id, message) VALUES (?, ?, ?, ?)",
            [$invoiceId, $userType, $userId, $message]
        );
        return (int) $db->lastInsertId();
    }

    public static function getUserName(string $userType, int $userId): string
    {
        $db = Database::getInstance();
        return match ($userType) {
            'admin' => ($db->fetchOne("SELECT username FROM users WHERE id = ?", [$userId]))['username'] ?? 'Admin',
            'office' => ($db->fetchOne("SELECT name FROM offices WHERE id = ?", [$userId]))['name'] ?? 'Biuro',
            'client' => ($db->fetchOne("SELECT company_name FROM clients WHERE id = ?", [$userId]))['company_name'] ?? 'Klient',
            default => 'Nieznany',
        };
    }
}
