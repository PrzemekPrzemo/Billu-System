<?php

if (class_exists('App\\Core\\Env')) {
    \App\Core\Env::load();
}

$envGet = function(string $key, ?string $default = null): ?string {
    return $_ENV[$key] ?? (getenv($key) !== false ? (string)getenv($key) : $default);
};
$envInt = function(string $key, int $default = 0) use ($envGet): int {
    $v = $envGet($key);
    return $v !== null && $v !== '' ? (int)$v : $default;
};

return [
    'driver'   => $envGet('CACHE_DRIVER', 'redis'),
    'prefix'   => $envGet('REDIS_PREFIX', 'billu:'),

    'redis' => [
        'host'     => $envGet('REDIS_HOST', '127.0.0.1'),
        'port'     => $envInt('REDIS_PORT', 6379),
        'password' => $envGet('REDIS_PASSWORD', '') ?: null,
        'database' => $envInt('REDIS_DB', 0),
        'timeout'  => 1.5,
    ],

    // TTL per logical bucket (seconds). Used by Cache::ttl('gus') etc.
    'ttl' => [
        'gus'        => 30 * 24 * 3600, // 30d
        'ceidg'      => 30 * 24 * 3600,
        'vies'       => 7  * 24 * 3600, // 7d
        'whitelist'  => 24 * 3600,      // 1d (banking data freshness)
        'nbp'        => 12 * 3600,      // 12h
        'report'     => 3600,           // 1h
        'aggregate'  => 1800,           // 30m
        'jpk'        => 3600,
    ],
];
