<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Controllers\OfficeEusController;
use App\Services\EusApiService;
use App\Services\EusSubmissionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins the public surface + safety invariants of Bramka B
 * (JPK_V7M submission). Pure reflection / source-scan / mock
 * invocation — no DB, no HTTP.
 */
final class EusBramkaBIsolationTest extends TestCase
{
    // ─── Submit endpoint wiring ───────────────────────────

    public function testSubmitJpkV7mEndpointExists(): void
    {
        self::assertTrue(method_exists(OfficeEusController::class, 'submitJpkV7m'));
        $rm = new ReflectionMethod(OfficeEusController::class, 'submitJpkV7m');
        self::assertTrue($rm->isPublic());
        self::assertFalse($rm->isStatic());
    }

    public function testSubmitJpkV7mGatesCsrfAndTenant(): void
    {
        $src = $this->methodBody(OfficeEusController::class, 'submitJpkV7m');
        self::assertStringContainsString('validateCsrf', $src,
            'submitJpkV7m MUST validate CSRF — POST endpoint');
        self::assertStringContainsString('requireClientForOffice', $src,
            'submitJpkV7m MUST go through requireClientForOffice tenant gate');
    }

    public function testSubmitJpkV7mValidatesPeriodFormat(): void
    {
        $src = $this->methodBody(OfficeEusController::class, 'submitJpkV7m');
        self::assertStringContainsString("preg_match('/^\\d{4}-\\d{2}\$/", $src,
            'Period MUST be validated as YYYY-MM before queueing');
    }

    public function testSubmitRouteRegistered(): void
    {
        $routes = (string) @file_get_contents(__DIR__ . '/../../public/index.php');
        self::assertStringContainsString(
            "post('/office/eus/{clientId}/submit-jpk-v7m'",
            $routes
        );
    }

    // ─── EusApiService — fails-closed for non-mock ────────

    public function testApiSubmitBFailsClosedForNonMock(): void
    {
        $svc = new EusApiService();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Real e-US Bramka B submission not yet implemented');
        $svc->submitB('test', '2026-04', '/tmp/dummy.xml');
    }

    public function testApiGetStatusBFailsClosedForNonMock(): void
    {
        $svc = new EusApiService();
        $this->expectException(\RuntimeException::class);
        $svc->getStatusB('prod', 'REF-123');
    }

    public function testApiSubmitBSucceedsInMock(): void
    {
        $svc = new EusApiService();
        $r = $svc->submitB('mock', '2026-04', '/tmp/dummy.xml');
        self::assertNotEmpty($r['reference_no']);
        self::assertNotEmpty($r['received_at']);
        self::assertStringStartsWith('MOCK-V7M-', $r['reference_no']);
    }

    public function testApiGetStatusBAdvancesInMock(): void
    {
        $svc = new EusApiService();
        // submittedAt = now → still in 'submitted' band
        $r1 = $svc->getStatusB('mock', 'REF-NEW', new \DateTimeImmutable('now'));
        self::assertSame('submitted', $r1['status']);

        // submittedAt = 2 minutes ago → past 90s threshold → zaakceptowany
        $r2 = $svc->getStatusB('mock', 'REF-OLD', new \DateTimeImmutable('-2 minutes'));
        self::assertSame('zaakceptowany', $r2['status']);
        self::assertNotEmpty($r2['upo']);
    }

    // ─── EusSubmissionService surface + invariants ────────

    public function testSubmissionServiceHasFullLifecycle(): void
    {
        foreach (['queueJpkV7M', 'submitNow', 'pollOnce'] as $m) {
            self::assertTrue(method_exists(EusSubmissionService::class, $m),
                "EusSubmissionService::{$m} required for full lifecycle");
        }
    }

