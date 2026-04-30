<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Controllers\AdminEusController;
use App\Services\RodoDeleteService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Hardening invariants for PR-5 — master dashboard, RODO guard,
 * retention purge. Pure reflection / source-scan — no DB, no HTTP.
 */
final class EusHardeningIsolationTest extends TestCase
{
    // ─── AdminEusController ───────────────────────────────

    public function testAdminControllerExistsAndAdminGated(): void
    {
        self::assertTrue(method_exists(AdminEusController::class, 'dashboard'));
        $rm = new ReflectionMethod(AdminEusController::class, '__construct');
        $src = $this->methodBody(AdminEusController::class, '__construct');
        self::assertStringContainsString('Auth::requireAdmin()', $src,
            'AdminEusController constructor MUST gate on Auth::requireAdmin()');
    }

    public function testAdminControllerHasNoMutationEndpoints(): void
    {
        // Master dashboard is read-only — any mutating method (delete,
        // purge, save) would break the contract. Pin at reflection.
        $rc = new \ReflectionClass(AdminEusController::class);
        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__construct') continue;
            if ($method->isStatic()) continue;
            self::assertSame('dashboard', $method->getName(),
                "AdminEusController must expose ONLY 'dashboard' (read-only). Found: {$method->getName()}");
        }
    }

    public function testAdminRouteRegistered(): void
    {
        $routes = (string) @file_get_contents(__DIR__ . '/../../public/index.php');
        self::assertStringContainsString(
            "'/admin/eus'",
            $routes,
            'Master dashboard route /admin/eus MUST be registered'
        );
    }

    // ─── RodoDeleteService — KAS retention guard ─────────

    public function testRodoDeleteServiceRefusesActiveKasRetention(): void
    {
        $src = $this->methodBody(RodoDeleteService::class, 'deleteClientData');
        self::assertStringContainsString(
            'EusDocument::hasActiveRetention',
            $src,
            'RodoDeleteService MUST check EusDocument::hasActiveRetention before deletion'
        );
        self::assertStringContainsString("'blocked_by' => 'eus_kas_retention'", $src,
            'Refusal MUST include blocked_by marker so callers can discriminate this case');
    }

    public function testRodoCheckHappensBeforeAnyDestruction(): void
    {
        // The hasActiveRetention check must be BEFORE the try/catch
        // block that contains delete operations — otherwise a
        // partial deletion could happen before the refusal triggers.
        $src = $this->methodBody(RodoDeleteService::class, 'deleteClientData');
        $hasActiveOffset = strpos($src, 'hasActiveRetention');
        $tryOffset       = strpos($src, 'try {');

        self::assertNotFalse($hasActiveOffset);
        self::assertNotFalse($tryOffset);
        self::assertLessThan($tryOffset, $hasActiveOffset,
            'KAS retention check MUST run BEFORE the destructive try block');
    }

    // ─── Retention purge script ──────────────────────────

    public function testRetentionPurgeIsCliOnly(): void
    {
        $src = (string) @file_get_contents(
            __DIR__ . '/../../scripts/eus_retention_purge.php'
        );
        self::assertNotEmpty($src);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $src);
        self::assertStringContainsString('--dry-run', $src,
            'Purge script MUST support --dry-run for safe operator preview');
    }

    public function testRetentionPurgeOnlyTouchesExpiredRows(): void
    {
        $src = (string) @file_get_contents(
            __DIR__ . '/../../scripts/eus_retention_purge.php'
        );
        // Defense in depth: source-level pin that the WHERE clause
        // includes both retain_until < today AND purged_at IS NULL.
        self::assertStringContainsString('retain_until IS NOT NULL', $src);
        self::assertStringContainsString('retain_until < CURDATE()', $src);
        self::assertStringContainsString('purged_at IS NULL', $src);
        // LIMIT prevents a runaway purge of millions of rows.
        self::assertStringContainsString('LIMIT 1000', $src);
    }

    // ─── CronService::eusHealthMetrics ───────────────────

    public function testEusHealthMetricsExists(): void
    {
        self::assertTrue(method_exists(\App\Services\CronService::class, 'eusHealthMetrics'));
    }

    public function testEusHealthMetricsIsIdempotent(): void
    {
        // Source-level pin: ON DUPLICATE KEY UPDATE is the idempotency
        // mechanism. Multiple cron ticks on the same day MUST overwrite,
        // never insert duplicates.
        $src = $this->methodBody(\App\Services\CronService::class, 'eusHealthMetrics');
        self::assertStringContainsString(
            'ON DUPLICATE KEY UPDATE',
            $src,
            'eusHealthMetrics MUST use ON DUPLICATE KEY UPDATE for idempotent daily upsert'
        );
    }

    // ─── Migration v51 ────────────────────────────────────

    public function testMigrationV51CreatesMetricsTable(): void
    {
        $sql = (string) @file_get_contents(
            __DIR__ . '/../../sql/migration_v51.0_eus_hardening.sql'
        );
        self::assertNotEmpty($sql);
        self::assertStringContainsString('eus_metrics_daily', $sql);
        self::assertStringContainsString('captured_date', $sql);
        self::assertStringContainsString('UNIQUE KEY ux_metrics_date', $sql,
            'UNIQUE on captured_date is required for ON DUPLICATE KEY UPDATE upsert');
        self::assertStringContainsString('IF NOT EXISTS', $sql);
        self::assertStringContainsString('schema_migrations', $sql);
    }

    // ─── Sidebar registration ────────────────────────────

    public function testAdminSidebarLinkRegistered(): void
    {
        $layout = (string) @file_get_contents(__DIR__ . '/../../templates/layout.php');
        self::assertStringContainsString('/admin/eus', $layout,
            'Admin sidebar MUST link to /admin/eus master dashboard');
    }

    // ─── helpers ─────────────────────────────────────────

    private function methodBody(string $class, string $method): string
    {
        $rm   = new ReflectionMethod($class, $method);
        $file = file($rm->getFileName());
        if ($file === false) {
            self::fail("Cannot read source for {$class}::{$method}");
        }
        $start = $rm->getStartLine() - 1;
        $end   = $rm->getEndLine();
        return implode('', array_slice($file, $start, $end - $start));
    }
}
