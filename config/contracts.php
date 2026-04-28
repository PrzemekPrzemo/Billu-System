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
    // Path to the pdftk binary on the server (apt install pdftk-java).
    'pdftk_path' => $envGet('PDFTK_PATH', '/usr/bin/pdftk'),

    // SIGNIUS Professional integration. API key + base URL come from .env.
    'signius' => [
        'base_url'        => $envGet('SIGNIUS_BASE_URL', 'https://api.signius.eu/v1'),
        'api_key'         => $envGet('SIGNIUS_API_KEY', ''),
        'webhook_secret'  => $envGet('SIGNIUS_WEBHOOK_SECRET', ''),
        // Default flow when a template doesn't override it: sequential, expire after 30 days.
        'default_signing_flow' => 'sequential',
        'package_ttl_days'     => 30,
        // HTTP timeouts (seconds) — keep tight, every controller-side call holds a request thread.
        'http_timeout'         => 15,
        'http_connect_timeout' => 5,
    ],

    // Template upload limits.
    'template_max_size_mb' => 10,
    'template_storage_dir' => 'storage/contract_templates',

    // Public form link lifetime (default = 14 days).
    'form_token_ttl_hours' => 24 * 14,
];
