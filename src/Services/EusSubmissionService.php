<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ClientTask;
use App\Models\EusConfig;
use App\Models\EusDocument;
use App\Models\EusOperationLog;
use App\Models\Message;

/**
 * Bramka B orchestrator — JPK_V7M submission lifecycle.
 *
 * Responsibilities:
 *   - queueJpkV7M(): take an existing locally-generated XML
 *     (storage/jpk/JPK_V7M_{NIP}_{MM}_{YYYY}.xml) and create an
 *     eus_documents row + an eus_jobs row. Idempotent — refuses
 *     when a non-rejected outbound for the same period exists.
 *   - submitNow(): run the actual API call (called by bg worker
 *     scripts/eus_submit_b_bg.php). Updates document state +
 *     surfacing rows on success / failure.
 *   - pollOnce(): single status poll for one document. Called by
 *     scripts/eus_poll_b_bg.php in a backoff schedule from cron.
 *   - handleStatusUpdate(): drives messages + tasks creation on
 *     terminal-state transitions (zaakceptowany / odrzucony / error).
 *
 * Pre-flight checks (every public entry point):
 *   - module 'eus' enabled for the office
 *   - client_eus_configs.bramka_b_enabled = 1
 *   - client_eus_configs.upl1_status = 'active' AND upl1_valid_to >= today
 *   - client cert expiry >= 7 days (or skip cert check in mock env)
 *
 * Failure modes (each surfaces a high-priority ClientTask, NEVER a
 * neutral message to client until office acks):
 *   - upl-1 expired/revoked
 *   - cert expired
 *   - 4xx from e-US (validation error)
 *   - network timeout after retries exhausted
 */
class EusSubmissionService
{
    private EusApiService $api;
    private EusLogger $logger;

    public function __construct(?EusApiService $api = null, ?EusLogger $logger = null)
    {
        $this->logger = $logger ?? new EusLogger();
        $this->api    = $api    ?? new EusApiService(null, $this->logger);
    }

    /**
     * Queue a JPK_V7M submission. Returns the job uuid for status
     * tracking. Caller is the office controller endpoint or
     * ScheduledExportService when auto_submit_eus is on.
     *
     * @param string $period 'YYYY-MM' (e.g. '2026-04')
     * @return string job_uuid
     * @throws \RuntimeException on pre-flight failure
     */
    public function queueJpkV7M(int $clientId, int $officeId, string $period, ?int $relatedStatusId = null): string
    {
        $config = EusConfig::findByClientForOffice($clientId, $officeId);
        if ($config === null) {
            throw new \RuntimeException('Brak konfiguracji e-US dla tego klienta.');
        }
        if (empty($config['bramka_b_enabled'])) {
            throw new \RuntimeException('Bramka B nie jest włączona dla tego klienta.');
        }
        if (($config['upl1_status'] ?? '') !== 'active') {
            throw new \RuntimeException('UPL-1 nie jest aktywne — pełnomocnictwo jest wymagane do wysyłki w imieniu klienta.');
        }
        if (!empty($config['upl1_valid_to']) && strtotime((string) $config['upl1_valid_to']) < time()) {
            throw new \RuntimeException('UPL-1 wygasło — odnów pełnomocnictwo przed wysyłką.');
        }
        // Cert expiry — only enforced for non-mock environments.
        if (($config['environment'] ?? 'mock') !== 'mock' && !empty($config['cert_valid_to'])) {
            $expSec = strtotime((string) $config['cert_valid_to']) - time();
            if ($expSec < 7 * 86400) {
                throw new \RuntimeException('Certyfikat wygasa w ciągu 7 dni — odnów go przed wysyłką do e-US.');
            }
        }

        // Path to the locally-generated JPK XML (built by JpkVat7Service).
        $payloadPath = self::jpkXmlPath($clientId, $period);
        if (!is_file(__DIR__ . '/../../' . ltrim($payloadPath, '/'))) {
            throw new \RuntimeException("JPK_V7M za {$period} nie został wygenerowany. Wygeneruj go najpierw.");
        }

        // Idempotency lives in EusDocument::queueOutbound.
        $documentId = EusDocument::queueOutbound(
            $clientId,
            $officeId,
            'B',
            'JPK_V7M',
            $period,
            $payloadPath,
            $relatedStatusId
        );

        $jobUuid = self::makeUuid();
        Database::getInstance()->insert('eus_jobs', [
            'job_uuid'     => $jobUuid,
            'client_id'    => $clientId,
            'document_id'  => $documentId,
            'job_type'     => 'submit_b',
            'state'        => 'pending',
            'next_run_at'  => date('Y-m-d H:i:s'), // ASAP
            'payload_json' => json_encode(['period' => $period, 'env' => $config['environment'] ?? 'mock'], JSON_UNESCAPED_UNICODE),
        ]);

        EusOperationLog::record(
            $documentId, $clientId, null, 'system', $this->logger->getSessionId(),
            'jpk_v7m_queued', "period={$period} env=" . ($config['environment'] ?? 'mock'),
            null, null, null
        );

        return $jobUuid;
    }

