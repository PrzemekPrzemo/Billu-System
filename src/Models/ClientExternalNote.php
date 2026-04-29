<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Append-only register lookup notes (GUS / KRS / CEIDG / CRBR / e-US).
 *
 * Tenant scope: every read goes through client_id first. The companion
 * accessor findByIdForOffice() joins clients.office_id so a stray ID
 * from another office can never resolve.
 *
 * NEVER mass-assignable: client_id, target_id, target_type, raw_json,
 * formatted_html, fetched_by_*. Notes are only created via the dedicated
 * append() helper, which the orchestrator service drives.
 */
class ClientExternalNote
{
    public const SOURCES = ['gus', 'krs', 'ceidg', 'crbr', 'eus', 'manual'];
    public const TARGETS = ['client', 'contractor'];

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_external_notes WHERE id = ?",
            [$id]
        );
    }

    /**
     * Office-scoped lookup. Joins clients.office_id so a note from another
     * office cannot resolve even if its id was guessed.
     */
    public static function findByIdForOffice(int $id, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT n.*
               FROM client_external_notes n
               JOIN clients c ON c.id = n.client_id
              WHERE n.id = ? AND c.office_id = ?",
            [$id, $officeId]
        );
    }

    /**
     * Latest note for a target (client or contractor). $source filters
     * to a single register, or NULL for any source.
     */
    public static function findLatestForTarget(string $targetType, int $targetId, ?string $source = null): ?array
    {
        if (!in_array($targetType, self::TARGETS, true)) {
            return null;
        }
        $sql = "SELECT * FROM client_external_notes
                 WHERE target_type = ? AND target_id = ?";
        $params = [$targetType, $targetId];
        if ($source !== null) {
            if (!in_array($source, self::SOURCES, true)) {
                return null;
            }
            $sql .= " AND source = ?";
            $params[] = $source;
        }
        $sql .= " ORDER BY fetched_at DESC LIMIT 1";
        return Database::getInstance()->fetchOne($sql, $params);
    }

    /**
     * Notes history for a target, newest first. Caller must verify
     * tenant ownership (e.g. via Client::findByIdForOffice) BEFORE
     * calling this — the helper itself does not gate on office_id
     * because callers may be in client context too.
     */
    public static function findHistoryForTarget(string $targetType, int $targetId, int $limit = 50): array
    {
        if (!in_array($targetType, self::TARGETS, true)) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        return Database::getInstance()->fetchAll(
            "SELECT * FROM client_external_notes
              WHERE target_type = ? AND target_id = ?
              ORDER BY fetched_at DESC
              LIMIT {$limit}",
            [$targetType, $targetId]
        );
    }

    /**
     * Office-scoped history with a tenant gate (joins clients.office_id).
     * Use this from office controllers — it cannot return rows from
     * another office even if target_id collides.
     */
    public static function findHistoryForOffice(string $targetType, int $targetId, int $officeId, int $limit = 50): array
    {
        if (!in_array($targetType, self::TARGETS, true)) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        return Database::getInstance()->fetchAll(
            "SELECT n.*
               FROM client_external_notes n
               JOIN clients c ON c.id = n.client_id
              WHERE n.target_type = ? AND n.target_id = ? AND c.office_id = ?
              ORDER BY n.fetched_at DESC
              LIMIT {$limit}",
            [$targetType, $targetId, $officeId]
        );
    }

    /**
     * Append a note. The only public path that writes to this table —
     * controllers MUST NOT call insert() / update() directly so the
     * privilege fields cannot be forged from a form.
     *
     * @param array  $rawJson        decoded API response (already PII-redacted)
     * @param string $formattedHtml  pre-rendered display HTML
     */
    public static function append(
        int $clientId,
        string $targetType,
        int $targetId,
        string $source,
        ?string $sourceRef,
        array $rawJson,
        string $formattedHtml,
        string $fetchedByType,
        int $fetchedById
    ): int {
        if (!in_array($targetType, self::TARGETS, true)) {
            throw new \InvalidArgumentException("Invalid target_type: {$targetType}");
        }
        if (!in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException("Invalid source: {$source}");
        }
        return Database::getInstance()->insert('client_external_notes', [
            'client_id'       => $clientId,
            'target_type'     => $targetType,
            'target_id'       => $targetId,
            'source'          => $source,
            'source_ref'      => $sourceRef,
            'raw_json'        => json_encode($rawJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'formatted_html'  => $formattedHtml,
            'fetched_at'      => date('Y-m-d H:i:s'),
            'fetched_by_type' => $fetchedByType,
            'fetched_by_id'   => $fetchedById,
        ]);
    }
}
