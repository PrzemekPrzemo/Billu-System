<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Controllers\OfficeController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins the structural invariants for office-tenant isolation across HR
 * endpoints. The actual cross-office redirect behaviour is integration-
 * tested elsewhere; here we verify the helpers exist with the right
 * signatures so accidental refactors are caught early.
 */
final class OfficeIsolationHelpersTest extends TestCase
{
    public function testRequireClientForOfficeHelperExists(): void
    {
        self::assertTrue(method_exists(OfficeController::class, 'requireClientForOffice'));
        $m = new ReflectionMethod(OfficeController::class, 'requireClientForOffice');
        self::assertTrue($m->isPrivate(), 'requireClientForOffice must be private');
        // ($clientId, $redirectUrl = '/office/hr') — 1 required + 1 optional.
        self::assertSame(1, $m->getNumberOfRequiredParameters());
        self::assertSame(2, $m->getNumberOfParameters());
    }

    public function testRequireRecordForOfficeHelperExists(): void
    {
        self::assertTrue(method_exists(OfficeController::class, 'requireRecordForOffice'));
        $m = new ReflectionMethod(OfficeController::class, 'requireRecordForOffice');
        self::assertTrue($m->isPrivate(), 'requireRecordForOffice must be private');
        self::assertSame(1, $m->getNumberOfRequiredParameters());
        self::assertSame(2, $m->getNumberOfParameters());
    }

    public function testGetEmployeeClientFilterStillExists(): void
    {
        // Office-employee assignment filter is the second tenant gate;
        // requireClientForOffice depends on it. Removing it would make
        // the assignment system silently fail-open.
        self::assertTrue(method_exists(OfficeController::class, 'getEmployeeClientFilter'));
        $m = new ReflectionMethod(OfficeController::class, 'getEmployeeClientFilter');
        self::assertTrue($m->isPrivate());
    }

    /**
     * Build a regex that matches a method body without crossing into the next
     * method declaration. Negative lookahead `(?!public function)` lets us
     * traverse past inner `}` braces (e.g. from `if () { … }`) which a naive
     * `[^}]+?` would mistakenly stop at.
     */
    private static function bodyContains(string $method, string $needle): string
    {
        return '/public function ' . preg_quote($method, '/')
             . '\b(?:(?!public function).)*?' . preg_quote($needle, '/') . '/s';
    }

    /** Smoke: every HR endpoint that takes {clientId} must reference requireClientForOffice. */
    public function testHrEndpointsReferenceTenantGate(): void
    {
        $source = file_get_contents((new ReflectionClass(OfficeController::class))->getFileName());
        $clientIdMethods = [
            'hrEmployees', 'hrEmployeeCreate', 'hrEmployeeStore',
            'hrEmployeeEdit', 'hrEmployeeUpdate',
            'hrContracts', 'hrContractCreate', 'hrContractStore',
            'hrPayrollList', 'hrPayrollGenerate',
            'hrLeaves', 'hrLeaveCreate', 'hrLeaveStore',
            'hrDeclarations', 'hrDeclarationGenerate',
        ];
        foreach ($clientIdMethods as $method) {
            self::assertMatchesRegularExpression(
                self::bodyContains($method, 'requireClientForOffice'),
                $source,
                "{$method} must call \$this->requireClientForOffice() before touching client data."
            );
        }
    }

    /** Methods that take a record id (not clientId) must use requireRecordForOffice. */
    public function testRecordEndpointsReferenceRecordGate(): void
    {
        $source = file_get_contents((new ReflectionClass(OfficeController::class))->getFileName());
        $recordMethods = [
            'hrContractEdit', 'hrContractUpdate',
            'hrPayrollDetail', 'hrPayrollApprove', 'hrPayrollPdf',
            'hrLeaveApprove', 'hrLeaveReject',
            'hrDeclarationDownload',
        ];
        foreach ($recordMethods as $method) {
            self::assertMatchesRegularExpression(
                self::bodyContains($method, 'requireRecordForOffice'),
                $source,
                "{$method} must call \$this->requireRecordForOffice() before acting on the record."
            );
        }
    }
}
