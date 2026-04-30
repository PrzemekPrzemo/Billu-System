<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Audit trail of every e-US API call. SEPARATE from audit_log so the
 * 37-day retention policy applied to audit_log does NOT touch e-US
 * operations — these may need to be referenced years later for
 * KAS dispute resolution.
 *
 * Sensitive payload bytes (signed XML, raw responses) are NOT stored
 * here. Only short request/response excerpts (4kB max) with tokens
 * masked by EusLogger before reaching this layer.
 */
class EusOperationLog
{
    public static function record(
        ?int $documentId,
        ?int $clientId,
        ?int $userId,
        ?string $userType,
        ?string $sessionId,
        string $operation,
        ?string $requestExcerpt = null,
        ?string $responseExcerpt = null,
        ?int $httpStatus = null,
        ?int $durationMs = null
    ): int {
        return Database::getInstance()->insert('eus_operations_log', [
            'document_id'      => $documentId,
            'client_id'        => $clientId,
            'user_id'          => $userId,
            'user_type'        => $userType,
            'session_id'       => $sessionId,
            'operation'        => $operation,
            'request_excerpt'  => $requestExcerpt !== null ? mb_substr($requestExcerpt, 0, 4096) : null,
            'response_excerpt' => $responseExcerpt !== null ? mb_substr($responseExcerpt, 0, 4096) : null,
            'http_status'      => $httpStatus,
            'duration_ms'      => $durationMs,
        ]);
    }

    /**
     * Operations for a single document (timeline view in UI).
     */
    public static function findByDocument(int $documentId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM eus_operations_log
              WHERE document_id = ?
              ORDER BY created_at ASC, id ASC",
            [$documentId]
        );
    }

    /**
     * Operations for a single auth session — used by the master
     * dashboard to inspect a flaky cert auth flow.
     */
    public static function findBySession(string $sessionId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM eus_operations_log
              WHERE session_id = ?
              ORDER BY created_at ASC, id ASC",
            [$sessionId]
        );
    }

    /**
     * Recent operations for a client — surfaced in office UI under
     * "Diagnostyka". Limited to 200 rows.
     */
    public static function findRecentForClient(int $clientId, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        return Database::getInstance()->fetchAll(
            "SELECT * FROM eus_operations_log
              WHERE client_id = ?
              ORDER BY created_at DESC
              LIMIT {$limit}",
            [$clientId]
        );
    }
}
