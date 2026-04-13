<?php

namespace App\Models;

use App\Core\Database;

class ScheduledExport
{
    public static function findAll(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT se.*, c.company_name, c.nip
             FROM scheduled_exports se
             JOIN clients c ON se.client_id = c.id
             ORDER BY se.created_at DESC"
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT se.*, c.company_name, c.nip
             FROM scheduled_exports se
             JOIN clients c ON se.client_id = c.id
             WHERE se.id = ?",
            [$id]
        );
    }

    public static function findDue(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT se.*, c.company_name, c.nip
             FROM scheduled_exports se
             JOIN clients c ON se.client_id = c.id
             WHERE se.is_active = 1 AND se.next_run_at <= NOW()
             ORDER BY se.next_run_at ASC"
        );
    }

    public static function findByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM scheduled_exports WHERE client_id = ? ORDER BY created_at DESC",
            [$clientId]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('scheduled_exports', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('scheduled_exports', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM scheduled_exports WHERE id = ?", [$id]);
    }

    public static function toggle(int $id): void
    {
        Database::getInstance()->query(
            "UPDATE scheduled_exports SET is_active = NOT is_active WHERE id = ?",
            [$id]
        );
    }

    public static function markRun(int $id, string $nextRunAt): void
    {
        Database::getInstance()->update('scheduled_exports', [
            'last_run_at' => date('Y-m-d H:i:s'),
            'next_run_at' => $nextRunAt,
        ], 'id = ?', [$id]);
    }

    public static function calculateNextRun(string $frequency, int $dayOfMonth): string
    {
        if ($frequency === 'weekly') {
            return date('Y-m-d H:i:s', strtotime('+1 week'));
        }
        // Monthly: next month on specified day
        $nextMonth = date('Y-m', strtotime('+1 month'));
        $maxDay = (int) date('t', strtotime($nextMonth . '-01'));
        $day = min($dayOfMonth, $maxDay);
        return sprintf('%s-%02d 06:00:00', $nextMonth, $day);
    }
}
