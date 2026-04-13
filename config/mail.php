<?php

// Load .env if available (safe fallback if Env class or .env file missing)
if (class_exists('App\\Core\\Env')) {
    \App\Core\Env::load();
}

$envGet = function(string $key, ?string $default = null): ?string {
    return $_ENV[$key] ?? (getenv($key) !== false ? (string)getenv($key) : $default);
};

return [
    'host'       => $envGet('MAIL_HOST', 'smtp.example.com'),
    'port'       => (int)($envGet('MAIL_PORT', '587')),
    'encryption' => $envGet('MAIL_ENCRYPTION', 'tls'),
    'username'   => $envGet('MAIL_USERNAME', ''),
    'password'   => $envGet('MAIL_PASSWORD', ''),
    'from_email' => $envGet('MAIL_FROM_EMAIL', 'noreply@example.com'),
    'from_name'  => $envGet('MAIL_FROM_NAME', 'BiLLU'),
];
