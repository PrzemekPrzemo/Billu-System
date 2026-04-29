<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Helper do generowania URL-i assetów z cache-bustingiem.
 *
 * Manifest budowany przez scripts/build-assets.sh ma format:
 *   {"css/style.css": "css/style.min.css?v=abc123", ...}
 *
 * Gdy manifest nie istnieje (dev / brak buildu), zwracamy oryginalną ścieżkę
 * — strona dalej działa, po prostu bez minifikacji i cache-bustingu.
 */
final class Asset
{
    /** @var array<string,string>|null */
    private static ?array $manifest = null;

    public static function url(string $logical): string
    {
        if (self::$manifest === null) {
            self::$manifest = self::loadManifest();
        }
        $resolved = self::$manifest[$logical] ?? $logical;
        return '/assets/' . ltrim($resolved, '/');
    }

    /** @return array<string,string> */
    private static function loadManifest(): array
    {
        $path = dirname(__DIR__, 2) . '/public/assets/manifest.json';
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
