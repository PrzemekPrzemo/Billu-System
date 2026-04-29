<?php

declare(strict_types=1);

$envGet = static function (string $key, ?string $default = null): ?string {
    if (class_exists(\App\Core\Env::class) && method_exists(\App\Core\Env::class, 'get')) {
        return \App\Core\Env::get($key, $default);
    }
    $v = getenv($key);
    return $v !== false ? $v : $default;
};

return [
    // KRS Open Data API — public, no API key required.
    // Docs: https://api-krs.ms.gov.pl/api/Krs (rejestr P=przedsiebiorcy, S=stowarzyszenia).
    'krs' => [
        'base_url'      => $envGet('KRS_API_BASE_URL', 'https://api-krs.ms.gov.pl/api/krs'),
        'timeout_sec'   => 10,
        'cache_ttl_sec' => 2592000, // 30 days — KRS state is stable enough.
        'retry_attempts'=> 2,
        'retry_backoff' => [1, 3], // seconds between retries on 5xx
    ],

    // CRBR (Centralny Rejestr Beneficjentów Rzeczywistych) — public, no key.
    // Docs: https://crbr.podatki.gov.pl/adcrbr/.
    // PII-heavy: response includes PESELs of beneficial owners, so cache TTL
    // is short and access is gated to office_admin only at controller level.
    'crbr' => [
        'base_url'      => $envGet('CRBR_API_BASE_URL', 'https://crbr.podatki.gov.pl/adcrbr/api'),
        'timeout_sec'   => 15,
        'cache_ttl_sec' => 604800, // 7 days
        'retry_attempts'=> 1,
        'retry_backoff' => [2],
    ],
];
