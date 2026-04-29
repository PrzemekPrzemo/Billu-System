<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClientExternalNote;
use App\Services\Formatters\CeidgNoteFormatter;
use App\Services\Formatters\CrbrNoteFormatter;
use App\Services\Formatters\GusNoteFormatter;
use App\Services\Formatters\KrsNoteFormatter;

/**
 * Unified company lookup — GUS / CEIDG / KRS / CRBR.
 *
 * The static findByNip() is the legacy public surface used by existing
 * controllers (autofill via GUS-with-CEIDG-fallback). It is preserved
 * unchanged so PR-D/E land without touching any controller.
 *
 * The instance methods below are the new orchestrator surface:
 *  - enrichedLookup() — GUS + KRS (when applicable) + CEIDG, optional
 *    persistence as office-only notes
 *  - lookupCrbr() — separate flow gated to office_admin (caller's job)
 *
 * Tests construct with mocked services; production code constructs with
 * defaults via the no-argument constructor or with new dependencies as
 * they're added (e.g. e-US in iteration 5).
 */
class CompanyLookupService
{
    private GusApiService   $gus;
    private CeidgApiService $ceidg;
    private KrsApiService   $krs;
    private CrbrApiService  $crbr;

    public function __construct(
        ?GusApiService   $gus   = null,
        ?CeidgApiService $ceidg = null,
        ?KrsApiService   $krs   = null,
        ?CrbrApiService  $crbr  = null,
    ) {
        $this->gus   = $gus   ?? new GusApiService();
        $this->ceidg = $ceidg ?? new CeidgApiService();
        $this->krs   = $krs   ?? new KrsApiService();
        $this->crbr  = $crbr  ?? new CrbrApiService();
    }

    // ─────────────────────────────────────────────────────────────────
    // Legacy static API (back-compat — DO NOT MODIFY without auditing
    // every caller in src/Controllers/).
    // ─────────────────────────────────────────────────────────────────

