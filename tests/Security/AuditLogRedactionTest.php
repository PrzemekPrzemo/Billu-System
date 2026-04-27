<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\AuditLog;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * AuditLog must never persist credentials (passwords, TOTP secrets, API
 * tokens) in plaintext under old_values/new_values, even when the caller
 * forwards a raw $_POST or model row.
 */
final class AuditLogRedactionTest extends TestCase
{
    private static function redact(array $values): array
    {
        $method = new ReflectionMethod(AuditLog::class, 'redact');
        $method->setAccessible(true);
        return $method->invoke(null, $values);
    }

    public function testRedactsPasswordHash(): void
    {
        $out = self::redact(['email' => 'a@b.com', 'password_hash' => '$argon2id$abc']);
        self::assertSame('a@b.com', $out['email']);
        self::assertSame('[REDACTED]', $out['password_hash']);
    }

    public function testRedactsTotpSecretAndRecoveryCodes(): void
    {
        $out = self::redact([
            'totp_secret'    => 'JBSWY3DPEHPK3PXP',
            'recovery_codes' => ['code1', 'code2'],
            'two_factor_secret' => 'old_secret',
        ]);
        self::assertSame('[REDACTED]', $out['totp_secret']);
        self::assertSame('[REDACTED]', $out['recovery_codes']);
        self::assertSame('[REDACTED]', $out['two_factor_secret']);
    }

    public function testRedactsApiAndKsefTokens(): void
    {
        $out = self::redact([
            'ksef_token'     => 'eyJhbGciOi...',
            'ksef_api_token' => 'token-xyz',
            'api_key'        => 'sk_live_...',
            'jwt_secret'     => 'secret-xyz',
            'smtp_password'  => 'p@ss',
        ]);
        foreach (['ksef_token', 'ksef_api_token', 'api_key', 'jwt_secret', 'smtp_password'] as $key) {
            self::assertSame('[REDACTED]', $out[$key], "{$key} must be redacted");
        }
    }

    public function testRedactsCaseInsensitively(): void
    {
        $out = self::redact(['Password_Hash' => 'x', 'TOTP_SECRET' => 'y']);
        self::assertSame('[REDACTED]', $out['Password_Hash']);
        self::assertSame('[REDACTED]', $out['TOTP_SECRET']);
    }

    public function testRedactsNestedArrays(): void
    {
        $out = self::redact([
            'user' => [
                'name'          => 'Alice',
                'password_hash' => 'secret',
                'profile'       => ['api_key' => 'sk_xxx'],
            ],
        ]);
        self::assertSame('Alice', $out['user']['name']);
        self::assertSame('[REDACTED]', $out['user']['password_hash']);
        self::assertSame('[REDACTED]', $out['user']['profile']['api_key']);
    }

    public function testPreservesNonSensitiveValues(): void
    {
        $out = self::redact(['nip' => '1234567890', 'is_active' => 1, 'language' => 'pl']);
        self::assertSame('1234567890', $out['nip']);
        self::assertSame(1, $out['is_active']);
        self::assertSame('pl', $out['language']);
    }

    public function testRedactedKeysAreAllLowercase(): void
    {
        // Sanity: REDACTED_KEYS list must be lowercase for case-insensitive match to work.
        $reflection = new ReflectionClass(AuditLog::class);
        $keys = $reflection->getConstant('REDACTED_KEYS');
        self::assertIsArray($keys);
        foreach ($keys as $k) {
            self::assertSame(strtolower($k), $k, "Key {$k} must be lowercase");
        }
    }
}
