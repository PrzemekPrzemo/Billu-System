<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

/**
 * CRBR (Central Register of Beneficial Owners) API client.
 *
 * PII-heavy: responses include PESELs and other personal data of
 * beneficial owners. The controller layer MUST gate this to office_admin
 * only (not office_employee). The formatter masks PESEL in the rendered
 * HTML; the raw_json keeps the full value because subsequent legal
 * obligations (AML KYC) need it.
 *
 * Cache TTL is short (7 days) — beneficial-owner changes propagate
 * faster than KRS court entries.
 */
class CrbrApiService
{
    /** @var array<string,mixed> config/krs.php['crbr'] */
    private array $config;

    public function __construct(?array $config = null)
    {
        $config ??= (require __DIR__ . '/../../config/krs.php')['crbr'];
        $this->config = $config;
    }

    public function fetchByNip(string $nip): ?array
    {
        $nip = preg_replace('/[^0-9]/', '', $nip);
        if (strlen($nip) !== 10) {
            return null;
        }
        return $this->fetchByIdentifier('nip', $nip);
    }

    public function fetchByKrs(string $krs): ?array
    {
        $krs = preg_replace('/[^0-9]/', '', $krs);
        if (strlen($krs) !== 10) {
            return null;
        }
        return $this->fetchByIdentifier('krs', $krs);
    }

    private function fetchByIdentifier(string $kind, string $value): ?array
    {
        $cache = Cache::getInstance();
        $cacheKey = "crbr:{$kind}:{$value}";
        $hit = $cache->get($cacheKey);
        if ($hit !== null) {
            return $hit;
        }

        $url = rtrim($this->config['base_url'], '/')
             . "/beneficiaries?{$kind}={$value}";

        $json = $this->httpGetJson($url);
        if ($json === null) {
            return null;
        }

        $cache->set($cacheKey, $json, (int) $this->config['cache_ttl_sec']);
        return $json;
    }

    /**
     * HTTP GET returning decoded JSON, or null on any failure.
     * Protected so tests can subclass-override.
     */
    protected function httpGetJson(string $url): ?array
    {
        $attempts = max(1, (int) ($this->config['retry_attempts'] ?? 1) + 1);
        $backoff  = $this->config['retry_backoff'] ?? [];

        for ($i = 0; $i < $attempts; $i++) {
            [$body, $http] = $this->curlGet($url);

            if ($http >= 200 && $http < 300 && is_string($body) && $body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                return null;
            }
            if ($http >= 400 && $http < 500) {
                return null;
            }
            if ($i + 1 < $attempts) {
                $sleep = $backoff[$i] ?? end($backoff) ?: 2;
                sleep((int) $sleep);
            }
        }
        return null;
    }

    /** @return array{0: ?string, 1: int} */
    private function curlGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $this->config['timeout_sec'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'BiLLU/1.0 (CrbrApiService)',
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_errno($ch);
        curl_close($ch);

        if ($err !== 0) {
            return [null, 0];
        }
        return [is_string($body) ? $body : null, $http];
    }
}
