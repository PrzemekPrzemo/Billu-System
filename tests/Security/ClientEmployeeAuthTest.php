<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Core\Auth;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the new client-employee auth surface in App\Core\Auth.
 * Pure-logic checks — no DB needed.
 */
final class ClientEmployeeAuthTest extends TestCase
{
    public function testIsClientEmployeeReturnsFalseByDefault(): void
    {
        // No session set — must NOT report a logged-in client-employee.
        unset($_SESSION['user_type']);
        self::assertFalse(Auth::isClientEmployee());
    }

    public function testIsClientEmployeeReturnsTrueOnlyForCorrectUserType(): void
    {
        $_SESSION['user_type'] = 'client_employee';
        self::assertTrue(Auth::isClientEmployee());

        // Other roles must NOT collide with the new one.
        foreach (['client', 'office', 'employee', 'admin', ''] as $other) {
            $_SESSION['user_type'] = $other;
            self::assertFalse(Auth::isClientEmployee(),
                "isClientEmployee() must be false for user_type='{$other}'");
        }

        unset($_SESSION['user_type']);
    }

    public function testLoginAndRequireMethodsExist(): void
    {
        self::assertTrue(method_exists(Auth::class, 'loginClientEmployee'));
        self::assertTrue(method_exists(Auth::class, 'isClientEmployee'));
        self::assertTrue(method_exists(Auth::class, 'requireClientEmployee'));
    }
}
