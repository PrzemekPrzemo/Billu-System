<?php

namespace App\Models;

use App\Core\Database;

class ClientNote
{
    public static function findByClient(int $clientId, int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM client_internal_notes WHERE client_id = ? AND office_id = ? ORDER BY is_pinned DESC, updated_at DESC",
            [$clientId, $officeId]
        );
    }

    public static function findLatestByClient(int $clientId, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_internal_notes WHERE client_id = ? AND office_id = ? ORDER BY updated_at DESC LIMIT 1",
            [$clientId, $officeId]
        );
    }

    public static function findPinnedByOffice(int $officeId, int $limit = 5): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT n.*, c.company_name FROM client_internal_notes n
             JOIN clients c ON c.id = n.client_id
             WHERE n.office_id = ? AND n.is_pinned = 1
             ORDER BY n.updated_at DESC LIMIT ?",
            [$officeId, $limit]
        );
    }

    public static function save(int $clientId, int $officeId, string $note, string $createdBy): int
    {
        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT id FROM client_internal_notes WHERE client_id = ? AND office_id = ?",
            [$clientId, $officeId]
        );

        if ($existing) {
            $db->query(
                "UPDATE client_internal_notes SET note = ?, created_by = ? WHERE id = ?",
                [$note, $createdBy, $existing['id']]
            );
            return (int) $existing['id'];
        }

        $db->query(
            "INSERT INTO client_internal_notes (client_id, office_id, note, created_by) VALUES (?, ?, ?, ?)",
            [$clientId, $officeId, $note, $createdBy]
        );
        return (int) $db->lastInsertId();
    }

    public static function togglePin(int $clientId, int $officeId): void
    {
        Database::getInstance()->query(
            "UPDATE client_internal_notes SET is_pinned = NOT is_pinned WHERE client_id = ? AND office_id = ?",
            [$clientId, $officeId]
        );
    }

    public static function delete(int $id, int $officeId): void
    {
        Database::getInstance()->query(
            "DELETE FROM client_internal_notes WHERE id = ? AND office_id = ?",
            [$id, $officeId]
        );
    }
}
