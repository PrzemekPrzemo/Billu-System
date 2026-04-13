<?php

namespace App\Models;

use App\Core\Database;

class ClientSmtpConfig
{
    public static function findByClientId(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_smtp_configs WHERE client_id = ?",
            [$clientId]
        );
    }

    public static function findEnabledByClientId(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_smtp_configs WHERE client_id = ? AND is_enabled = 1",
            [$clientId]
        );
    }

    public static function upsert(int $clientId, array $data): void
    {
        $existing = self::findByClientId($clientId);
        $db = Database::getInstance();

        if ($existing) {
            $sets = [];
            $params = [];
            foreach ($data as $key => $value) {
                $sets[] = "{$key} = ?";
                $params[] = $value;
            }
            $params[] = $clientId;
            $db->query(
                "UPDATE client_smtp_configs SET " . implode(', ', $sets) . " WHERE client_id = ?",
                $params
            );
        } else {
            $data['client_id'] = $clientId;
            $cols = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $db->query(
                "INSERT INTO client_smtp_configs ({$cols}) VALUES ({$placeholders})",
                array_values($data)
            );
        }
    }

    public static function delete(int $clientId): void
    {
        Database::getInstance()->query(
            "DELETE FROM client_smtp_configs WHERE client_id = ?",
            [$clientId]
        );
    }
}
