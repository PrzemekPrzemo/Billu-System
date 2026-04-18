<?php

namespace App\Services;

use App\Core\Database;
use App\Core\HrDatabase;
use App\Models\HrClientSettings;
use App\Models\AuditLog;

class HrAccessService
{
    public static function isEnabledForOffice(int $officeId): bool
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT hr_module_enabled FROM offices WHERE id = ?",
            [$officeId]
        );
        return (bool) ($row['hr_module_enabled'] ?? false);
    }

    public static function isEnabledForClient(int $clientId): bool
    {
        $hrDb = HrDatabase::hrDbName();
        $row = Database::getInstance()->fetchOne(
            "SELECT hs.hr_enabled, o.hr_module_enabled
             FROM clients c
             JOIN offices o ON c.office_id = o.id
             LEFT JOIN {$hrDb}.hr_client_settings hs ON hs.client_id = c.id
             WHERE c.id = ?",
            [$clientId]
        );
        if (!$row) return false;
        return (bool) $row['hr_module_enabled'] && (bool) ($row['hr_enabled'] ?? 0);
    }

    public static function enableClient(int $clientId, int $actorId, string $actorType): void
    {
        HrClientSettings::setHrEnabled($clientId, true);
        AuditLog::log($actorType, $actorId, 'hr_module_toggle', json_encode([
            'client_id' => $clientId,
            'action'    => 'enable',
        ]), 'client', $clientId);
    }

    public static function disableClient(int $clientId, int $actorId, string $actorType): void
    {
        HrClientSettings::setHrEnabled($clientId, false);
        AuditLog::log($actorType, $actorId, 'hr_module_toggle', json_encode([
            'client_id' => $clientId,
            'action'    => 'disable',
        ]), 'client', $clientId);
    }

    public static function enableAllClientsOfOffice(int $officeId, int $actorId, string $actorType): int
    {
        $count = HrClientSettings::enableAllForOffice($officeId);
        AuditLog::log($actorType, $actorId, 'hr_module_toggle', json_encode([
            'action'   => 'enable_all',
            'affected' => $count,
        ]), 'office', $officeId);
        return $count;
    }

    public static function disableAllClientsOfOffice(int $officeId, int $actorId, string $actorType): int
    {
        $count = HrClientSettings::disableAllForOffice($officeId);
        AuditLog::log($actorType, $actorId, 'hr_module_toggle', json_encode([
            'action'   => 'disable_all',
            'affected' => $count,
        ]), 'office', $officeId);
        return $count;
    }

    public static function getClientsWithHrStatus(int $officeId): array
    {
        $hrDb = HrDatabase::hrDbName();
        return Database::getInstance()->fetchAll(
            "SELECT c.id, c.company_name, c.nip, c.is_active,
                    COALESCE(hs.hr_enabled, 0) AS hr_enabled
             FROM clients c
             LEFT JOIN {$hrDb}.hr_client_settings hs ON hs.client_id = c.id
             WHERE c.office_id = ?
             ORDER BY c.company_name",
            [$officeId]
        );
    }
}
