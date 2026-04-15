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

    // ── Module dependencies & bundles ──────────────────────

    /**
     * Get required dependencies for a module (slugs that must be enabled).
     */
    public static function getRequiredDependencies(int $moduleId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT m.id, m.slug, m.name
             FROM module_dependencies md
             INNER JOIN modules m ON m.id = md.depends_on_module_id
             WHERE md.module_id = ? AND md.dependency_type = 'required'",
            [$moduleId]
        );
    }

    /**
     * Get modules that depend on a given module (will break if this is disabled).
     */
    public static function getDependentModules(int $moduleId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT m.id, m.slug, m.name
             FROM module_dependencies md
             INNER JOIN modules m ON m.id = md.module_id
             WHERE md.depends_on_module_id = ? AND md.dependency_type = 'required'",
            [$moduleId]
        );
    }

    /**
     * Get full dependency map: module_slug => [required_slug, ...].
     */
    public static function getDependencyMap(): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT m1.slug as module_slug, m2.slug as depends_on_slug, md.dependency_type
             FROM module_dependencies md
             INNER JOIN modules m1 ON m1.id = md.module_id
             INNER JOIN modules m2 ON m2.id = md.depends_on_module_id
             ORDER BY m1.slug"
        );

        $map = [];
        foreach ($rows as $r) {
            $map[$r['module_slug']][] = [
                'slug' => $r['depends_on_slug'],
                'type' => $r['dependency_type'],
            ];
        }
        return $map;
    }

    /**
     * Enable a module for an office with auto-cascade of required dependencies.
     * Returns array of additionally enabled module slugs.
     */
    public static function enableWithDependencies(int $officeId, int $moduleId, ?int $enabledById = null): array
    {
        $autoEnabled = [];
        $deps = self::getRequiredDependencies($moduleId);

        foreach ($deps as $dep) {
            if (!self::isEnabledForOffice($officeId, $dep['slug'])) {
                self::setOfficeModule($officeId, (int)$dep['id'], true, $enabledById);
                $autoEnabled[] = $dep['slug'];
                // Recursively enable dependencies of dependencies
                $nested = self::enableWithDependencies($officeId, (int)$dep['id'], $enabledById);
                $autoEnabled = array_merge($autoEnabled, $nested);
            }
        }

        self::setOfficeModule($officeId, $moduleId, true, $enabledById);
        return array_unique($autoEnabled);
    }

    /**
     * Disable a module for an office with auto-cascade of dependent modules.
     * Returns array of additionally disabled module slugs.
     */
    public static function disableWithDependents(int $officeId, int $moduleId, ?int $enabledById = null): array
    {
        $autoDisabled = [];
        $dependents = self::getDependentModules($moduleId);

        foreach ($dependents as $dep) {
            if (self::isEnabledForOffice($officeId, $dep['slug'])) {
                $nested = self::disableWithDependents($officeId, (int)$dep['id'], $enabledById);
                $autoDisabled = array_merge($autoDisabled, $nested);
                self::setOfficeModule($officeId, (int)$dep['id'], false, $enabledById);
                $autoDisabled[] = $dep['slug'];
            }
        }

        self::setOfficeModule($officeId, $moduleId, false, $enabledById);
        return array_unique($autoDisabled);
    }

    /**
     * Apply a bundle to an office — enable exactly the modules in the bundle, disable others.
     */
    public static function applyBundle(int $officeId, int $bundleId, ?int $enabledById = null): void
    {
        $db = Database::getInstance();
        $bundle = $db->fetchOne("SELECT * FROM module_bundles WHERE id = ?", [$bundleId]);
        if (!$bundle) return;

        $bundleSlugs = json_decode($bundle['modules_json'], true) ?: [];
        $allModules = self::findAll(true);

        foreach ($allModules as $mod) {
            $enabled = in_array($mod['slug'], $bundleSlugs, true) || !empty($mod['is_system']);
            self::setOfficeModule($officeId, (int)$mod['id'], $enabled, $enabledById);

            // Tag with bundle_id
            if ($enabled) {
                $db->query(
                    "UPDATE office_modules SET bundle_id = ? WHERE office_id = ? AND module_id = ?",
                    [$bundleId, $officeId, $mod['id']]
                );
            }
        }
    }

    /**
     * Get all bundles.
     */
    public static function getBundles(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM module_bundles";
        if ($activeOnly) $sql .= " WHERE is_active = 1";
        $sql .= " ORDER BY sort_order ASC";
        return Database::getInstance()->fetchAll($sql);
    }

    /**
     * Find bundle by ID.
     */
    public static function findBundleById(int $id): ?array
    {
        return Database::getInstance()->fetchOne("SELECT * FROM module_bundles WHERE id = ?", [$id]);
    }

    // ── Client-level module management ─────────────────────

    /**
     * Get all modules with enabled/disabled status for a specific client.
     * Respects office-level restrictions: if office has module disabled, it shows as disabled.
     */
    public static function getClientModuleMatrix(int $clientId, int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT m.*,
                    COALESCE(om.is_enabled, 1) as is_enabled_for_office,
                    COALESCE(cm.is_enabled, 1) as is_enabled_for_client,
                    cm.enabled_at as client_enabled_at,
                    cm.enabled_by_id as client_enabled_by_id
             FROM modules m
             LEFT JOIN office_modules om ON om.module_id = m.id AND om.office_id = ?
             LEFT JOIN client_modules cm ON cm.module_id = m.id AND cm.client_id = ?
             WHERE m.is_active = 1
             ORDER BY m.sort_order ASC",
            [$officeId, $clientId]
        );
    }

    /**
     * Check if a module is enabled for a specific client.
     * Cascade: office must have it enabled AND client must have it enabled.
     */
    public static function isEnabledForClient(int $clientId, int $officeId, string $slug): bool
    {
        static $cache = [];
        $key = $clientId;

        if (!isset($cache[$key])) {
            $cache[$key] = self::getEnabledSlugsForClient($clientId, $officeId);
        }

        return in_array($slug, $cache[$key], true);
    }

    /**
     * Get array of enabled module slugs for a client.
     * A module is enabled only if both office AND client have it enabled.
     * If no client_modules rows exist, inherits all from office.
     */
    public static function getEnabledSlugsForClient(int $clientId, int $officeId): array
    {
        $db = Database::getInstance();
        $officeSlugs = self::getEnabledSlugsForOffice($officeId);

        // Check if any client_modules rows exist
        $count = $db->fetchOne(
            "SELECT COUNT(*) as cnt FROM client_modules WHERE client_id = ?",
            [$clientId]
        );

        if (!$count || (int)$count['cnt'] === 0) {
            // No client-level config — inherit fully from office
            return $officeSlugs;
        }

        // Get client-enabled slugs (intersection with office)
        $rows = $db->fetchAll(
            "SELECT m.slug
             FROM modules m
             INNER JOIN client_modules cm ON cm.module_id = m.id
             WHERE cm.client_id = ? AND cm.is_enabled = 1 AND m.is_active = 1",
            [$clientId]
        );
        $clientSlugs = array_column($rows, 'slug');

        // Return intersection: must be enabled at both levels
        return array_values(array_intersect($officeSlugs, $clientSlugs));
    }

    /**
     * Set module enabled/disabled for a client.
     */
    public static function setClientModule(int $clientId, int $moduleId, bool $enabled, ?int $enabledById = null): void
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

    /**
     * Initialize all modules as enabled for a new client.
     */
    public static function initClientModules(int $clientId, ?int $enabledById = null): void
    {
        $modules = self::findAll(true);
        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        foreach ($modules as $module) {
            $existing = $db->fetchOne(
                "SELECT id FROM client_modules WHERE client_id = ? AND module_id = ?",
                [$clientId, $module['id']]
            );
            if (!$existing) {
                $db->insert('client_modules', [
                    'client_id' => $clientId,
                    'module_id' => (int)$module['id'],
                    'is_enabled' => 1,
                    'enabled_at' => $now,
                    'enabled_by_id' => $enabledById,
                ]);
            }
        }
    }
}