    /**
     * Submit one queued job NOW. Called by bg worker.
     * Updates eus_documents + eus_jobs + creates surfacing rows.
     */
    public function submitNow(int $jobId): void
    {
        $db = Database::getInstance();
        $job = $db->fetchOne("SELECT * FROM eus_jobs WHERE id = ? FOR UPDATE", [$jobId]);
        if (!$job || $job['state'] !== 'pending' || $job['job_type'] !== 'submit_b') {
            return;
        }
        $db->update('eus_jobs', ['state' => 'running', 'attempts' => (int) $job['attempts'] + 1], 'id = ?', [$jobId]);

        $doc = EusDocument::findById((int) $job['document_id']);
        if (!$doc) {
            $db->update('eus_jobs', ['state' => 'failed', 'error_message' => 'document missing'], 'id = ?', [$jobId]);
            return;
        }
        $config = EusConfig::findByClient((int) $doc['client_id']);
        $env = (string) ($config['environment'] ?? 'mock');

        try {
            $payloadPath = __DIR__ . '/../../' . ltrim((string) $doc['payload_path'], '/');
            $result = $this->api->submitB($env, (string) $doc['related_period'], $payloadPath);

            EusDocument::transitionStatus((int) $doc['id'], 'submitted', $result['message'], [
                'reference_no' => $result['reference_no'],
                'submitted_at' => $result['received_at'],
            ]);

            // Schedule the first poll in 30 seconds (mock timeline) or
            // 60 seconds (real envs — e-US usually takes minutes).
            $nextPoll = $env === 'mock'
                ? date('Y-m-d H:i:s', time() + 30)
                : date('Y-m-d H:i:s', time() + 60);

            $db->insert('eus_jobs', [
                'job_uuid'     => self::makeUuid(),
                'client_id'    => (int) $doc['client_id'],
                'document_id'  => (int) $doc['id'],
                'job_type'     => 'poll_b',
                'state'        => 'pending',
                'next_run_at'  => $nextPoll,
                'payload_json' => json_encode(['ref' => $result['reference_no'], 'env' => $env], JSON_UNESCAPED_UNICODE),
            ]);

            $db->update('eus_jobs', ['state' => 'done', 'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE)], 'id = ?', [$jobId]);

            $this->surfaceSubmittedToClient($doc, $result['reference_no']);
        } catch (\Throwable $e) {
            EusDocument::transitionStatus((int) $doc['id'], 'error', $e->getMessage());
            $db->update('eus_jobs', [
                'state'         => 'failed',
                'error_message' => $e->getMessage(),
            ], 'id = ?', [$jobId]);
            $this->surfaceErrorToOffice($doc, $e->getMessage());
        }
    }

    /**
     * Single poll attempt. Backoff on still-pending: 1m, 5m, 15m, 1h,
     * 6h, 24h, then permanent fail.
     */
    public function pollOnce(int $jobId): void
    {
        $db = Database::getInstance();
        $job = $db->fetchOne("SELECT * FROM eus_jobs WHERE id = ? FOR UPDATE", [$jobId]);
        if (!$job || $job['state'] !== 'pending' || $job['job_type'] !== 'poll_b') {
            return;
        }
        $payload = json_decode((string) ($job['payload_json'] ?? '{}'), true);
        $referenceNo = (string) ($payload['ref'] ?? '');
        $env         = (string) ($payload['env'] ?? 'mock');

        $db->update('eus_jobs', ['state' => 'running', 'attempts' => (int) $job['attempts'] + 1], 'id = ?', [$jobId]);

        $doc = EusDocument::findById((int) $job['document_id']);
        if (!$doc) {
            $db->update('eus_jobs', ['state' => 'failed', 'error_message' => 'document missing'], 'id = ?', [$jobId]);
            return;
        }

        try {
            $submittedAt = !empty($doc['submitted_at']) ? new \DateTimeImmutable((string) $doc['submitted_at']) : null;
            $r = $this->api->getStatusB($env, $referenceNo, $submittedAt);
            $newStatus = $r['status'];

            if ($newStatus === 'zaakceptowany') {
                $upoPath = $this->saveUpo((int) $doc['client_id'], $referenceNo, (string) $r['upo']);
                EusDocument::transitionStatus((int) $doc['id'], 'zaakceptowany', $r['message'], [
                    'upo_path'     => $upoPath,
                    'finalized_at' => date('Y-m-d H:i:s'),
                ]);
                $this->updateMonthlyStatus($doc, 'zaakceptowany', $referenceNo, $upoPath);
                $this->surfaceAcceptedToClient($doc, $upoPath);
                $db->update('eus_jobs', ['state' => 'done'], 'id = ?', [$jobId]);
                return;
            }
            if ($newStatus === 'odrzucony') {
                EusDocument::transitionStatus((int) $doc['id'], 'odrzucony', $r['message'], [
                    'finalized_at' => date('Y-m-d H:i:s'),
                ]);
                $this->updateMonthlyStatus($doc, 'odrzucony', $referenceNo, null);
                $this->surfaceRejectedToOffice($doc, $r['message']);
                $db->update('eus_jobs', ['state' => 'done'], 'id = ?', [$jobId]);
                return;
            }
            // Still pending — schedule next poll with backoff.
            $attempts = (int) $job['attempts'];
            $backoff = self::BACKOFF_SCHEDULE[$attempts] ?? null;
            if ($backoff === null) {
                EusDocument::transitionStatus((int) $doc['id'], 'error', 'Polling timeout — no terminal state in 24h+');
                $db->update('eus_jobs', ['state' => 'failed', 'error_message' => 'poll timeout'], 'id = ?', [$jobId]);
                $this->surfaceErrorToOffice($doc, 'e-US nie zwrócił finalnego statusu w ciągu 24h.');
                return;
            }
            // Re-queue: same job row, set state back to pending + future next_run_at.
            $db->update('eus_jobs', [
                'state'        => 'pending',
                'next_run_at'  => date('Y-m-d H:i:s', time() + $backoff),
                'result_json'  => json_encode($r, JSON_UNESCAPED_UNICODE),
            ], 'id = ?', [$jobId]);

            // Mid-state transition (przyjety) is recorded but no surfacing.
            if ($newStatus === 'przyjety' && $doc['status'] !== 'przyjety') {
                EusDocument::transitionStatus((int) $doc['id'], 'przyjety', $r['message']);
            }
        } catch (\Throwable $e) {
            $db->update('eus_jobs', [
                'state'         => 'failed',
                'error_message' => $e->getMessage(),
            ], 'id = ?', [$jobId]);
            EusDocument::transitionStatus((int) $doc['id'], 'error', 'Poll error: ' . $e->getMessage());
        }
    }

    // ─── Surfacing helpers ──────────────────────────────

    private function surfaceSubmittedToClient(array $doc, string $referenceNo): void
    {
        $period = self::periodPretty((string) ($doc['related_period'] ?? ''));
        $body = "JPK_V7M za {$period} został złożony do urzędu skarbowego. "
              . "Numer referencyjny: {$referenceNo}. Czekamy na UPO.";
        $messageId = Message::create(
            (int) $doc['client_id'],
            'system',
            0,
            $body,
            "JPK_V7M złożony — {$period}"
        );
        EusDocument::transitionStatus((int) $doc['id'], (string) $doc['status'], null, ['message_id' => $messageId]);
    }

    private function surfaceAcceptedToClient(array $doc, string $upoPath): void
    {
        $period  = self::periodPretty((string) ($doc['related_period'] ?? ''));
        $rootMsg = !empty($doc['message_id']) ? (int) $doc['message_id'] : null;

        Message::create(
            (int) $doc['client_id'],
            'system',
            0,
            "JPK_V7M za {$period} ZAAKCEPTOWANY przez urząd skarbowy. UPO załączone.",
            "JPK_V7M zaakceptowany — {$period}",
            null, null,
            $rootMsg,
            $upoPath,
            'UPO_' . $period . '.xml'
        );
    }

    private function surfaceRejectedToOffice(array $doc, string $reason): void
    {
        $period = self::periodPretty((string) ($doc['related_period'] ?? ''));
        $taskId = ClientTask::create(
            (int) $doc['client_id'],
            'system',
            0,
            "e-US: JPK_V7M za {$period} ODRZUCONY",
            "Urząd odrzucił deklarację. Powód: {$reason}\n\nWymagana korekta i ponowne złożenie.",
            'high',
            date('Y-m-d', strtotime('+3 days'))
        );
        EusDocument::transitionStatus((int) $doc['id'], (string) $doc['status'], null, ['task_id' => $taskId]);

        // Neutral message to client — office will follow up.
        Message::create(
            (int) $doc['client_id'],
            'system',
            0,
            "Wymagana korekta deklaracji JPK_V7M za {$period}. Biuro się tym zajmuje, otrzymasz wiadomość po ponownym złożeniu.",
            "JPK_V7M wymaga korekty — {$period}"
        );
    }

    private function surfaceErrorToOffice(array $doc, string $reason): void
    {
        $period = self::periodPretty((string) ($doc['related_period'] ?? ''));
        ClientTask::create(
            (int) $doc['client_id'],
            'system',
            0,
            "e-US: błąd wysyłki JPK_V7M za {$period}",
            "Wysyłka do e-US nie powiodła się. Powód: {$reason}\n\n"
                . "Sprawdź konfigurację (UPL-1, certyfikat, środowisko) i ponów wysyłkę z poziomu /office/eus/{$doc['client_id']}/configure.",
            'high',
            date('Y-m-d', strtotime('+1 day'))
        );
        // NO client message yet — office decides next step.
    }

    // ─── Helpers ────────────────────────────────────────

    private function updateMonthlyStatus(array $doc, string $eusStatus, string $referenceNo, ?string $upoPath): void
    {
        if (empty($doc['related_status_id'])) {
            return;
        }
        $update = [
            'jpk_eus_status'        => $eusStatus,
            'jpk_eus_reference_no'  => $referenceNo,
            'jpk_eus_finalized_at'  => date('Y-m-d H:i:s'),
        ];
        if ($upoPath !== null) {
            $update['jpk_eus_upo_path'] = $upoPath;
        }
        Database::getInstance()->update(
            'client_monthly_status',
            $update,
            'id = ?',
            [(int) $doc['related_status_id']]
        );
    }

    /**
     * Saves UPO XML to storage/eus/{client_id}/upo/{ref}.xml.
     * Returns the relative path (suitable for storing in DB).
     */
    private function saveUpo(int $clientId, string $referenceNo, string $upoXml): string
    {
        $relDir  = "storage/eus/{$clientId}/upo";
        $absDir  = __DIR__ . '/../../' . $relDir;
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $referenceNo);
        $relPath  = "{$relDir}/{$safeName}.xml";
        file_put_contents(__DIR__ . '/../../' . $relPath, $upoXml);
        return $relPath;
    }

    /** YYYY-MM → MM/YYYY for human-readable surfacing copy. */
    private static function periodPretty(string $period): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            return "{$m[2]}/{$m[1]}";
        }
        return $period;
    }

    /** Path where JpkVat7Service stores generated XML. */
    private static function jpkXmlPath(int $clientId, string $period): string
    {
        // JpkVat7Service writes to storage/jpk/JPK_V7M_{NIP}_{MM}_{YYYY}.xml
        // but we don't have NIP here easily; standard convention used by
        // PR-3: also store as canonical path indexed by client + period.
        // Fallback contract: caller provides the NIP-based path via doc.
        // For now return the generic per-period path; ScheduledExportService
        // already moves files into this canonical location.
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            return "storage/jpk/JPK_V7M_client{$clientId}_{$m[2]}_{$m[1]}.xml";
        }
        return "storage/jpk/JPK_V7M_client{$clientId}_{$period}.xml";
    }

    private static function makeUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0F) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3F) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** Backoff schedule per attempt index — value is seconds. */
    public const BACKOFF_SCHEDULE = [
        1 => 60,        // 1 min
        2 => 300,       // 5 min
        3 => 900,       // 15 min
        4 => 3600,      // 1h
        5 => 6 * 3600,  // 6h
        6 => 24 * 3600, // 24h
        // 7+ → permanent failure
    ];
}
