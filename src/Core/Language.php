<?php

namespace App\Core;

class Language
{
    private static array $translations = [];
    private static string $locale = 'pl';

    public static function setLocale(string $locale): void
    {
        if (in_array($locale, ['pl', 'en'])) {
            self::$locale = $locale;
        }
        self::load();
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function load(): void
    {
        $file = __DIR__ . '/../../lang/' . self::$locale . '.php';
        if (file_exists($file)) {
            self::$translations = require $file;
        }
        $hrFile = __DIR__ . '/../../lang/' . self::$locale . '_hr.php';
        if (file_exists($hrFile)) {
            self::$translations = array_merge(self::$translations, require $hrFile);
        }
    }

    public static function get(string $key, array $params = []): string
    {
        $text = self::$translations[$key] ?? $key;

        foreach ($params as $param => $value) {
            $text = str_replace(':' . $param, $value, $text);
        }

        return $text;
    }
}

function __($key, array $params = []): string
{
    return Language::get($key, $params);
}
