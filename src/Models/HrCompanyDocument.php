<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrCompanyDocument
{
    public const TYPE_LABELS = [
        'regulamin_pracy'          => 'Regulamin pracy',
        'regulamin_wynagradzania'  => 'Regulamin wynagradzania',
        'zfss'                     => 'Regulamin ZFSś',
        'uklad_zbiorowy'           => 'Układ zbiorowy pracy',
        'obwieszczenie'            => 'Obwieszczenie',
        'inne'                     => 'Inne',
    ];

    public static function findByClient(int $clientId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_company_documents WHERE client_id = ? ORDER BY created_at DESC",
            [$clientId]
        );
    }

    public static function findById(int $id): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_company_documents WHERE id = ?",
            [$id]
        );
    }

    public static function create(array $data): int
    {
        return HrDatabase::getInstance()->insert('hr_company_documents', $data);
    }

    public static function delete(int $id): void
    {
        HrDatabase::getInstance()->query("DELETE FROM hr_company_documents WHERE id = ?", [$id]);
    }
}
