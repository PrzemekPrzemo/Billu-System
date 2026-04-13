<?php

namespace App\Models;

use App\Core\Database;

class ClientModule
{
    /**
     * Find all module assignments for a given client.
     */
    public static function findByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT cm.*, m.name AS module_name, m.slug AS module_slug
             FROM client_modules cm
             JOIN modules m ON m.id = cm.module_id
             WHERE cm.client_id = ?
             ORDER BY m.sort_order ASC",
            [$clientId]
        );
    }

    /**
     * Enable or disable a module for a client (upsert).
     */
    public static function setModule(int $clientId, int $moduleId, bool $enabled, ?int $enabledById = null): void
    {
        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT id FROM client_modules WHERE client_id = ? AND module_id = ?",
            [$clientId, $moduleId]
        );

        if ($existing) {
            $db->update('client_modules', [
                'is_enabled' => $enabled ? 1 : 0,
                'enabled_at' => date('Y-m-d H:i:s'),
                'enabled_by_id' => $enabledById,
            ], 'id = ?', [$existing['id']]);
        } else {
            $db->insert('client_modules', [
                'client_id' => $clientId,
                'module_id' => $moduleId,
                'is_enabled' => $enabled ? 1 : 0,
                'enabled_at' => date('Y-m-d H:i:s'),
                'enabled_by_id' => $enabledById,
            ]);
        }
    }
}
