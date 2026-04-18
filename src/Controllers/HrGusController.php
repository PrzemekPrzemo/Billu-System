<?php

namespace App\Controllers;

use App\Core\HrDatabase;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrCompanyDocument;
use App\Models\HrEmployee;

class HrGusController extends HrController
{
    public function gusReports(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $year    = (int) ($_GET['year'] ?? date('Y'));
        $quarter = (int) ($_GET['quarter'] ?? ceil(date('n') / 3));

        $db     = HrDatabase::getInstance();
        $qStart = sprintf('%04d-%02d-01', $year, ($quarter - 1) * 3 + 1);
        $qEnd   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $quarter * 3)));

        $employeesEndQ = (int) ($db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND is_active = 1
               AND (employment_start IS NULL OR employment_start <= ?)",
            [$clientId, $qEnd]
        )['cnt'] ?? 0);

        $newHires = (int) ($db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND employment_start BETWEEN ? AND ?",
            [$clientId, $qStart, $qEnd]
        )['cnt'] ?? 0);

        $departures = (int) ($db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM hr_employees
             WHERE client_id = ? AND archived_at IS NOT NULL
               AND archived_at BETWEEN ? AND ?",
            [$clientId, $qStart, $qEnd . ' 23:59:59']
        )['cnt'] ?? 0);

        $contractBreakdown = $db->fetchAll(
            "SELECT c.contract_type, COUNT(*) AS cnt
             FROM hr_contracts c
             JOIN hr_employees e ON c.employee_id = e.id
             WHERE c.client_id = ? AND c.is_current = 1 AND e.is_active = 1
             GROUP BY c.contract_type",
            [$clientId]
        );

        $wageData = $db->fetchOne(
            "SELECT SUM(pi.gross_salary) AS total_gross,
                    AVG(pi.gross_salary) AS avg_gross,
                    COUNT(pi.id) AS emp_count
             FROM hr_payroll_items pi
             JOIN hr_payroll_runs pr ON pi.payroll_run_id = pr.id
             WHERE pr.client_id = ? AND pr.period_year = ?
               AND pr.period_month = ? AND pr.status IN ('approved','locked')",
            [$clientId, $year, $quarter * 3]
        );

        $gusData = [
            'employees_end_q'    => $employeesEndQ,
            'new_hires'          => $newHires,
            'departures'         => $departures,
            'contract_breakdown' => $contractBreakdown,
            'wage_data'          => $wageData,
        ];

        $this->render('office/hr/gus_reports', compact('client', 'clientId', 'year', 'quarter', 'gusData'));
    }

    public function companyDocuments(string $clientId): void
    {
        $clientId  = (int) $clientId;
        $client    = $this->authorizeClientHr($clientId);
        $documents = HrCompanyDocument::findByClient($clientId);

        $this->render('office/hr/company_documents', compact('client', 'clientId', 'documents'));
    }

    public function companyDocumentUpload(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/company-docs");
            return;
        }

        if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Brak pliku lub b\u0142\u0105d przesy\u0142ania.');
            $this->redirect("/office/hr/{$clientId}/company-docs");
            return;
        }

        $file     = $_FILES['document'];
        $fileSize = (int) $file['size'];

        if ($fileSize > 20 * 1024 * 1024) {
            Session::flash('error', 'Plik jest za du\u017cy (max 20 MB).');
            $this->redirect("/office/hr/{$clientId}/company-docs");
            return;
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowed  = ['application/pdf', 'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($mimeType, $allowed, true)) {
            Session::flash('error', 'Dozwolone tylko pliki PDF i DOC/DOCX.');
            $this->redirect("/office/hr/{$clientId}/company-docs");
            return;
        }

        $docType = $_POST['document_type'] ?? 'inne';
        if (!array_key_exists($docType, HrCompanyDocument::TYPE_LABELS)) {
            $docType = 'inne';
        }

        $allowedExts = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            Session::flash('error', 'Niedozwolone rozszerzenie pliku.');
            $this->redirect("/office/hr/{$clientId}/company-docs");
            return;
        }

        $dir = "storage/hr/company_docs/{$clientId}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $storedPath = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $storedPath);

        $validDate = fn(?string $d): ?string =>
            $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && \DateTime::createFromFormat('Y-m-d', $d) ? $d : null;

        HrCompanyDocument::create([
            'client_id'        => $clientId,
            'document_type'    => $docType,
            'title'            => trim($_POST['title'] ?? '') ?: $file['name'],
            'stored_path'      => $storedPath,
            'original_name'    => $file['name'],
            'mime_type'        => $mimeType,
            'file_size'        => $fileSize,
            'valid_from'       => $validDate($_POST['valid_from'] ?? null),
            'valid_until'      => $validDate($_POST['valid_until'] ?? null),
            'uploaded_by_type' => $this->actorType(),
            'uploaded_by_id'   => $this->actorId(),
        ]);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_company_doc_upload',
            json_encode(['client_id' => $clientId, 'type' => $docType]), 'hr_client', $clientId);
        Session::flash('success', 'Dokument firmowy zosta\u0142 przes\u0142any.');
        $this->redirect("/office/hr/{$clientId}/company-docs");
    }

    public function companyDocumentDownload(string $clientId, string $docId): void
    {
        $clientId = (int) $clientId;
        $docId    = (int) $docId;
        $this->authorizeClientHr($clientId);

        $doc = HrCompanyDocument::findById($docId);
        if (!$doc || (int)$doc['client_id'] !== $clientId) {
            $this->forbidden();
        }

        if (!file_exists($doc['stored_path'])) {
            Session::flash('error', 'Plik nie istnieje.');
            $this->redirect("/office/hr/{$clientId}/company-docs");
            return;
        }

        $allowedMimes = ['application/pdf', 'application/msword',
                         'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $safeMime = in_array($doc['mime_type'], $allowedMimes, true)
            ? $doc['mime_type']
            : 'application/octet-stream';
        $safeFilename = str_replace(['"', '\\', "\n", "\r", "\0"], '', basename($doc['original_name']));

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: ' . $safeMime);
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($doc['stored_path']));
        readfile($doc['stored_path']);
        exit;
    }

    public function companyDocumentDelete(string $clientId, string $docId): void
    {
        $clientId = (int) $clientId;
        $docId    = (int) $docId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/company-docs");
            return;
        }

        $doc = HrCompanyDocument::findById($docId);
        if (!$doc || (int)$doc['client_id'] !== $clientId) {
            $this->forbidden();
        }

        if (file_exists($doc['stored_path'])) {
            @unlink($doc['stored_path']);
        }
        HrCompanyDocument::delete($docId);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_company_doc_delete',
            json_encode(['doc_id' => $docId]), 'hr_client', $clientId);
        Session::flash('success', 'Dokument zosta\u0142 usuni\u0119ty.');
        $this->redirect("/office/hr/{$clientId}/company-docs");
    }
}
