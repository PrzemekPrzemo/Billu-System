<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Controllers\OfficeEusController;
use App\Services\DemoEusMockService;
use App\Services\EusApiService;
use App\Services\EusProfilZaufanyService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins the public surface + tenant gating of the office e-US UI.
 * Pure reflection / source-scan / mock invocation — no DB, no HTTP.
 */
final class EusOfficeUiIsolationTest extends TestCase
{
    // ─── OfficeEusController ───────────────────────────────

    public function testControllerEndpointsExist(): void
    {
        foreach (['index', 'configureForm', 'configureSave', 'testConnection'] as $m) {
            self::assertTrue(method_exists(OfficeEusController::class, $m),
                "OfficeEusController::{$m} required");
            $rm = new ReflectionMethod(OfficeEusController::class, $m);
            self::assertTrue($rm->isPublic());
            self::assertFalse($rm->isStatic());
        }
    }

    public function testControllerConstructorEnforcesAuthAndModuleGate(): void
    {
        // Both gates MUST be in the constructor — easier to maintain
        // than per-method, and a missing 'use' or typo can't bypass.
        $src = $this->methodBody(OfficeEusController::class, '__construct');
        self::assertStringContainsString(
            'Auth::requireOfficeOrEmployee()',
            $src,
            'Constructor MUST require office-or-employee auth'
        );
        self::assertStringContainsString(
            "ModuleAccess::requireModule('eus')",
            $src,
            "Constructor MUST gate on the 'eus' module — master admin can disable per office"
        );
    }

    public function testEveryEndpointGoesThroughTenantGate(): void
    {
        // Every method that takes a clientId MUST pass it through
        // requireClientForOffice — direct findById would skip the
        // office_id check + the office_employee assignment filter.
        foreach (['configureForm', 'configureSave', 'testConnection'] as $m) {
            $src = $this->methodBody(OfficeEusController::class, $m);
            self::assertStringContainsString(
                'requireClientForOffice', $src,
                "OfficeEusController::{$m} MUST go through requireClientForOffice"
            );
        }
    }

    public function testWriteEndpointsValidateCsrf(): void
    {
        // POST endpoints MUST validate the CSRF token. GET endpoints
        // (index, configureForm) are read-only.
        foreach (['configureSave', 'testConnection'] as $m) {
            $src = $this->methodBody(OfficeEusController::class, $m);
            self::assertStringContainsString(
                'validateCsrf', $src,
                "POST endpoint OfficeEusController::{$m} MUST call validateCsrf()"
            );
        }
    }

    public function testConfigureSaveDoesNotMassAssignPrivilegeFields(): void
    {
        // The configure form SHOULD NOT touch cert_*, auth_provider_*,
        // office_id, client_id, last_poll_at — those are set by
        // dedicated paths (cert upload, PZ callback, cron poller).
        $src = $this->methodBody(OfficeEusController::class, 'configureSave');
        foreach (['cert_encrypted', 'cert_passphrase_encrypted',
                  'auth_provider_token_encrypted', 'last_poll_at'] as $forbidden) {
            self::assertStringNotContainsString(
                "'{$forbidden}'", $src,
                "configureSave MUST NOT mass-assign '{$forbidden}' from POST"
            );
        }
    }

    public function testRoutesAreRegistered(): void
    {
        $routes = (string) @file_get_contents(__DIR__ . '/../../public/index.php');
        $expected = [
            "get ('/office/eus'",
            "get ('/office/eus/{clientId}/configure'",
            "post('/office/eus/{clientId}/configure'",
            "post('/office/eus/{clientId}/test-connection'",
        ];
        foreach ($expected as $needle) {
            self::assertStringContainsString($needle, $routes,
                "Route '{$needle}' MUST be registered in public/index.php");
        }
    }

    public function testNoClientFacingEusRoutes(): void
    {
        // Cert / API decisions live in the office tier ONLY. A client
        // panel /client/eus route would be a mass-assignment
        // surface area — guard against accidental future addition.
        $routes = (string) @file_get_contents(__DIR__ . '/../../public/index.php');
        self::assertStringNotContainsString('/client/eus',     $routes);
        self::assertStringNotContainsString('/employee/eus',   $routes);
    }

    // ─── DemoEusMockService ────────────────────────────────

