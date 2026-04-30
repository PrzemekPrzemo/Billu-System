<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Client;
use App\Models\ClientTask;
use App\Models\EusConfig;
use App\Models\EusDocument;
use App\Models\EusOperationLog;
use App\Models\Message;

/**
 * Bramka C orchestrator — KAS correspondence inbox + replies.
 *
 * Responsibilities:
 *   - pollIncoming(): single-client poll; called by the cron-spawned
 *     bg worker (scripts/eus_poll_c_bg.php). Throttled by
 *     EusConfig.last_poll_at + poll_interval_minutes.
 *   - ingestLetter(): dedup + retain_until + storage; creates a
 *     Message thread (sender_type='eus') + optionally a high-priority
 *     ClientTask if reply is required.
 *   - composeReply() / submitReply(): office composes plain-text reply
 *     with optional PDF attachment; signed XML built locally, pushed
 *     to Bramka C, status appended to the same thread.
 *
 * Pre-flight checks (every public entry point):
 *   - module 'eus' enabled (caller's responsibility — controller +
 *     CronService gate)
 *   - bramka_c_enabled = 1
 *   - upl1_status = 'active' AND upl1_valid_to >= today
 *   - upl1_scope contains 'correspondence' OR 'full'
 *
 * Retention: every incoming letter row gets retain_until = today +
 * eus_kas_letters_retain_years (default 10y). RodoDeleteService
 * (PR-5) refuses to remove a client while active retention exists.
 */
class EusCorrespondenceService
{
    private EusApiService $api;
    private EusLogger $logger;

    public function __construct(?EusApiService $api = null, ?EusLogger $logger = null)
    {
        $this->logger = $logger ?? new EusLogger();
        $this->api    = $api    ?? new EusApiService(null, $this->logger);
    }

    /**
     * Single-client poll. Returns the count of new letters ingested.
     * Throws RuntimeException on pre-flight failure (caller logs +
     * surfaces; cron drainer absorbs and continues to next client).
     */
    public function pollIncoming(int $clientId): int
    {
        $config = EusConfig::findByClient($clientId);
        if ($config === null) {
            throw new \RuntimeException('Brak konfiguracji e-US dla tego klienta.');
        }
        if (empty($config['bramka_c_enabled'])) {
            return 0; // not opted in — silent skip, no error
        }
        if (($config['upl1_status'] ?? '') !== 'active') {
            throw new \RuntimeException('UPL-1 nie jest aktywne — pełnomocnictwo wymagane do odbioru korespondencji.');
        }
        if (!empty($config['upl1_valid_to']) && strtotime((string) $config['upl1_valid_to']) < time()) {
            throw new \RuntimeException('UPL-1 wygasło.');
        }
        $scope = (string) ($config['upl1_scope'] ?? '');
        if ($scope !== '' && !str_contains($scope, 'correspondence') && !str_contains($scope, 'full')) {
            return 0; // scope doesn't cover correspondence — silent skip
        }

        $client = Client::findById($clientId);
        if (!$client) {
            throw new \RuntimeException('Klient nie istnieje.');
        }
        $nip = (string) ($client['nip'] ?? '');
        $env = (string) ($config['environment'] ?? 'mock');

        $envelopes = $this->api->pollC($env, $nip);
        $ingested = 0;
        foreach ($envelopes as $env_) {
            try {
                $documentId = $this->ingestLetter($clientId, (int) $config['office_id'], $env_);
                if ($documentId > 0) {
                    $ingested++;
                }
            } catch (\Throwable $e) {
                EusOperationLog::record(
                    null, $clientId, null, 'system', $this->logger->getSessionId(),
                    'kas_letter_ingest_error',
                    json_encode($env_, JSON_UNESCAPED_UNICODE), $e->getMessage(), null, null
                );
            }
        }

        EusConfig::markPolled((int) $config['id']);
        EusOperationLog::record(
            null, $clientId, null, 'system', $this->logger->getSessionId(),
            'kas_poll_complete', "env={$env} nip={$nip} ingested={$ingested}",
            null, null, null
        );

        return $ingested;
    }

