<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\ContractForm;
use App\Services\SigniusApiService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Additional structural guarantees for the public token surface:
 * - SIGNIUS webhook signature verification really HMACs the body.
 * - The webhook controller uses verifyWebhookSignature before any
 *   side effect, so a forged POST cannot flip a form to 'signed'.
 */
final class ContractsTokenTest extends TestCase
{
    public function testWebhookSignatureUsesConstantTimeCompare(): void
    {
        $rc = new ReflectionClass(SigniusApiService::class);
        $src = file_get_contents($rc->getFileName());
        // hash_hmac('sha256', …) computed over rawBody + hash_equals for compare.
        self::assertMatchesRegularExpression(
            "/hash_hmac\\(\\s*'sha256'\\s*,\\s*\\\$rawBody/",
            $src,
            'verifyWebhookSignature must HMAC the raw body with sha256'
        );
        self::assertStringContainsString('hash_equals(', $src,
            'Signature comparison must use hash_equals (constant-time)');
    }

    public function testWebhookRejectsEmptySecret(): void
    {
        // verifyWebhookSignature must return false when configured secret
        // is empty — never accept unsigned events on a misconfigured prod.
        $rc = new ReflectionClass(SigniusApiService::class);
        $src = file_get_contents($rc->getFileName());
        self::assertMatchesRegularExpression(
            "/if\\s*\\(\\s*\\\$secret\\s*===\\s*''\\s*\\)\\s*\\{[^}]+return\\s+false/s",
            $src,
            'Empty webhook_secret must short-circuit verification to false'
        );
    }

    public function testWebhookControllerCallsVerifyBeforeAnyMutation(): void
    {
        $path = dirname(__DIR__, 2) . '/src/Controllers/SigniusWebhookController.php';
        $src = file_get_contents($path);
        // Signature check must come before ContractSigningEvent::create.
        $verifyPos = strpos($src, 'verifyWebhookSignature(');
        $createPos = strpos($src, 'ContractSigningEvent::create(');
        self::assertNotFalse($verifyPos);
        self::assertNotFalse($createPos);
        self::assertLessThan($createPos, $verifyPos,
            'verifyWebhookSignature() MUST run before any ContractSigningEvent::create()');
    }

    public function testFormSubmitChecksStatusAndExpiry(): void
    {
        // ContractFormService::submitForm must:
        //   1. read status='pending'
        //   2. honor expires_at (mark expired + reject)
        // before calling pdftk or SIGNIUS.
        $path = dirname(__DIR__, 2) . '/src/Services/ContractFormService.php';
        $src = file_get_contents($path);
        $statusPos = strpos($src, "'pending'");
        $expiredPos = strpos($src, 'isExpired(');
        $fillPos    = strpos($src, 'fillForm(');
        $createPkg  = strpos($src, 'createPackage(');
        self::assertNotFalse($statusPos);
        self::assertNotFalse($expiredPos);
        self::assertNotFalse($fillPos);
        self::assertNotFalse($createPkg);
        self::assertLessThan($fillPos, $statusPos);
        self::assertLessThan($fillPos, $expiredPos);
        self::assertLessThan($createPkg, $fillPos,
            'PDF must be filled BEFORE any SIGNIUS dispatch');
    }

    public function testTokenLengthIs32BytesHex(): void
    {
        $path = dirname(__DIR__, 2) . '/src/Services/ContractFormService.php';
        $src = file_get_contents($path);
        self::assertMatchesRegularExpression(
            '/random_bytes\(\s*32\s*\)/',
            $src,
            'Token must be generated from random_bytes(32) → 64 hex chars'
        );
    }
}
