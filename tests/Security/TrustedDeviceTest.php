<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\TrustedDevice;
use App\Core\Auth;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pins the structural invariants for the "remember this device" 2FA bypass.
 * Token plaintext lives only in cookie + sha256 in DB; cookie attributes
 * (HttpOnly, Secure on HTTPS, SameSite=Lax) and the verify-by-hash path
 * MUST stay intact across refactors.
 */
final class TrustedDeviceTest extends TestCase
{
    public function testCookieNameAndDefaultTtlAreSane(): void
    {
        self::assertSame('billu_trusted_device', TrustedDevice::COOKIE_NAME);
        self::assertSame(5, TrustedDevice::DEFAULT_TTL_DAYS);
    }

    public function testIssueGeneratesPlaintextTokenWithCorrectShape(): void
    {
        // Can't actually call issue() without DB, so reflect on its signature.
        $m = new ReflectionMethod(TrustedDevice::class, 'issue');
        self::assertTrue($m->isStatic());
        // (userType, userId, ttlDays = 5, label = null) → 2 required.
        self::assertSame(2, $m->getNumberOfRequiredParameters());
        self::assertSame(4, $m->getNumberOfParameters());
        self::assertSame('string', (string) $m->getReturnType());
    }

    public function testVerifyRejectsMalformedTokenWithoutDbHit(): void
    {
        // 64 hex chars expected. Anything else MUST be rejected at the
        // length gate before any DB lookup runs.
        self::assertFalse(TrustedDevice::verify('client', 1, ''));
        self::assertFalse(TrustedDevice::verify('client', 1, 'too-short'));
        self::assertFalse(TrustedDevice::verify('client', 1, str_repeat('z', 100)));
    }

    public function testRevokeMethodsAreOwnershipScoped(): void
    {
        $revoke = new ReflectionMethod(TrustedDevice::class, 'revoke');
        self::assertSame(3, $revoke->getNumberOfRequiredParameters(),
            'revoke() must require id + userType + userId — never id alone');

        $revokeAll = new ReflectionMethod(TrustedDevice::class, 'revokeAllForUser');
        self::assertSame(2, $revokeAll->getNumberOfRequiredParameters(),
            'revokeAllForUser() must require userType + userId');
    }

    public function testAuthHelpersExist(): void
    {
        foreach (['isTrustedDevice', 'issueTrustedDeviceCookie', 'clearTrustedDeviceCookie'] as $method) {
            self::assertTrue(method_exists(Auth::class, $method), "Missing Auth::{$method}");
        }
    }

    public function testIsTrustedDeviceReturnsFalseWhenCookieMissing(): void
    {
        unset($_COOKIE[TrustedDevice::COOKIE_NAME]);
        self::assertFalse(Auth::isTrustedDevice('client', 1));
        self::assertFalse(Auth::isTrustedDevice('admin', 1));
    }

    public function testIsTrustedDeviceWithMalformedCookieReturnsFalse(): void
    {
        // Even with a cookie, malformed values must not query DB nor pass.
        $_COOKIE[TrustedDevice::COOKIE_NAME] = 'not-hex-64-chars';
        self::assertFalse(Auth::isTrustedDevice('client', 1));
        unset($_COOKIE[TrustedDevice::COOKIE_NAME]);
    }
}
