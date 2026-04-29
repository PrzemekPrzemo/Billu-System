<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AuditLog;
use App\Models\ContractForm;
use App\Models\ContractSigningEvent;
use App\Services\SigniusApiService;

/**
 * Receives webhooks from SIGNIUS Professional. Auth = HMAC-SHA256 over the
 * raw request body using SIGNIUS_WEBHOOK_SECRET.
 *
 * Idempotency: every event lands in contract_signing_events. Terminal
 * status changes (signed/rejected/expired) are applied to contract_forms
 * only if no prior event of the same type exists for that form — so SIGNIUS
 * retrying the same webhook (or a network-replay) doesn't overwrite the
 * already-stored signed PDF.
 */
class SigniusWebhookController extends Controller
{
    public function handle(): void
    {
        $rawBody = (string) file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_X_SIGNIUS_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '');

        if (!SigniusApiService::verifyWebhookSignature($rawBody, $signature)) {
            error_log('SIGNIUS webhook: bad signature from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'bad_signature']);
            return;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'bad_json']);
            return;
        }

        $packageId = (string) ($payload['package_id'] ?? $payload['packageId'] ?? '');
        $eventType = strtolower((string) ($payload['event'] ?? $payload['event_type'] ?? ''));
        $signerEmail = isset($payload['signer']['email']) ? (string) $payload['signer']['email']
                       : (string) ($payload['signer_email'] ?? '');

        if ($packageId === '' || $eventType === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'missing_fields']);
            return;
        }

        $form = $this->findFormByPackageId($packageId);
        if (!$form) {
            // Unknown package — log and accept. SIGNIUS will not retry.
            error_log('SIGNIUS webhook: unknown package ' . $packageId);
            http_response_code(202);
            echo json_encode(['ok' => true, 'note' => 'unknown_package']);
            return;
        }
        $formId = (int) $form['id'];

        // Always record the raw event.
        ContractSigningEvent::create($formId, $eventType, $signerEmail !== '' ? $signerEmail : null, $payload);

        // Terminal transitions are idempotent: rely on the row's current status,
        // since the event row above is always inserted (audit trail keeps every
        // duplicate webhook). A second 'signed' webhook arriving for an already
        // 'signed' form is logged but does NOT redownload / overwrite the PDF.
        if ($eventType === 'signed' && $form['status'] !== 'signed') {
            $this->finalizeSigned($form, $packageId);
        } elseif ($eventType === 'rejected' && $form['status'] !== 'rejected') {
            ContractForm::setStatus($formId, 'rejected');
            AuditLog::log('signius', 0, 'contract_rejected', "Form {$formId} rejected", 'contract_form', $formId);
        } elseif ($eventType === 'expired' && $form['status'] !== 'expired') {
            ContractForm::setStatus($formId, 'expired');
            AuditLog::log('signius', 0, 'contract_expired', "Form {$formId} expired by SIGNIUS", 'contract_form', $formId);
        }

        http_response_code(200);
        echo json_encode(['ok' => true]);
    }

    // ─────────────────────────────────────────────

    private function findFormByPackageId(string $packageId): ?array
    {
        return \App\Core\Database::getInstance()->fetchOne(
            "SELECT * FROM contract_forms WHERE signius_package_id = ? LIMIT 1",
            [$packageId]
        );
    }

    private function finalizeSigned(array $form, string $packageId): void
    {
        $cfg = require __DIR__ . '/../../config/contracts.php';
        $relDir = trim((string) $cfg['template_storage_dir'], '/')
            . '/' . (int) $form['office_id'] . '/signed';
        $absDir = __DIR__ . '/../../' . $relDir;
        if (!is_dir($absDir)) mkdir($absDir, 0750, true);

        $signedRel = $relDir . '/form_' . (int) $form['id'] . '.pdf';
        $signedAbs = __DIR__ . '/../../' . $signedRel;

        try {
            SigniusApiService::downloadSignedPdf($packageId, $signedAbs);
        } catch (\Throwable $e) {
            error_log('SIGNIUS download signed PDF failed: ' . $e->getMessage());
            // Don't transition status — let the office retry via UI button.
            return;
        }

        ContractForm::attachSignedPdf((int) $form['id'], $signedRel);
        AuditLog::log('signius', 0, 'contract_signed',
            "Form {$form['id']} signed (package {$packageId})",
            'contract_form', (int) $form['id']);
    }
}
