<?php

namespace App\Models;

use App\Core\Database;

class ClientTaxConfig
{
    private static array $defaults = [
        'vat_period' => 'monthly',
        'taxation_type' => 'PIT',
        'tax_form' => 'skala',
        'zus_payer_type' => 'self_employed',
        'jpk_vat_required' => 1,
        'alert_days_before' => 5,
    ];

    public static function getDefaults(): array
    {
        return self::$defaults;
    }

    public static function findByClient(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_tax_config WHERE client_id = ?",
            [$clientId]
        );
    }

    public static function findByClientOrDefaults(int $clientId): array
    {
        $config = self::findByClient($clientId);
        if ($config) {
            return $config;
        }
        return array_merge(self::$defaults, ['client_id' => $clientId]);
    }

    public static function upsert(int $clientId, array $data): void
    {
        $allowed = ['vat_period', 'taxation_type', 'tax_form', 'zus_payer_type', 'jpk_vat_required', 'alert_days_before'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        $setClauses = [];
        $params = [$clientId];
        foreach ($filtered as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $params[] = $value;
        }

        if (empty($setClauses)) {
            return;
        }

        $setStr = implode(', ', $setClauses);
        $columns = 'client_id, ' . implode(', ', array_keys($filtered));
        $placeholders = '?, ' . implode(', ', array_fill(0, count($filtered), '?'));
        $insertParams = array_merge([$clientId], array_values($filtered));

        Database::getInstance()->query(
            "INSERT INTO client_tax_config ({$columns}) VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE {$setStr}",
            array_merge($insertParams, array_values($filtered))
        );
    }

    public static function findByOffice(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ctc.*, c.company_name, c.nip
             FROM client_tax_config ctc
             JOIN clients c ON ctc.client_id = c.id
             WHERE c.office_id = ? AND c.is_active = 1
             ORDER BY c.company_name",
            [$officeId]
        );
    }

    public static function findAllWithClients(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT c.id as client_id, c.company_name, c.nip,
                    ctc.vat_period, ctc.taxation_type, ctc.tax_form,
                    ctc.zus_payer_type, ctc.jpk_vat_required, ctc.alert_days_before
             FROM clients c
             LEFT JOIN client_tax_config ctc ON c.id = ctc.client_id
             WHERE c.office_id = ? AND c.is_active = 1
             ORDER BY c.company_name",
            [$officeId]
        );
    }
}
