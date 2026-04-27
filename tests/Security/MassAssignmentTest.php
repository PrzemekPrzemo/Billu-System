<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\Client;
use App\Models\Office;
use App\Models\IssuedInvoice;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Verifies that user-controlled fields cannot escalate privileges or
 * cross tenant boundaries via mass assignment in the model layer.
 */
final class MassAssignmentTest extends TestCase
{
    public function testClientFillableExcludesTenantAndPrivilegeFields(): void
    {
        self::assertNotContains('office_id', Client::FILLABLE);
        self::assertNotContains('password_hash', Client::FILLABLE);
        self::assertNotContains('password_changed_at', Client::FILLABLE);
        self::assertNotContains('is_demo', Client::FILLABLE);
        self::assertNotContains('id', Client::FILLABLE);
        self::assertNotContains('last_login_at', Client::FILLABLE);
        self::assertNotContains('privacy_accepted', Client::FILLABLE);
    }

    public function testClientFillableIncludesProfileFields(): void
    {
        self::assertContains('nip', Client::FILLABLE);
        self::assertContains('company_name', Client::FILLABLE);
        self::assertContains('email', Client::FILLABLE);
        self::assertContains('language', Client::FILLABLE);
    }

    public function testClientAdminAllowedFieldsExtendsFillable(): void
    {
        $admin = Client::adminAllowedFields();
        self::assertContains('office_id', $admin);
        self::assertContains('password_hash', $admin);
        self::assertContains('is_demo', $admin);
        // sanity: still a superset of FILLABLE
        foreach (Client::FILLABLE as $field) {
            self::assertContains($field, $admin);
        }
    }

    public function testOfficeFillableExcludesPrivilegeFields(): void
    {
        self::assertNotContains('password_hash', Office::FILLABLE);
        self::assertNotContains('id', Office::FILLABLE);
        self::assertNotContains('is_demo', Office::FILLABLE);
        self::assertNotContains('last_login_at', Office::FILLABLE);
    }

    public function testOfficeAdminAllowedFieldsExtendsFillable(): void
    {
        $admin = Office::adminAllowedFields();
        self::assertContains('password_hash', $admin);
        self::assertContains('is_demo', $admin);
    }

    public function testIssuedInvoiceProtectedFieldsBlockTenantEscalation(): void
    {
        $reflection = new ReflectionClass(IssuedInvoice::class);
        self::assertTrue($reflection->hasConstant('PROTECTED_FIELDS'));
        $protected = $reflection->getConstant('PROTECTED_FIELDS');
        self::assertContains('id', $protected);
        self::assertContains('client_id', $protected);
        self::assertContains('created_at', $protected);
    }

    public function testIssuedInvoiceFillableExcludesSystemFields(): void
    {
        // pdf_path, ksef_*, email_sent_* are populated by services, not user form
        self::assertNotContains('id', IssuedInvoice::FILLABLE);
        self::assertNotContains('client_id', IssuedInvoice::FILLABLE);
        self::assertNotContains('pdf_path', IssuedInvoice::FILLABLE);
        self::assertNotContains('ksef_status', IssuedInvoice::FILLABLE);
        self::assertNotContains('ksef_reference_number', IssuedInvoice::FILLABLE);
        self::assertNotContains('email_sent_at', IssuedInvoice::FILLABLE);
    }

    public function testClientFilterRejectsMaliciousPostFields(): void
    {
        // Simulate $_POST data containing privilege-escalation attempts
        $userPost = [
            'company_name'  => 'Acme',
            'email'         => 'a@b.com',
            'office_id'     => 999,             // attempted tenant hop
            'password_hash' => '$argon2id$...', // attempted password injection
            'is_demo'       => 1,
            'id'            => 42,
        ];
        $filtered = array_intersect_key($userPost, array_flip(Client::FILLABLE));
        self::assertArrayHasKey('company_name', $filtered);
        self::assertArrayHasKey('email', $filtered);
        self::assertArrayNotHasKey('office_id', $filtered);
        self::assertArrayNotHasKey('password_hash', $filtered);
        self::assertArrayNotHasKey('is_demo', $filtered);
        self::assertArrayNotHasKey('id', $filtered);
    }

    public function testOfficeFilterRejectsPasswordInjection(): void
    {
        $userPost = [
            'name'          => 'Office X',
            'email'         => 'x@y.com',
            'password_hash' => 'forged',
        ];
        $filtered = array_intersect_key($userPost, array_flip(Office::FILLABLE));
        self::assertArrayNotHasKey('password_hash', $filtered);
        self::assertArrayHasKey('name', $filtered);
    }
}