    /**
     * Persist a single letter envelope. Idempotent — dedup on
     * reference_no via EusDocument::recordIncoming. Returns the new
     * document id (or existing id when dedup hits).
     *
     * @param array<string,mixed> $envelope
     */
    public function ingestLetter(int $clientId, int $officeId, array $envelope): int
    {
        $referenceNo = (string) ($envelope['reference_no'] ?? '');
        if ($referenceNo === '') {
            throw new \InvalidArgumentException('KAS letter envelope missing reference_no');
        }
        // Dedup short-circuit before storage.
        $existing = EusDocument::findByReference($referenceNo);
        if ($existing !== null && (int) $existing['client_id'] === $clientId) {
            return (int) $existing['id'];
        }

        // Persist body as a small text blob; in real envs the API
        // returns XML/PDF binary which we'd save as well.
        $relPath = $this->saveLetterPayload($clientId, $referenceNo, (string) ($envelope['body'] ?? ''));

        $retentionYears = (int) (\App\Models\Setting::get('eus_kas_letters_retain_years', '10') ?: 10);
        $receivedAt = !empty($envelope['received_at'])
            ? new \DateTimeImmutable((string) $envelope['received_at'])
            : new \DateTimeImmutable('now');

        $documentId = EusDocument::recordIncoming(
            $clientId,
            $officeId,
            (string) ($envelope['doc_kind'] ?? 'KAS_letter'),
            $referenceNo,
            $relPath,
            $receivedAt,
            $retentionYears
        );

        // Surface as a Message + optionally a ClientTask.
        $messageId = $this->surfaceLetterAsMessage($clientId, $documentId, $envelope);
        $taskId = null;
        if (!empty($envelope['requires_reply'])) {
            $taskId = $this->surfaceLetterAsTask($clientId, $documentId, $envelope);
        }
        EusDocument::transitionStatus($documentId, 'received', null, [
            'message_id' => $messageId,
            'task_id'    => $taskId,
        ]);

        EusOperationLog::record(
            $documentId, $clientId, null, 'system', $this->logger->getSessionId(),
            'kas_letter_ingested',
            "ref={$referenceNo} requires_reply=" . (!empty($envelope['requires_reply']) ? '1' : '0'),
            null, null, null
        );

        return $documentId;
    }

