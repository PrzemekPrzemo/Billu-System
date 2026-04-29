<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\AuditLog;
use App\Models\IssuedInvoice;
use App\Services\InvoicePdfService;
use App\Services\KsefInvoiceSendService;
use App\Core\Database;

class SalesInvoiceApiController
{
    // GET /api/v1/sales?status=&search=&month=&year=&page=&per_page=
    public function index(array $params, ?int $clientId): void
    {
        $status  = $_GET['status'] ?? null;
        $search  = $_GET['search'] ?? null;
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));

        $invoices = IssuedInvoice::findByClient($clientId, $status ?: null, $search ?: null);

        // Filter by month/year if provided
        if (isset($_GET['month'], $_GET['year'])) {
            $month = (int) $_GET['month'];
            $year  = (int) $_GET['year'];
            $invoices = array_filter($invoices, function ($inv) use ($month, $year) {
                $date = $inv['issue_date'] ?? '';
                if (!$date) return false;
                $d = \DateTime::createFromFormat('Y-m-d', $date);
                return $d && (int) $d->format('m') === $month && (int) $d->format('Y') === $year;
            });
            $invoices = array_values($invoices);
        }

        $total  = count($invoices);
        $offset = ($page - 1) * $perPage;
        $items  = array_slice($invoices, $offset, $perPage);

        ApiResponse::paginated(
            array_map([$this, 'formatSummary'], $items),
            $page,
            $perPage,
            $total
        );
    }

    // GET /api/v1/sales/{id}
    public function show(array $params, ?int $clientId): void
    {
        $invoice = $this->findForClient((int) $params['id'], $clientId);
        ApiResponse::success($this->formatFull($invoice));
    }

    // POST /api/v1/sales
    public function create(array $params, ?int $clientId): void
    {
        $body = $this->getJsonBody();
        $data = $this->validateAndBuildData($body, $clientId);

        $id = IssuedInvoice::create($data);

        AuditLog::log('client', $clientId, 'api_sales_created', "Sales invoice draft created via mobile", 'issued_invoice', $id);

        $invoice = IssuedInvoice::findById($id);
        ApiResponse::created($this->formatFull($invoice));
    }

    // PUT /api/v1/sales/{id}
    public function update(array $params, ?int $clientId): void
    {
        $invoice = $this->findForClient((int) $params['id'], $clientId);

        if ($invoice['status'] !== 'draft') {
            ApiResponse::error(422, 'cannot_edit', 'Only draft invoices can be edited');
        }

        $body = $this->getJsonBody();
        $data = $this->validateAndBuildData($body, $clientId, false);

        IssuedInvoice::update((int) $invoice['id'], $data, IssuedInvoice::FILLABLE);

        $updated = IssuedInvoice::findById((int) $invoice['id']);
        ApiResponse::success($this->formatFull($updated));
    }

    // DELETE /api/v1/sales/{id}
    public function destroy(array $params, ?int $clientId): void
    {
        $invoice = $this->findForClient((int) $params['id'], $clientId);

        if ($invoice['status'] !== 'draft') {
            ApiResponse::error(422, 'cannot_delete', 'Only draft invoices can be deleted');
        }

        IssuedInvoice::delete((int) $invoice['id']);

        AuditLog::log('client', $clientId, 'api_sales_deleted', "Sales invoice #{$invoice['invoice_number']} deleted via mobile");

        ApiResponse::noContent();
    }

    // POST /api/v1/sales/{id}/issue
    public function issue(array $params, ?int $clientId): void
    {
        $invoice = $this->findForClient((int) $params['id'], $clientId);

        if ($invoice['status'] !== 'draft') {
            ApiResponse::error(422, 'already_issued', 'Invoice is already issued');
        }

        IssuedInvoice::updateStatus((int) $invoice['id'], 'issued');

        AuditLog::log('client', $clientId, 'api_sales_issued', "Sales invoice #{$invoice['invoice_number']} issued via mobile", 'issued_invoice', (int) $invoice['id']);

        $updated = IssuedInvoice::findById((int) $invoice['id']);
        ApiResponse::success($this->formatFull($updated));
    }

    // POST /api/v1/sales/{id}/send-ksef
    public function sendKsef(array $params, ?int $clientId): void
    {
        $invoice = $this->findForClient((int) $params['id'], $clientId);

        if (!in_array($invoice['status'], ['issued'], true)) {
            ApiResponse::error(422, 'invoice_not_issued', 'Invoice must be issued before sending to KSeF');
        }

        try {
            $result = KsefInvoiceSendService::sendInvoice((int) $invoice['id']);

            AuditLog::log('client', $clientId, 'api_sales_ksef_sent', "Sales invoice #{$invoice['invoice_number']} sent to KSeF via mobile", 'issued_invoice', (int) $invoice['id']);

            $updated = IssuedInvoice::findById((int) $invoice['id']);
            ApiResponse::success(array_merge($this->formatFull($updated), ['ksef_result' => $result]));
        } catch (\Throwable $e) {
            ApiResponse::error(500, 'ksef_send_failed', $e->getMessage());
        }
    }

    // POST /api/v1/sales/{id}/duplicate
    public function duplicate(array $params, ?int $clientId): void
    {
        $invoice = $this->findForClient((int) $params['id'], $clientId);

        $data                  = $invoice;
        unset($data['id'], $data['created_at'], $data['updated_at']);
        $data['status']        = 'draft';
        $data['invoice_number'] = null;
        $data['issue_date']    = date('Y-m-d');
        $data['ksef_status']   = null;
        $data['ksef_reference_number'] = null;

        $newId   = IssuedInvoice::create($data);
        $newInv  = IssuedInvoice::findById($newId);

        AuditLog::log('client', $clientId, 'api_sales_duplicated', "Sales invoice duplicated via mobile", 'issued_invoice', $newId);

        ApiResponse::created($this->formatFull($newInv));
    }

    // GET /api/v1/sales/{id}/pdf
    public function pdf(array $params, ?int $clientId): void
    {
        $invoice = $this->findForClient((int) $params['id'], $clientId);

        try {
            while (ob_get_level()) ob_end_clean();

            $path     = InvoicePdfService::generate((int) $invoice['id']);
            $num      = $invoice['invoice_number'] ?? $invoice['id'];
            $filename = 'FS_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $num) . '.pdf';

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } catch (\Throwable $e) {
            ApiResponse::error(500, 'pdf_generation_failed', $e->getMessage());
        }
    }

    // ── Helpers ────────────────────────────────────

    private function findForClient(int $id, int $clientId): array
    {
        $invoice = IssuedInvoice::findById($id);
        if (!$invoice || (int) $invoice['client_id'] !== $clientId) {
            ApiResponse::notFound('invoice_not_found');
        }
        return $invoice;
    }

    private function validateAndBuildData(array $body, int $clientId, bool $requireBuyer = true): array
    {
        $fields = [];

        if ($requireBuyer && empty($body['buyer_name'])) {
            ApiResponse::validation(['buyer_name' => 'required']);
        }

        $fields['client_id']   = $clientId;
        $fields['status']      = 'draft';
        $fields['buyer_name']  = $body['buyer_name'] ?? null;
        $fields['buyer_nip']   = $body['buyer_nip'] ?? null;
        $fields['buyer_address'] = $body['buyer_address'] ?? null;
        $fields['issue_date']  = $body['issue_date'] ?? date('Y-m-d');
        $fields['due_date']    = $body['due_date'] ?? null;
        $fields['payment_method'] = $body['payment_method'] ?? 'przelew';
        $allowedCurrencies = ['PLN', 'EUR', 'USD'];
        $currency = strtoupper(trim($body['currency'] ?? 'PLN'));
        if (!in_array($currency, $allowedCurrencies, true)) {
            $currency = 'PLN';
        }
        $fields['currency']    = $currency;
        $fields['notes']       = $body['notes'] ?? null;

        // Line items (stored as JSON)
        if (!empty($body['items'])) {
            $fields['items_json'] = json_encode($body['items']);
        }

        // Calculate totals from items if provided
        if (!empty($body['items'])) {
            $net = $vat = $gross = 0;
            foreach ($body['items'] as $item) {
                $net   += (float) ($item['net_amount'] ?? 0);
                $vat   += (float) ($item['vat_amount'] ?? 0);
                $gross += (float) ($item['gross_amount'] ?? 0);
            }
            $fields['net_amount']   = $net;
            $fields['vat_amount']   = $vat;
            $fields['gross_amount'] = $gross;
        } else {
            $fields['net_amount']   = (float) ($body['net_amount'] ?? 0);
            $fields['vat_amount']   = (float) ($body['vat_amount'] ?? 0);
            $fields['gross_amount'] = (float) ($body['gross_amount'] ?? 0);
        }

        return $fields;
    }

    private function formatSummary(array $inv): array
    {
        return [
            'id'             => (int) $inv['id'],
            'invoice_number' => $inv['invoice_number'],
            'buyer_name'     => $inv['buyer_name'] ?? null,
            'buyer_nip'      => $inv['buyer_nip'] ?? null,
            'issue_date'     => $inv['issue_date'] ?? null,
            'due_date'       => $inv['due_date'] ?? null,
            'net_amount'     => (float) ($inv['net_amount'] ?? 0),
            'vat_amount'     => (float) ($inv['vat_amount'] ?? 0),
            'gross_amount'   => (float) ($inv['gross_amount'] ?? 0),
            'currency'       => $inv['currency'] ?? 'PLN',
            'status'         => $inv['status'],
            'ksef_status'    => $inv['ksef_status'] ?? null,
        ];
    }

    private function formatFull(array $inv): array
    {
        $items = [];
        if (!empty($inv['items_json'])) {
            $items = json_decode($inv['items_json'], true) ?? [];
        }

        return array_merge($this->formatSummary($inv), [
            'buyer_address'        => $inv['buyer_address'] ?? null,
            'payment_method'       => $inv['payment_method'] ?? null,
            'notes'                => $inv['notes'] ?? null,
            'items'                => $items,
            'ksef_reference_number'=> $inv['ksef_reference_number'] ?? null,
            'ksef_error'           => $inv['ksef_error'] ?? null,
            'created_at'           => $inv['created_at'] ?? null,
            'updated_at'           => $inv['updated_at'] ?? null,
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
