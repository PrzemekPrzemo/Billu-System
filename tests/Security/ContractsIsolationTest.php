<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\ContractForm;
use App\Models\ContractTemplate;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pure-logic invariants for the Contracts module.
 * No DB or pdftk calls — these protect the FILLABLE allowlists, the
 * dedicated setter API around sensitive columns, and the existence of
 * tenant-checked accessors.
 */
final class ContractsIsolationTest extends TestCase
{
    public function testTemplateFillableIsTight(): void
    {
        // FILLABLE must contain ONLY surface form fields. Anything that
        // identifies tenancy or storage location lives outside.
        self::assertSame(
            ['name', 'slug', 'description', 'is_active'],
            ContractTemplate::FILLABLE
        );
        // Sentinel: critical columns are NOT in the list.
        foreach (['office_id', 'stored_path', 'fields_json', 'signers_json',
                  'created_by_type', 'created_by_id', 'id', 'created_at'] as $forbidden) {
            self::assertNotContains($forbidden, ContractTemplate::FILLABLE,
                "ContractTemplate::FILLABLE must NOT include {$forbidden}");
        }
    }

    public function testFormFillableExcludesTokenAndPdfPaths(): void
    {
        foreach (['token', 'status', 'signed_pdf_path', 'filled_pdf_path',
                  'signius_package_id', 'office_id', 'template_id',
                  'created_by_type', 'created_by_id', 'submitted_at', 'signed_at',
                  'form_data', 'id'] as $forbidden) {
            self::assertNotContains($forbidden, ContractForm::FILLABLE,
                "ContractForm::FILLABLE must NOT include {$forbidden}");
        }
        // The three things FILLABLE *should* allow.
        foreach (['recipient_email', 'recipient_name', 'expires_at'] as $expected) {
            self::assertContains($expected, ContractForm::FILLABLE);
        }
    }

    public function testTemplateOwnershipAccessorExists(): void
    {
        self::assertTrue(method_exists(ContractTemplate::class, 'findByIdForOffice'));
        $m = new ReflectionMethod(ContractTemplate::class, 'findByIdForOffice');
        self::assertTrue($m->isStatic());
        self::assertSame(2, $m->getNumberOfRequiredParameters(),
            'findByIdForOffice must require BOTH id and officeId');
    }

    public function testFormOwnershipAccessorExists(): void
    {
        self::assertTrue(method_exists(ContractForm::class, 'findByIdForOffice'));
        $m = new ReflectionMethod(ContractForm::class, 'findByIdForOffice');
        self::assertSame(2, $m->getNumberOfRequiredParameters());

        // findByToken must length-gate before DB hit.
        self::assertTrue(method_exists(ContractForm::class, 'findByToken'));
    }

    public function testFormDedicatedSettersExist(): void
    {
        // The whole point of FILLABLE excluding token/status/*_pdf_path is
        // that there's a dedicated setter for each terminal transition. If
        // someone refactors them away, this test catches it.
        foreach (['markFilled', 'markSubmitted', 'attachSignedPdf', 'setStatus', 'expireOverdue'] as $m) {
            self::assertTrue(method_exists(ContractForm::class, $m), "Missing ContractForm::{$m}()");
        }
    }

    public function testSetStatusOnlyAllowsTerminalTransitions(): void
    {
        // We can't easily call setStatus without DB, but the source must show
        // its whitelist. Read source and assert the exact allowed set.
        $rc = new \ReflectionClass(ContractForm::class);
        $source = file_get_contents($rc->getFileName());
        self::assertMatchesRegularExpression(
            "/in_array\\(\\\$status, \\['rejected', 'expired', 'cancelled'\\]/",
            $source,
            'setStatus must whitelist exactly rejected/expired/cancelled'
        );
    }

    public function testFindByTokenLengthGate(): void
    {
        // Calling with malformed token must return null without touching DB.
        // We rely on the early-return inside the method; reflection alone
        // can't run it without DB, so smoke-check the source for the gate.
        $rc = new \ReflectionClass(ContractForm::class);
        $source = file_get_contents($rc->getFileName());
        self::assertMatchesRegularExpression(
            '/strlen\(\$token\)\s*!==\s*64/',
            $source,
            'findByToken must reject non-64-char tokens before DB'
        );
    }
}
