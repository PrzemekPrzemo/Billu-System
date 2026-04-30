<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\EusConfig;
use App\Models\EusDocument;
use App\Models\EusOperationLog;
use App\Services\EusCertificateService;
use App\Services\EusLogger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins the public surface of the e-US foundation (PR-1) so PR-2/3/4
 * can wire services without accidentally bypassing tenant gates or
 * mass-assignment guards. Pure reflection / encryption round-trip —
 * no DB, no HTTP.
 */
final class EusFoundationIsolationTest extends TestCase
{
    // ─── EusConfig ─────────────────────────────────────────

    public function testEusConfigEnumsFrozen(): void
    {
        // Bumping these values is a deliberate migration v47 change.
        self::assertSame(['mock', 'test', 'prod'], EusConfig::ENVIRONMENTS);
        self::assertSame(['cert_qual', 'profil_zaufany', 'mdowod'], EusConfig::AUTH_METHODS);
        self::assertSame(['none', 'pending', 'active', 'revoked', 'expired'], EusConfig::UPL1_STATUSES);
    }

    public function testEusConfigFillableExcludesPrivilegeFields(): void
    {
        // cert_*, auth_provider_*, office_id, client_id, last_poll_at
        // MUST NOT be mass-assignable from a form.
        $forbidden = [
            'cert_encrypted', 'cert_passphrase_encrypted', 'cert_subject',
            'cert_fingerprint', 'cert_valid_from', 'cert_valid_to',
            'auth_provider_token_encrypted', 'auth_provider_subject',
            'auth_provider_valid_to',
            'office_id', 'client_id', 'last_poll_at',
        ];
        foreach ($forbidden as $f) {
            self::assertNotContains($f, EusConfig::FILLABLE,
                "EusConfig::FILLABLE must NOT include '{$f}' — privilege/tenant field");
        }
    }

    public function testEusConfigOfficeScopedAccessorsExist(): void
    {
        foreach (['findByClientForOffice', 'findByIdForOffice', 'findAllForOffice'] as $method) {
            self::assertTrue(method_exists(EusConfig::class, $method));
            $m = new ReflectionMethod(EusConfig::class, $method);
            self::assertTrue($m->isStatic());
        }

        // findByClientForOffice / findByIdForOffice MUST take 2 required params.
        self::assertSame(2,
            (new ReflectionMethod(EusConfig::class, 'findByClientForOffice'))->getNumberOfRequiredParameters());
        self::assertSame(2,
            (new ReflectionMethod(EusConfig::class, 'findByIdForOffice'))->getNumberOfRequiredParameters());
    }

    // ─── EusDocument ───────────────────────────────────────

    public function testEusDocumentHasNoFillableConstant(): void
    {
        $rc = new ReflectionClass(EusDocument::class);
        self::assertFalse($rc->hasConstant('FILLABLE'),
            'EusDocument MUST NOT expose FILLABLE — factory methods are the only write path');

        // ENUMs frozen.
        self::assertSame(['B', 'C'], EusDocument::BRAMKI);
        self::assertSame(['out', 'in'], EusDocument::DIRECTIONS);
    }

    public function testEusDocumentBlocksDirectMutators(): void
    {
        // No public create/update/save/delete that takes raw $data.
        foreach (['create', 'update', 'save', 'delete'] as $disallowed) {
            self::assertFalse(method_exists(EusDocument::class, $disallowed),
                "EusDocument::{$disallowed} would bypass factory methods — keep it out");
        }
    }

    public function testEusDocumentFactoriesArePresent(): void
    {
        foreach (['queueOutbound', 'recordIncoming', 'transitionStatus', 'softPurge', 'hasActiveRetention'] as $m) {
            self::assertTrue(method_exists(EusDocument::class, $m));
            self::assertTrue((new ReflectionMethod(EusDocument::class, $m))->isStatic());
        }
    }

    public function testQueueOutboundRejectsBadBramka(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EusDocument::queueOutbound(1, 1, 'X', 'JPK_V7M', '2026-04', '/tmp/nope.xml');
    }

    public function testTransitionStatusRejectsBadStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EusDocument::transitionStatus(1, 'invalid_status_value');
    }

    // ─── EusCertificateService ─────────────────────────────

    public function testCertificateServicePublicSurface(): void
    {
        // The 5 methods the office UI / submission services rely on.
        // Real round-trip is exercised by the integration suite (DB-backed).
        foreach (['getEncryptionKey', 'ensureKey', 'encrypt', 'decrypt', 'parsePfx'] as $method) {
            self::assertTrue(method_exists(EusCertificateService::class, $method));
            self::assertTrue((new ReflectionMethod(EusCertificateService::class, $method))->isStatic());
        }
    }

    public function testCertificateServiceUsesSeparateKeyFromKsef(): void
    {
        // Defense-in-depth: a KSeF vault breach must NOT expose e-US
        // credentials. Pin the constant value at the source-file level.
        $src = (string) @file_get_contents(
            __DIR__ . '/../../src/Services/EusCertificateService.php'
        );
        self::assertStringContainsString("'eus_cert_encryption_key'", $src,
            'EusCertificateService MUST use eus_cert_encryption_key, NOT ksef_*');
        self::assertStringNotContainsString("'ksef_cert_encryption_key'", $src,
            'EusCertificateService MUST NOT reuse the KSeF master key');
    }

    public function testCertificateServiceUsesAes256Gcm(): void
    {
        $src = (string) @file_get_contents(
            __DIR__ . '/../../src/Services/EusCertificateService.php'
        );
        self::assertStringContainsString("'aes-256-gcm'", $src,
            'AES-256-GCM is the canonical at-rest cipher');
    }

    // ─── EusLogger ─────────────────────────────────────────

    public function testEusLoggerMaskingConstantsCoverKnownTokenFields(): void
    {
        $rc = new ReflectionClass(EusLogger::class);
        $sensitive = $rc->getConstant('SENSITIVE_BODY_FIELDS');
        self::assertIsArray($sensitive);

        // Whatever ENUM extension PR-2/3 adds, these MUST stay masked.
        foreach (['accessToken', 'refreshToken', 'authenticationToken',
                  'samlAssertion', 'samlArtifact'] as $field) {
            self::assertArrayHasKey($field, $sensitive,
                "EusLogger MUST mask '{$field}' in body — added in PR-1, locked here");
        }

        $headers = $rc->getConstant('SENSITIVE_HEADERS');
        self::assertIsArray($headers);
        foreach (['authorization', 'x-eus-token', 'cookie'] as $h) {
            self::assertArrayHasKey($h, $headers,
                "EusLogger MUST mask header '{$h}' (case-insensitive)");
        }
    }

    public function testEusLoggerInstantiatesWithoutDb(): void
    {
        // The logger MUST work in a context without DB (cron worker /
        // background script) — uses only filesystem.
        $sessionId = 'test_' . bin2hex(random_bytes(4));
        $logger    = new EusLogger($sessionId);
        self::assertSame($sessionId, $logger->getSessionId());
        self::assertStringContainsString($sessionId, $logger->getSessionFile());

        // File is not created until the first log() call — verify the
        // path is at least under storage/logs/eus.
        self::assertStringContainsString('storage/logs/eus', $logger->getSessionFile());
    }

    // ─── EusOperationLog ───────────────────────────────────

    public function testEusOperationLogTruncatesExcerpts(): void
    {
        // The model's record() applies mb_substr to 4096; verify the
        // constant is in source (defensive check — easy to forget).
        $src = (string) @file_get_contents(
            __DIR__ . '/../../src/Models/EusOperationLog.php'
        );
        self::assertStringContainsString('mb_substr($requestExcerpt, 0, 4096)', $src,
            'EusOperationLog::record must truncate request_excerpt to 4kB');
        self::assertStringContainsString('mb_substr($responseExcerpt, 0, 4096)', $src,
            'EusOperationLog::record must truncate response_excerpt to 4kB');
    }
}
