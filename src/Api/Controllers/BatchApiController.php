<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\Invoice;
use App\Models\InvoiceBatch;

class BatchApiController
{
    // GET /api/v1/batches
    public function index(array $params, ?int $clientId): void
    {
        $batches = InvoiceBatch::findByClient($clientId);

        $result = array_map(fn($b) => $this->formatBatch($b, $clientId), $batches);

        ApiResponse::success($result);
    }

    // GET /api/v1/batches/{id}
    public function show(array $params, ?int $clientId): void
    {
        $batch = $this->findBatchForClient((int) $params['id'], $clientId);

        $stats = Invoice::countByBatchAndStatus((int) $batch['id']);

        ApiResponse::success(array_merge($this->formatBatch($batch, $clientId), [
            'stats' => $stats,
        ]));
    }

    // ── Helpers ────────────────────────────────────

    private function findBatchForClient(int $batchId, int $clientId): array
    {
        $batch = InvoiceBatch::findById($batchId);
        if (!$batch || (int) $batch['client_id'] !== $clientId) {
            ApiResponse::notFound('batch_not_found');
        }
        return $batch;
    }

    private function formatBatch(array $batch, int $clientId): array
    {
        $stats   = Invoice::countByBatchAndStatus((int) $batch['id']);
        $total   = array_sum(array_column($stats, 'count'));
        $pending = 0;
        $accepted = 0;
        $rejected = 0;

        foreach ($stats as $s) {
            $count = (int) ($s['count'] ?? 0);
            match ($s['status'] ?? '') {
                'pending'  => $pending  = $count,
                'accepted' => $accepted = $count,
                'rejected' => $rejected = $count,
                default    => null,
            };
        }

        return [
            'id'                    => (int) $batch['id'],
            'period_month'          => (int) $batch['period_month'],
            'period_year'           => (int) $batch['period_year'],
            'verification_deadline' => $batch['verification_deadline'],
            'is_finalized'          => (bool) $batch['is_finalized'],
            'invoice_count'         => $total,
            'pending_count'         => $pending,
            'accepted_count'        => $accepted,
            'rejected_count'        => $rejected,
            'created_at'            => $batch['created_at'],
        ];
    }
}
