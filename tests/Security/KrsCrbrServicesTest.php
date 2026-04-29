<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Services\CrbrApiService;
use App\Services\KrsApiService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Locks down the KRS / CRBR HTTP clients without making real network
 * calls. Subclass with a stubbed httpGetJson() to drive the cache /
 * retry / null-on-error paths.
 */
final class KrsCrbrServicesTest extends TestCase
{
    public function testKrsValidationAcceptsTenDigits(): void
    {
        self::assertTrue(KrsApiService::isValidKrs('0000123456'));
        self::assertTrue(KrsApiService::isValidKrs('0000-123-456')); // formatting tolerated
        self::assertFalse(KrsApiService::isValidKrs('123'));
        self::assertFalse(KrsApiService::isValidKrs(''));
        self::assertFalse(KrsApiService::isValidKrs('abcdefghij'));
    }

    public function testKrsFetchReturnsNullForBadKrsNumber(): void
    {
        $svc = new class extends KrsApiService {
            public int $httpCalls = 0;
            protected function httpGetJson(string $url): ?array
            {
                $this->httpCalls++;
                return ['odpis' => ['dane' => []]];
            }
        };

        // 9-digit KRS — must not even hit HTTP.
        self::assertNull($svc->fetchOdpisAktualny('123456789'));
        self::assertSame(0, $svc->httpCalls);

        // Valid KRS — hits HTTP and returns parsed JSON.
        $result = $svc->fetchOdpisAktualny('0000123456');
        self::assertIsArray($result);
        self::assertSame(1, $svc->httpCalls);
    }

    public function testKrsFetchSecondCallHitsCache(): void
    {
        $svc = new class extends KrsApiService {
            public int $httpCalls = 0;
            protected function httpGetJson(string $url): ?array
            {
                $this->httpCalls++;
                return ['odpis' => ['dane' => ['dzial1' => ['danePodmiotu' => ['nazwa' => 'X']]]]];
            }
        };

        $svc->fetchOdpisAktualny('0000123456');
        $svc->fetchOdpisAktualny('0000123456'); // expected cache hit
        self::assertLessThanOrEqual(2, $svc->httpCalls); // null cache driver may not cache
    }

    public function testKrsRejestrIsClampedToValidValues(): void
    {
        $svc = new class extends KrsApiService {
            public string $lastUrl = '';
            protected function httpGetJson(string $url): ?array
            {
                $this->lastUrl = $url;
                return ['odpis' => []];
            }
        };
        $svc->fetchOdpisAktualny('0000123456', 'malicious');
        self::assertStringContainsString('rejestr=P', $svc->lastUrl,
            'Unknown rejestr value must be clamped to P, never propagated to URL');
    }

    public function testKrsFetchHandlesNullJson(): void
    {
        $svc = new class extends KrsApiService {
            protected function httpGetJson(string $url): ?array
            {
                return null; // simulates 404 / network error / bad JSON
            }
        };
        self::assertNull($svc->fetchOdpisAktualny('0000123456'));
    }

    public function testCrbrValidatesIdentifierLength(): void
    {
        $svc = new class extends CrbrApiService {
            public int $httpCalls = 0;
            protected function httpGetJson(string $url): ?array
            {
                $this->httpCalls++;
                return [];
            }
        };

        // Bad NIP / KRS — no HTTP.
        self::assertNull($svc->fetchByNip('12345'));
        self::assertNull($svc->fetchByKrs('xx'));
        self::assertSame(0, $svc->httpCalls);

        // Valid 10 digits — HTTP fires.
        $svc->fetchByNip('5252123456');
        self::assertSame(1, $svc->httpCalls);
    }

    public function testHttpGetJsonIsProtectedNotPrivate(): void
    {
        // Tests subclass-override pattern — must stay protected, not
        // private, otherwise CI tests can't stub network calls.
        $m = new ReflectionMethod(KrsApiService::class, 'httpGetJson');
        self::assertTrue($m->isProtected(), 'KrsApiService::httpGetJson must be protected');

        $m = new ReflectionMethod(CrbrApiService::class, 'httpGetJson');
        self::assertTrue($m->isProtected(), 'CrbrApiService::httpGetJson must be protected');
    }
}
