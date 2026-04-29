<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\ModuleAccess;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ContractForm;
use App\Models\ContractSigningEvent;
use App\Models\ContractTemplate;
use App\Services\ContractFormService;
use App\Services\ContractPdfService;

/**
 * Office-side Contracts module. Templates CRUD + share-link issuance +
 * inspection of submitted/signed forms. All endpoints gated by
 * ModuleAccess::requireModule('contracts'). Office-employee users can
 * use the module against the clients they're assigned to (the share-link
 * issue endpoint cross-checks via Client::findById + office_id).
 */
class ContractController extends Controller
{
    // ── Dashboard ─────────────────────────────────

    public function dashboard(): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $templates = ContractTemplate::findActiveByOffice($officeId);
        $countsByStatus = self::countsByStatus($officeId);
        $recent = ContractForm::findByOfficePaginated($officeId, 0, 10);
        $this->render('office/contracts/dashboard', [
            'templates'      => $templates,
            'countsByStatus' => $countsByStatus,
            'recent'         => $recent,
        ]);
    }

    // ── Templates ────────────────────────────────

    public function templatesIndex(): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $templates = ContractTemplate::findAllByOffice($officeId);
        $this->render('office/contracts/templates_index', ['templates' => $templates]);
    }

    public function templateUploadForm(): void
    {
        ModuleAccess::requireModule('contracts');
        $this->render('office/contracts/template_form', [
            'template' => null,
            'fields'   => [],
            'signers'  => [],
        ]);
    }

    public function templateUpload(): void
    {
        ModuleAccess::requireModule('contracts');
        if (!$this->validateCsrf()) { $this->redirect('/office/contracts/templates'); return; }
        $officeId = (int) Session::get('office_id');

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            Session::flash('error', 'contracts_name_required');
            $this->redirect('/office/contracts/templates/upload'); return;
        }
        $slug = self::slugify($name);

        if (empty($_FILES['pdf']['tmp_name']) || ($_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'contracts_pdf_required');
            $this->redirect('/office/contracts/templates/upload'); return;
        }

        $cfg = require __DIR__ . '/../../config/contracts.php';
        $maxBytes = (int) ($cfg['template_max_size_mb'] ?? 10) * 1024 * 1024;
        if ($_FILES['pdf']['size'] > $maxBytes) {
            Session::flash('error', 'contracts_pdf_too_large');
            $this->redirect('/office/contracts/templates/upload'); return;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['pdf']['tmp_name']);
        if ($mime !== 'application/pdf') {
            Session::flash('error', 'contracts_pdf_invalid_mime');
            $this->redirect('/office/contracts/templates/upload'); return;
        }

        // Move into permanent storage with predictable path
        $relDir = trim((string) $cfg['template_storage_dir'], '/') . '/' . $officeId;
        $absDir = __DIR__ . '/../../' . $relDir;
        if (!is_dir($absDir)) mkdir($absDir, 0750, true);

        // Insert placeholder row to get id, then rename file with that id (slug-safe filename).
        try {
            $fields = ContractPdfService::parseFields($_FILES['pdf']['tmp_name']);
        } catch (\Throwable $e) {
            Session::flash('error', 'contracts_pdftk_failed');
            $this->redirect('/office/contracts/templates/upload'); return;
        }
        if (empty($fields)) {
            Session::flash('error', 'contracts_no_acroform_fields');
            $this->redirect('/office/contracts/templates/upload'); return;
        }

        // Default signers: one role 'client' that takes email from a field named 'email' if present.
        $emailField = '';
        foreach ($fields as $f) {
            if (in_array($f['name'], ['email', 'Email', 'e_mail', 'klient_email'], true)) {
                $emailField = $f['name']; break;
            }
        }
        $signers = [['role' => 'client', 'label' => 'Klient', 'email_field' => $emailField, 'order' => 1]];

        $tempAbs  = $absDir . '/__pending_' . bin2hex(random_bytes(6)) . '.pdf';
        if (!@move_uploaded_file($_FILES['pdf']['tmp_name'], $tempAbs)) {
            Session::flash('error', 'contracts_storage_failed');
            $this->redirect('/office/contracts/templates/upload'); return;
        }

        // Insert with the temp path; we know the id only after insert, so we
        // rename + patch stored_path right after.
        $tempRel = $relDir . '/' . basename($tempAbs);
        $newId = ContractTemplate::create($officeId, [
            'name'              => $name,
            'slug'              => $slug,
            'description'       => trim($_POST['description'] ?? ''),
            'is_active'         => 1,
            'original_filename' => substr((string) ($_FILES['pdf']['name'] ?? 'template.pdf'), 0, 255),
        ], $tempRel, $fields, $signers,
        Auth::currentUserType(), (int) Auth::currentUserId());

        $finalRel = $relDir . '/' . $newId . '_' . $slug . '.pdf';
        $finalAbs = __DIR__ . '/../../' . $finalRel;
        @rename($tempAbs, $finalAbs);
        @chmod($finalAbs, 0640);
        Database::getInstance()->update('contract_templates',
            ['stored_path' => $finalRel], 'id = ?', [$newId]);

        AuditLog::log(Auth::currentUserType(), (int) Auth::currentUserId(),
            'contract_template_uploaded',
            "Template '{$name}' (id {$newId}) uploaded with " . count($fields) . ' fields',
            'contract_template', $newId);

        Session::flash('success', 'contracts_template_uploaded');
        $this->redirect('/office/contracts/templates/' . $newId . '/edit');
    }

    public function templateEdit(int $id): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $tpl = ContractTemplate::findByIdForOffice($id, $officeId);
        if (!$tpl) { $this->redirect('/office/contracts/templates'); return; }
        $this->render('office/contracts/template_form', [
            'template' => $tpl,
            'fields'   => ContractTemplate::decodeFields($tpl),
            'signers'  => ContractTemplate::decodeSigners($tpl),
        ]);
    }

    public function templateUpdate(int $id): void
    {
        ModuleAccess::requireModule('contracts');
        if (!$this->validateCsrf()) { $this->redirect("/office/contracts/templates/{$id}/edit"); return; }
        $officeId = (int) Session::get('office_id');
        $tpl = ContractTemplate::findByIdForOffice($id, $officeId);
        if (!$tpl) { $this->redirect('/office/contracts/templates'); return; }

        ContractTemplate::update($id, [
            'name'        => trim($_POST['name'] ?? $tpl['name']),
            'description' => trim($_POST['description'] ?? ''),
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ]);

        // Signers — accept JSON-encoded list from the form.
        if (isset($_POST['signers_json'])) {
            $decoded = json_decode((string) $_POST['signers_json'], true);
            if (is_array($decoded)) {
                Database::getInstance()->update('contract_templates',
                    ['signers_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE)],
                    'id = ?', [$id]);
            }
        }
        Session::flash('success', 'contracts_template_saved');
        $this->redirect("/office/contracts/templates/{$id}/edit");
    }

    public function templateDelete(int $id): void
    {
        ModuleAccess::requireModule('contracts');
        if (!$this->validateCsrf()) { $this->redirect('/office/contracts/templates'); return; }
        $officeId = (int) Session::get('office_id');
        $tpl = ContractTemplate::findByIdForOffice($id, $officeId);
        if (!$tpl) { $this->redirect('/office/contracts/templates'); return; }
        ContractTemplate::deactivate($id);
        AuditLog::log(Auth::currentUserType(), (int) Auth::currentUserId(),
            'contract_template_deactivated', "Template id {$id} deactivated",
            'contract_template', $id);
        Session::flash('success', 'contracts_template_deactivated');
        $this->redirect('/office/contracts/templates');
    }

    public function templatePreview(int $id): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $tpl = ContractTemplate::findByIdForOffice($id, $officeId);
        if (!$tpl) { $this->redirect('/office/contracts/templates'); return; }
        $abs = __DIR__ . '/../../' . ltrim((string) $tpl['stored_path'], '/');
        if (!is_file($abs)) { $this->redirect('/office/contracts/templates'); return; }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . rawurlencode($tpl['original_filename'] ?? 'template.pdf') . '"');
        readfile($abs);
        exit;
    }

    // ── Forms (issued share links) ───────────────

    public function formsIndex(): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $statusFilter = $_GET['status'] ?? null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        $forms = ContractForm::findByOfficePaginated($officeId, $offset, $perPage, $statusFilter);
        $total = ContractForm::countByOffice($officeId, $statusFilter);
        $this->render('office/contracts/forms_index', [
            'forms'        => $forms,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function formCreateForm(int $templateId): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $tpl = ContractTemplate::findByIdForOffice($templateId, $officeId);
        if (!$tpl || empty($tpl['is_active'])) { $this->redirect('/office/contracts/templates'); return; }
        $clients = Client::findByOffice($officeId, true);
        $this->render('office/contracts/form_create', [
            'template' => $tpl,
            'clients'  => $clients,
        ]);
    }

    public function formStore(int $templateId): void
    {
        ModuleAccess::requireModule('contracts');
        if (!$this->validateCsrf()) { $this->redirect("/office/contracts/templates/{$templateId}/issue"); return; }
        $officeId = (int) Session::get('office_id');

        $clientId = !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null;
        $email = trim($_POST['email'] ?? '');
        $name  = trim($_POST['recipient_name'] ?? '');

        try {
            $result = ContractFormService::issueShareLink(
                $officeId, $templateId, $clientId, $email ?: null, $name ?: null,
                Auth::currentUserType(), (int) Auth::currentUserId()
            );
        } catch (\Throwable $e) {
            Session::flash('error', 'contracts_issue_failed');
            $this->redirect("/office/contracts/templates/{$templateId}/issue");
            return;
        }

        AuditLog::log(Auth::currentUserType(), (int) Auth::currentUserId(),
            'contract_form_issued',
            "Form id {$result['form_id']} issued for template {$templateId}",
            'contract_form', $result['form_id']);

        Session::flash('contracts_share_url', $result['url']);
        Session::flash('success', 'contracts_form_issued');
        $this->redirect("/office/contracts/forms/{$result['form_id']}");
    }

    public function formDetail(int $formId): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $form = ContractForm::findByIdForOffice($formId, $officeId);
        if (!$form) { $this->redirect('/office/contracts/forms'); return; }
        $template = ContractTemplate::findById((int) $form['template_id']);
        $events = ContractSigningEvent::findByForm($formId);
        $client = $form['client_id'] ? Client::findById((int) $form['client_id']) : null;
        $this->render('office/contracts/form_detail', [
            'form'     => $form,
            'template' => $template,
            'events'   => $events,
            'client'   => $client,
            'fields'   => $template ? ContractTemplate::decodeFields($template) : [],
            'data'     => ContractForm::decodeFormData($form),
            'shareUrl' => Session::getFlash('contracts_share_url'),
        ]);
    }

    public function formCancel(int $formId): void
    {
        ModuleAccess::requireModule('contracts');
        if (!$this->validateCsrf()) { $this->redirect('/office/contracts/forms'); return; }
        $officeId = (int) Session::get('office_id');
        $form = ContractForm::findByIdForOffice($formId, $officeId);
        if (!$form) { $this->redirect('/office/contracts/forms'); return; }
        if (in_array($form['status'], ['signed', 'cancelled', 'rejected'], true)) {
            Session::flash('error', 'contracts_cannot_cancel');
            $this->redirect("/office/contracts/forms/{$formId}"); return;
        }
        ContractForm::setStatus($formId, 'cancelled');
        AuditLog::log(Auth::currentUserType(), (int) Auth::currentUserId(),
            'contract_form_cancelled', "Form id {$formId} cancelled",
            'contract_form', $formId);
        Session::flash('success', 'contracts_form_cancelled');
        $this->redirect("/office/contracts/forms/{$formId}");
    }

    public function downloadFilledPdf(int $formId): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $form = ContractForm::findByIdForOffice($formId, $officeId);
        if (!$form || empty($form['filled_pdf_path'])) {
            $this->redirect('/office/contracts/forms'); return;
        }
        $abs = __DIR__ . '/../../' . ltrim((string) $form['filled_pdf_path'], '/');
        if (!is_file($abs)) { $this->redirect("/office/contracts/forms/{$formId}"); return; }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="form_' . $formId . '_filled.pdf"');
        readfile($abs);
        exit;
    }

    public function downloadSignedPdf(int $formId): void
    {
        ModuleAccess::requireModule('contracts');
        $officeId = (int) Session::get('office_id');
        $form = ContractForm::findByIdForOffice($formId, $officeId);
        if (!$form || empty($form['signed_pdf_path'])) {
            $this->redirect('/office/contracts/forms'); return;
        }
        $abs = __DIR__ . '/../../' . ltrim((string) $form['signed_pdf_path'], '/');
        if (!is_file($abs)) { $this->redirect("/office/contracts/forms/{$formId}"); return; }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="form_' . $formId . '_signed.pdf"');
        readfile($abs);
        exit;
    }

    // ─────────────────────────────────────────────

    private static function countsByStatus(int $officeId): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT status, COUNT(*) AS c FROM contract_forms WHERE office_id = ? GROUP BY status",
            [$officeId]
        );
        $out = ['pending' => 0, 'filled' => 0, 'submitted' => 0, 'signed' => 0,
                'rejected' => 0, 'expired' => 0, 'cancelled' => 0];
        foreach ($rows as $r) { $out[$r['status']] = (int) $r['c']; }
        return $out;
    }

    private static function slugify(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
        $s = trim((string) $s, '-');
        return $s === '' ? 'umowa' : substr($s, 0, 80);
    }
}
