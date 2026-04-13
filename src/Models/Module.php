<?php

namespace App\Models;

use App\Core\Database;

class Module
{
    public static function findAll(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM modules";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC";
        return Database::getInstance()->fetchAll($sql);
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM modules WHERE id = ?", [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM modules WHERE slug = ?", [$slug]);
    }

    /**
     * Get all modules with enabled/disabled status for a specific office.
     */
    public static function getOfficeModuleMatrix(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT m.*,
                    COALESCE(om.is_enabled, 1) as is_enabled_for_office,
                    om.enabled_at,
                    om.enabled_by_id
             FROM modules m
             LEFT JOIN office_modules om ON om.module_id = m.id AND om.office_id = ?
             WHERE m.is_active = 1
             ORDER BY m.sort_order ASC",
            [$officeId]
        );
    }

    /**
     * Check if a module is enabled for a specific office.
     * Uses static cache to avoid repeated DB queries within a single request.
     * If no office_modules rows exist for the office, all modules are considered enabled.
     */
    public static function isEnabledForOffice(int $officeId, string $slug): bool
    {
        static $cache = [];
        $key = $officeId;

        if (!isset($cache[$key])) {
            $cache[$key] = self::getEnabledSlugsForOffice($officeId);
        }

        return in_array($slug, $cache[$key], true);
    }

    /**
     * Get array of enabled module slugs for an office.
     * If no office_modules rows exist, returns all active module slugs (default-enabled).
     */
    public static function getEnabledSlugsForOffice(int $officeId): array
    {
        $db = Database::getInstance();

        // Check if any office_modules rows exist for this office
        $count = $db->fetchOne(
            "SELECT COUNT(*) as cnt FROM office_modules WHERE office_id = ?",
            [$officeId]
        );

        if (!$count || (int)$count['cnt'] === 0) {
            // No configuration yet - all active modules are enabled by default
            $all = $db->fetchAll("SELECT slug FROM modules WHERE is_active = 1");
            return array_column($all, 'slug');
        }

        $rows = $db->fetchAll(
            "SELECT m.slug
             FROM modules m
             INNER JOIN office_modules om ON om.module_id = m.id
             WHERE om.office_id = ? AND om.is_enabled = 1 AND m.is_active = 1",
            [$officeId]
        );

        return array_column($rows, 'slug');
    }

    /**
     * Set module enabled/disabled for an office.
     */
    public static function setOfficeModule(int $officeId, int $moduleId, bool $enabled, ?int $enabledById = null): void
    {
        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT id FROM office_modules WHERE office_id = ? AND module_id = ?",
            [$officeId, $moduleId]
        );

        if ($existing) {
            $db->update('office_modules', [
                'is_enabled' => $enabled ? 1 : 0,
                'enabled_at' => date('Y-m-d H:i:s'),
                'enabled_by_id' => $enabledById,
            ], 'id = ?', [$existing['id']]);
        } else {
            $db->insert('office_modules', [
                'office_id' => $officeId,
                'module_id' => $moduleId,
                'is_enabled' => $enabled ? 1 : 0,
                'enabled_at' => date('Y-m-d H:i:s'),
                'enabled_by_id' => $enabledById,
            ]);
        }
    }

    /**
     * Initialize all modules as enabled for a new office.
     */
    public static function initOfficeModules(int $officeId, ?int $enabledById = null): void
    {
        $modules = self::findAll(true);
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        foreach ($modules as $module) {
            $existing = $db->fetchOne(
                "SELECT id FROM office_modules WHERE office_id = ? AND module_id = ?",
                [$officeId, $module['id']]
            );
            if (!$existing) {
                $db->insert('office_modules', [
                    'office_id' => $officeId,
                    'module_id' => (int)$module['id'],
                    'is_enabled' => 1,
                    'enabled_at' => $now,
                    'enabled_by_id' => $enabledById,
                ]);
            }
        }
    }
}
