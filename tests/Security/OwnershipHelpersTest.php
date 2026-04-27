<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Contractor;
use App\Models\Message;
use App\Models\ClientFile;
use App\Models\ClientEmployee;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Validates that every model exposed in client/office controllers has
 * the canonical ownership-checked accessor pair. New endpoints should
 * use these instead of ::findById to avoid forgetting the inline check.
 */
final class OwnershipHelpersTest extends TestCase
{
    public static function modelProvider(): array
    {
        return [
            'Invoice'        => [Invoice::class],
            'InvoiceBatch'   => [InvoiceBatch::class],
            'Contractor'     => [Contractor::class],
            'Message'        => [Message::class],
            'ClientFile'     => [ClientFile::class],
            'ClientEmployee' => [ClientEmployee::class],
        ];
    }

    /** @dataProvider modelProvider */
    public function testFindByIdForClientIsDefined(string $class): void
    {
        self::assertTrue(method_exists($class, 'findByIdForClient'),
            "{$class} must expose findByIdForClient(\$id, \$clientId).");
        $m = new ReflectionMethod($class, 'findByIdForClient');
        self::assertTrue($m->isStatic(), "{$class}::findByIdForClient must be static");
        self::assertSame(2, $m->getNumberOfRequiredParameters());
    }

    /** @dataProvider modelProvider */
    public function testFindByIdForOfficeIsDefined(string $class): void
    {
        self::assertTrue(method_exists($class, 'findByIdForOffice'),
            "{$class} must expose findByIdForOffice(\$id, \$officeId).");
        $m = new ReflectionMethod($class, 'findByIdForOffice');
        self::assertTrue($m->isStatic());
        self::assertSame(2, $m->getNumberOfRequiredParameters());
    }
}
