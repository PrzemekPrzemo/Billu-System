<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Core\Auth;
use PHPUnit\Framework\TestCase;

/**
 * Password complexity rules — pure logic, regression check that the
 * acceptance set has not been silently weakened.
 */
final class PasswordStrengthTest extends TestCase
{
    public function testStrongPasswordIsAccepted(): void
    {
        $errors = Auth::validatePasswordStrength('SuperSecret123!');
        self::assertSame([], $errors);
    }

    public function testTooShortRejected(): void
    {
        $errors = Auth::validatePasswordStrength('Aa1!');
        self::assertContains('password_min_length', $errors);
    }

    public function testMissingUppercaseRejected(): void
    {
        $errors = Auth::validatePasswordStrength('lowercase123!');
        self::assertContains('password_uppercase', $errors);
    }

    public function testMissingLowercaseRejected(): void
    {
        $errors = Auth::validatePasswordStrength('UPPERCASE123!');
        self::assertContains('password_lowercase', $errors);
    }

    public function testMissingDigitRejected(): void
    {
        $errors = Auth::validatePasswordStrength('NoDigitsHere!');
        self::assertContains('password_digit', $errors);
    }

    public function testMissingSpecialRejected(): void
    {
        $errors = Auth::validatePasswordStrength('NoSpecial1234');
        self::assertContains('password_special', $errors);
    }

    public function testEmptyPasswordHitsAllRules(): void
    {
        $errors = Auth::validatePasswordStrength('');
        self::assertContains('password_min_length', $errors);
        self::assertContains('password_lowercase', $errors);
        self::assertContains('password_uppercase', $errors);
        self::assertContains('password_digit', $errors);
        self::assertContains('password_special', $errors);
    }

    public function testHashAndVerifyRoundtrip(): void
    {
        $hash = Auth::hashPassword('CorrectHorseBattery1!');
        self::assertTrue(Auth::verifyPassword('CorrectHorseBattery1!', $hash));
        self::assertFalse(Auth::verifyPassword('wrong', $hash));
    }
}
