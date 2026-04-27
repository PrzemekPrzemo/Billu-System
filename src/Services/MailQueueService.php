<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Asynchroniczna kolejka maili.
 *
 * Wzorzec użycia: w kontrolerze wołasz MailQueueService::enqueue(...)
 * - request kończy się natychmiast.
 * Worker (mail-worker.php uruchamiany przez cron co minutę) zdejmuje
 * pending i wysyła przez MailService::createSimpleMail().
 *
 * Retry policy: max 3 próby z exponential backoff (1min, 5min, 30min).
 */
final class MailQueueService
{
    private const MAX_RETRIES = 3;
    private const BACKOFF_MINUTES = [1, 5, 30];

    /**
     * Dodaje maila do kolejki. Zwraca ID rekordu.
     */
    public static function enqueue(
        string $to,
        string $subject,
        string $htmlBody,
        ?int $clientId = null,
        ?string $scheduledAt = null
    ): int {
        return Database::getInstance()->insert('mail_queue', [
            'to_email'     => $to,
            'subject'      => $subject,
            'html_body'    => $htmlBody,
            'client_id'    => $clientId,
            'status'       => 'pending',
            'scheduled_at' => $scheduledAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Przetwarza paczkę pending maili (wywoływane przez worker).
     * Używa SELECT ... FOR UPDATE SKIP LOCKED żeby wielu workerów nie kolidowało.
     *
     * @return array{processed:int, sent:int, failed:int, errors:array<int,string>}
     */
    public static function processBatch(int $batchSize = 10): array
    {
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'errors' => []];
        $db = Database::getInstance();

        $now = date('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $rows = $db->fetchAll(
                "SELECT id FROM mail_queue
                 WHERE status = 'pending' AND scheduled_at <= ?
                 ORDER BY scheduled_at ASC
                 LIMIT ?
                 FOR UPDATE SKIP LOCKED",
                [$now, $batchSize]
            );

            if (empty($rows)) {
                $db->commit();
                return $result;
            }

            $ids = array_map(fn($r) => (int)$r['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->query(
                "UPDATE mail_queue SET status = 'processing' WHERE id IN ({$placeholders})",
                $ids
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $result['errors'][] = 'lock_failed: ' . $e->getMessage();
            return $result;
        }

        // Wysyłka poza transakcją - SMTP może długo trwać.
        foreach ($ids as $id) {
            $row = $db->fetchOne("SELECT * FROM mail_queue WHERE id = ?", [$id]);
            if (!$row) {
                continue;
            }
            $result['processed']++;

            try {
                $clientId = $row['client_id'] !== null ? (int)$row['client_id'] : null;
                $ok = MailService::createSimpleMail(
                    (string)$row['to_email'],
                    (string)$row['subject'],
                    (string)$row['html_body'],
                    $clientId
                );

                if ($ok) {
                    $db->update('mail_queue', [
                        'status'  => 'sent',
                        'sent_at' => date('Y-m-d H:i:s'),
                        'error_message' => null,
                    ], 'id = ?', [$id]);
                    $result['sent']++;
                } else {
                    self::handleFailure($id, (int)$row['retry_count'], 'send_returned_false', $result);
                }
            } catch (\Throwable $e) {
                self::handleFailure($id, (int)$row['retry_count'], $e->getMessage(), $result);
            }
        }

        return $result;
    }

    private static function handleFailure(int $id, int $retryCount, string $error, array &$result): void
    {
        $db = Database::getInstance();
        $nextRetry = $retryCount + 1;

        if ($nextRetry >= self::MAX_RETRIES) {
            $db->update('mail_queue', [
                'status'        => 'failed',
                'retry_count'   => $nextRetry,
                'error_message' => $error,
            ], 'id = ?', [$id]);
            $result['failed']++;
        } else {
            $minutes = self::BACKOFF_MINUTES[$retryCount] ?? 30;
            $next = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
            $db->update('mail_queue', [
                'status'        => 'pending',
                'retry_count'   => $nextRetry,
                'scheduled_at'  => $next,
                'error_message' => $error,
            ], 'id = ?', [$id]);
        }
        $result['errors'][] = "mail_queue_id={$id}: {$error}";
    }

    /**
     * Czyści stare wpisy (sent > 30 dni, failed > 90 dni).
     * Wywoływane przez CronService::cleanupOldData.
     */
    public static function cleanup(): array
    {
        $db = Database::getInstance();
        $sentDeleted = $db->query(
            "DELETE FROM mail_queue WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->rowCount();
        $failedDeleted = $db->query(
            "DELETE FROM mail_queue WHERE status = 'failed' AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        )->rowCount();
        return ['sent_deleted' => $sentDeleted, 'failed_deleted' => $failedDeleted];
    }
}
