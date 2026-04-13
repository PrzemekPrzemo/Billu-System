<?php

namespace App\Models;

use App\Core\Database;

class CompanyProfile
{
    public static function findByClient(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM company_profiles WHERE client_id = ?",
            [$clientId]
        );
    }

    public static function upsert(int $clientId, array $data): void
    {
        $existing = self::findByClient($clientId);
        $data['client_id'] = $clientId;

        if ($existing) {
            Database::getInstance()->update('company_profiles', $data, 'client_id = ?', [$clientId]);
        } else {
            Database::getInstance()->insert('company_profiles', $data);
        }
    }

    /**
     * Get prefix and counter column for a given invoice type.
     */
    private static function getTypeConfig(string $invoiceType): array
    {
        return match ($invoiceType) {
            'FV_KOR' => ['prefix' => 'FV-KOR', 'column' => 'next_correction_number'],
            'FP'     => ['prefix' => 'PRO',    'column' => 'next_proforma_number'],
            'FV_ZAL' => ['prefix' => 'FV-ZAL', 'column' => 'next_advance_number'],
            'FV_KON' => ['prefix' => 'FV-KON', 'column' => 'next_final_number'],
            default  => ['prefix' => 'FV',      'column' => 'next_invoice_number'],
        };
    }

    public static function getNextInvoiceNumber(int $clientId, string $invoiceType = 'FV'): string
    {
        $profile = self::findByClient($clientId);
        $cfg = self::getTypeConfig($invoiceType);
        $nextNr = (int) ($profile[$cfg['column']] ?? 1);

        return str_replace(
            ['{NR}', '{MM}', '{RRRR}'],
            [str_pad($nextNr, 3, '0', STR_PAD_LEFT), date('m'), date('Y')],
            $cfg['prefix'] . '/{NR}/{MM}/{RRRR}'
        );
    }

    /**
     * Atomically get the next invoice number and increment the counter.
     * Uses SELECT ... FOR UPDATE to prevent race conditions.
     *
     * @param string $invoiceType 'FV' or 'FV_KOR'
     */
    public static function getAndIncrementInvoiceNumber(int $clientId, string $invoiceType = 'FV'): string
    {
        $db = Database::getInstance();
        $cfg = self::getTypeConfig($invoiceType);
        $db->beginTransaction();
        try {
            $profile = $db->fetchOne(
                "SELECT * FROM company_profiles WHERE client_id = ? FOR UPDATE",
                [$clientId]
            );

            $nextNr = (int) ($profile[$cfg['column']] ?? 1);

            // Skip numbers that already exist in the database
            $maxAttempts = 100;
            $number = '';
            for ($i = 0; $i < $maxAttempts; $i++) {
                $number = str_replace(
                    ['{NR}', '{MM}', '{RRRR}'],
                    [str_pad($nextNr, 3, '0', STR_PAD_LEFT), date('m'), date('Y')],
                    $cfg['prefix'] . '/{NR}/{MM}/{RRRR}'
                );

                $existing = $db->fetchOne(
                    "SELECT id FROM issued_invoices WHERE client_id = ? AND invoice_number = ?",
                    [$clientId, $number]
                );

                if (!$existing) {
                    break;
                }

                $nextNr++;
            }

            $db->query(
                "UPDATE company_profiles SET `{$cfg['column']}` = ? WHERE client_id = ?",
                [$nextNr + 1, $clientId]
            );

            $db->commit();
            return $number;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function incrementInvoiceCounter(int $clientId): void
    {
        Database::getInstance()->query(
            "UPDATE company_profiles SET next_invoice_number = next_invoice_number + 1 WHERE client_id = ?",
            [$clientId]
        );
    }
}
