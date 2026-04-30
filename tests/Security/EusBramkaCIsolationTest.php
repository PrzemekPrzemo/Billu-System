<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Controllers\OfficeEusController;
use App\Services\EusApiService;
use App\Services\EusCorrespondenceService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Bramka C (KAS correspondence) — isolation invariants.
 * Pure reflection / source-scan / mock invocation — no DB, no HTTP.
 */
final class EusBramkaCIsolationTest extends TestCase
{
    // ─── Reply endpoint wiring ───────────────────────────

    public function testReplyEndpointsExist(): void
    {
        foreach (['replyForm', 'replySubmit'] as $m) {
            self::assertTrue(method_exists(OfficeEusController::class, $m));
            $rm = new ReflectionMethod(OfficeEusController::class, $m);
            self::assertTrue($rm->isPublic());
            self::assertFalse($rm->isStatic());
        }
    }

    public function testReplyEndpointsGateProperly(): void
    {
        $form = $this->methodBody(OfficeEusController::class, 'replyForm');
        $sub  = $this->methodBody(OfficeEusController::class, 'replySubmit');

        // Both endpoints MUST go through the office-scoped document
        // accessor + the per-employee assignment filter.
        self::assertStringContainsString('findByIdForOffice', $form);
        self::assertStringContainsString('findByIdForOffice', $sub);
        self::assertStringContainsString('requireClientForOffice', $form);
        self::assertStringContainsString('requireClientForOffice', $sub);

        // POST endpoint MUST validate CSRF.
        self::assertStringContainsString('validateCsrf', $sub);

        // Reply target MUST be an incoming Bramka C document — refuse
        // anything else (otherwise an attacker could route a reply
        // through an unrelated outbound row).
        self::assertStringContainsString("direction'] !== 'in'", $form);
        self::assertStringContainsString("direction'] !== 'in'", $sub);
        self::assertStringContainsString("bramka'] !== 'C'", $form);
        self::assertStringContainsString("bramka'] !== 'C'", $sub);
    }

    public function testReplySubmitValidatesBodyLength(): void
    {
        $src = $this->methodBody(OfficeEusController::class, 'replySubmit');
        self::assertStringContainsString('mb_strlen($body) > 50000', $src,
            'replySubmit MUST cap body length so a 50MB textarea cannot OOM the worker');
        self::assertStringContainsString("\$body === ''", $src,
            'replySubmit MUST reject empty body');
    }

    public function testReplyRoutesRegistered(): void
    {
        $routes = (string) @file_get_contents(__DIR__ . '/../../public/index.php');
        self::assertStringContainsString("get ('/office/eus/letter/{documentId}/reply'", $routes);
        self::assertStringContainsString("post('/office/eus/letter/{documentId}/reply'", $routes);
    }

    // ─── EusApiService Bramka C — fails-closed for non-mock ─

    public function testApiPollCFailsClosedForNonMock(): void
    {
        $svc = new EusApiService();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Real e-US Bramka C poll not yet implemented');
        $svc->pollC('test', '1234567890');
    }

    public function testApiSubmitReplyCFailsClosedForNonMock(): void
    {
        $svc = new EusApiService();
        $this->expectException(\RuntimeException::class);
        $svc->submitReplyC('prod', 'KAS-REF', '/tmp/x.xml');
    }

    public function testApiPollCMockReturnsExpectedShape(): void
    {
        $svc = new EusApiService();
        $r = $svc->pollC('mock', '1234567898'); // tail 8 = letter+deadline
        self::assertCount(1, $r);
        self::assertArrayHasKey('reference_no', $r[0]);
        self::assertArrayHasKey('subject', $r[0]);
        self::assertArrayHasKey('requires_reply', $r[0]);
        self::assertTrue($r[0]['requires_reply']);
    }

    // ─── EusCorrespondenceService surface + invariants ────

    public function testCorrespondenceServiceFullLifecycle(): void
    {
        foreach (['pollIncoming', 'ingestLetter', 'composeReply', 'submitReply'] as $m) {
            self::assertTrue(method_exists(EusCorrespondenceService::class, $m),
                "EusCorrespondenceService::{$m} required");
        }
    }

    public function testIngestLetterEnforcesReferenceNo(): void
    {
        $svc = new EusCorrespondenceService();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reference_no');
        $svc->ingestLetter(1, 1, ['subject' => 'no ref']);
    }

