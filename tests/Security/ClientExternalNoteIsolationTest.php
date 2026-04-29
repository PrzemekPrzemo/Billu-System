<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\ClientExternalNote;
use App\Services\KrsApiService;
use App\Services\CrbrApiService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Pins the public-surface invariants the orchestrator relies on:
 *  - ClientExternalNote has the canonical office-scoped accessors and
 *    a single append() write path (no insert/update for controllers).
 *  - KrsApiService / CrbrApiService skeletons expose the methods the
 *    orchestrator type-hints.
 *
 * Pure reflection — no DB or HTTP.
 */
final class ClientExternalNoteIsolationTest extends TestCase
{
    public function testSourcesAndTargetsAreFrozen(): void
    {
        // Adding a new source MUST be a deliberate ENUM bump in migration v46.
        self::assertSame(
            ['gus', 'krs', 'ceidg', 'crbr', 'eus', 'manual'],
            ClientExternalNote::SOURCES
        );
        self::assertSame(['client', 'contractor'], ClientExternalNote::TARGETS);
    }

    public function testOfficeScopedAccessorsExist(): void
    {
        foreach (['findByIdForOffice', 'findHistoryForOffice'] as $method) {
            self::assertTrue(
                method_exists(ClientExternalNote::class, $method),
                "ClientExternalNote::{$method} must exist for office tenant gating"
            );
            $m = new ReflectionMethod(ClientExternalNote::class, $method);
            self::assertTrue($m->isStatic(), "{$method} must be static");
        }
        // findByIdForOffice($id, $officeId) — both required.
        $m = new ReflectionMethod(ClientExternalNote::class, 'findByIdForOffice');
        self::assertSame(2, $m->getNumberOfRequiredParameters());

        // findHistoryForOffice($targetType, $targetId, $officeId) — 3 required.
        $m = new ReflectionMethod(ClientExternalNote::class, 'findHistoryForOffice');
        self::assertSame(3, $m->getNumberOfRequiredParameters());
    }

    public function testAppendIsTheOnlyPublicWritePath(): void
    {
        // append() exists, has all required tenant fields up front.
        $m = new ReflectionMethod(ClientExternalNote::class, 'append');
        $params = array_map(fn($p) => $p->getName(), $m->getParameters());
        // First three positions MUST be tenant scope — never 'source' or 'raw_json'
        // first, otherwise a partial application is a footgun.
        self::assertSame(
            ['clientId', 'targetType', 'targetId'],
            array_slice($params, 0, 3),
            'append() positional contract: tenant scope must come first'
        );
        // Caller-provided actor identity — fetched_by_* are NOT auto-derived
        // from session, the controller passes them explicitly so the model
        // never needs Auth/Session imports.
        self::assertContains('fetchedByType', $params);
        self::assertContains('fetchedById', $params);
    }

    public function testNoMassAssignableInsertOrUpdate(): void
    {
        // Defense-in-depth: there is no FILLABLE constant + no public
        // create/update so a controller cannot accidentally call
        // ClientExternalNote::create($_POST).
        $rc = new ReflectionClass(ClientExternalNote::class);
        self::assertFalse(
            $rc->hasConstant('FILLABLE'),
            'ClientExternalNote MUST NOT expose FILLABLE — append() is the only write path'
        );
        foreach (['create', 'update', 'save', 'delete'] as $disallowed) {
            self::assertFalse(
                method_exists(ClientExternalNote::class, $disallowed),
                "ClientExternalNote::{$disallowed} would bypass append() — keep it out"
            );
        }
    }

    public function testInvalidTargetTypeRejected(): void
    {
        // Even if a caller bypasses controller validation, the model
        // refuses unknown ENUM values rather than letting MySQL truncate.
        $this->expectException(\InvalidArgumentException::class);
        ClientExternalNote::append(
            1, 'invoice', 1, 'gus', null, [], '<p>x</p>', 'office', 1
        );
    }

    public function testInvalidSourceRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ClientExternalNote::append(
            1, 'client', 1, 'instagram', null, [], '<p>x</p>', 'office', 1
        );
    }

    public function testKrsServicePublicSurface(): void
    {
        foreach (['fetchOdpisAktualny', 'fetchOdpisPelny', 'isValidKrs'] as $method) {
            self::assertTrue(method_exists(KrsApiService::class, $method));
        }
        // KRS validation: 10 digits, ignores formatting.
        self::assertTrue(KrsApiService::isValidKrs('0000123456'));
        self::assertTrue(KrsApiService::isValidKrs('0000-123-456'));
        self::assertFalse(KrsApiService::isValidKrs('123'));
        self::assertFalse(KrsApiService::isValidKrs(''));
    }

    public function testCrbrServicePublicSurface(): void
    {
        foreach (['fetchByNip', 'fetchByKrs'] as $method) {
            self::assertTrue(method_exists(CrbrApiService::class, $method));
        }
    }
}
