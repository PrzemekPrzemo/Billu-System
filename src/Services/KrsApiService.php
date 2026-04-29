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
 * Skeleton: methods return null until PR-E wires the HTTP layer. The
 * empty contract is enough for the orchestrator to compile and for
 * isolation tests to pin the public surface.
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
     * Current excerpt — implemented in PR-E.
     */
    public function fetchOdpisAktualny(string $krs, string $rejestr = 'P'): ?array
    {
        return null;
    }

    /**
     * Full excerpt — implemented in PR-E.
     */
    public function fetchOdpisPelny(string $krs, string $rejestr = 'P'): ?array
    {
        return null;
    }
}
