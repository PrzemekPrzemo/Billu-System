<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\ClientEmployee;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins the four invariants that guard cross-tenant access for the new
 * client-employee role: mass-assignment whitelist, dedicated ownership
 * accessors, and the auth-only column gate.
 */
final class ClientEmployeeIsolationTest extends TestCase
{
    public function testFillableExcludesAuthAndPrivilegeFields(): void
    {
        // HR data only — login_email / can_login require AUTH_FILLABLE.
        self::assertNotContains('login_email', ClientEmployee::FILLABLE);
        self::assertNotContains('can_login', ClientEmployee::FILLABLE);
        // Password / activation / 2FA fields are NEVER mass-assignable.
        self::assertNotContains('password_hash', ClientEmployee::FILLABLE);
        self::assertNotContains('activation_token', ClientEmployee::FILLABLE);
        self::assertNotContains('two_factor_secret', ClientEmployee::FILLABLE);
        self::assertNotContains('recovery_codes', ClientEmployee::FILLABLE);
        // client_id can never come from a form — it's set by the controller from session.
        self::assertNotContains('client_id', ClientEmployee::FILLABLE);
        self::assertNotContains('id', ClientEmployee::FILLABLE);
    }

    public function testFillableIncludesExpectedHrFields(): void
    {
        foreach (['first_name', 'last_name', 'pesel', 'email', 'phone',
                  'address_street', 'bank_account', 'tax_office',
                  'hired_at', 'is_active'] as $field) {
            self::assertContains($field, ClientEmployee::FILLABLE, "FILLABLE missing {$field}");
        }
    }

    public function testAuthFillableLimitedToLoginEmailAndCanLogin(): void
    {
        // No password / 2FA / token fields here either — those are setter-only.
        self::assertSame(['login_email', 'can_login'], ClientEmployee::AUTH_FILLABLE);
    }

    public function testClientAllowedFieldsIsUnionOfFillableAndAuth(): void
    {
        $allowed = ClientEmployee::clientAllowedFields();
        foreach (ClientEmployee::FILLABLE as $f)      { self::assertContains($f, $allowed); }
        foreach (ClientEmployee::AUTH_FILLABLE as $f) { self::assertContains($f, $allowed); }
        // But not raw password / 2FA fields, even via this combined list.
        self::assertNotContains('password_hash', $allowed);
        self::assertNotContains('activation_token', $allowed);
        self::assertNotContains('two_factor_secret', $allowed);
    }

    public function testFilterRejectsMaliciousPostFields(): void
    {
        $userPost = [
            'first_name'        => 'Anna',
            'last_name'         => 'Nowak',
            'login_email'       => 'anna@firma.pl',
            'can_login'         => 1,
            // Attack surface — every one of these MUST be dropped.
            'client_id'         => 999,
            'password_hash'     => '$argon2id$forged',
            'activation_token'  => 'forged-token',
            'two_factor_secret' => 'forged',
            'recovery_codes'    => '["a","b"]',
            'id'                => 42,
        ];
        $allowed = ClientEmployee::clientAllowedFields();
        $filtered = array_intersect_key($userPost, array_flip($allowed));

        self::assertArrayHasKey('first_name', $filtered);
        self::assertArrayHasKey('login_email', $filtered);
        self::assertArrayHasKey('can_login', $filtered);
        foreach (['client_id', 'password_hash', 'activation_token', 'two_factor_secret', 'recovery_codes', 'id'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $filtered, "{$forbidden} leaked through whitelist");
        }
    }

    public function testOwnershipAccessorsAreDefinedAndStatic(): void
    {
        foreach (['findByIdForClient', 'findByIdForOffice', 'findByLoginEmail', 'findByActivationToken'] as $method) {
            self::assertTrue(method_exists(ClientEmployee::class, $method), "Missing {$method}");
            $m = new ReflectionMethod(ClientEmployee::class, $method);
            self::assertTrue($m->isStatic(), "{$method} must be static");
        }
        // Ownership pair — 2 required params each.
        self::assertSame(2, (new ReflectionMethod(ClientEmployee::class, 'findByIdForClient'))->getNumberOfRequiredParameters());
        self::assertSame(2, (new ReflectionMethod(ClientEmployee::class, 'findByIdForOffice'))->getNumberOfRequiredParameters());
    }

    public function testPasswordAndActivationSettersExist(): void
    {
        // Password / activation flow MUST go through dedicated setters,
        // never through ::update($id, $data).
        foreach (['issueActivationToken', 'setPasswordAndActivate', 'updatePassword', 'updateLastLogin'] as $method) {
            self::assertTrue(method_exists(ClientEmployee::class, $method), "Missing {$method}");
        }
    }

    public function testUpdateSignatureSupportsAllowedOverride(): void
    {
        $m = new ReflectionMethod(ClientEmployee::class, 'update');
        self::assertSame(2, $m->getNumberOfRequiredParameters(),
            'update() must accept (id, data) by default and an optional $allowed');
        self::assertSame(3, $m->getNumberOfParameters(),
            'update() third parameter ($allowed) must be optional');
    }
}
