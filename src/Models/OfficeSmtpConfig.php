<?php

namespace App\Models;

use App\Core\Database;

class OfficeSmtpConfig
{
    public static function findByOfficeId(int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM office_smtp_configs WHERE office_id = ?",
            [$officeId]
        );
    }

    public static function upsert(int $officeId, array $data): void
    {
        $db = Database::getInstance();
        $existing = self::findByOfficeId($officeId);
        $data['office_id'] = $officeId;

        if ($existing) {
            $db->update('office_smtp_configs', $data, 'office_id = ?', [$officeId]);
        } else {
            $db->insert('office_smtp_configs', $data);
        }
    }

    public static function delete(int $officeId): void
    {
        Database::getInstance()->query(
            "DELETE FROM office_smtp_configs WHERE office_id = ?",
            [$officeId]
        );
    }

    /**
     * Get enabled SMTP config for a client's office.
     */
    public static function findEnabledByClientId(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT osc.* FROM office_smtp_configs osc
             JOIN clients c ON c.office_id = osc.office_id
             WHERE c.id = ? AND osc.is_enabled = 1",
            [$clientId]
        );
    }
}
