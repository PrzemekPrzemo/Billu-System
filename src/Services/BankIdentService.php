<?php

namespace App\Services;

class BankIdentService
{
    /**
     * Mapping of Polish bank sort codes (first 4 digits of the 8-digit sort code,
     * i.e. positions 3-6 of the 26-digit NRB number) to bank names.
     */
    private const BANK_CODES = [
        '1010' => 'Narodowy Bank Polski',
        '1020' => 'PKO Bank Polski',
        '1030' => 'Bank Handlowy w Warszawie (Citi)',
        '1050' => 'ING Bank Śląski',
        '1060' => 'Bank BPH',
        '1090' => 'Santander Bank Polska',
        '1130' => 'BGK (Bank Gospodarstwa Krajowego)',
        '1140' => 'mBank',
        '1160' => 'Bank Millennium',
        '1240' => 'Bank Pekao SA',
        '1280' => 'HSBC',
        '1320' => 'Bank Pocztowy',
        '1540' => 'BOŚ Bank',
        '1580' => 'Mercedes-Benz Bank Polska',
        '1610' => 'SGB-Bank',
        '1670' => 'BPS (Bank Polskiej Spółdzielczości)',
        '1750' => 'Raiffeisen Digital Bank',
        '1840' => 'Societe Generale',
        '1870' => 'Nest Bank',
        '1930' => 'Bank Polskiej Spółdzielczości',
        '1940' => 'Credit Agricole Bank Polska',
        '2030' => 'BNP Paribas Bank Polska',
        '2070' => 'FCE Bank Polska',
        '2120' => 'Santander Consumer Bank',
        '2160' => 'Toyota Bank Polska',
        '2190' => 'DNB Bank Polska',
        '2480' => 'Getin Noble Bank',
        '2490' => 'Alior Bank',
        '2710' => 'Bank Handlowy (Citi Handlowy)',
        '2720' => 'Plus Bank',
    ];

    /**
     * Normalize account number: remove spaces, dashes, PL prefix.
     * Returns 26-digit NRB or null if invalid.
     */
    public static function normalizeAccountNumber(string $input): ?string
    {
        $cleaned = preg_replace('/[\s\-]/', '', $input);
        // Remove PL prefix (IBAN)
        if (stripos($cleaned, 'PL') === 0) {
            $cleaned = substr($cleaned, 2);
        }
        // Must be 26 digits
        if (!preg_match('/^\d{26}$/', $cleaned)) {
            return null;
        }
        return $cleaned;
    }

    /**
     * Identify the bank name from a Polish account number (NRB or IBAN).
     * Returns bank name or null if not recognized.
     */
    public static function identifyBank(string $accountNumber): ?string
    {
        $normalized = self::normalizeAccountNumber($accountNumber);
        if ($normalized === null) {
            return null;
        }

        // Extract first 4 digits of the sort code (positions 3-6, 0-indexed: index 2-5)
        $bankCode = substr($normalized, 2, 4);

        return self::BANK_CODES[$bankCode] ?? null;
    }

    /**
     * Get all known bank codes.
     */
    public static function getAllBankCodes(): array
    {
        return self::BANK_CODES;
    }
}
