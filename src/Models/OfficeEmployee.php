<?php

namespace App\Models;

use App\Core\Database;

class OfficeEmployee
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM office_employees WHERE id = ?",
            [$id]
        );
    }

    public static function countByOffice(int $officeId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM office_employees WHERE office_id = ?",
            [$officeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function findByOffice(int $officeId, bool $activeOnly = true): array
    {
        $sql = "SELECT oe.*,
                       (SELECT COUNT(*) FROM office_employee_clients oec WHERE oec.employee_id = oe.id) as client_count
                FROM office_employees oe
                WHERE oe.office_id = ?";
        if ($activeOnly) {
            $sql .= " AND oe.is_active = 1";
        }
        $sql .= " ORDER BY oe.name";
        return Database::getInstance()->fetchAll($sql, [$officeId]);
    }

    public static function findByClient(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT oe.* FROM office_employees oe
             JOIN office_employee_clients oec ON oe.id = oec.employee_id
             WHERE oec.client_id = ? AND oe.is_active = 1
             LIMIT 1",
            [$clientId]
        );
    }

    public static function findAllByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT oe.* FROM office_employees oe
             JOIN office_employee_clients oec ON oe.id = oec.employee_id
             WHERE oec.client_id = ? AND oe.is_active = 1
             ORDER BY oe.name",
            [$clientId]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('office_employees', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::getInstance()->update('office_employees', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM office_employees WHERE id = ?", [$id]);
    }

    public static function assignClients(int $employeeId, array $clientIds): void
    {
        $db = Database::getInstance();
        $db->query("DELETE FROM office_employee_clients WHERE employee_id = ?", [$employeeId]);
        foreach ($clientIds as $clientId) {
            $db->insert('office_employee_clients', [
                'employee_id' => $employeeId,
                'client_id'   => (int) $clientId,
            ]);
        }
    }

    public static function getAssignedClientIds(int $employeeId): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT client_id FROM office_employee_clients WHERE employee_id = ?",
            [$employeeId]
        );
        return array_column($rows, 'client_id');
    }

    public static function getAssignedClients(int $employeeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT c.id, c.company_name, c.nip
             FROM clients c
             JOIN office_employee_clients oec ON c.id = oec.client_id
             WHERE oec.employee_id = ?
             ORDER BY c.company_name",
            [$employeeId]
        );
    }

    // ── Auth Methods ──────────────────────────────────

    public static function findByEmail(string $email): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT oe.*, o.name as office_name, o.nip as office_nip, o.is_active as office_is_active,
                    o.language as office_language
             FROM office_employees oe
             JOIN offices o ON oe.office_id = o.id
             WHERE oe.email = ?
             LIMIT 1",
            [$email]
        );
    }

    public static function updatePassword(int $id, string $hash): void
    {
        Database::getInstance()->update('office_employees', [
            'password_hash' => $hash,
            'password_changed_at' => date('Y-m-d H:i:s'),
            'force_password_change' => 0,
        ], 'id = ?', [$id]);
    }

    public static function updateLastLogin(int $id): void
    {
        Database::getInstance()->update('office_employees', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }
}
