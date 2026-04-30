<?php

declare(strict_types=1);

namespace App\Services;

/**
 * e-Urząd Skarbowy HTTP client. Skeleton in PR-2 — only health-check
 * endpoints are wired so the office "Test connection" button works
 * end-to-end against the chosen environment (mock | test | prod).
 *
 * PR-3 fills in submitB() / getStatusB() / downloadUpoB() for JPK_V7M.
 * PR-4 fills in pollC() / fetchLetter() / submitReplyC() for KAS
 * correspondence.
 *
 * The XAdES-BES signing path delegates to KsefCertificateService::signXml()
 * directly — no need to clone 300 LOC of well-tested signing code.
 *
 * Environment routing:
 *   - 'mock' → DemoEusMockService is used instead of HTTP calls
 *   - 'test' → MF test sandbox URLs (config/eus.php)
 *   - 'prod' → MF production URLs
 */
class EusApiService
{
    private array $config;
    private EusLogger $logger;

    public function __construct(?array $config = null, ?EusLogger $logger = null)
    {
        $this->config = $config ?? self::loadConfig();
        $this->logger = $logger ?? new EusLogger();
    }

    /**
     * Resolve URL for the given bramka + environment.
     */
    public function urlFor(string $bramka, string $environment): string
    {
        $env = in_array($environment, ['mock', 'test', 'prod'], true) ? $environment : 'mock';
        $b   = strtoupper($bramka) === 'C' ? 'C' : 'B';
        $key = $env . '_' . $b;
        return (string) ($this->config['urls'][$key] ?? '');
    }

    /**
     * Cheap GET to verify the network path + cert is operational.
     * In 'mock' environment this is satisfied without a network call.
     *
     * @return array{ok:bool, http_status:int, message:string, duration_ms:int}
     */
    public function healthCheckB(string $environment = 'mock'): array
    {
        if ($environment === 'mock') {
            return DemoEusMockService::health('B');
        }
        return $this->doHealth($this->urlFor('B', $environment) . '/health');
    }

    public function healthCheckC(string $environment = 'mock'): array
    {
        if ($environment === 'mock') {
            return DemoEusMockService::health('C');
        }
        return $this->doHealth($this->urlFor('C', $environment) . '/health');
    }

    /**
     * Internal: actual cURL hit. Logged through EusLogger so the full
     * roundtrip is captured for support purposes.
     *
     * @return array{ok:bool, http_status:int, message:string, duration_ms:int}
     */
    private function doHealth(string $url): array
    {
        if ($url === '' || strpos($url, 'http') !== 0) {
            return ['ok' => false, 'http_status' => 0, 'message' => 'Brak poprawnego URL dla wybranego środowiska.', 'duration_ms' => 0];
        }

        $start = microtime(true);
        $this->logger->logRequest('GET', $url);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: BiLLU/1.0 (eus health)'],
        ]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);

        $duration = (int) round((microtime(true) - $start) * 1000);
        $this->logger->logResponse('GET', $url, $code, $body, $duration / 1000);

        if ($err !== 0) {
            return [
                'ok'          => false,
                'http_status' => 0,
                'message'     => 'Błąd połączenia: ' . $errMsg,
                'duration_ms' => $duration,
            ];
        }
        $ok = $code >= 200 && $code < 300;
        return [
            'ok'          => $ok,
            'http_status' => $code,
            'message'     => $ok ? 'Połączenie OK' : "Serwer e-US zwrócił HTTP {$code}",
            'duration_ms' => $duration,
        ];
    }

    /** Loads URL list + timeouts from config/eus.php with sane defaults. */
    private static function loadConfig(): array
    {
        $path = __DIR__ . '/../../config/eus.php';
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                return $loaded;
            }
        }
        return [
            'urls' => [
                'mock_B' => 'mock://eus/B',
                'mock_C' => 'mock://eus/C',
                'test_B' => 'https://test-eus.mf.gov.pl/api/b',
                'test_C' => 'https://test-eus.mf.gov.pl/api/c',
                'prod_B' => 'https://eus.mf.gov.pl/api/b',
                'prod_C' => 'https://eus.mf.gov.pl/api/c',
            ],
            'timeout_sec'    => 30,
            'retry_attempts' => 2,
        ];
    }
}
