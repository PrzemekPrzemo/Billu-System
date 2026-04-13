<?php

namespace App\Services;

use App\Models\Webhook;

class WebhookService
{
    public static function dispatch(string $event, array $data, ?int $clientId = null): void
    {
        $webhooks = Webhook::findActive($clientId);

        foreach ($webhooks as $wh) {
            $events = explode(',', $wh['events']);
            if ($wh['events'] !== 'all' && !in_array($event, $events)) {
                continue;
            }

            $payload = json_encode([
                'event' => $event,
                'timestamp' => date('c'),
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);

            $signature = hash_hmac('sha256', $payload, $wh['secret']);

            $startTime = microtime(true);
            $responseCode = null;
            $responseBody = null;

            try {
                $ch = curl_init($wh['url']);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'X-Webhook-Event: ' . $event,
                        'X-Webhook-Signature: sha256=' . $signature,
                        'User-Agent: BiLLU/4.0',
                    ],
                ]);
                $responseBody = curl_exec($ch);
                $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            } catch (\Throwable $e) {
                $responseBody = $e->getMessage();
            }

            $durationMs = (int)((microtime(true) - $startTime) * 1000);
            Webhook::updateLastTriggered((int)$wh['id'], $responseCode ?? 0);
            Webhook::logDelivery((int)$wh['id'], $event, $payload, $responseCode, $responseBody, $durationMs);
        }
    }
}
