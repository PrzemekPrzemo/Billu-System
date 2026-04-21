<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrDocument;
use App\Models\HrEmployee;
use App\Models\HrOnboardingTask;
use App\Services\HrDocumentStorageService;

class HrDocumentOnboardingController extends HrController
{
    public function documentList(string $clientId, string $id): void
    {
        $clientId   = (int) $clientId;
        $employeeId = (int) $id;
        $client   = $this->authorizeClientHr($clientId);
        $employee = HrEmployee::findById($employeeId);

        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->notFound();
        }

        $documents = HrDocument::findByEmployee($employeeId);

        $this->render('office/hr/employee_documents', [
            'client'    => $client,
            'employee'  => $employee,
            'documents' => $documents,
        ]);
    }

    public function documentUpload(string $clientId, string $id): void
    {
        $clientId   = (int) $clientId;
        $employeeId = (int) $id;
        $client   = $this->authorizeClientHr($clientId);
        $employee = HrEmployee::findById($employeeId);

        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->notFound();
        }

        $this->validateCsrf();

        if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Brak pliku lub błąd przesyłania');
            $this->redirect("/office/hr/{$clientId}/employees/{$employeeId}/documents");
        }

        $file     = $_FILES['document'];
        $tmpPath  = $file['tmp_name'];
        $fileSize = (int) $file['size'];

        if ($fileSize > 10 * 1024 * 1024) {
            Session::flash('error', 'Plik jest za duży (max 10 MB)');
            $this->redirect("/office/hr/{$clientId}/employees/{$employeeId}/documents");
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpPath);
        $allowed  = ['application/pdf', 'image/jpeg', 'image/png',
                     'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($mimeType, $allowed, true)) {
            Session::flash('error', 'Niedozwolony typ pliku. Dozwolone: PDF, JPG, PNG, DOC, DOCX');
            $this->redirect("/office/hr/{$clientId}/employees/{$employeeId}/documents");
        }

        $category    = $_POST['category'] ?? 'inne';
        $expiryDate  = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $originalName = $file['name'];

        try {
            $storedPath = HrDocumentStorageService::storeEncrypted($tmpPath, $clientId, $employeeId);

            HrDocument::create([
                'employee_id'      => $employeeId,
                'client_id'        => $clientId,
                'category'         => $category,
                'original_name'    => $originalName,
                'stored_path'      => $storedPath,
                'mime_type'        => $mimeType,
                'file_size'        => $fileSize,
                'expiry_date'      => $expiryDate,
                'uploaded_by_type' => $this->actorType(),
                'uploaded_by_id'   => $this->actorId(),
            ]);

            AuditLog::log($this->actorType(), $this->actorId(), 'hr_doc_upload',
                json_encode(['employee_id' => $employeeId, 'category' => $category]),
                'hr_employee', $employeeId);

            Session::flash('success', 'Dokument został przesłany');
        } catch (\Throwable $e) {
            Session::flash('error', 'Błąd przesyłania: ' . $e->getMessage());
        }

        $this->redirect("/office/hr/{$clientId}/employees/{$employeeId}/documents");
    }

    public function documentDownload(string $clientId, string $id, string $docId): void
    {
        $clientId   = (int) $clientId;
        $employeeId = (int) $id;
        $docId      = (int) $docId;
        $this->authorizeClientHr($clientId);

        $doc = HrDocument::findById($docId);
        if (!$doc || (int)$doc['employee_id'] !== $employeeId || (int)$doc['client_id'] !== $clientId) {
            $this->notFound();
        }

        try {
            $content  = HrDocumentStorageService::readDecrypted($doc['stored_path']);
            $filename = $doc['original_name'] ?? 'document';
            $mimeType = $doc['mime_type'] ?? 'application/octet-stream';

            while (ob_get_level()) ob_end_clean();
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . addslashes(basename($filename)) . '"');
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        } catch (\Throwable $e) {
            Session::flash('error', 'Błąd pobierania pliku: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/employees/{$employeeId}/documents");
        }
    }

    public function documentDelete(string $clientId, string $id, string $docId): void
    {
        $clientId   = (int) $clientId;
        $employeeId = (int) $id;
        $docId      = (int) $docId;
        $this->authorizeClientHr($clientId);
        $this->validateCsrf();

        $doc = HrDocument::findById($docId);
        if (!$doc || (int)$doc['employee_id'] !== $employeeId || (int)$doc['client_id'] !== $clientId) {
            $this->notFound();
        }

        HrDocumentStorageService::delete($doc['stored_path']);
        HrDocument::delete($docId);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_doc_delete',
            json_encode(['doc_id' => $docId, 'employee_id' => $employeeId]),
            'hr_employee', $employeeId);

        Session::flash('success', 'Dokument został usunięty');
        $this->redirect("/office/hr/{$clientId}/employees/{$employeeId}/documents");
    }

    public function onboarding(string $clientId, string $empId): void
    {
        $clientId = (int) $clientId;
        $empId    = (int) $empId;
        $client   = $this->authorizeClientHr($clientId);

        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $phase = in_array($_GET['phase'] ?? '', ['onboarding','offboarding'], true)
               ? $_GET['phase']
               : 'onboarding';

        $tasks       = HrOnboardingTask::findByEmployee($empId, $phase);
        $progressOn  = HrOnboardingTask::getProgress($empId, 'onboarding');
        $progressOff = HrOnboardingTask::getProgress($empId, 'offboarding');

        $grouped = [];
        foreach ($tasks as $task) {
            $grouped[$task['category']][] = $task;
        }

        $this->render('office/hr/onboarding', compact(
            'client', 'clientId', 'employee', 'empId',
            'phase', 'tasks', 'grouped', 'progressOn', 'progressOff'
        ));
    }

    public function onboardingToggle(string $clientId, string $empId, string $taskId): void
    {
        $clientId = (int) $clientId;
        $empId    = (int) $empId;
        $taskId   = (int) $taskId;
        $this->authorizeClientHr($clientId);
        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/employees/{$empId}/onboarding");
            return;
        }

        $task = HrOnboardingTask::findById($taskId);
        if (!$task || (int)$task['employee_id'] !== $empId || (int)$task['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $phase = $task['phase'] ?? 'onboarding';

        if ($task['is_done']) {
            HrOnboardingTask::markUndone($taskId);
        } else {
            HrOnboardingTask::markDone($taskId, $this->actorType(), $this->actorId());
        }

        $this->redirect("/office/hr/{$clientId}/employees/{$empId}/onboarding?phase={$phase}");
    }
}