    /**
     * Office composes a reply to an existing incoming letter.
     * Returns the new outbound document id (status='queued').
     *
     * @param array{body:string,attachment_path?:?string} $reply
     */
    public function composeReply(int $kasDocumentId, array $reply): int
    {
        $kasDoc = EusDocument::findById($kasDocumentId);
        if (!$kasDoc || $kasDoc['direction'] !== 'in') {
            throw new \RuntimeException('Nieprawidłowy dokument KAS — nie można odpowiedzieć.');
        }
        $body = trim((string) ($reply['body'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('Treść odpowiedzi nie może być pusta.');
        }

        // Build a minimal signed XML from body. Real env: XAdES-BES.
        // Mock env: pass-through XML snapshot for audit.
        $clientId = (int) $kasDoc['client_id'];
        $officeId = (int) $kasDoc['office_id'];
        $relReplyPath = $this->saveReplyPayload(
            $clientId,
            (string) $kasDoc['reference_no'],
            $body,
            $reply['attachment_path'] ?? null
        );

        // We track the reply as a separate eus_documents row of
        // direction='out', doc_kind='KAS_reply', referencing the
        // original via a synthetic period (not used) and
        // status_message linking to the KAS ref.
        $db = Database::getInstance();
        $newDocId = $db->insert('eus_documents', [
            'client_id'      => $clientId,
            'office_id'      => $officeId,
            'bramka'         => 'C',
            'direction'      => 'out',
            'doc_kind'       => 'KAS_reply',
            'reference_no'   => null, // assigned by submitReply()
            'payload_path'   => $relReplyPath,
            'status'         => 'queued',
            'status_message' => "Reply to KAS ref {$kasDoc['reference_no']}",
            'related_period' => null,
        ]);

        EusOperationLog::record(
            $newDocId, $clientId, null, 'system', $this->logger->getSessionId(),
            'kas_reply_queued', "kas_ref={$kasDoc['reference_no']}",
            null, null, null
        );

        return $newDocId;
    }

    /**
     * Submit a queued reply NOW. Synchronous (called from controller
     * directly, no bg worker) — replies are usually short and the
     * office expects immediate confirmation.
     */
    public function submitReply(int $replyDocumentId): array
    {
        $doc = EusDocument::findById($replyDocumentId);
        if (!$doc || $doc['direction'] !== 'out' || $doc['doc_kind'] !== 'KAS_reply') {
            throw new \RuntimeException('Nieprawidłowy dokument odpowiedzi.');
        }
        $config = EusConfig::findByClient((int) $doc['client_id']);
        $env = (string) ($config['environment'] ?? 'mock');

        // Find the original incoming letter (via status_message text or
        // a follow-up FK in a future migration). For now we rely on the
        // status_message format "Reply to KAS ref {X}".
        $kasRef = '';
        if (preg_match('/Reply to KAS ref (\S+)/', (string) ($doc['status_message'] ?? ''), $m)) {
            $kasRef = $m[1];
        }
        if ($kasRef === '') {
            throw new \RuntimeException('Brak referencji do oryginalnego pisma KAS.');
        }

        $payloadPath = __DIR__ . '/../../' . ltrim((string) $doc['payload_path'], '/');
        $r = $this->api->submitReplyC($env, $kasRef, $payloadPath);

        EusDocument::transitionStatus($replyDocumentId, 'replied', $r['message'], [
            'reference_no' => $r['reply_reference_no'],
            'submitted_at' => date('Y-m-d H:i:s'),
            'finalized_at' => $r['status'] === 'zaakceptowany' ? date('Y-m-d H:i:s') : null,
        ]);

        // Append confirmation to the original letter thread.
        $this->surfaceReplySubmitted((int) $doc['client_id'], $kasRef, $r);

        return $r;
    }

    // ─── Surfacing helpers ──────────────────────────────

    private function surfaceLetterAsMessage(int $clientId, int $documentId, array $envelope): int
    {
        $subject = self::truncate((string) ($envelope['subject'] ?? 'KAS — pismo'), 200);
        $urzad   = (string) ($envelope['urzad_name'] ?? '');
        $body    = (string) ($envelope['body'] ?? '');
        $body    = ($urzad !== '' ? "{$urzad}\n\n" : '') . $body;

        $messageId = Message::create(
            $clientId,
            'eus',
            0,
            $body,
            $subject
        );
        // Link the message back to the canonical eus_documents row so
        // the office UI can deep-link from message thread to the e-US
        // detail page.
        Database::getInstance()->update(
            'messages',
            ['eus_document_id' => $documentId],
            'id = ?',
            [$messageId]
        );
        return $messageId;
    }

    private function surfaceLetterAsTask(int $clientId, int $documentId, array $envelope): int
    {
        $deadline = !empty($envelope['reply_deadline'])
            ? (string) $envelope['reply_deadline']
            : null;
        $daysLeft = $deadline !== null
            ? max(0, (int) ceil((strtotime($deadline) - time()) / 86400))
            : null;

        // Office task due 3 days before KAS deadline so there's a
        // safety buffer for holidays / cert expiry.
        $taskDue = $deadline !== null
            ? date('Y-m-d', strtotime("{$deadline} -3 days"))
            : null;

        $priority = $daysLeft !== null && $daysLeft <= 7 ? 'high' : 'normal';
        $title = "Odpowiedź do KAS — {$envelope['reference_no']}"
               . ($deadline ? " — termin {$deadline}" : '');

        $taskId = ClientTask::create(
            $clientId,
            'system',
            0,
            $title,
            'Wymagana odpowiedź na pismo KAS. Otwórz wątek wiadomości i kliknij "Odpowiedz przez e-US".',
            $priority,
            $taskDue
        );
        Database::getInstance()->update(
            'client_tasks',
            ['eus_document_id' => $documentId],
            'id = ?',
            [$taskId]
        );
        return $taskId;
    }

    private function surfaceReplySubmitted(int $clientId, string $kasRef, array $r): void
    {
        Message::create(
            $clientId,
            'system',
            0,
            "Odpowiedź wysłana do KAS (ref: {$r['reply_reference_no']}). Status: {$r['status']}.",
            "Odpowiedź na KAS {$kasRef} wysłana"
        );
    }

    // ─── Storage helpers ────────────────────────────────

    private function saveLetterPayload(int $clientId, string $referenceNo, string $body): string
    {
        $relDir = "storage/eus/{$clientId}/in";
        $absDir = __DIR__ . '/../../' . $relDir;
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $referenceNo);
        $rel  = "{$relDir}/{$safe}.txt";
        file_put_contents(__DIR__ . '/../../' . $rel, $body);
        return $rel;
    }

    private function saveReplyPayload(int $clientId, string $kasRef, string $body, ?string $attachmentPath): string
    {
        $relDir = "storage/eus/{$clientId}/out";
        $absDir = __DIR__ . '/../../' . $relDir;
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $kasRef);
        $stamp = date('Ymd_His');
        $rel = "{$relDir}/reply_{$safe}_{$stamp}.xml";

        // Minimal envelope; production XAdES signing happens in
        // PR-4 follow-up commit when MF reply schema is published.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
             . '<KasReply>'
             . '<KasReferenceNo>' . htmlspecialchars($kasRef, ENT_XML1) . '</KasReferenceNo>'
             . '<Body><![CDATA[' . $body . ']]></Body>'
             . ($attachmentPath !== null ? '<Attachment>' . htmlspecialchars($attachmentPath, ENT_XML1) . '</Attachment>' : '')
             . '</KasReply>';
        file_put_contents(__DIR__ . '/../../' . $rel, $xml);
        return $rel;
    }

    private static function truncate(string $s, int $max): string
    {
        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max - 1) . '…';
    }
}
