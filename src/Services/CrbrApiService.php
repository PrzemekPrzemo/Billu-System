<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

/**
 * CRBR (Central Register of Beneficial Owners) API client.
 *
 * PII-heavy: responses include PESELs and other personal data of
 * beneficial owners. Controller layer MUST gate this to office_admin
 * (not office_employee) and the formatter MUST mask PESEL in the
 * rendered HTML even though the raw_json keeps the full value for
 * subsequent queries.
 *
 * Skeleton: methods return null until PR-E wires HTTP.
 */
class CrbrApiService
{
    /** @var array<string,mixed> config/krs.php['crbr'] */
    private array $config;

    public function __construct(?array $config = null)
    {
        $config ??= (require __DIR__ . '/../../config/krs.php')['crbr'];
        $this->config = $config;
    }

    public function fetchByNip(string $nip): ?array
    {
        return null;
    }

    public function fetchByKrs(string $krs): ?array
    {
        return null;
    }
}
