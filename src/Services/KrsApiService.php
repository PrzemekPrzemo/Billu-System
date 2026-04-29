<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

/**
 * KRS Open Data API client (public, no API key).
 *
 * Routes:
 *   GET /api/krs/OdpisAktualny/{krs}?rejestr={P|S}&format=json
 *   GET /api/krs/OdpisPelny/{krs}?rejestr={P|S}&format=json
 *
 * Cache TTL is long (30 days by default) — KRS state changes are
 * deliberate court actions, not frequent. The orchestrator still
 * appends a fresh note on every refresh, the cache only avoids
 * repeated round-trips for the same lookup within the window.
 *
 * HTTP layer is in httpGetJson() so tests can override via a subclass
 * without needing an external HTTP-client mock.
 */
class KrsApiService
{
    /** @var array<string,mixed> config/krs.php['krs'] */
    private array $config;

    public function __construct(?array $config = null)
    {
        $config ??= (require __DIR__ . '/../../config/krs.php')['krs'];
        $this->config = $config;
    }

    /**
     * Validate KRS number format (10 digits).
     */
    public static function isValidKrs(string $krs): bool
    {
        $krs = preg_replace('/[^0-9]/', '', $krs);
        return strlen($krs) === 10;
    }

    /**
     * Current excerpt — what the courts consider the present state.
     * Returns parsed JSON on success, null on 404 / network failure
     * (so the orchestrator can degrade gracefully).
     */
    public function fetchOdpisAktualny(string $krs, string $rejestr = 'P'): ?array
    {
        return $this->fetchExcerpt('OdpisAktualny', $krs, $rejestr);
    }

    /**
     * Full excerpt — including historical entries (changes of board,
     * shareholders, capital, etc.). Larger response, slower; used on
     * explicit request only.
     */
    public function fetchOdpisPelny(string $krs, string $rejestr = 'P'): ?array
    {
        return $this->fetchExcerpt('OdpisPelny', $krs, $rejestr);
    }

    private function fetchExcerpt(string $kind, string $krs, string $rejestr): ?array
    {
        $krs = preg_replace('/[^0-9]/', '', $krs);
        if (strlen($krs) !== 10) {
            return null;
        }
        $rejestr = strtoupper($rejestr);
        if (!in_array($rejestr, ['P', 'S'], true)) {
            $rejestr = 'P';
        }

        $cache = Cache::getInstance();
        $cacheKey = "krs:{$kind}:{$rejestr}:{$krs}";
        $hit = $cache->get($cacheKey);
        if ($hit !== null) {
            return $hit;
        }

        $url = rtrim($this->config['base_url'], '/')
             . "/{$kind}/{$krs}?rejestr={$rejestr}&format=json";

        $json = $this->httpGetJson($url);
        if ($json === null) {
            return null;
        }

        $cache->set($cacheKey, $json, (int) $this->config['cache_ttl_sec']);
        return $json;
    }

    /**
     * HTTP GET returning decoded JSON, or null on any failure.
     * Retries on 5xx with backoff from config; 4xx is final.
     * Protected so tests can subclass-override without touching cURL.
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

            // 4xx — final, don't retry.
            if ($http >= 400 && $http < 500) {
                return null;
            }

            // 5xx / connection error — backoff if attempts remain.
            if ($i + 1 < $attempts) {
                $sleep = $backoff[$i] ?? end($backoff) ?: 1;
                sleep((int) $sleep);
            }
        }
        return null;
    }

    /**
     * Tight cURL wrapper. Returns [body, http_code]; body is null on
     * connection failure.
     *
     * @return array{0: ?string, 1: int}
     */
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
            CURLOPT_USERAGENT      => 'BiLLU/1.0 (KrsApiService)',
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