    public function testBackoffScheduleIsConservative(): void
    {
        $rc = new ReflectionClass(EusSubmissionService::class);
        $schedule = $rc->getConstant('BACKOFF_SCHEDULE');
        self::assertIsArray($schedule);

        // 6 backoff steps before permanent failure — pinned so a
        // future "more retries" change requires deliberate migration.
        self::assertCount(6, $schedule);

        // Schedule must be monotonically increasing — never re-poll
        // faster than the last attempt.
        $prev = 0;
        foreach ($schedule as $sec) {
            self::assertGreaterThan($prev, $sec,
                'Backoff schedule must be strictly increasing');
            $prev = $sec;
        }

        // Last delay >= 24h — gives ops time to react before total fail
        self::assertGreaterThanOrEqual(24 * 3600, end($schedule));
    }

    public function testSurfacingHelpersExistAndArePrivate(): void
    {
        // Auto-create logic must NOT be callable from a controller —
        // only the orchestrator should drive surfacing transitions.
        $rc = new ReflectionClass(EusSubmissionService::class);
        foreach (['surfaceSubmittedToClient', 'surfaceAcceptedToClient',
                  'surfaceRejectedToOffice', 'surfaceErrorToOffice'] as $m) {
            self::assertTrue($rc->hasMethod($m), "Missing surfacing helper {$m}");
            $rm = $rc->getMethod($m);
            self::assertTrue($rm->isPrivate(),
                "Surfacing helper {$m} MUST be private — orchestrator-only");
        }
    }

    public function testSubmissionServicePreFlightLogicInSource(): void
    {
        // Source-level pin: queueJpkV7M MUST refuse submission when
        // bramka_b is off, UPL-1 is not active, or UPL-1 expired.
        $src = $this->methodBody(EusSubmissionService::class, 'queueJpkV7M');
        self::assertStringContainsString("bramka_b_enabled", $src,
            'queueJpkV7M must check bramka_b_enabled');
        self::assertStringContainsString("upl1_status", $src,
            'queueJpkV7M must check UPL-1 status');
        self::assertStringContainsString("upl1_valid_to", $src,
            'queueJpkV7M must check UPL-1 expiry');
    }

    public function testCertExpiryCheckSkippedForMock(): void
    {
        // Source-level pin: cert expiry check is wrapped in
        // 'environment' !== 'mock' — otherwise dev iteration without
        // a real cert would always fail.
        $src = $this->methodBody(EusSubmissionService::class, 'queueJpkV7M');
        self::assertStringContainsString("!== 'mock'", $src,
            'Cert expiry check MUST be skipped for mock environment');
    }

    // ─── Bg scripts ───────────────────────────────────────

    public function testBgScriptsAreCliOnly(): void
    {
        foreach (['eus_submit_b_bg.php', 'eus_poll_b_bg.php'] as $name) {
            $path = __DIR__ . '/../../scripts/' . $name;
            self::assertFileExists($path, "{$name} must exist for cron drainer");

            $src = (string) @file_get_contents($path);
            self::assertStringContainsString("PHP_SAPI !== 'cli'", $src,
                "{$name} MUST refuse to run via web (PHP_SAPI guard)");
            self::assertStringContainsString("ctype_digit", $src,
                "{$name} MUST validate jobId as numeric — no shell injection");
        }
    }

    // ─── Migration v49 ────────────────────────────────────

    public function testMigrationV49AddsJpkEusColumns(): void
    {
        $sql = (string) @file_get_contents(__DIR__ . '/../../sql/migration_v49.0_eus_bramka_b.sql');
        foreach (['jpk_eus_status', 'jpk_eus_reference_no', 'jpk_eus_upo_path',
                  'jpk_eus_submitted_at', 'jpk_eus_finalized_at'] as $col) {
            self::assertStringContainsString($col, $sql,
                "Migration v49 MUST add column {$col}");
        }
        self::assertStringContainsString('IF NOT EXISTS', $sql,
            'Migration v49 MUST use IF NOT EXISTS for idempotency');
        self::assertStringContainsString('schema_migrations', $sql,
            'Migration v49 MUST self-register');
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
