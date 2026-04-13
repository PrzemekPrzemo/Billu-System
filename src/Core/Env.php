<?php

namespace App\Core;

/**
 * Simple .env file loader.
 * Reads KEY=VALUE pairs from .env file into $_ENV and getenv().
 */
class Env
{
    private static bool $loaded = false;

    /**
     * Load .env file from project root.
     */
    public static function load(string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $path = $path ?: dirname(__DIR__, 2) . '/.env';

        if (!file_exists($path)) {
            return; // .env is optional — fall back to config file defaults
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            // Parse KEY=VALUE
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove surrounding quotes
            if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
                $value = substr($value, 1, -1);
            } elseif (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
                $value = substr($value, 1, -1);
            }

            // Don't override existing environment variables
            if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? (getenv($key) !== false ? getenv($key) : $default);
    }

    /**
     * Get environment variable as boolean.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Get environment variable as integer.
     */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value !== null ? (int) $value : $default;
    }
}
