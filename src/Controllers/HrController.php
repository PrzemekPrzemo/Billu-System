<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Language;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\HrClientSettings;
use App\Models\HrContract;
use App\Models\HrEmployee;
use App\Models\HrLeaveBalance;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\OfficeEmployee;
use App\Models\HrPayrollRun;
use App\Models\HrZusDeclaration;
use App\Services\HrAccessService;
use App\Services\HrLeaveService;
use App\Services\HrPayrollCalculationService;
use App\Services\HrPayrollRunService;
use App\Services\HrPayslipPdfService;
use App\Services\HrZusDraService;
use App\Models\HrPitDeclaration;
use App\Services\HrPit11Service;
use App\Services\HrPit4rService;
use App\Services\HrReportService;
use App\Services\HrPayslipEmailService;
use App\Models\HrDocument;
use App\Models\HrPayrollBudget;
use App\Services\HrDocumentStorageService;
use App\Services\HrBudgetExportService;
use App\Services\HrSwiadectwoPdfService;
use App\Services\HrUmowaPdfService;
use App\Models\HrOnboardingTask;
use App\Services\HrNotificationService;
use App\Services\HrOnboardingService;

class HrController extends Controller
{
    public function __construct()
    {
        Auth::requireOfficeOrEmployee();
        $lang = Session::get('office_language', 'pl');
        Language::setLocale($lang);
    }

    protected function getOfficeId(): int
    {
        return (int) Session::get('office_id');
    }

    protected function authorizeClientHr(int $clientId): array
    {
        $officeId = $this->getOfficeId();
        $client = Client::findById($clientId);

        if (!$client || (int)$client['office_id'] !== $officeId) {
            $this->forbidden();
        }

        if (Auth::isEmployee()) {
            $employeeId = (int) Session::get('employee_id');
            $assigned = OfficeEmployee::getAssignedClientIds($employeeId);
            if (!in_array($clientId, $assigned)) {
                $this->forbidden();
            }
        }

        if (!HrAccessService::isEnabledForOffice($officeId)) {
            Session::flash('error', 'hr_module_not_enabled_for_office');
            $this->redirect('/office');
        }

        if (!HrAccessService::isEnabledForClient($clientId)) {
            Session::flash('error', 'hr_module_not_enabled_for_client');
            $this->redirect('/office/hr/settings');
        }

        return $client;
    }

    protected function actorType(): string
    {
        return Auth::isEmployee() ? 'employee' : 'office';
    }

    protected function actorId(): int
    {
        return Auth::isEmployee()
            ? (int) Session::get('employee_id')
            : (int) Session::get('office_id');
    }

    public function settings(): void
    {
        $officeId = $this->getOfficeId();

        if (!HrAccessService::isEnabledForOffice($officeId)) {
            $this->render('office/hr/hr_disabled', []);
            return;
        }

        $clients = HrAccessService::getClientsWithHrStatus($officeId);
        $this->render('office/hr/settings_global', compact('clients'));
    }

