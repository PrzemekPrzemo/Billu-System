<?php

namespace App\Models;

use App\Core\Database;

class ClientCostCenter
{
    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM client_cost_centers WHERE id = ?", [$id]);
    }

    public static function findByClient(int $clientId, bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM client_cost_centers WHERE client_id = ?";
        if ($activeOnly) $sql .= " AND is_active = 1";
        $sql .= " ORDER BY sort_order, name";
        return Database::getInstance()->fetchAll($sql, [$clientId]);
    }

    public static function countByClient(int $clientId): int
    {
        $result = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) as cnt FROM client_cost_centers WHERE client_id = ?",
            [$clientId]
        );
        return (int) ($result['cnt'] ?? 0);
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('client_cost_centers', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::getInstance()->update('client_cost_centers', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM client_cost_centers WHERE id = ?", [$id]);
    }

    public static function deleteByClient(int $clientId): void
    {
        Database::getInstance()->query("DELETE FROM client_cost_centers WHERE client_id = ?", [$clientId]);
    }

    public static function syncForClient(int $clientId, array $names): void
    {
        $db = Database::getInstance();
        $existing = self::findByClient($clientId);
        $existingMap = [];
        foreach ($existing as $cc) {
            $existingMap[$cc['id']] = $cc;
        }

        // Process submitted names (max 10)
        $names = array_slice(array_filter(array_map('trim', $names)), 0, 10);
        $processedIds = [];

        foreach ($names as $i => $name) {
            if (empty($name)) continue;

            // Try to find existing with same name
            $found = false;
            foreach ($existingMap as $id => $cc) {
                if ($cc['name'] === $name && !in_array($id, $processedIds)) {
                    // Update sort order
                    self::update($id, ['sort_order' => $i, 'is_active' => 1]);
                    $processedIds[] = $id;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $newId = self::create([
                    'client_id' => $clientId,
                    'name' => $name,
                    'sort_order' => $i,
                    'is_active' => 1,
                ]);
                $processedIds[] = $newId;
            }
        }

        // Deactivate removed ones (don't delete - may be referenced by invoices)
        foreach ($existingMap as $id => $cc) {
            if (!in_array($id, $processedIds)) {
                self::update($id, ['is_active' => 0]);
            }
        }
    }
}
