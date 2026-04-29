<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Services\PasswordResetService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionClass;

/**
 * Throttle window protects /forgot-password from being used as an
 * account enumeration oracle or mail-flood vector. Behaviour depends
 * on the Cache driver: when Redis is online attempts are tracked
 * per IP+NIP; when CACHE_DRIVER=null the throttle fails open
 * (intentional, validated below).
 */
final class PasswordResetThrottleTest extends TestCase
{
    public function testThrottleConstantsAreSane(): void
    {
        $rc = new ReflectionClass(PasswordResetService::class);
        self::assertTrue($rc->hasConstant('THROTTLE_MAX'));
        self::assertTrue($rc->hasConstant('THROTTLE_WINDOW'));
        $max    = $rc->getConstant('THROTTLE_MAX');
        $window = $rc->getConstant('THROTTLE_WINDOW');
        self::assertGreaterThanOrEqual(3, $max, 'THROTTLE_MAX too low — legitimate retries blocked');
        self::assertLessThanOrEqual(20, $max, 'THROTTLE_MAX too high — enumeration / flood window too wide');
        self::assertGreaterThanOrEqual(300, $window, 'THROTTLE_WINDOW too short to be useful');
    }

    public function testIsThrottledReturnsFalseWhenIpEmpty(): void
    {
        $original = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = '';

        $m = new ReflectionMethod(PasswordResetService::class, 'isThrottled');
        $m->setAccessible(true);
        $result = $m->invoke(null, '1234567890');

        if ($original !== null) {
            $_SERVER['REMOTE_ADDR'] = $original;
        }

        self::assertFalse($result, 'Empty IP must fail open (no rate-limit basis)');
    }

    public function testIsThrottledFailsOpenWithNullCacheDriver(): void
    {
        // Default test environment has CACHE_DRIVER=null (no Redis), so cache->get/set are no-ops.
        // Verify that under this configuration the throttle does NOT block requests.
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $m = new ReflectionMethod(PasswordResetService::class, 'isThrottled');
        $m->setAccessible(true);
        for ($i = 0; $i < 10; $i++) {
            self::assertFalse($m->invoke(null, '9999999999'), "iter {$i} unexpectedly throttled");
        }
    }
}