    public function settingsToggleClient(string $clientId): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/hr/settings');
            return;
        }
        if (Auth::isEmployee()) {
            $this->forbidden();
        }

        $clientId = (int) $clientId;
        $officeId = $this->getOfficeId();
        $client = Client::findById($clientId);
        if (!$client || (int)$client['office_id'] !== $officeId) {
            $this->forbidden();
        }

        $settings = HrClientSettings::findByClient($clientId);
        $currentlyEnabled = (bool) ($settings['hr_enabled'] ?? false);

        if ($currentlyEnabled) {
            HrAccessService::disableClient($clientId, $this->actorId(), $this->actorType());
            Session::flash('success', 'hr_disabled_for_client');
        } else {
            HrAccessService::enableClient($clientId, $this->actorId(), $this->actorType());
            Session::flash('success', 'hr_enabled_for_client');
        }

        $this->redirect('/office/hr/settings');
    }

    public function settingsEnableAll(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/hr/settings');
            return;
        }
        if (Auth::isEmployee()) {
            $this->forbidden();
        }
        $officeId = $this->getOfficeId();
        $count = HrAccessService::enableAllClientsOfOffice($officeId, $this->actorId(), $this->actorType());
        Session::flash('success', "HR włączono dla {$count} klientów.");
        $this->redirect('/office/hr/settings');
    }

    public function settingsDisableAll(): void
    {
        if (!$this->validateCsrf()) {
            $this->redirect('/office/hr/settings');
            return;
        }
        if (Auth::isEmployee()) {
            $this->forbidden();
        }
        $officeId = $this->getOfficeId();
        HrAccessService::disableAllClientsOfOffice($officeId, $this->actorId(), $this->actorType());
        Session::flash('success', 'HR wyłączono dla wszystkich klientów.');
        $this->redirect('/office/hr/settings');
    }

    public function clientSettings(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);
        $hrSettings = HrClientSettings::getOrCreate($clientId);
        $this->render('office/hr/client_settings', compact('client', 'hrSettings', 'clientId'));
    }

    public function clientSettingsSave(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/settings");
            return;
        }

        $data = [
            'wypadkowe_rate'                => (float) ($_POST['wypadkowe_rate'] ?? 0.0167),
            'default_kup'                   => in_array($_POST['default_kup'] ?? '', ['250','300']) ? $_POST['default_kup'] : '250',
            'min_wage_monthly'              => (float) ($_POST['min_wage_monthly'] ?? 4666),
            'zus_payer_nip'                 => trim($_POST['zus_payer_nip'] ?? '') ?: null,
            'zus_payer_name'                => trim($_POST['zus_payer_name'] ?? '') ?: null,
            'payslip_email_enabled'         => (int) (($_POST['payslip_email_enabled'] ?? '0') === '1'),
            'payslip_email_from'            => trim($_POST['payslip_email_from'] ?? '') ?: null,
            'payslip_email_subject_template'=> trim($_POST['payslip_email_subject_template'] ?? '') ?: null,
        ];

        HrClientSettings::update($clientId, $data);
        AuditLog::log($this->actorType(), $this->actorId(), 'hr_client_settings_update', json_encode($data), 'client', $clientId);
        Session::flash('success', 'Ustawienia HR zostały zapisane.');
        $this->redirect("/office/hr/{$clientId}/settings");
    }

    public function employees(string $clientId): void
    {
        $clientId  = (int) $clientId;
        $client    = $this->authorizeClientHr($clientId);
        $employees = HrEmployee::findByClient($clientId);
        $this->render('office/hr/employees', compact('client', 'employees', 'clientId'));
    }

    public function employeeCreateForm(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);
        $this->render('office/hr/employee_form', [
            'client'   => $client,
            'clientId' => $clientId,
            'employee' => null,
            'editing'  => false,
        ]);
    }

    public function employeeCreate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/employees/create");
            return;
        }

        $errors = $this->validateEmployeePost($clientId);
        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            $this->redirect("/office/hr/{$clientId}/employees/create");
            return;
        }

        $data = $this->buildEmployeeData($clientId);
        $empId = HrEmployee::create($data);

        HrLeaveBalance::initForYear($empId, $clientId, (int)date('Y'), (int)($data['annual_leave_days'] ?? 26));
        HrOnboardingService::initOnboarding($empId, $clientId);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_employee_create', json_encode(['employee_id' => $empId]), 'hr_employee', $empId);
        Session::flash('success', 'Pracownik został dodany.');
        $this->redirect("/office/hr/{$clientId}/employees/{$empId}");
    }

    public function employeeDetail(string $clientId, string $id): void
    {
        $clientId  = (int) $clientId;
        $id        = (int) $id;
        $client    = $this->authorizeClientHr($clientId);
        $employee  = HrEmployee::findById($id);

        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $contracts    = HrContract::findByEmployee($id);
        $currentContract = HrContract::findCurrentByEmployee($id);
        $leaveBalance = HrLeaveBalance::findByEmployeeYear($id, (int)date('Y'));
        $leaveRequests = HrLeaveRequest::findByEmployee($id);
        $onboardingProgress  = HrOnboardingTask::getProgress($id, 'onboarding');
        $offboardingProgress = HrOnboardingTask::getProgress($id, 'offboarding');

        $this->render('office/hr/employee_detail', compact(
            'client', 'employee', 'contracts', 'currentContract',
            'leaveBalance', 'leaveRequests', 'clientId',
            'onboardingProgress', 'offboardingProgress'
        ));
    }

    public function employeeEditForm(string $clientId, string $id): void
    {
        $clientId = (int) $clientId;
        $id       = (int) $id;
        $client   = $this->authorizeClientHr($clientId);
        $employee = HrEmployee::findById($id);

        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $this->render('office/hr/employee_form', [
            'client'   => $client,
            'clientId' => $clientId,
            'employee' => $employee,
            'editing'  => true,
        ]);
    }

    public function employeeEdit(string $clientId, string $id): void
    {
        $clientId = (int) $clientId;
        $id       = (int) $id;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/employees/{$id}/edit");
            return;
        }

        $employee = HrEmployee::findById($id);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $errors = $this->validateEmployeePost($clientId, $id);
        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            $this->redirect("/office/hr/{$clientId}/employees/{$id}/edit");
            return;
        }

        $data = $this->buildEmployeeData($clientId);
        HrEmployee::update($id, $data);
        AuditLog::log($this->actorType(), $this->actorId(), 'hr_employee_update', json_encode(['employee_id' => $id]), 'hr_employee', $id);
        Session::flash('success', 'Dane pracownika zostały zaktualizowane.');
        $this->redirect("/office/hr/{$clientId}/employees/{$id}");
    }

    public function employeeArchive(string $clientId, string $id): void
    {
        $clientId = (int) $clientId;
        $id       = (int) $id;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/employees");
            return;
        }

        $employee = HrEmployee::findById($id);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        HrEmployee::archive($id);
        AuditLog::log($this->actorType(), $this->actorId(), 'hr_employee_archive', json_encode(['employee_id' => $id]), 'hr_employee', $id);
        Session::flash('success', 'Pracownik został zarchiwizowany.');
        $this->redirect("/office/hr/{$clientId}/employees");
    }

    public function employeeArchiveForm(string $clientId, string $id): void
    {
        $clientId = (int) $clientId;
        $id       = (int) $id;
        $client   = $this->authorizeClientHr($clientId);

        $employee = HrEmployee::findById($id);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }
        if (!$employee['is_active']) {
            Session::flash('error', 'Pracownik jest już zarchiwizowany.');
            $this->redirect("/office/hr/{$clientId}/employees/{$id}");
            return;
        }

        $contracts = HrContract::findByEmployee($id);
        $this->render('office/hr/employee_archive_form', compact('client', 'clientId', 'employee', 'contracts'));
    }

    public function employeeArchiveConfirm(string $clientId, string $id): void
    {
        $clientId = (int) $clientId;
        $id       = (int) $id;
        $this->authorizeClientHr($clientId);
        $this->validateCsrf();

        $employee = HrEmployee::findById($id);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $reason     = $_POST['archive_reason'] ?? 'other';
        $endDate    = $_POST['end_date'] ?? date('Y-m-d');
        $genPdf     = !empty($_POST['generate_swiadectwo']);
        $contractId = (int) ($_POST['contract_id'] ?? 0);

        HrEmployee::archiveWithReason($id, $reason, $endDate);
        HrOnboardingService::initOffboarding($id, $clientId);

        if ($contractId > 0) {
            HrContract::terminate($contractId, $endDate);
        }

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_employee_archive_reason',
            json_encode(['employee_id' => $id, 'reason' => $reason, 'end_date' => $endDate]), 'hr_employee', $id);

        if ($genPdf && $contractId > 0) {
            try {
                HrSwiadectwoPdfService::generate($id, $contractId);
                Session::flash('success', 'Pracownik zarchiwizowany. Świadectwo pracy zostało wygenerowane.');
            } catch (\Throwable $e) {
                Session::flash('success', 'Pracownik zarchiwizowany. Błąd generowania świadectwa: ' . $e->getMessage());
            }
        } else {
            Session::flash('success', 'Pracownik został zarchiwizowany.');
        }

        $this->redirect("/office/hr/{$clientId}/employees/{$id}");
    }

    public function swiadectwoPdf(string $clientId, string $id): void
    {
        $clientId = (int) $clientId;
        $id       = (int) $id;
        $this->authorizeClientHr($clientId);

        $employee = HrEmployee::findById($id);
        if (!$employee || (int)$employee['client_id'] !== $clientId || empty($employee['swiadectwo_pdf_path'])) {
            $this->forbidden();
        }

        $absPath = __DIR__ . '/../../' . $employee['swiadectwo_pdf_path'];
        if (!file_exists($absPath)) {
            Session::flash('error', 'Plik świadectwa nie istnieje. Wygeneruj ponownie.');
            $this->redirect("/office/hr/{$clientId}/employees/{$id}");
            return;
        }

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_swiadectwo_download',
            json_encode(['employee_id' => $id]), 'hr_employee', $id);

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($absPath) . '"');
        header('Content-Length: ' . filesize($absPath));
        readfile($absPath);
        exit;
    }

    public function contractPdf(string $clientId, string $empId, string $contractId): void
    {
        $clientId   = (int) $clientId;
        $empId      = (int) $empId;
        $contractId = (int) $contractId;
        $this->authorizeClientHr($clientId);

        $contract = HrContract::findById($contractId);
        if (!$contract || (int)$contract['client_id'] !== $clientId || (int)$contract['employee_id'] !== $empId) {
            $this->forbidden();
        }

        try {
            $path = HrUmowaPdfService::generate($contractId);
        } catch (\Throwable $e) {
            Session::flash('error', 'Błąd generowania PDF umowy: ' . $e->getMessage());
            $this->redirect("/office/hr/{$clientId}/employees/{$empId}");
            return;
        }

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_contract_pdf_download',
            json_encode(['contract_id' => $contractId]), 'hr_contract', $contractId);

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function validateEmployeePost(int $clientId, ?int $excludeId = null): array
    {
        $errors = [];
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        if (!$firstName) $errors[] = 'Imię jest wymagane.';
        if (!$lastName)  $errors[] = 'Nazwisko jest wymagane.';

        $pesel = preg_replace('/\s/', '', $_POST['pesel'] ?? '');
        if ($pesel) {
            if (!HrEmployee::validatePesel($pesel)) {
                $errors[] = 'Nieprawidłowy PESEL (błędna suma kontrolna).';
            } else {
                $existing = HrEmployee::findByPesel($clientId, $pesel);
                if ($existing && (int)$existing['id'] !== (int)$excludeId) {
                    $errors[] = 'Pracownik z tym numerem PESEL już istnieje.';
                }
            }
        }
        return $errors;
    }

    private function buildEmployeeData(int $clientId): array
    {
        $pesel = preg_replace('/\s/', '', $_POST['pesel'] ?? '') ?: null;
        return [
            'client_id'        => $clientId,
            'first_name'       => trim($_POST['first_name'] ?? ''),
            'last_name'        => trim($_POST['last_name'] ?? ''),
            'pesel'            => $pesel,
            'nip'              => trim($_POST['nip'] ?? '') ?: null,
            'birth_date'       => trim($_POST['birth_date'] ?? '') ?: null,
            'gender'           => in_array($_POST['gender'] ?? '', ['M','K']) ? $_POST['gender'] : null,
            'address_street'   => trim($_POST['address_street'] ?? '') ?: null,
            'address_city'     => trim($_POST['address_city'] ?? '') ?: null,
            'address_zip'      => trim($_POST['address_zip'] ?? '') ?: null,
            'address_country'  => trim($_POST['address_country'] ?? 'PL') ?: 'PL',
            'email'            => trim($_POST['email'] ?? '') ?: null,
            'phone'            => trim($_POST['phone'] ?? '') ?: null,
            'bank_account_iban'=> preg_replace('/\s/', '', $_POST['bank_account_iban'] ?? '') ?: null,
            'bank_name'        => trim($_POST['bank_name'] ?? '') ?: null,
            'tax_office_code'  => trim($_POST['tax_office_code'] ?? '') ?: null,
            'tax_office_name'  => trim($_POST['tax_office_name'] ?? '') ?: null,
            'pit2_submitted'   => (int)(($_POST['pit2_submitted'] ?? '0') === '1'),
            'pit2_submitted_at'=> (($_POST['pit2_submitted'] ?? '0') === '1') ? date('Y-m-d') : null,
            'kup_amount'       => in_array($_POST['kup_amount'] ?? '', ['250','300']) ? $_POST['kup_amount'] : '250',
            'zus_title_code'   => trim($_POST['zus_title_code'] ?? '0110') ?: '0110',
            'disability_level' => in_array($_POST['disability_level'] ?? '', ['none','mild','moderate','severe']) ? $_POST['disability_level'] : 'none',
            'ppk_enrolled'     => (int)(($_POST['ppk_enrolled'] ?? '0') === '1'),
            'ppk_employee_rate'=> max(2.0, min(4.0, (float)($_POST['ppk_employee_rate'] ?? 2.0))),
            'ppk_employer_rate'=> max(1.5, min(4.0, (float)($_POST['ppk_employer_rate'] ?? 1.5))),
            'annual_leave_days'      => in_array((int)($_POST['annual_leave_days'] ?? 26), [20, 26]) ? (int)$_POST['annual_leave_days'] : 26,
            'notes'                  => trim($_POST['notes'] ?? '') ?: null,
            'receive_payslip_email'  => (int)(($_POST['receive_payslip_email'] ?? '0') === '1'),
            'email_payslip'          => trim($_POST['email_payslip'] ?? '') ?: null,
            'created_by_type'        => $this->actorType(),
            'created_by_id'          => $this->actorId(),
        ];
    }

    private function countBusinessDays(string $from, string $to): float
    {
        return HrLeaveService::countBusinessDays($from, $to);
    }
}
