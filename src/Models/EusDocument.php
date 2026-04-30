<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Canonical registry of every e-US document — submissions to Bramka B
 * (declarations like JPK_V7M) and incoming letters from Bramka C
 * (KAS correspondence).
 *
 * Mass-assignment policy: there is NO FILLABLE constant. Documents
 * are created via the dedicated factories below (queueOutbound,
 * recordIncoming) — controllers MUST use those instead of insert()
 * so privilege fields (status, reference_no, retain_until) cannot
 * be forged from a form.
 */
class EusDocument
{
    public const BRAMKI     = ['B', 'C'];
    public const DIRECTIONS = ['out', 'in'];
    public const STATUSES   = [
        'queued', 'signed', 'submitted',
        'przyjety', 'zaakceptowany', 'odrzucony',
        'received', 'replied', 'error',
    ];

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM eus_documents WHERE id = ?",
            [$id]
        );
    }

    public static function findByIdForOffice(int $id, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM eus_documents WHERE id = ? AND office_id = ?",
            [$id, $officeId]
        );
    }

    public static function findByReference(string $referenceNo): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM eus_documents WHERE reference_no = ?",
            [$referenceNo]
        );
    }

    /**
     * Existing outbound submission for (client, doc_kind, period). Used
     * by the submission-queue idempotency guard — re-submitting the
     * same period must reuse / refuse, never create a duplicate row.
     */
    public static function findOutboundForPeriod(int $clientId, string $docKind, string $period): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM eus_documents
              WHERE client_id = ?
                AND doc_kind = ?
                AND related_period = ?
                AND direction = 'out'",
            [$clientId, $docKind, $period]
        );
    }

    /**
     * History per client for the office UI. Returns rows newest first.
     * Cap is enforced (max 200) so a malicious client_id cannot pull
     * the entire table.
     */
    public static function findHistoryForOffice(int $clientId, int $officeId, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        return Database::getInstance()->fetchAll(
            "SELECT * FROM eus_documents
              WHERE client_id = ? AND office_id = ?
              ORDER BY created_at DESC
              LIMIT {$limit}",
            [$clientId, $officeId]
        );
    }

    /**
     * Factory: queue an outbound submission. Returns the new id.
     * Idempotency: throws if a non-rejected outbound for the same
     * (client, doc_kind, period) already exists.
     */
    public static function queueOutbound(
        int $clientId,
        int $officeId,
        string $bramka,
        string $docKind,
        string $period,
        string $payloadPath,
        ?int $relatedStatusId = null
    ): int {
        if (!in_array($bramka, self::BRAMKI, true)) {
            throw new \InvalidArgumentException("Invalid bramka: {$bramka}");
        }

        $existing = self::findOutboundForPeriod($clientId, $docKind, $period);
        if ($existing !== null && !in_array($existing['status'], ['odrzucony', 'error'], true)) {
            throw new \RuntimeException(
                "Outbound {$docKind} for period {$period} already exists in state '{$existing['status']}' (id {$existing['id']})"
            );
        }

        // Replace a previously rejected attempt rather than spawning a
        // duplicate row — the UNIQUE index would refuse otherwise.
        if ($existing !== null) {
            Database::getInstance()->update(
                'eus_documents',
                [
                    'status'         => 'queued',
                    'status_message' => null,
                    'payload_path'   => $payloadPath,
                    'reference_no'   => null,
                    'upo_path'       => null,
                    'submitted_at'   => null,
                    'finalized_at'   => null,
                ],
                'id = ?',
                [$existing['id']]
            );
            return (int) $existing['id'];
        }

        return Database::getInstance()->insert('eus_documents', [
            'client_id'         => $clientId,
            'office_id'         => $officeId,
            'bramka'            => $bramka,
            'direction'         => 'out',
            'doc_kind'          => $docKind,
            'related_period'    => $period,
            'related_status_id' => $relatedStatusId,
            'payload_path'      => $payloadPath,
            'status'            => 'queued',
        ]);
    }

    /**
     * Factory: record an incoming KAS letter from Bramka C. Sets
     * retain_until to today + $retentionYears years (default 10y per
     * KSH/RODO interaction). Dedup via reference_no.
     */
    public static function recordIncoming(
        int $clientId,
        int $officeId,
        string $docKind,
        string $referenceNo,
        string $payloadPath,
        ?\DateTimeInterface $externalReceivedAt = null,
        int $retentionYears = 10
    ): int {
        $existing = self::findByReference($referenceNo);
        if ($existing !== null && (int) $existing['client_id'] === $clientId) {
            return (int) $existing['id'];
        }

        $retainUntil = (new \DateTimeImmutable('today'))
            ->modify("+{$retentionYears} years")
            ->format('Y-m-d');

        return Database::getInstance()->insert('eus_documents', [
            'client_id'             => $clientId,
            'office_id'             => $officeId,
            'bramka'                => 'C',
            'direction'             => 'in',
            'doc_kind'              => $docKind,
            'reference_no'          => $referenceNo,
            'payload_path'          => $payloadPath,
            'status'                => 'received',
            'external_received_at'  => $externalReceivedAt
                ? $externalReceivedAt->format('Y-m-d H:i:s')
                : date('Y-m-d H:i:s'),
            'retain_until'          => $retainUntil,
        ]);
    }

    /**
     * Single canonical setter for status transitions — the submission
     * service / poll worker call this rather than touching the table
     * directly. Optionally also sets reference_no / upo_path / message_id /
     * task_id when the corresponding event happens.
     */
    public static function transitionStatus(
        int $id,
        string $newStatus,
        ?string $message = null,
        array $extra = []
    ): void {
        if (!in_array($newStatus, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }

        $data = ['status' => $newStatus];
        if ($message !== null) {
            $data['status_message'] = $message;
        }

        // Whitelist the optional extras — keep the controller surface tight.
        $allowedExtra = ['reference_no', 'upo_path', 'message_id', 'task_id', 'submitted_at', 'finalized_at'];
        foreach ($extra as $k => $v) {
            if (in_array($k, $allowedExtra, true)) {
                $data[$k] = $v;
            }
        }

        Database::getInstance()->update('eus_documents', $data, 'id = ?', [$id]);
    }

    /**
     * Returns rows past their retain_until that have not been soft-purged.
     * Used by scripts/eus_retention_purge.php (PR-5).
     */
    public static function findExpiredRetention(int $limit = 500): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM eus_documents
              WHERE retain_until IS NOT NULL
                AND retain_until < CURDATE()
                AND purged_at IS NULL
              ORDER BY retain_until ASC
              LIMIT {$limit}"
        );
    }

    /**
     * Soft-purge: keep the row id (FK integrity), null payload + UPO,
     * mark purged_at. Reads can detect tombstones by purged_at IS NOT NULL.
     */
    public static function softPurge(int $id): void
    {
        Database::getInstance()->update(
            'eus_documents',
            [
                'payload_path' => null,
                'upo_path'     => null,
                'status_message' => '[purged after retention]',
                'purged_at'    => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$id]
        );
    }

    /**
     * Used by RodoDeleteService to refuse deletion when active
     * KAS retention exists.
     */
    public static function hasActiveRetention(int $clientId): bool
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT 1 FROM eus_documents
              WHERE client_id = ?
                AND retain_until IS NOT NULL
                AND retain_until > CURDATE()
                AND purged_at IS NULL
              LIMIT 1",
            [$clientId]
        );
        return $row !== null;
    }
}
