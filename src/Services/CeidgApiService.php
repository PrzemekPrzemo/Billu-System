<?php

namespace App\Services;

use App\Core\Cache;
use App\Models\Setting;

/**
 * CEIDG API Service - wyszukiwanie jednoosobowych działalności gospodarczych po NIP.
 *
 * Fallback dla GUS API — CEIDG zawiera wpisy JDG, których może brakować w GUS.
 *
 * API docs: https://akademia.biznes.gov.pl/portal/004856
 *
 * Two-step lookup:
 * 1. GET {baseUrl}?nip=X → list with {id, link} entries
 * 2. GET {link} → full company detail with adresDzialalnosci, wlasciciel, etc.
 *
 * Default environments (can be overridden via ceidg_api_url setting):
 * - test: https://test-dane.biznes.gov.pl/api/ceidg/v2/firmy
 * - production: https://dane.biznes.gov.pl/api/ceidg/v2/firmy
 */
class CeidgApiService
{
    private string $token;
    private string $env;
    private string $baseUrl;

    private const ENV_URLS = [
        'test'       => 'https://test-dane.biznes.gov.pl/api/ceidg/v3/firmy',
        'production' => 'https://dane.biznes.gov.pl/api/ceidg/v3/firmy',
    ];

    public function __construct()
    {
        $this->token = Setting::get('ceidg_api_token', '');
        $this->env = Setting::get('ceidg_api_env', 'test');

        // Custom URL overrides default env-based URL
        $customUrl = Setting::get('ceidg_api_url', '');
        $this->baseUrl = !empty($customUrl)
            ? rtrim($customUrl, '/')
            : (self::ENV_URLS[$this->env] ?? self::ENV_URLS['test']);
    }

    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    public function getEnv(): string { return $this->env; }
    public function getBaseUrl(): string { return $this->baseUrl; }

    /**
     * Search company data by NIP (two-step: search → detail).
     * Returns normalized array compatible with GusApiService format, or null if not found.
     */
    public function findByNip(string $nip): ?array
    {
        $nip = preg_replace('/[^0-9]/', '', $nip);

        if (strlen($nip) !== 10) {
            throw new \RuntimeException('Nieprawidłowy NIP (wymagane 10 cyfr).');
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('CEIDG API nie jest skonfigurowane. Ustaw token API w Admin → Ustawienia → CEIDG API.');
        }

        $cache = Cache::getInstance();
        $cacheKey = 'ceidg:' . $this->env . ':' . $nip;
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Search by NIP — v3 returns full data in search results
        $searchUrl = $this->baseUrl . '?' . http_build_query(['nip' => $nip]);
        $searchResult = $this->httpGet($searchUrl);

        if (!$searchResult) {
            return null;
        }

        // Pick the best entry from results (prefer AKTYWNY)
        $firma = $this->pickBestEntry($searchResult);
        if (!$firma) {
            return null;
        }

        $result = $this->parseCompanyData($firma, $nip);
        if ($result !== null) {
            $cache->set($cacheKey, $result, $cache->ttl('ceidg'));
        }
        return $result;
    }

    /**
     * Run full diagnostic — returns ['steps' => [...], 'log' => '...']
     */
    public function diagnose(string $nip): array
    {
        $steps = [];
        $log = [];
        $addLog = function (string $level, string $msg, $detail = null) use (&$log) {
            $entry = '[' . date('H:i:s') . '] [' . $level . '] ' . $msg;
            if ($detail !== null) {
                $entry .= "\n" . (is_string($detail) ? $detail : json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            $entry .= "\n" . str_repeat('-', 80);
            $log[] = $entry;
        };

        // Step 1: Config
        $addLog('INFO', 'Konfiguracja CEIDG API', [
            'token' => $this->token ? (substr($this->token, 0, 10) . '...' . substr($this->token, -5)) : 'BRAK',
            'token_length' => strlen($this->token),
            'environment' => $this->env,
            'base_url' => $this->baseUrl,
            'php_version' => PHP_VERSION,
            'curl_version' => curl_version()['version'] ?? '?',
            'test_nip' => $nip,
        ]);

        $steps[] = [
            'name' => 'Konfiguracja',
            'ok' => $this->isConfigured(),
            'details' => [
                'token' => $this->isConfigured() ? 'Ustawiony (' . strlen($this->token) . ' znaków)' : 'BRAK — ustaw w Ustawienia → CEIDG API',
                'environment' => $this->env,
                'base_url' => $this->baseUrl,
            ],
        ];

        if (!$this->isConfigured()) {
            return ['steps' => $steps, 'log' => implode("\n", $log)];
        }

        // Step 2: Search request
        $addLog('INFO', "Wyszukiwanie NIP: {$nip}...");
        $searchUrl = $this->baseUrl . '?' . http_build_query(['nip' => $nip]);
        $addLog('DEBUG', 'Request URL', $searchUrl);

        $searchResponse = $this->diagHttpGet($searchUrl, $addLog);
        if ($searchResponse === false) {
            $steps[] = [
                'name' => 'Połączenie z API',
                'ok' => false,
                'details' => ['error' => 'Błąd połączenia — sprawdź log poniżej'],
            ];
            return ['steps' => $steps, 'log' => implode("\n", $log)];
        }

        $searchData = json_decode($searchResponse['body'], true);
        $firmaCount = 0;

        if ($searchData) {
            $firmaCount = count($searchData['firmy'] ?? []);
            $addLog('INFO', "Znaleziono {$firmaCount} wynik(ów)", $searchData['firmy'] ?? []);
        } else {
            $addLog('ERROR', 'Nie udało się sparsować odpowiedzi JSON', $searchResponse['body']);
        }

        $steps[] = [
            'name' => 'Połączenie z API',
            'ok' => $searchResponse['http_code'] >= 200 && $searchResponse['http_code'] < 400,
            'details' => [
                'http_code' => $searchResponse['http_code'],
                'czas' => "{$searchResponse['time']}s",
                'znaleziono' => $firmaCount,
            ],
        ];

        // Step 3: Parse company data
        $firma = $searchData ? $this->pickBestEntry($searchData) : null;
        $parsed = $firma ? $this->parseCompanyData($firma, $nip) : null;

        if ($parsed) {
            $addLog('INFO', 'Parsed data', $parsed);
        } else {
            $addLog('ERROR', 'Nie udało się sparsować danych firmy');
        }

        $steps[] = [
            'name' => "Dane firmy (NIP: {$nip})",
            'ok' => !empty($parsed) && !empty($parsed['company_name']),
            'details' => $parsed ?: ['error' => 'Nie znaleziono aktywnego podmiotu lub błąd parsowania'],
        ];

        return ['steps' => $steps, 'log' => implode("\n", $log)];
    }

    /**
     * HTTP GET with diagnostic logging. Returns ['body', 'http_code', 'time'] or false on error.
     */
    private function diagHttpGet(string $url, callable $addLog): array|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 2);
        curl_close($ch);

        if ($error) {
            $addLog('ERROR', "cURL error: {$error} (po {$totalTime}s)");
            return false;
        }

        $addLog('INFO', "HTTP {$httpCode} (czas: {$totalTime}s)");
        $addLog('DEBUG', 'Response body', $response);

        return ['body' => $response, 'http_code' => $httpCode, 'time' => $totalTime];
    }

