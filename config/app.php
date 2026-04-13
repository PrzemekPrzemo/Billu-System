<?php

// Load .env if available (safe fallback if Env class or .env file missing)
if (class_exists('App\\Core\\Env')) {
    \App\Core\Env::load();
}

$envGet = function(string $key, ?string $default = null): ?string {
    return $_ENV[$key] ?? (getenv($key) !== false ? (string)getenv($key) : $default);
};
$envBool = function(string $key, bool $default = false) use ($envGet): bool {
    $v = $envGet($key);
    return $v !== null ? in_array(strtolower($v), ['true','1','yes','on'], true) : $default;
};
$envInt = function(string $key, int $default = 0) use ($envGet): int {
    $v = $envGet($key);
    return $v !== null ? (int)$v : $default;
};

$allowedOrigins = $envGet('API_ALLOWED_ORIGINS', '');
if ($allowedOrigins === '*') {
    $origins = ['*'];
} else {
    $origins = array_filter(array_map('trim', explode(',', $allowedOrigins)));
}

return [
    'name'       => $envGet('APP_NAME', 'BiLLU'),
    'url'        => $envGet('APP_URL', 'http://localhost'),
    'debug'      => $envBool('APP_DEBUG', false),
    'timezone'   => $envGet('APP_TIMEZONE', 'Europe/Warsaw'),
    'locale'     => $envGet('APP_LOCALE', 'pl'),
    'storage'    => __DIR__ . '/../storage',
    'secret_key' => $envGet('APP_SECRET_KEY') ?: bin2hex(random_bytes(32)),

    // REST API settings
    'api_allowed_origins' => !empty($origins) ? $origins : ['https://localhost'],
    'api_cors_max_age'    => $envInt('API_CORS_MAX_AGE', 86400),
];