    /**
     * Find company by NIP using GUS → CEIDG fallback chain.
     *
     * @return array|null Normalized company data with 'source' field,
     *                    or null if not found in either registry.
     */
    public static function findByNip(string $nip): ?array
    {
        // 1. Try GUS first
        $gus = new GusApiService();
        if ($gus->isConfigured()) {
            try {
                $data = $gus->findByNip($nip);
                if ($data && !empty($data['company_name'])) {
                    $data['source'] = 'gus';
                    return $data;
                }
            } catch (\RuntimeException $e) {
                error_log("CompanyLookup GUS error for NIP {$nip}: " . $e->getMessage());
            }
        }

        // 2. Fallback to CEIDG
        $ceidg = new CeidgApiService();
        if ($ceidg->isConfigured()) {
            try {
                $data = $ceidg->findByNip($nip);
                if ($data && !empty($data['company_name'])) {
                    $data['source'] = 'ceidg';
                    return $data;
                }
            } catch (\RuntimeException $e) {
                error_log("CompanyLookup CEIDG error for NIP {$nip}: " . $e->getMessage());
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // New orchestrator API (PR-E / PR-F).
    // ─────────────────────────────────────────────────────────────────

    /**
     * Enriched lookup. Returns a superset of the legacy GUS data plus
     * KRS / CEIDG enrichment, and optionally persists one note per
     * source (office-only).
     *
     * Result shape:
     *   [
     *     'gus'    => array|null,    // raw GUS response
     *     'ceidg'  => array|null,    // raw CEIDG response (JDG)
     *     'krs'    => array|null,    // raw KRS response
     *     'merged' => array,         // best-of-merge for autofill
     *     'notes_created' => int[],  // ClientExternalNote ids
     *     'errors' => string[],      // soft errors per source
     *   ]
     *
     * @param array{client_id?:int,target_type?:string,target_id?:int,actor_type?:string,actor_id?:int}|null $context
     */
    public function enrichedLookup(string $nip, ?array $context = null): array
    {
        $nip = preg_replace('/[^0-9]/', '', $nip) ?? '';
        $result = [
            'gus'           => null,
            'ceidg'         => null,
            'krs'           => null,
            'merged'        => [],
            'notes_created' => [],
            'errors'        => [],
        ];

        // 1) GUS — always first. Failure is soft so the autofill flow
        // can still degrade to CEIDG.
        try {
            $result['gus'] = $this->gus->findByNip($nip);
        } catch (\Throwable $e) {
            $result['errors'][] = 'gus: ' . $e->getMessage();
        }

        if ($result['gus'] === null) {
            try {
                $result['ceidg'] = $this->ceidg->findByNip($nip);
            } catch (\Throwable $e) {
                $result['errors'][] = 'ceidg: ' . $e->getMessage();
            }
        } else {
            $type = (string) ($result['gus']['type'] ?? '');
            $krs  = (string) ($result['gus']['krs']  ?? '');

            if ($type === 'F' || $type === 'LF') {
                try {
                    $result['ceidg'] = $this->ceidg->findByNip($nip);
                } catch (\Throwable $e) {
                    $result['errors'][] = 'ceidg: ' . $e->getMessage();
                }
            }
            if ($krs !== '' && KrsApiService::isValidKrs($krs)) {
                try {
                    $result['krs'] = $this->krs->fetchOdpisAktualny($krs);
                } catch (\Throwable $e) {
                    $result['errors'][] = 'krs: ' . $e->getMessage();
                }
            }
        }

        $result['merged'] = $this->mergeForAutofill($result);

        if ($this->shouldPersist($context)) {
            $result['notes_created'] = $this->persistNotes($result, $context);
        }

        return $result;
    }

    /**
     * CRBR is a separate flow — caller MUST gate to office_admin
     * before invoking. Always persists a note when the API returns data.
     *
     * @param array{client_id:int,target_type:string,target_id:int,actor_type:string,actor_id:int} $context
     */
    public function lookupCrbr(string $identifier, array $context, bool $isKrs = false): ?array
    {
        try {
            $crbr = $isKrs
                ? $this->crbr->fetchByKrs($identifier)
                : $this->crbr->fetchByNip($identifier);
        } catch (\Throwable) {
            return null;
        }
        if ($crbr === null) {
            return null;
        }

        ClientExternalNote::append(
            (int) $context['client_id'],
            (string) $context['target_type'],
            (int) $context['target_id'],
            'crbr',
            $identifier,
            $crbr,
            CrbrNoteFormatter::format($crbr),
            (string) $context['actor_type'],
            (int) $context['actor_id'],
        );
        return $crbr;
    }

    // ─────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────

    /** KRS > CEIDG > GUS — KRS reflects the latest court entry. */
    private function mergeForAutofill(array $r): array
    {
        $merged = is_array($r['gus']) ? $r['gus'] : [];

        if (is_array($r['ceidg'])) {
            foreach ($r['ceidg'] as $k => $v) {
                if ($v !== '' && $v !== null) {
                    $merged[$k] = $v;
                }
            }
        }
        if (is_array($r['krs'])) {
            foreach ($this->flattenKrs($r['krs']) as $k => $v) {
                if ($v !== '' && $v !== null) {
                    $merged[$k] = $v;
                }
            }
        }
        return $merged;
    }

    private function flattenKrs(array $krs): array
    {
        $dane     = $krs['odpis']['dane']['dzial1'] ?? [];
        $danePodm = $dane['danePodmiotu']  ?? [];
        $adres    = $dane['siedzibaIAdres']['adres'] ?? [];
        $kapital  = $dane['kapital']       ?? [];

        return [
            'company_name' => (string) ($danePodm['nazwa'] ?? ''),
            'krs'          => (string) ($danePodm['identyfikatory']['krs']   ?? ''),
            'regon'        => (string) ($danePodm['identyfikatory']['regon'] ?? ''),
            'nip'          => (string) ($danePodm['identyfikatory']['nip']   ?? ''),
            'street'       => (string) ($adres['ulica']        ?? ''),
            'building_no'  => (string) ($adres['nrDomu']       ?? ''),
            'apartment_no' => (string) ($adres['nrLokalu']     ?? ''),
            'postal_code'  => (string) ($adres['kodPocztowy']  ?? ''),
            'city'         => (string) ($adres['miejscowosc']  ?? ''),
            'capital'      => (string) ($kapital['wysokoscKapitaluZakladowego']['wartosc'] ?? ''),
            'legal_form'   => (string) ($danePodm['formaPrawna'] ?? ''),
        ];
    }

    private function shouldPersist(?array $context): bool
    {
        if ($context === null) {
            return false;
        }
        return isset(
            $context['client_id'], $context['target_type'], $context['target_id'],
            $context['actor_type'], $context['actor_id']
        );
    }

    /** @return int[] inserted note ids */
    private function persistNotes(array $result, array $context): array
    {
        $ids = [];
        $sources = [
            'gus'   => ['data' => $result['gus'],   'fmt' => [GusNoteFormatter::class,   'format']],
            'ceidg' => ['data' => $result['ceidg'], 'fmt' => [CeidgNoteFormatter::class, 'format']],
            'krs'   => ['data' => $result['krs'],   'fmt' => [KrsNoteFormatter::class,   'format']],
        ];
        foreach ($sources as $source => $cfg) {
            if (!is_array($cfg['data']) || empty($cfg['data'])) {
                continue;
            }
            $html = call_user_func($cfg['fmt'], $cfg['data']);
            $ids[] = ClientExternalNote::append(
                (int)    $context['client_id'],
                (string) $context['target_type'],
                (int)    $context['target_id'],
                $source,
                (string) ($cfg['data']['nip'] ?? $cfg['data']['krs'] ?? null),
                $cfg['data'],
                $html,
                (string) $context['actor_type'],
                (int)    $context['actor_id'],
            );
        }
        return $ids;
    }
}
