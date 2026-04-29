<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Controllers\OfficeController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins the public surface of the new register-notes endpoints.
 *
 * Pure reflection / file-content scan — does not boot the controller
 * (which would require DB + session). For end-to-end behavior, the
 * orchestrator's logic is already covered by CompanyLookupServiceTest;
 * this file guards the wiring (method names, route file, gating).
 */
final class ExternalNoteEndpointIsolationTest extends TestCase
{
    public function testOfficeRegisterEndpointsExist(): void
    {
        $required = [
            'clientRegisters',
            'clientRegistersRefresh',
            'clientCrbrRefresh',
            'contractorRegisters',
            'contractorRegistersRefresh',
        ];
        foreach ($required as $method) {
            self::assertTrue(
                method_exists(OfficeController::class, $method),
                "OfficeController::{$method} must exist"
            );
            $m = new ReflectionMethod(OfficeController::class, $method);
            self::assertTrue($m->isPublic(), "{$method} must be public (router target)");
            self::assertFalse($m->isStatic(), "{$method} must be instance (uses session)");
        }
    }

    public function testCrbrRefreshGatedToOfficeAdmin(): void
    {
        // Defence-in-depth: the controller body must explicitly check
        // Auth::isOffice() and reject Auth::isEmployee() — sidebar
        // hiding alone is NOT a security boundary.
        $body = $this->methodBody(OfficeController::class, 'clientCrbrRefresh');
        self::assertStringContainsString('Auth::isOffice()', $body,
            'clientCrbrRefresh MUST gate on Auth::isOffice() — CRBR includes PESEL of beneficial owners');
        self::assertStringNotContainsString('isEmployee', $body); // i.e. does not allow employees
    }

    public function testEveryRegisterEndpointGoesThroughTenantGate(): void
    {
        // requireClientForOffice() is the canonical tenant gate. Every
        // new endpoint MUST call it before reading or writing.
        foreach ([
            'clientRegisters',
            'clientRegistersRefresh',
            'clientCrbrRefresh',
            'contractorRegisters',
            'contractorRegistersRefresh',
        ] as $method) {
            $body = $this->methodBody(OfficeController::class, $method);
            self::assertStringContainsString(
                'requireClientForOffice', $body,
                "OfficeController::{$method} must invoke requireClientForOffice() — no direct findById"
            );
        }
    }

    public function testWriteEndpointsValidateCsrf(): void
    {
        // POST endpoints MUST validate the CSRF token. GET endpoints
        // (clientRegisters, contractorRegisters) are read-only.
        foreach ([
            'clientRegistersRefresh',
            'clientCrbrRefresh',
            'contractorRegistersRefresh',
        ] as $method) {
            $body = $this->methodBody(OfficeController::class, $method);
            self::assertStringContainsString('validateCsrf', $body,
                "POST endpoint OfficeController::{$method} must call validateCsrf()");
        }
    }

    public function testRoutesAreRegistered(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../public/index.php');
        self::assertNotFalse($routes);

        $expected = [
            "get('/office/clients/{id}/registers'",
            "post('/office/clients/{id}/registers/refresh'",
            "post('/office/clients/{id}/crbr/refresh'",
            "get('/office/clients/{id}/contractors/{contractorId}/registers'",
            "post('/office/clients/{id}/contractors/{contractorId}/registers/refresh'",
        ];
        foreach ($expected as $needle) {
            self::assertStringContainsString($needle, $routes,
                "Route '{$needle}' must be registered in public/index.php");
        }
    }

    public function testNoClientFacingRegisterEndpoints(): void
    {
        // Notes are office-only — clients and client-employees must NEVER
        // see register notes. Guard against an accidental future route.
        $routes = file_get_contents(__DIR__ . '/../../public/index.php');
        self::assertNotFalse($routes);
        self::assertStringNotContainsString('/client/clients/registers', $routes);
        self::assertStringNotContainsString('/employee/registers',       $routes);
        self::assertStringNotContainsString('/client/registers',         $routes);
    }

    private function methodBody(string $class, string $method): string
    {
        $rm = new ReflectionMethod($class, $method);
        $file = file($rm->getFileName());
        if ($file === false) {
            self::fail("Cannot read source for {$class}");
        }
        $start = $rm->getStartLine() - 1;
        $end   = $rm->getEndLine();
        return implode('', array_slice($file, $start, $end - $start));
    }
}
