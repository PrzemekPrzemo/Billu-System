<?php

namespace App\Models;

use App\Core\Database;

/**
 * Append-only log of webhooks received from SIGNIUS for each contract_form.
 * Used both for audit and for idempotency: the webhook controller writes one
 * row per call, and the controller upgrades contract_forms.status only the
 * first time a terminal event ('signed', 'rejected', 'expired') arrives.
 */
class ContractSigningEvent
{
    public static function create(int $formId, string $eventType, ?string $signerEmail, array $payload): int
    {
        return Database::getInstance()->insert('contract_signing_events', [
            'form_id'      => $formId,
            'event_type'   => $eventType,
            'signer_email' => $signerEmail,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'received_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public static function findByForm(int $formId, int $limit = 50): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM contract_signing_events
             WHERE form_id = ?
             ORDER BY received_at DESC, id DESC
             LIMIT ?",
            [$formId, $limit]
        );
    }

    /** True iff at least one row of the given type already exists for this form. */
    public static function hasEvent(int $formId, string $eventType): bool
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT 1 FROM contract_signing_events
             WHERE form_id = ? AND event_type = ? LIMIT 1",
            [$formId, $eventType]
        );
        return $row !== null;
    }
}
