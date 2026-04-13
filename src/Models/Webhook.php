<?php

namespace App\Models;

use App\Core\Database;

class Webhook
{
    public static function findAll(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT w.*, c.company_name, c.nip FROM webhooks w LEFT JOIN clients c ON w.client_id = c.id ORDER BY w.created_at DESC"
        );
    }

    public static function findActive(?int $clientId = null): array
    {
        $sql = "SELECT * FROM webhooks WHERE is_active = 1";
        $params = [];
        if ($clientId !== null) {
            $sql .= " AND (client_id IS NULL OR client_id = ?)";
            $params[] = $clientId;
        }
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('webhooks', $data);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM webhooks WHERE id = ?", [$id]);
    }

    public static function toggle(int $id): void
    {
        Database::getInstance()->query(
            "UPDATE webhooks SET is_active = NOT is_active WHERE id = ?",
            [$id]
        );
    }

    public static function updateLastTriggered(int $id, int $statusCode): void
    {
        Database::getInstance()->update('webhooks', [
            'last_triggered_at' => date('Y-m-d H:i:s'),
            'last_status_code' => $statusCode,
        ], 'id = ?', [$id]);
    }

    public static function logDelivery(int $webhookId, string $event, string $payload, ?int $responseCode, ?string $responseBody, int $durationMs): void
    {
        Database::getInstance()->insert('webhook_logs', [
            'webhook_id' => $webhookId,
            'event' => $event,
            'payload' => $payload,
            'response_code' => $responseCode,
            'response_body' => $responseBody ? mb_substr($responseBody, 0, 2000) : null,
            'duration_ms' => $durationMs,
        ]);
    }
}
