<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\AuditLog;
use App\Models\ClientCostCenter;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceComment;
use App\Services\PurchaseInvoicePdfService;

class PurchaseInvoiceApiController
{
    // GET /api/v1/invoices?batch_id=&status=&search=&page=&per_page=
    public function index(array $params, ?int $clientId): void
    {
        $batchId  = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : null;
        $status   = $_GET['status'] ?? null;
        $search   = $_GET['search'] ?? null;
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));

        if ($batchId !== null) {
            // Verify batch belongs to client
            $batch = InvoiceBatch::findById($batchId);
            if (!$batch || (int) $batch['client_id'] !== $clientId) {
                ApiResponse::notFound('batch_not_found');
            }
            $invoices = Invoice::findByBatchFiltered($batchId, $status ?: null, $search ?: null);
        } else {
            $invoices = Invoice::findByClient($clientId, $status ?: null);
            if ($search) {
                $search = mb_strtolower($search);
                $invoices = array_filter($invoices, function ($inv) use ($search) {
                    return str_contains(mb_strtolower($inv['seller_name'] ?? ''), $search)
                        || str_contains($inv['invoice_number'] ?? '', $search);
                });
                $invoices = array_values($invoices);
            }
        }

        $total  = count($invoices);
        $offset = ($page - 1) * $perPage;
        $items  = array_slice($invoices, $offset, $perPage);

        ApiResponse::paginated(
            array_map([$this, 'formatInvoiceSummary'], $items),
            $page,
            $perPage,
            $total
        );
    }

    // GET /api/v1/invoices/{id}
    public function show(array $params, ?int $clientId): void
    {
        $invoice = $this->findInvoiceForClient((int) $params['id'], $clientId);
        ApiResponse::success($this->formatInvoiceFull($invoice));
    }

    // POST /api/v1/invoices/{id}/verify
    // Body: {action: "accept"|"reject", cost_center_id?: int, comment?: string}
    public function verify(array $params, ?int $clientId): void
    {
        $invoice = $this->findInvoiceForClient((int) $params['id'], $clientId);
        $body    = $this->getJsonBody();
        $action  = $body['action'] ?? '';

        if (!in_array($action, ['accept', 'reject'], true)) {
            ApiResponse::validation(['action' => 'must be accept or reject']);
        }

        // Check batch is not finalized
        $batch = InvoiceBatch::findById((int) $invoice['batch_id']);
        if ($batch && $batch['is_finalized']) {
            ApiResponse::error(422, 'batch_finalized', 'Cannot modify invoices in a finalized batch');
        }

        $status       = $action === 'accept' ? 'accepted' : 'rejected';
        $comment      = trim($body['comment'] ?? '');
        $costCenterId = isset($body['cost_center_id']) ? (int) $body['cost_center_id'] : null;
        $costCenterName = null;

        if ($costCenterId) {
            $cc = ClientCostCenter::findById($costCenterId);
            if ($cc && (int) $cc['client_id'] === $clientId) {
                $costCenterName = $cc['name'];
            }
        }

        Invoice::updateStatus(
            (int) $invoice['id'],
            $status,
            $comment ?: null,
            $costCenterName,
            $costCenterId
        );

        if ($comment) {
            InvoiceComment::create((int) $invoice['id'], 'client', $clientId, $comment);
        }

        AuditLog::log('client', $clientId, 'api_invoice_' . $status,
            "Invoice #{$invoice['invoice_number']} {$status} via mobile", 'invoice', (int) $invoice['id']);

        $updated = Invoice::findById((int) $invoice['id']);
        ApiResponse::success($this->formatInvoiceFull($updated));
    }

    // POST /api/v1/invoices/{id}/comment
    // Body: {message: string}
    public function addComment(array $params, ?int $clientId): void
    {
        $invoice = $this->findInvoiceForClient((int) $params['id'], $clientId);
        $body    = $this->getJsonBody();
        $message = trim($body['message'] ?? '');

        if (empty($message)) {
            ApiResponse::validation(['message' => 'required']);
        }

        $commentId = InvoiceComment::create((int) $invoice['id'], 'client', $clientId, $message);

        ApiResponse::created([
            'id'         => $commentId,
            'message'    => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // GET /api/v1/invoices/{id}/comments
    public function comments(array $params, ?int $clientId): void
    {
        $invoice  = $this->findInvoiceForClient((int) $params['id'], $clientId);
        $comments = InvoiceComment::findByInvoice((int) $invoice['id']);

        ApiResponse::success(array_map(fn($c) => [
            'id'         => (int) $c['id'],
            'message'    => $c['message'],
            'user_type'  => $c['user_type'],
            'user_name'  => InvoiceComment::getUserName($c['user_type'], (int) $c['user_id']),
            'created_at' => $c['created_at'],
        ], $comments));
    }

    // PATCH /api/v1/invoices/{id}/cost-center
    // Body: {cost_center_id: int}
    public function setCostCenter(array $params, ?int $clientId): void
    {
        $invoice      = $this->findInvoiceForClient((int) $params['id'], $clientId);
        $body         = $this->getJsonBody();
        $costCenterId = isset($body['cost_center_id']) ? (int) $body['cost_center_id'] : null;

        $costCenterName = null;
        if ($costCenterId) {
            $cc = ClientCostCenter::findById($costCenterId);
            if (!$cc || (int) $cc['client_id'] !== $clientId) {
                ApiResponse::notFound('cost_center_not_found');
            }
            $costCenterName = $cc['name'];
        }

        Invoice::updateFields((int) $invoice['id'], [
            'cost_center_id'   => $costCenterId,
            'cost_center_name' => $costCenterName,
        ]);

        ApiResponse::success(['updated' => true]);
    }

    // PATCH /api/v1/invoices/{id}/paid
    public function togglePaid(array $params, ?int $clientId): void
    {
        $invoice = $this->findInvoiceForClient((int) $params['id'], $clientId);

        $newPaid = $invoice['is_paid'] ? 0 : 1;
        Invoice::updateFields((int) $invoice['id'], ['is_paid' => $newPaid]);

        ApiResponse::success(['is_paid' => (bool) $newPaid]);
    }

    // GET /api/v1/invoices/{id}/pdf
    public function pdf(array $params, ?int $clientId): void
    {
        $invoice = $this->findInvoiceForClient((int) $params['id'], $clientId);

        try {
            while (ob_get_level()) ob_end_clean();

            $path     = PurchaseInvoicePdfService::generate((int) $invoice['id']);
            $filename = 'FZ_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice['invoice_number']) . '.pdf';

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            ApiResponse::error(500, 'pdf_generation_failed', $e->getMessage());
        }
    }

    // POST /api/v1/invoices/bulk-verify
    // Body: {ids: [int], action: "accept"|"reject", cost_center_id?: int, comment?: string}
    public function bulkVerify(array $params, ?int $clientId): void
    {
        $body   = $this->getJsonBody();
        $ids    = array_map('intval', array_filter($body['ids'] ?? []));
        $action = $body['action'] ?? '';

        if (empty($ids)) {
            ApiResponse::validation(['ids' => 'required, must be non-empty array']);
        }

        if (!in_array($action, ['accept', 'reject'], true)) {
            ApiResponse::validation(['action' => 'must be accept or reject']);
        }

        $status = $action === 'accept' ? 'accepted' : 'rejected';
        $comment = trim($body['comment'] ?? '');
        $costCenterId = isset($body['cost_center_id']) ? (int) $body['cost_center_id'] : null;
        $costCenterName = null;

        if ($costCenterId) {
            $cc = ClientCostCenter::findById($costCenterId);
            if ($cc && (int) $cc['client_id'] === $clientId) {
                $costCenterName = $cc['name'];
            }
        }

        $processed = 0;
        $errors    = [];

        foreach ($ids as $id) {
            $invoice = Invoice::findById($id);
            if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
                $errors[] = $id;
                continue;
            }

            $batch = InvoiceBatch::findById((int) $invoice['batch_id']);
            if ($batch && $batch['is_finalized']) {
                $errors[] = $id;
                continue;
            }

            Invoice::updateStatus($id, $status, $comment ?: null, $costCenterName, $costCenterId);
            $processed++;
        }

        ApiResponse::success([
            'processed' => $processed,
            'failed'    => $errors,
        ]);
    }

    // ── Helpers ────────────────────────────────────

    private function findInvoiceForClient(int $invoiceId, int $clientId): array
    {
        $invoice = Invoice::findById($invoiceId);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            ApiResponse::notFound('invoice_not_found');
        }
        return $invoice;
    }

    private function formatInvoiceSummary(array $inv): array
    {
        return [
            'id'               => (int) $inv['id'],
            'batch_id'         => (int) ($inv['batch_id'] ?? 0),
            'invoice_number'   => $inv['invoice_number'],
            'seller_name'      => $inv['seller_name'],
            'seller_nip'       => $inv['seller_nip'] ?? null,
            'issue_date'       => $inv['issue_date'] ?? null,
            'net_amount'       => (float) ($inv['net_amount'] ?? 0),
            'vat_amount'       => (float) ($inv['vat_amount'] ?? 0),
            'gross_amount'     => (float) ($inv['gross_amount'] ?? 0),
            'currency'         => $inv['currency'] ?? 'PLN',
            'status'           => $inv['status'],
            'cost_center_id'   => $inv['cost_center_id'] ? (int) $inv['cost_center_id'] : null,
            'cost_center_name' => $inv['cost_center_name'] ?? null,
            'is_paid'          => (bool) ($inv['is_paid'] ?? false),
            'whitelist_failed' => (bool) ($inv['whitelist_failed'] ?? false),
            'has_ksef'         => !empty($inv['ksef_reference_number']),
        ];
    }

    private function formatInvoiceFull(array $inv): array
    {
        $comments = InvoiceComment::findByInvoice((int) $inv['id']);

        return array_merge($this->formatInvoiceSummary($inv), [
            'comment'              => $inv['comment'] ?? null,
            'verified_at'          => $inv['verified_at'] ?? null,
            'ksef_reference_number'=> $inv['ksef_reference_number'] ?? null,
            'xml_data'             => $inv['xml_data'] ?? null,
            'created_at'           => $inv['created_at'] ?? null,
            'comments'             => array_map(fn($c) => [
                'id'         => (int) $c['id'],
                'message'    => $c['message'],
                'user_type'  => $c['user_type'],
                'user_name'  => InvoiceComment::getUserName($c['user_type'], (int) $c['user_id']),
                'created_at' => $c['created_at'],
            ], $comments),
        ]);
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
