<?php

namespace App\Services;

use App\Models\Setting;

/**
 * GUS API (BIR1) Service - wyszukiwanie podmiotów po NIP.
 *
 * Wymaga:
 * - Klucz API z GUS (settings: gus_api_key)
 *
 * API docs: https://api.stat.gov.pl/Home/RegonApi
 *
 * Environments:
 * - test: https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc
 * - production: https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc
 *
 * Test API key (public): abcde12345abcde12345
 *
 * Note: Production GUS returns MTOM (multipart MIME) responses which PHP
 * SoapClient cannot parse. All calls use raw cURL + XML to handle this.
 */
class GusApiService
{
    private string $apiKey;
    private string $apiUrl;
    private string $env;
    private ?string $sessionId = null;

    private const ENV_URLS = [
        'test'       => 'https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc',
        'production' => 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc',
    ];

    public function __construct()
    {
        $this->apiKey = Setting::get('gus_api_key', '');
        $this->env = Setting::get('gus_api_env', 'test');
        $this->apiUrl = self::ENV_URLS[$this->env] ?? self::ENV_URLS['test'];
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getEnv(): string { return $this->env; }
    public function getApiUrl(): string { return $this->apiUrl; }

    /**
     * Search company data by NIP.
     * Throws RuntimeException with descriptive message on configuration/connection errors.
     */
    public function findByNip(string $nip): ?array
    {
        $nip = preg_replace('/[^0-9]/', '', $nip);

        if (strlen($nip) !== 10) {
            throw new \RuntimeException('Nieprawidłowy NIP (wymagane 10 cyfr).');
        }

        if (!$this->isConfigured()) {
            throw new \RuntimeException('GUS API nie jest skonfigurowane. Ustaw klucz API w Admin → Ustawienia → GUS API.');
        }

        try {
            return $this->findByNipCurl($nip);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            error_log("GUS API error [{$this->env}]: " . $e->getMessage());
            throw new \RuntimeException('Błąd połączenia z GUS API: ' . $e->getMessage());
        }
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
        $addLog('INFO', 'Konfiguracja GUS API', [
            'api_key' => $this->apiKey ? (substr($this->apiKey, 0, 5) . '...' . substr($this->apiKey, -3)) : 'BRAK',
            'api_key_length' => strlen($this->apiKey),
            'environment' => $this->env,
            'api_url' => $this->apiUrl,
            'php_version' => PHP_VERSION,
            'curl_version' => curl_version()['version'] ?? '?',
            'test_nip' => $nip,
        ]);

        $steps[] = [
            'name' => 'Konfiguracja',
            'ok' => $this->isConfigured(),
            'details' => [
                'api_key' => $this->isConfigured() ? 'Ustawiony (' . strlen($this->apiKey) . ' znaków)' : 'BRAK — ustaw w Ustawienia → GUS API',
                'environment' => $this->env,
                'api_url' => $this->apiUrl,
            ],
        ];

        if (!$this->isConfigured()) {
            return ['steps' => $steps, 'log' => implode("\n", $log)];
        }

        // Step 2: Login
        $addLog('INFO', 'SOAP Zaloguj...');
        $loginBody = $this->buildSoapEnvelope('Zaloguj',
            '<ns:Zaloguj><ns:pKluczUzytkownika>' . htmlspecialchars($this->apiKey) . '</ns:pKluczUzytkownika></ns:Zaloguj>'
        );
        $addLog('DEBUG', 'Request XML', $loginBody);

        try {
            $loginResponse = $this->soapCurl($loginBody);
            $addLog('DEBUG', 'Response (raw)', $loginResponse);

            preg_match('/<ZalogujResult>(.*?)<\/ZalogujResult>/s', $loginResponse, $matches);
            $this->sessionId = $matches[1] ?? null;
            $addLog($this->sessionId ? 'INFO' : 'ERROR', 'Session ID: ' . ($this->sessionId ?: 'BRAK'));
        } catch (\Throwable $e) {
            $addLog('ERROR', 'Login error: ' . $e->getMessage());
        }

        $steps[] = [
            'name' => 'Logowanie (Zaloguj)',
            'ok' => !empty($this->sessionId),
            'details' => [
                'session_id' => $this->sessionId ? (substr($this->sessionId, 0, 10) . '...') : 'BRAK — sprawdź klucz API',
                'hint' => $this->env === 'test' ? 'Klucz testowy: abcde12345abcde12345' : '',
            ],
        ];

        if (!$this->sessionId) {
            return ['steps' => $steps, 'log' => implode("\n", $log)];
        }

        // Step 3: Search
        $addLog('INFO', "Wyszukiwanie NIP: {$nip}...");
        $searchBody = $this->buildSoapEnvelope('DaneSzukajPodmioty',
            '<ns:DaneSzukajPodmioty><ns:pParametryWyszukiwania><dat:Nip>' . htmlspecialchars($nip) . '</dat:Nip></ns:pParametryWyszukiwania></ns:DaneSzukajPodmioty>',
            'xmlns:dat="http://CIS/BIR/PUBL/2014/07/DataContract"'
        );
        $addLog('DEBUG', 'Request XML', $searchBody);

        $searchData = null;
        try {
            $searchResponse = $this->soapCurl($searchBody);
            $addLog('DEBUG', 'Response (raw)', $searchResponse);

            preg_match('/<DaneSzukajPodmiotyResult>(.*?)<\/DaneSzukajPodmiotyResult>/s', $searchResponse, $matches);
            $xmlData = html_entity_decode($matches[1] ?? '');
            $addLog('DEBUG', 'Decoded XML', $xmlData);

            if (!empty($xmlData)) {
                $searchData = $this->parseXmlResponse($xmlData);
                $addLog('INFO', 'Parsed data', $searchData);
            } else {
                $addLog('ERROR', 'Brak danych w odpowiedzi (pusty DaneSzukajPodmiotyResult)');
            }
        } catch (\Throwable $e) {
            $addLog('ERROR', 'Search error: ' . $e->getMessage());
        }

        // Logout
        try {
            $logoutBody = $this->buildSoapEnvelope('Wyloguj',
                '<ns:Wyloguj><ns:pIdentyfikatorSesji>' . htmlspecialchars($this->sessionId) . '</ns:pIdentyfikatorSesji></ns:Wyloguj>'
            );
            $this->soapCurl($logoutBody);
            $addLog('INFO', 'Wylogowano z GUS API');
        } catch (\Exception $e) {
            // ignore
        }

        $steps[] = [
            'name' => "Wyszukiwanie NIP: {$nip}",
            'ok' => !empty($searchData) && !empty($searchData['company_name']),
            'details' => $searchData ?: ['error' => 'Nie znaleziono podmiotu lub błąd parsowania'],
        ];

        return ['steps' => $steps, 'log' => implode("\n", $log)];
    }

    private function findByNipCurl(string $nip): ?array
    {
        // Login
        $loginBody = $this->buildSoapEnvelope('Zaloguj',
            '<ns:Zaloguj><ns:pKluczUzytkownika>' . htmlspecialchars($this->apiKey) . '</ns:pKluczUzytkownika></ns:Zaloguj>'
        );

        $response = $this->soapCurl($loginBody);
        preg_match('/<ZalogujResult>(.*?)<\/ZalogujResult>/s', $response, $matches);
        $this->sessionId = $matches[1] ?? null;

        if (!$this->sessionId) {
            throw new \RuntimeException('GUS API: Logowanie nie powiodło się. Sprawdź klucz API i środowisko (test/production).');
        }

        // Search
        $searchBody = $this->buildSoapEnvelope('DaneSzukajPodmioty',
            '<ns:DaneSzukajPodmioty><ns:pParametryWyszukiwania><dat:Nip>' . htmlspecialchars($nip) . '</dat:Nip></ns:pParametryWyszukiwania></ns:DaneSzukajPodmioty>',
            'xmlns:dat="http://CIS/BIR/PUBL/2014/07/DataContract"'
        );

        try {
            $response = $this->soapCurl($searchBody);
            preg_match('/<DaneSzukajPodmiotyResult>(.*?)<\/DaneSzukajPodmiotyResult>/s', $response, $matches);
            $xmlData = html_entity_decode($matches[1] ?? '');
        } finally {
            // Logout
            try {
                $logoutBody = $this->buildSoapEnvelope('Wyloguj',
                    '<ns:Wyloguj><ns:pIdentyfikatorSesji>' . htmlspecialchars($this->sessionId) . '</ns:pIdentyfikatorSesji></ns:Wyloguj>'
                );
                $this->soapCurl($logoutBody);
            } catch (\Exception $e) {
                // ignore
            }
        }

        if (empty($xmlData)) {
            return null;
        }

        return $this->parseXmlResponse($xmlData);
    }

    /**
     * Build SOAP 1.2 envelope with WS-Addressing headers.
     */
    private function buildSoapEnvelope(string $action, string $bodyContent, string $extraNs = ''): string
    {
        $actionUrl = "http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/{$action}";
        $to = htmlspecialchars($this->apiUrl);
        $extraNs = $extraNs ? ' ' . $extraNs : '';

        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ns="http://CIS/BIR/PUBL/2014/07"' . $extraNs . '>
    <soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing">
        <wsa:Action>' . $actionUrl . '</wsa:Action>
        <wsa:To>' . $to . '</wsa:To>
    </soap:Header>
    <soap:Body>' . $bodyContent . '</soap:Body>
</soap:Envelope>';
    }

    private function soapCurl(string $body): string
    {
        $headers = [
            'Content-Type: application/soap+xml; charset=utf-8',
        ];

        if ($this->sessionId) {
            $headers[] = 'sid: ' . $this->sessionId;
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("GUS API HTTP error: {$httpCode}");
        }

        // Handle MTOM multipart responses — extract the XML part
        if ($response && str_contains($response, '--uuid:')) {
            if (preg_match('/<s:Envelope.*?<\/s:Envelope>/s', $response, $m)) {
                return $m[0];
            }
        }

        return $response ?: '';
    }

    private function parseXmlResponse(string $xml): ?array
    {
        $xml = trim($xml);
        if (empty($xml)) return null;

        // Decode HTML entities if wrapped in CDATA/encoded response
        if (strpos($xml, '&lt;') !== false) {
            $xml = html_entity_decode($xml);
        }

        // Prevent XXE attacks
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader(true);
        }
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
        if (!$doc) {
            error_log("GUS API: Failed to parse XML response: " . substr($xml, 0, 500));
            return null;
        }

        $dane = $doc->dane ?? $doc;

        return [
            'nip'          => (string) ($dane->Nip ?? ''),
            'regon'        => (string) ($dane->Regon ?? ''),
            'company_name' => (string) ($dane->Nazwa ?? ''),
            'province'     => (string) ($dane->Wojewodztwo ?? ''),
            'city'         => (string) ($dane->Miejscowosc ?? ''),
            'street'       => (string) ($dane->Ulica ?? ''),
            'building_no'  => (string) ($dane->NrNieruchomosci ?? ''),
            'apartment_no' => (string) ($dane->NrLokalu ?? ''),
            'postal_code'  => (string) ($dane->KodPocztowy ?? ''),
            'type'         => (string) ($dane->Typ ?? ''),
        ];
    }

    /**
     * Format address from GUS data.
     */
    public static function formatAddress(array $data): string
    {
        $parts = [];
        $street = $data['street'] ?? '';
        if ($street) {
            $num = $data['building_no'] ?? '';
            if ($data['apartment_no'] ?? '') {
                $num .= '/' . $data['apartment_no'];
            }
            $parts[] = "ul. {$street} {$num}";
        }
        $postal = $data['postal_code'] ?? '';
        $city = $data['city'] ?? '';
        if ($postal || $city) {
            $parts[] = trim("{$postal} {$city}");
        }
        return implode(', ', $parts);
    }
}