    /**
     * Perform HTTP GET request to CEIDG API.
     * Returns decoded JSON array, null for 404/empty, or throws on error.
     */
    private function httpGet(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("CEIDG API cURL error: {$error}");
        }

        if ($httpCode === 404 || $httpCode === 204) {
            return null;
        }

        if ($httpCode === 429) {
            throw new \RuntimeException('CEIDG API: Przekroczono limit zapytań (rate limit). Spróbuj ponownie za chwilę.');
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("CEIDG API HTTP error: {$httpCode}");
        }

        $data = json_decode($response, true);
        if (!$data) {
            return null;
        }

        return $data;
    }

    /**
     * Pick the best company entry from search results.
     * Prefers AKTYWNY (active) status, falls back to first entry.
     * Handles both v3 (firmy array at top level) and detail (firma array) responses.
     */
    private function pickBestEntry(array $data): ?array
    {
        // v3 search: { firmy: [{ ... }, { ... }] }
        $firmy = $data['firmy'] ?? $data['firma'] ?? [];
        if (empty($firmy)) {
            return null;
        }

        // Prefer active entry
        foreach ($firmy as $f) {
            if (($f['status'] ?? '') === 'AKTYWNY') {
                return $f;
            }
        }

        // Fallback to first entry
        return $firmy[0];
    }

    /**
     * Parse CEIDG API company entry into normalized format (compatible with GUS).
     *
     * v3 entry structure:
     * {
     *   "nazwa": "Firma XYZ",
     *   "adresDzialalnosci": { "ulica", "budynek", "lokal", "miasto", "kod", "wojewodztwo", ... },
     *   "wlasciciel": { "imie", "nazwisko", "nip", "regon" },
     *   "status": "AKTYWNY",
     *   ...
     * }
     */
    private function parseCompanyData(array $firma, string $searchNip = ''): ?array
    {
        $nazwa = $firma['nazwa'] ?? '';
        if (empty($nazwa)) {
            return null;
        }

        // Address — business address first, then correspondence
        $adres = $firma['adresDzialalnosci']
            ?? $firma['adresKorespondencyjny']
            ?? [];

        // Owner data
        $wlasciciel = $firma['wlasciciel'] ?? [];

        // NIP/REGON from owner, then top-level, then search NIP
        $nip = $wlasciciel['nip'] ?? $firma['nip'] ?? $searchNip;
        $regon = $wlasciciel['regon'] ?? $firma['regon'] ?? '';

        return [
            'nip'          => (string) $nip,
            'regon'        => (string) $regon,
            'company_name' => (string) $nazwa,
            'province'     => (string) ($adres['wojewodztwo'] ?? ''),
            'city'         => (string) ($adres['miasto'] ?? ''),
            'street'       => (string) ($adres['ulica'] ?? ''),
            'building_no'  => (string) ($adres['budynek'] ?? ''),
            'apartment_no' => (string) ($adres['lokal'] ?? ''),
            'postal_code'  => (string) ($adres['kod'] ?? ''),
            'type'         => 'F',
            'source'       => 'ceidg',
        ];
    }
}
