<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrClientSettings
{
    public static function findByClient(int $clientId): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_client_settings WHERE client_id = ?",
            [$clientId]
        );
    }

    public static function getOrCreate(int $clientId): array
    {
        $settings = self::findByClient($clientId);
        if (!$settings) {
            self::createDefaults($clientId);
            $settings = self::findByClient($clientId);
        }
        return $settings;
    }

    public static function createDefaults(int $clientId): int
    {
        return HrDatabase::getInstance()->insert('hr_client_settings', [
            'client_id' => $clientId,
        ]);
    }

    public static function update(int $clientId, array $data): int
    {
        $existing = self::findByClient($clientId);
        if (!$existing) {
            $data['client_id'] = $clientId;
            return HrDatabase::getInstance()->insert('hr_client_settings', $data);
        }
        return HrDatabase::getInstance()->update('hr_client_settings', $data, 'client_id = ?', [$clientId]);
    }

    public static function setHrEnabled(int $clientId, bool $enabled): void
    {
        $existing = self::findByClient($clientId);
        if (!$existing) {
            HrDatabase::getInstance()->insert('hr_client_settings', [
                'client_id'  => $clientId,
                'hr_enabled' => (int) $enabled,
            ]);
        } else {
            HrDatabase::getInstance()->update('hr_client_settings', ['hr_enabled' => (int) $enabled], 'client_id = ?', [$clientId]);
        }
    }

    public static function enableAllForOffice(int $officeId): int
    {
        $mainDb = HrDatabase::mainDbName();
        $db = HrDatabase::getInstance();

        $db->query(
            "INSERT INTO hr_client_settings (client_id, hr_enabled)
             SELECT id, 1 FROM {$mainDb}.clients WHERE office_id = ?
             ON DUPLICATE KEY UPDATE hr_enabled = 1",
            [$officeId]
        );

        $row = $db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_client_settings hs
             JOIN {$mainDb}.clients c ON hs.client_id = c.id
             WHERE c.office_id = ? AND hs.hr_enabled = 1",
            [$officeId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public static function disableAllForOffice(int $officeId): int
    {
        $mainDb = HrDatabase::mainDbName();
        return HrDatabase::getInstance()->update(
            'hr_client_settings',
            ['hr_enabled' => 0],
            "client_id IN (SELECT id FROM {$mainDb}.clients WHERE office_id = ?)",
            [$officeId]
        );
    }
}
