<?php

namespace App\Models;

use App\Core\Database;

class InvoiceBatch
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT ib.*, c.company_name, c.nip, c.report_email, c.email as client_email,
                    o.name as office_name
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             LEFT JOIN offices o ON ib.office_id = o.id
             WHERE ib.id = ?",
            [$id]
        );
    }

    public static function findByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ib.*, (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id) as invoice_count,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id AND status = 'pending') as pending_count
             FROM invoice_batches ib WHERE client_id = ? ORDER BY period_year DESC, period_month DESC",
            [$clientId]
        );
    }

    public static function findByClientAndPeriod(int $clientId, int $month, int $year): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM invoice_batches WHERE client_id = ? AND period_month = ? AND period_year = ?",
            [$clientId, $month, $year]
        );
    }

    public static function findAll(bool $excludeDemo = false): array
    {
        $where = $excludeDemo ? " WHERE c.is_demo = 0" : "";
        return Database::getInstance()->fetchAll(
            "SELECT ib.*, c.company_name, c.nip, o.name as office_name,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id) as invoice_count,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id AND status = 'pending') as pending_count
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             LEFT JOIN offices o ON ib.office_id = o.id
             {$where}
             ORDER BY ib.period_year DESC, ib.period_month DESC, c.company_name"
        );
    }

    public static function findByOffice(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ib.*, c.company_name, c.nip,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id) as invoice_count,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id AND status = 'pending') as pending_count
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             WHERE c.office_id = ?
             ORDER BY ib.period_year DESC, ib.period_month DESC, c.company_name",
            [$officeId]
        );
    }

    public static function getOverdueByOffice(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ib.*, c.company_name, c.nip,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id AND status = 'pending') as pending_count
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             WHERE c.office_id = ? AND ib.is_finalized = 0
               AND ib.verification_deadline < CURDATE()
               AND (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id AND status = 'pending') > 0
             ORDER BY ib.verification_deadline ASC",
            [$officeId]
        );
    }

    public static function findActiveByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ib.*,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id) as invoice_count,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id AND status = 'pending') as pending_count
             FROM invoice_batches ib
             WHERE ib.client_id = ? AND ib.is_finalized = 0
             ORDER BY ib.period_year DESC, ib.period_month DESC",
            [$clientId]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('invoice_batches', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::getInstance()->update('invoice_batches', $data, 'id = ?', [$id]);
    }

    public static function finalize(int $id): void
    {
        Database::getInstance()->update(
            'invoice_batches',
            ['is_finalized' => 1, 'finalized_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
    }

    public static function reopen(int $id): void
    {
        Database::getInstance()->update(
            'invoice_batches',
            ['is_finalized' => 0, 'finalized_at' => null],
            'id = ?',
            [$id]
        );
    }

    public static function findExpiredUnfinalized(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ib.*, c.company_name, c.nip, c.email, c.report_email, c.office_id
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             WHERE ib.is_finalized = 0 AND ib.verification_deadline < CURDATE()"
        );
    }

    public static function findPendingNotification(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ib.*, c.company_name, c.email, c.language
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             WHERE ib.is_finalized = 0 AND ib.notification_sent = 0"
        );
    }

    public static function countByOffice(int $officeId): int
    {
        $result = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM invoice_batches ib JOIN clients c ON ib.client_id = c.id WHERE c.office_id = ?",
            [$officeId]
        );
        return (int) ($result['cnt'] ?? 0);
    }

    public static function findByOfficePaginated(int $officeId, int $offset, int $limit): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ib.*, c.company_name, c.nip,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id) as invoice_count,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id AND status = 'pending') as pending_count
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             WHERE c.office_id = ?
             ORDER BY ib.period_year DESC, ib.period_month DESC, c.company_name
             LIMIT ? OFFSET ?",
            [$officeId, $limit, $offset]
        );
    }

    public static function countAll(): int
    {
        $result = Database::getInstance()->fetchOne("SELECT COUNT(*) as cnt FROM invoice_batches");
        return (int) ($result['cnt'] ?? 0);
    }

    public static function findAllPaginated(int $offset, int $limit): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ib.*, c.company_name, c.nip, o.name as office_name,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id) as invoice_count,
                    (SELECT COUNT(*) FROM invoices WHERE batch_id = ib.id AND status = 'pending') as pending_count
             FROM invoice_batches ib
             JOIN clients c ON ib.client_id = c.id
             LEFT JOIN offices o ON ib.office_id = o.id
             ORDER BY ib.period_year DESC, ib.period_month DESC, c.company_name
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }
}
