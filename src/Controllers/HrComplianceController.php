<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrBhpTraining;
use App\Models\HrEmployee;
use App\Models\HrMedicalExam;

class HrComplianceController extends HrController
{
    public function bhpTraining(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $trainings = HrBhpTraining::findByClient($clientId);
        $alerts    = HrBhpTraining::findExpiredOrExpiring($clientId);
        $employees = HrEmployee::findByClient($clientId, true);

        $this->render('office/hr/bhp_training', compact('client', 'clientId', 'trainings', 'alerts', 'employees'));
    }

    public function bhpTrainingCreate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/bhp");
            return;
        }

        $empId    = (int) ($_POST['employee_id'] ?? 0);
        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $type = $_POST['training_type'] ?? '';
        if (!array_key_exists($type, HrBhpTraining::TYPE_LABELS)) {
            Session::flash('error', 'Nieprawid\u0142owy typ szkolenia.');
            $this->redirect("/office/hr/{$clientId}/bhp");
            return;
        }

        $completedAt = $_POST['completed_at'] ?? '';
        $expiresAt   = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $completedAt) || !\DateTime::createFromFormat('Y-m-d', $completedAt)) {
            $completedAt = date('Y-m-d');
        }
        if ($expiresAt !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt) || !\DateTime::createFromFormat('Y-m-d', $expiresAt))) {
            $expiresAt = null;
        }

        HrBhpTraining::create([
            'employee_id'        => $empId,
            'client_id'          => $clientId,
            'training_type'      => $type,
            'completed_at'       => $completedAt,
            'expires_at'         => $expiresAt,
            'certificate_number' => trim($_POST['certificate_number'] ?? '') ?: null,
            'trainer_name'       => trim($_POST['trainer_name'] ?? '') ?: null,
            'notes'              => trim($_POST['notes'] ?? '') ?: null,
            'created_by_type'    => $this->actorType(),
            'created_by_id'      => $this->actorId(),
        ]);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_bhp_create',
            json_encode(['employee_id' => $empId, 'type' => $type]), 'hr_employee', $empId);
        Session::flash('success', 'Szkolenie BHP zosta\u0142o dodane.');
        $this->redirect("/office/hr/{$clientId}/bhp");
    }

    public function medicalExams(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $exams     = HrMedicalExam::findByClient($clientId);
        $alerts    = HrMedicalExam::findExpiredOrExpiring($clientId);
        $employees = HrEmployee::findByClient($clientId, true);

        $this->render('office/hr/medical_exams', compact('client', 'clientId', 'exams', 'alerts', 'employees'));
    }

    public function medicalExamCreate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/medical");
            return;
        }

        $empId    = (int) ($_POST['employee_id'] ?? 0);
        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $type = $_POST['exam_type'] ?? '';
        if (!array_key_exists($type, HrMedicalExam::TYPE_LABELS)) {
            Session::flash('error', 'Nieprawid\u0142owy typ badania.');
            $this->redirect("/office/hr/{$clientId}/medical");
            return;
        }

        $examDate   = $_POST['exam_date'] ?? '';
        $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate) || !\DateTime::createFromFormat('Y-m-d', $examDate)) {
            $examDate = date('Y-m-d');
        }
        if ($validUntil !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validUntil) || !\DateTime::createFromFormat('Y-m-d', $validUntil))) {
            $validUntil = null;
        }
        $result = $_POST['result'] ?? null;
        if ($result !== null && !array_key_exists($result, HrMedicalExam::RESULT_LABELS)) {
            $result = null;
        }

        HrMedicalExam::create([
            'employee_id'        => $empId,
            'client_id'          => $clientId,
            'exam_type'          => $type,
            'exam_date'          => $examDate,
            'valid_until'        => $validUntil,
            'result'             => $result,
            'doctor_name'        => trim($_POST['doctor_name'] ?? '') ?: null,
            'certificate_number' => trim($_POST['certificate_number'] ?? '') ?: null,
            'notes'              => trim($_POST['notes'] ?? '') ?: null,
            'created_by_type'    => $this->actorType(),
            'created_by_id'      => $this->actorId(),
        ]);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_medical_create',
            json_encode(['employee_id' => $empId, 'type' => $type]), 'hr_employee', $empId);
        Session::flash('success', 'Badanie lekarskie zosta\u0142o dodane.');
        $this->redirect("/office/hr/{$clientId}/medical");
    }
}