    public function testComposeReplyRejectsEmptyBody(): void
    {
        // Reflection — we don't actually run composeReply (DB write).
        // Source-level pin that the empty-body check is in place.
        $src = $this->methodBody(EusCorrespondenceService::class, 'composeReply');
        self::assertStringContainsString("\$body === ''", $src,
            'composeReply MUST reject empty body — defense in depth alongside controller validation');
    }

    public function testSurfacingHelpersArePrivate(): void
    {
        $rc = new ReflectionClass(EusCorrespondenceService::class);
        foreach (['surfaceLetterAsMessage', 'surfaceLetterAsTask', 'surfaceReplySubmitted'] as $m) {
            self::assertTrue($rc->hasMethod($m), "Missing surfacing helper {$m}");
            self::assertTrue($rc->getMethod($m)->isPrivate(),
                "Surfacing helper {$m} MUST be private — orchestrator-only");
        }
    }

    public function testPollIncomingPreFlightChecksInSource(): void
    {
        $src = $this->methodBody(EusCorrespondenceService::class, 'pollIncoming');
        self::assertStringContainsString('bramka_c_enabled', $src);
        self::assertStringContainsString('upl1_status', $src);
        self::assertStringContainsString('upl1_valid_to', $src);
        // Scope must be checked — UPL-1 with only 'declarations' scope
        // is NOT enough for KAS correspondence.
        self::assertStringContainsString("'correspondence'", $src);
    }

    public function testKasRetentionConfigurableButDefault10y(): void
    {
        $src = $this->methodBody(EusCorrespondenceService::class, 'ingestLetter');
        self::assertStringContainsString("'eus_kas_letters_retain_years', '10'", $src,
            'KAS retention MUST default to 10 years (KSH 5y + safety buffer)');
    }

    public function testReplySurfaceAppendsToExistingThread(): void
    {
        // Source-level pin: surfaceReplySubmitted must NOT create a
        // new thread — caller controls parent_id via the existing
        // KAS message thread, but we accept either model. Just verify
        // it creates a Message of sender_type='system'.
        $src = $this->methodBody(EusCorrespondenceService::class, 'surfaceReplySubmitted');
        self::assertStringContainsString("'system'", $src,
            'Reply confirmation MUST be sender_type=system (not eus, which is reserved for KAS letters)');
    }

    // ─── Bg poller ────────────────────────────────────────

    public function testPollCBgScriptIsCliOnly(): void
    {
        $path = __DIR__ . '/../../scripts/eus_poll_c_bg.php';
        self::assertFileExists($path);
        $src = (string) @file_get_contents($path);
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $src);
        self::assertStringContainsString('ctype_digit', $src);
    }

    // ─── Migration v50 ────────────────────────────────────

    public function testMigrationV50ExtendsEnumsAndAddsFks(): void
    {
        $sql = (string) @file_get_contents(__DIR__ . '/../../sql/migration_v50.0_eus_bramka_c.sql');

        // ENUM extension on messages.sender_type — both 'system' and 'eus'
        self::assertMatchesRegularExpression(
            "/MODIFY sender_type ENUM\\(.*'system'.*'eus'.*\\)/s",
            $sql,
            'v50 MUST extend messages.sender_type ENUM with system + eus'
        );

        // ENUM extension on client_tasks.created_by_type — at least 'system'
        self::assertMatchesRegularExpression(
            "/MODIFY created_by_type ENUM\\(.*'system'.*\\)/s",
            $sql,
            'v50 MUST extend client_tasks.created_by_type ENUM with system'
        );

        // FK columns + indexes
        self::assertStringContainsString('messages', $sql);
        self::assertStringContainsString('eus_document_id', $sql);
        self::assertStringContainsString('idx_msg_eus_document', $sql);
        self::assertStringContainsString('idx_task_eus_document', $sql);

        // KAS retention setting
        self::assertStringContainsString("'eus_kas_letters_retain_years'", $sql);

        // Self-register
        self::assertStringContainsString("INSERT IGNORE INTO schema_migrations", $sql);
    }

    // ─── helpers ─────────────────────────────────────────

    private function methodBody(string $class, string $method): string
    {
        $rm   = new ReflectionMethod($class, $method);
        $file = file($rm->getFileName());
        if ($file === false) {
            self::fail("Cannot read source for {$class}::{$method}");
        }
        $start = $rm->getStartLine() - 1;
        $end   = $rm->getEndLine();
        return implode('', array_slice($file, $start, $end - $start));
    }
}
