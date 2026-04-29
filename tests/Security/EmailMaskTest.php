<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Services\MailService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * MailService::maskEmail produces the masked form that error_log uses
 * so recipients are not leaked into log files / log aggregators.
 */
final class EmailMaskTest extends TestCase
{
    private static function mask(string $email): string
    {
        $m = new ReflectionMethod(MailService::class, 'maskEmail');
        $m->setAccessible(true);
        return $m->invoke(null, $email);
    }

    public function testMasksLocalPartButKeepsDomain(): void
    {
        self::assertSame('a*@example.com', self::mask('ab@example.com'));
        self::assertSame('a****@example.com', self::mask('alice@example.com'));
    }

    public function testHandlesShortLocalPart(): void
    {
        // single-char local: keep first letter, add a single '*' (never empty)
        self::assertSame('a*@x.com', self::mask('a@x.com'));
    }

    public function testHandlesMissingAtSign(): void
    {
        self::assertSame('***', self::mask('not-an-email'));
        self::assertSame('***', self::mask(''));
    }

    public function testPreservesSubdomainInDomain(): void
    {
        self::assertSame('j***@mail.corp.example.com', self::mask('jdoe@mail.corp.example.com'));
    }

    public function testDoesNotLeakPlainAddress(): void
    {
        $masked = self::mask('confidential@billu.pl');
        self::assertStringNotContainsString('confidential', $masked);
        self::assertStringContainsString('@billu.pl', $masked);
    }
}
