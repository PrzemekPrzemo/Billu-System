<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Services\CompanyLookupService;
use App\Services\CeidgApiService;
use App\Services\CrbrApiService;
use App\Services\GusApiService;
use App\Services\KrsApiService;
use PHPUnit\Framework\TestCase;

/**
 * Pins the orchestrator's routing logic without hitting real APIs:
 *  - GUS-first; type='F' routes to CEIDG; KRS number → KRS API
 *  - merge precedence KRS > CEIDG > GUS
 *  - persistence requires the full context tuple — partial = autofill only
 *
 * Note: persistNotes() writes to the DB, so persistence-positive tests
 * are intentionally NOT run here — they belong to PR-F's integration
 * suite. This file validates the routing + merge logic that does NOT
 * require a database.
 */
final class CompanyLookupServiceTest extends TestCase
{
    public function testLegacyStaticApiSurfaceUntouched(): void
    {
        // Existing controllers call this — the signature must not change.
        self::assertTrue(method_exists(CompanyLookupService::class, 'findByNip'));
        $rm = new \ReflectionMethod(CompanyLookupService::class, 'findByNip');
        self::assertTrue($rm->isStatic(),
            'CompanyLookupService::findByNip MUST stay static for back-compat');
        self::assertSame(1, $rm->getNumberOfRequiredParameters());
    }

    public function testEnrichedLookupReturnsAllSourceKeys(): void
    {
        $svc = $this->makeOrchestrator(
            gusReturn:   ['company_name' => 'X', 'nip' => '5252123456', 'type' => 'P', 'krs' => '0000123456'],
            ceidgReturn: null,
            krsReturn:   ['odpis' => ['dane' => ['dzial1' => ['danePodmiotu' => ['nazwa' => 'KRS Name']]]]],
        );

        $r = $svc->enrichedLookup('5252123456');
        self::assertArrayHasKey('gus',           $r);
        self::assertArrayHasKey('ceidg',         $r);
        self::assertArrayHasKey('krs',           $r);
        self::assertArrayHasKey('merged',        $r);
        self::assertArrayHasKey('notes_created', $r);
        self::assertArrayHasKey('errors',        $r);
        self::assertSame([], $r['notes_created'], 'No context passed → no DB writes');
    }

    public function testKrsOverridesGusInMerge(): void
    {
        $svc = $this->makeOrchestrator(
            gusReturn: [
                'company_name' => 'Stara nazwa z GUS',
                'nip' => '5252123456',
                'type' => 'P',
                'krs' => '0000123456',
            ],
            krsReturn: [
                'odpis' => ['dane' => ['dzial1' => [
                    'danePodmiotu' => ['nazwa' => 'Nowa nazwa z KRS', 'identyfikatory' => ['nip' => '5252123456']],
                ]]],
            ],
        );

        $r = $svc->enrichedLookup('5252123456');
        self::assertSame('Nowa nazwa z KRS', $r['merged']['company_name'],
            'KRS must override GUS in merged autofill output');
    }

    public function testTypeFRoutesToCeidgNotKrs(): void
    {
        $svc = $this->makeOrchestrator(
            gusReturn:   ['nip' => '5252123456', 'type' => 'F', 'krs' => '', 'company_name' => 'JDG Tester'],
            ceidgReturn: ['nip' => '5252123456', 'company_name' => 'JDG Tester z CEIDG'],
        );

        $r = $svc->enrichedLookup('5252123456');
        self::assertNotNull($r['ceidg']);
        self::assertNull($r['krs'], 'JDG must not trigger KRS lookup');
    }

    public function testInvalidKrsNumberSkippedSilently(): void
    {
        $svc = $this->makeOrchestrator(
            gusReturn: ['nip' => '5252123456', 'type' => 'P', 'krs' => 'NOT-A-KRS'],
        );

        $r = $svc->enrichedLookup('5252123456');
        self::assertNull($r['krs'], 'Malformed KRS field must NOT reach KRS API');
        self::assertSame([], $r['errors']);
    }

    public function testGusFailureIsSoftAndAttemptCeidgFallback(): void
    {
        $svc = $this->makeOrchestrator(
            gusReturn:   null, // throws below
            gusThrows:   new \RuntimeException('GUS down'),
            ceidgReturn: ['nip' => '5252123456', 'company_name' => 'CEIDG fallback'],
        );

        $r = $svc->enrichedLookup('5252123456');
        self::assertNull($r['gus']);
        self::assertNotEmpty($r['errors']);
        self::assertStringContainsString('gus:', $r['errors'][0]);
        self::assertNotNull($r['ceidg'], 'When GUS fails, CEIDG is tried as last resort');
    }

    public function testPartialContextDoesNotPersist(): void
    {
        $svc = $this->makeOrchestrator(
            gusReturn: ['nip' => '5252123456', 'type' => 'P', 'krs' => '', 'company_name' => 'X'],
        );

        // Context missing actor_* — must NOT trigger persist (would crash on DB).
        $r = $svc->enrichedLookup('5252123456', [
            'client_id' => 1, 'target_type' => 'client', 'target_id' => 1,
        ]);
        self::assertSame([], $r['notes_created']);
    }

    /**
     * Builds an orchestrator with stubbed dependencies. No real HTTP /
     * DB calls happen even when the methods are exercised.
     */
    private function makeOrchestrator(
        ?array $gusReturn   = null,
        ?array $ceidgReturn = null,
        ?array $krsReturn   = null,
        ?\Throwable $gusThrows = null,
    ): CompanyLookupService {
        $gus = new class($gusReturn, $gusThrows) extends GusApiService {
            public function __construct(private ?array $stub, private ?\Throwable $throws) {}
            public function findByNip(string $nip): ?array {
                if ($this->throws) { throw $this->throws; }
                return $this->stub;
            }
            public function isConfigured(): bool { return true; }
        };
        $ceidg = new class($ceidgReturn) extends CeidgApiService {
            public function __construct(private ?array $stub) {}
            public function findByNip(string $nip): ?array { return $this->stub; }
            public function isConfigured(): bool { return true; }
        };
        $krs = new class($krsReturn) extends KrsApiService {
            public function __construct(private ?array $stub) {}
            public function fetchOdpisAktualny(string $krs, string $rejestr = 'P'): ?array { return $this->stub; }
        };
        $crbr = new class extends CrbrApiService {
            public function fetchByNip(string $nip): ?array { return null; }
            public function fetchByKrs(string $krs): ?array { return null; }
        };

        return new CompanyLookupService($gus, $ceidg, $krs, $crbr);
    }
}
