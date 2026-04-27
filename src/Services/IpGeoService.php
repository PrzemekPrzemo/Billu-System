<?php

namespace App\Services;

use App\Core\Cache;

/**
 * Resolves a public IPv4/IPv6 to country/region/city using the public
 * ip-api.com endpoint. Result is cached in Redis for 30 days (per IP)
 * via App\Core\Cache, so we do at most one HTTP roundtrip per address
 * per month. Private/loopback ranges are skipped — they return null
 * without touching the network.
 *
 * The service NEVER throws; on timeout / network failure / API error
 * it returns null and the caller falls back to "raw IP only" display.
 */
class IpGeoService
{
    private const ENDPOINT = 'http://ip-api.com/json/';
    private const CACHE_TTL = 30 * 24 * 3600;
    private const TIMEOUT_SECONDS = 2;

    /**
     * @return array{country:string,country_code:string,region:string,city:string}|null
     */
    public static function lookup(string $ip): ?array
    {
        $ip = trim($ip);
        if (!self::isPublic($ip)) {
            return null;
        }

        $cache = Cache::getInstance();
        $cacheKey = 'ipgeo:' . $ip;
        $cached = $cache->get($cacheKey);
        if (is_array($cached) && array_key_exists('country', $cached)) {
            return $cached;
        }

        $url = self::ENDPOINT . rawurlencode($ip)
             . '?fields=status,country,countryCode,regionName,city';

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
                'header'        => "Accept: application/json\r\nUser-Agent: BiLLU\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '') {
            return null;
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
            return null;
        }

        $result = [
            'country'      => (string) ($decoded['country']     ?? ''),
            'country_code' => (string) ($decoded['countryCode'] ?? ''),
            'region'       => (string) ($decoded['regionName']  ?? ''),
            'city'         => (string) ($decoded['city']        ?? ''),
        ];
        $cache->set($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /** True if $ip is a routable public address — skips loopback / RFC1918 / link-local / IPv6 ULA. */
    private static function isPublic(string $ip): bool
    {
        if ($ip === '') return false;
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
