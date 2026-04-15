<?php

namespace App\Models;

use App\Core\Database;

class Advertisement
{
    const PLACEMENTS = [
        'client_panel' => 'Panel klienta',
        'office_panel' => 'Panel biura',
        'ksef'         => 'KSeF',
    ];

    const TYPES = [
        'info'    => 'Informacja',
        'promo'   => 'Promocja',
        'warning' => 'Ostrzeżenie',
        'success' => 'Sukces',
    ];

    /**
     * Return active ads for a given placement, respecting starts_at / ends_at window.
     */
    public static function findActive(string $placement): array
    {
        $db  = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        return $db->fetchAll(
            "SELECT * FROM advertisements
             WHERE placement = ?
               AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= ?)
               AND (ends_at   IS NULL OR ends_at   >= ?)
             ORDER BY sort_order ASC, id ASC",
            [$placement, $now, $now]
        );
    }

    /**
     * Return all ads for admin listing.
     */
    public static function findAll(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM advertisements ORDER BY placement ASC, sort_order ASC, id ASC"
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM advertisements WHERE id = ?",
            [$id]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('advertisements', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::getInstance()->update('advertisements', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query(
            "DELETE FROM advertisements WHERE id = ?",
            [$id]
        );
    }

    public static function toggleActive(int $id): void
    {
        Database::getInstance()->query(
            "UPDATE advertisements SET is_active = 1 - is_active WHERE id = ?",
            [$id]
        );
    }
}