    public function testMockHealthIsAlwaysGreen(): void
    {
        foreach (['B', 'C', 'b', 'c', 'X'] as $bramka) {
            $r = DemoEusMockService::health($bramka);
            self::assertTrue($r['ok']);
            self::assertSame(200, $r['http_status']);
        }
    }

    public function testMockKasLettersDeterministicByNipTail(): void
    {
        // tail 7 → 1 letter, no deadline
        $r = DemoEusMockService::pollKasLetters('1234567897');
        self::assertCount(1, $r);
        self::assertFalse($r[0]['requires_reply']);
        self::assertNull($r[0]['reply_deadline']);

        // tail 8 → 1 letter with deadline
        $r = DemoEusMockService::pollKasLetters('1234567898');
        self::assertCount(1, $r);
        self::assertTrue($r[0]['requires_reply']);
        self::assertNotEmpty($r[0]['reply_deadline']);

        // tail 9 → 1 letter urgent
        $r = DemoEusMockService::pollKasLetters('1234567899');
        self::assertCount(1, $r);
        self::assertTrue($r[0]['requires_reply']);

        // other tails → 0 letters
        foreach (['1234567890', '1234567891', '1234567892', '1234567893',
                  '1234567894', '1234567895', '1234567896'] as $nip) {
            self::assertSame([], DemoEusMockService::pollKasLetters($nip),
                "NIP {$nip} should yield no letters in mock");
        }
    }

    public function testMockSubmitJpkProducesReferenceNumber(): void
    {
        $r1 = DemoEusMockService::submitJpk('2026-04');
        $r2 = DemoEusMockService::submitJpk('2026-04');
        self::assertSame('submitted', $r1['status']);
        self::assertSame('submitted', $r2['status']);
        self::assertNotSame($r1['reference_no'], $r2['reference_no'],
            'Each mock submission MUST yield a unique reference');
    }

    // ─── EusApiService ─────────────────────────────────────

    public function testApiServiceUrlForRoutesByEnv(): void
    {
        $svc = new EusApiService();
        self::assertStringStartsWith('mock://', $svc->urlFor('B', 'mock'));
        self::assertStringStartsWith('mock://', $svc->urlFor('C', 'mock'));
        self::assertStringContainsString('test-eus.mf.gov.pl', $svc->urlFor('B', 'test'));
        self::assertStringContainsString('eus.mf.gov.pl',      $svc->urlFor('B', 'prod'));
    }

    public function testApiServiceMockHealthBypassesNetwork(): void
    {
        // Mock environment MUST NOT make any HTTP call — the ok flag
        // comes from DemoEusMockService directly. We verify by
        // checking the response instantly without any sandbox.
        $svc = new EusApiService();
        $b = $svc->healthCheckB('mock');
        $c = $svc->healthCheckC('mock');
        self::assertTrue($b['ok']);
        self::assertTrue($c['ok']);
        self::assertSame(200, $b['http_status']);
    }

    // ─── EusProfilZaufanyService ───────────────────────────

    public function testProfilZaufanyHidesUiWhenCredentialsMissing(): void
    {
        // With empty config the office UI must hide the PZ button.
        $svc = new EusProfilZaufanyService(['profil_zaufany' => []]);
        self::assertFalse($svc->isAvailable());

        $svc = new EusProfilZaufanyService(['profil_zaufany' => [
            'client_id' => 'X', 'client_secret' => 'Y', 'redirect_uri' => 'https://x',
            'authorize_url' => 'https://pz/auth', 'token_url' => 'https://pz/token',
        ]]);
        self::assertTrue($svc->isAvailable());
    }

    public function testProfilZaufanyMockExchangeReturnsArtifact(): void
    {
        $svc = new EusProfilZaufanyService(['profil_zaufany' => []]);
        $r = $svc->exchangeCodeForArtifact('any-code', 'mock');
        self::assertTrue($r['ok']);
        self::assertNotEmpty($r['artifact']);
        self::assertNull($r['error']);
    }

    public function testProfilZaufanyProductionExchangeNotYetImplemented(): void
    {
        // Real-environment exchange is an explicit TODO until we have
        // MF docs + redirect URI approval. Failing closed is correct.
        $svc = new EusProfilZaufanyService(['profil_zaufany' => []]);
        $r = $svc->exchangeCodeForArtifact('any-code', 'prod');
        self::assertFalse($r['ok']);
        self::assertNull($r['artifact']);
        self::assertNotNull($r['error']);
    }

    // ─── helpers ──────────────────────────────────────────

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
