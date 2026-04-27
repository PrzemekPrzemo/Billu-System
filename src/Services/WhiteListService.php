<?php

namespace App\Services;

use App\Core\Cache;
use App\Models\Setting;

class WhiteListService
{
    /**
     * Search entity by NIP — returns full data with all registered bank accounts.
     * Uses GET /api/search/nip/{nip}?date={YYYY-MM-DD}
     * More efficient than check endpoint — one request per NIP returns all accounts.
     *
     * Cached per (nip, date) — expires daily because the white list is "stan na dany dzień",
     * so freshness within a single calendar day is acceptable for batch verification.
     */
    public static function searchByNip(string $nip): ?array
    {
        $nip = self::normalizeNip($nip);
        if (empty($nip)) {
            return null;
        }

        $date = date('Y-m-d');
        $cache = Cache::getInstance();
        $cacheKey = 'whitelist:' . $nip . ':' . $date;

        return $cache->remember($cacheKey, $cache->ttl('whitelist'), function () use ($nip, $date) {
            $baseUrl = Setting::get('whitelist_api_url', 'https://wl-api.mf.gov.pl');
            $url = rtrim($baseUrl, '/') . '/api/search/nip/' . urlencode($nip) . '?date=' . $date;

            $response = self::httpGet($url);
            if ($response === null) {
                return null;
            }

            $data = json_decode($response, true);
            if (empty($data['result']['subject'])) {
                return null;
            }

            $subject = $data['result']['subject'];

            // Normalize all account numbers for comparison
            $accounts = [];
            foreach ($subject['accountNumbers'] ?? [] as $acc) {
                $accounts[] = self::normalizeAccount($acc);
            }

            return [
                'nip' => $subject['nip'] ?? $nip,
                'name' => $subject['name'] ?? '',
                'statusVat' => $subject['statusVat'] ?? '',
                'accountNumbers' => $accounts,
                'regon' => $subject['regon'] ?? '',
                'krs' => $subject['krs'] ?? '',
                'residenceAddress' => $subject['residenceAddress'] ?? '',
                'workingAddress' => $subject['workingAddress'] ?? '',
                'requestId' => $data['requestId'] ?? '',
            ];
        });
    }

    /**
     * Check if a specific bank account is registered for given NIP on the white list.
     * Returns structured result with verification status and reason.
     */
    public static function verifyNipBankAccount(string $nip, string $bankAccount): array
    {
        $enabled = Setting::get('whitelist_check_enabled', '1');
        if ($enabled !== '1') {
            return [
                'verified' => true,
                'status' => 'skipped',
                'message' => 'Weryfikacja białej listy wyłączona',
            ];
        }

        $normalizedAccount = self::normalizeAccount($bankAccount);
        if (empty($normalizedAccount)) {
            return [
                'verified' => false,
                'status' => 'error',
                'message' => 'Nieprawidłowy numer konta bankowego',
            ];
        }

        $entity = self::searchByNip($nip);
        if ($entity === null) {
            return [
                'verified' => false,
                'status' => 'not_found',
                'message' => 'Podmiot o NIP ' . self::normalizeNip($nip) . ' nie został znaleziony na białej liście VAT',
            ];
        }

        if ($entity['statusVat'] !== 'Czynny') {
            return [
                'verified' => false,
                'status' => 'inactive',
                'message' => 'Podmiot nie jest czynnym podatnikiem VAT (status: ' . ($entity['statusVat'] ?: 'brak') . ')',
            ];
        }

        if (in_array($normalizedAccount, $entity['accountNumbers'], true)) {
            return [
                'verified' => true,
                'status' => 'confirmed',
                'message' => 'Konto bankowe potwierdzone na białej liście VAT',
            ];
        }

        return [
            'verified' => false,
            'status' => 'account_not_found',
            'message' => 'Konto bankowe sprzedawcy nie znajduje się na białej liście VAT dla NIP ' . self::normalizeNip($nip),
        ];
    }

    /**
     * Clear in-memory cache (useful between tests or long processes).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Normalize bank account number — remove PL prefix, spaces, dashes.
     * Returns 26-digit Polish account number.
     */
    public static function normalizeAccount(string $account): string
    {
        // Remove all non-alphanumeric characters
        $account = preg_replace('/[^A-Za-z0-9]/', '', $account);

        // Remove PL prefix (IBAN)
        if (str_starts_with(strtoupper($account), 'PL')) {
            $account = substr($account, 2);
        }

        return $account;
    }

    /**
     * Normalize NIP — remove dashes and spaces.
     */
    public static function normalizeNip(string $nip): string
    {
        return preg_replace('/[^0-9]/', '', $nip);
    }

    /**
     * HTTP GET request using file_get_contents with timeout.
     */
    private static function httpGet(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        return $response;
    }
}
