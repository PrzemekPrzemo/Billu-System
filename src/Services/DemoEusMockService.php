<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Predictable mock responses for the e-Urząd Skarbowy integration.
 *
 * Activated when client_eus_configs.environment = 'mock'. PR-2 wires
 * the office "Test connection" button + UPL-1 form to it; PR-3/PR-4
 * extend with submission + correspondence simulators.
 *
 * Why a mock at all (versus pointing dev at the real test sandbox)?
 *   1. Sandbox access requires MF onboarding (cert + agreement) —
 *      blocks dev work for weeks.
 *   2. Predictable responses make CI integration tests possible.
 *   3. Dev can simulate edge cases (rejected JPK, expired cert,
 *      delayed UPO, KAS letter with deadline) deterministically.
 *
 * Switch to environment='test' is a single setting change once the
 * sandbox cert is provisioned. No code change.
 */
class DemoEusMockService
{
    /** Health response — always green in mock environment. */
    public static function health(string $bramka): array
    {
        $b = strtoupper($bramka) === 'C' ? 'C' : 'B';
        return [
            'ok'          => true,
            'http_status' => 200,
            'message'     => "[MOCK] Bramka {$b} dostępna (środowisko mock)",
            'duration_ms' => 5,
        ];
    }

    /**
     * PR-3 will use this to simulate a JPK_V7M submission lifecycle.
     * Returns synthesized reference number that the poller advances
     * through statuses based on time elapsed since submission.
     */
    public static function submitJpk(string $period): array
    {
        $ref = 'MOCK-V7M-' . str_replace('-', '', $period) . '-' . substr(uniqid('', true), -6);
        return [
            'reference_no' => $ref,
            'status'       => 'submitted',
            'received_at'  => date('Y-m-d H:i:s'),
            'message'      => '[MOCK] Submission accepted into queue',
        ];
    }

    /**
     * PR-3 status poller — deterministic transition timeline:
     *   t+0s  : submitted
     *   t+30s : przyjęty
     *   t+90s : zaakceptowany + UPO
     *
     * Real sandbox usually takes 2-15 minutes; we compress to seconds
     * for fast dev iteration.
     */
    public static function statusForReference(string $referenceNo, ?\DateTimeImmutable $submittedAt = null): array
    {
        $age = $submittedAt
            ? (time() - $submittedAt->getTimestamp())
            : 100;

        if ($age < 30) {
            return ['status' => 'submitted',     'upo' => null,            'message' => '[MOCK] queue'];
        }
        if ($age < 90) {
            return ['status' => 'przyjety',      'upo' => null,            'message' => '[MOCK] under review'];
        }
        return [
            'status'  => 'zaakceptowany',
            'upo'     => self::buildMockUpoXml($referenceNo),
            'message' => '[MOCK] UPO available',
        ];
    }

    /**
     * PR-4 KAS-letter generator. Returns 0..2 letters per poll. The
     * specific NIP suffix on the input controls behavior so tests can
     * pin exact scenarios:
     *   - NIP ending in 7 → 1 letter, no deadline
     *   - NIP ending in 8 → 1 letter with deadline (7 days)
     *   - NIP ending in 9 → 1 letter with deadline (3 days, urgent)
     *   - other           → no letters
     *
     * @return array<int,array<string,mixed>>
     */
    public static function pollKasLetters(string $nip): array
    {
        $tail = substr(preg_replace('/[^0-9]/', '', $nip) ?? '', -1);
        if (!in_array($tail, ['7', '8', '9'], true)) {
            return [];
        }
        $base = [
            'reference_no'         => 'MOCK-KAS-' . substr(uniqid('', true), -8),
            'doc_kind'             => 'KAS_letter',
            'subject'              => '[MOCK] Wezwanie do wyjaśnienia rozliczenia VAT',
            'urzad_name'           => '[MOCK] II Urząd Skarbowy w Warszawie',
            'received_at'          => date('Y-m-d H:i:s'),
            'requires_reply'       => false,
            'reply_deadline'       => null,
            'body'                 => "[MOCK] To jest treść wezwania. Termin reakcji: brak. NIP: {$nip}",
        ];
        if ($tail === '8') {
            $base['requires_reply']  = true;
            $base['reply_deadline']  = date('Y-m-d', strtotime('+7 days'));
            $base['body']            = "[MOCK] Wezwanie do uzupełnienia. Termin: 7 dni. NIP: {$nip}";
        }
        if ($tail === '9') {
            $base['requires_reply']  = true;
            $base['reply_deadline']  = date('Y-m-d', strtotime('+3 days'));
            $base['body']            = "[MOCK] PILNE. Wezwanie. Termin: 3 dni. NIP: {$nip}";
        }
        return [$base];
    }

    /**
     * Always-success reply submission for PR-4 dev iteration.
     */
    public static function submitReply(string $kasReferenceNo): array
    {
        return [
            'reply_reference_no' => 'MOCK-REPLY-' . substr(uniqid('', true), -8),
            'kas_reference_no'   => $kasReferenceNo,
            'status'             => 'zaakceptowany',
            'message'            => '[MOCK] Reply accepted',
        ];
    }

    private static function buildMockUpoXml(string $referenceNo): string
    {
        $now = date('c');
        return '<?xml version="1.0" encoding="UTF-8"?>'
             . '<UPO xmlns="http://crd.gov.pl/xml/schematy/UPO/2008/05/09/upo">'
             . '  <Identyfikator>' . htmlspecialchars($referenceNo, ENT_XML1) . '</Identyfikator>'
             . '  <DataPotwierdzenia>' . $now . '</DataPotwierdzenia>'
             . '  <Status>200 — Mock UPO</Status>'
             . '</UPO>';
    }
}
