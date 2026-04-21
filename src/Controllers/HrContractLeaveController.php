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
use App\Services\HrAccessService;
use App\Services\HrLeaveService;
use App\Services\HrNotificationService;

class HrContractLeaveController extends HrController
{
    public function contractCreateForm(string $clientId, string $empId): void
    {
        $clientId = (int) $clientId;
        $empId    = (int) $empId;
        $client   = $this->authorizeClientHr($clientId);
        $employee = HrEmployee::findById($empId);

        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $hrSettings = HrClientSettings::getOrCreate($clientId);
        $this->render('office/hr/contract_form', [
            'client'     => $client,
            'employee'   => $employee,
            'contract'   => null,
            'clientId'   => $clientId,
            'hrSettings' => $hrSettings,
        ]);
    }

    public function contractCreate(string $clientId, string $empId): void
    {
        $clientId = (int) $clientId;
        $empId    = (int) $empId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/employees/{$empId}/contracts/create");
            return;
        }

        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $contractType = $_POST['contract_type'] ?? '';
        if (!in_array($contractType, ['uop','uz','uod'])) {
            Session::flash('error', 'Nieprawidłowy typ umowy.');
            $this->redirect("/office/hr/{$clientId}/employees/{$empId}/contracts/create");
            return;
        }

        $startDate = trim($_POST['start_date'] ?? '');
        if (!$startDate || !strtotime($startDate)) {
            Session::flash('error', 'Data rozpoczęcia jest wymagana.');
            $this->redirect("/office/hr/{$clientId}/employees/{$empId}/contracts/create");
            return;
        }

        $endDate = trim($_POST['end_date'] ?? '') ?: null;
        $hrSettings = HrClientSettings::getOrCreate($clientId);

        $data = [
            'employee_id'              => $empId,
            'client_id'                => $clientId,
            'contract_type'            => $contractType,
            'position'                 => trim($_POST['position'] ?? '') ?: null,
            'department'               => trim($_POST['department'] ?? '') ?: null,
            'start_date'               => $startDate,
            'end_date'                 => $endDate,
            'contract_number'          => trim($_POST['contract_number'] ?? '') ?: null,
            'base_salary'              => (float) ($_POST['base_salary'] ?? 0),
            'salary_type'              => in_array($_POST['salary_type'] ?? '', ['monthly','hourly','task']) ? $_POST['salary_type'] : 'monthly',
            'hourly_rate'              => ($_POST['salary_type'] ?? '') === 'hourly' ? (float)($_POST['hourly_rate'] ?? 0) : null,
            'work_time_fraction'       => max(0.1, min(1.0, (float)($_POST['work_time_fraction'] ?? 1.0))),
            'zus_emerytalne_employee'  => (int)(($_POST['zus_emerytalne_employee'] ?? '1') === '1'),
            'zus_emerytalne_employer'  => (int)(($_POST['zus_emerytalne_employer'] ?? '1') === '1'),
            'zus_rentowe_employee'     => (int)(($_POST['zus_rentowe_employee'] ?? '1') === '1'),
            'zus_rentowe_employer'     => (int)(($_POST['zus_rentowe_employer'] ?? '1') === '1'),
            'zus_chorobowe'            => (int)(($_POST['zus_chorobowe'] ?? '1') === '1'),
            'zus_wypadkowe'            => (int)(($_POST['zus_wypadkowe'] ?? '1') === '1'),
            'zus_fp'                   => (int)(($_POST['zus_fp'] ?? '1') === '1'),
            'zus_fgsp'                 => (int)(($_POST['zus_fgsp'] ?? '1') === '1'),
            'zus_fep'                  => (int)(($_POST['zus_fep'] ?? '0') === '1'),
            'wypadkowe_rate'           => (float) ($hrSettings['wypadkowe_rate'] ?? 0.0167),
            'has_other_employment'     => (int)(($_POST['has_other_employment'] ?? '0') === '1'),
            'is_current'               => 1,
            'created_by_type'          => $this->actorType(),
            'created_by_id'            => $this->actorId(),
        ];

        if ($contractType === 'uod') {
            $data['zus_emerytalne_employee'] = 0;
            $data['zus_emerytalne_employer'] = 0;
            $data['zus_rentowe_employee']    = 0;
            $data['zus_rentowe_employer']    = 0;
            $data['zus_chorobowe']           = 0;
            $data['zus_wypadkowe']           = 0;
            $data['zus_fp']                  = 0;
            $data['zus_fgsp']                = 0;
        }

        $contractId = HrContract::create($data);

        $empStart = $employee['employment_start'];
        if (!$empStart || $startDate < $empStart) {
            HrEmployee::update($empId, ['employment_start' => $startDate]);
        }

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_contract_create', json_encode(['contract_id' => $contractId, 'employee_id' => $empId]), 'hr_contract', $contractId);
        Session::flash('success', 'Umowa została dodana.');
        $this->redirect("/office/hr/{$clientId}/employees/{$empId}");
    }

    public function contractTerminate(string $clientId, string $empId, string $contractId): void
    {
        $clientId   = (int) $clientId;
        $empId      = (int) $empId;
        $contractId = (int) $contractId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/employees/{$empId}");
            return;
        }

        $contract = HrContract::findById($contractId);
        if (!$contract || (int)$contract['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $endDate = trim($_POST['end_date'] ?? date('Y-m-d'));
        HrContract::terminate($contractId, $endDate);
        HrEmployee::update($empId, ['employment_end' => $endDate, 'is_active' => 0]);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_contract_terminate', json_encode(['contract_id' => $contractId, 'end_date' => $endDate]), 'hr_contract', $contractId);
        Session::flash('success', 'Umowa została rozwiązana.');
        $this->redirect("/office/hr/{$clientId}/employees/{$empId}");
    }

    public function leaveRequests(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);
        $status   = $_GET['status'] ?? null;
        $requests = HrLeaveRequest::findByClient($clientId, $status ?: null);
        $leaveTypes = HrLeaveType::findAll();
        $this->render('office/hr/leave_requests', compact('client', 'requests', 'leaveTypes', 'clientId', 'status'));
    }

    public function leaveCreate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/leaves");
            return;
        }

        $empId       = (int) ($_POST['employee_id'] ?? 0);
        $leaveTypeId = (int) ($_POST['leave_type_id'] ?? 0);
        $dateFrom    = trim($_POST['date_from'] ?? '');
        $dateTo      = trim($_POST['date_to'] ?? '');

        if (!$empId || !$leaveTypeId || !$dateFrom || !$dateTo) {
            Session::flash('error', 'Wypełnij wszystkie wymagane pola.');
            $this->redirect("/office/hr/{$clientId}/leaves");
            return;
        }

        $employee = HrEmployee::findById($empId);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            $this->forbidden();
        }

        $daysCount = HrLeaveService::countBusinessDays($dateFrom, $dateTo);

        $requestId = HrLeaveRequest::create([
            'employee_id'       => $empId,
            'client_id'         => $clientId,
            'leave_type_id'     => $leaveTypeId,
            'date_from'         => $dateFrom,
            'date_to'           => $dateTo,
            'days_count'        => $daysCount,
            'notes'             => trim($_POST['notes'] ?? '') ?: null,
            'status'            => 'pending',
            'submitted_by_type' => $this->actorType(),
            'submitted_by_id'   => $this->actorId(),
        ]);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_leave_create', json_encode(['request_id' => $requestId]), 'hr_leave_request', $requestId);
        HrNotificationService::notifyLeaveCreatedByOffice($clientId, $employee['first_name'] . ' ' . $employee['last_name'], $dateFrom, $dateTo);
        Session::flash('success', 'Wniosek urlopowy został złożony.');
        $this->redirect("/office/hr/{$clientId}/leaves");
    }

    public function leaveApprove(string $clientId, string $id): void
    {
        $clientId  = (int) $clientId;
        $id        = (int) $id;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/leaves");
            return;
        }

        $request = HrLeaveRequest::findById($id);
        if (!$request || (int)$request['client_id'] !== $clientId || $request['status'] !== 'pending') {
            Session::flash('error', 'Nie można zatwierdzić tego wniosku.');
            $this->redirect("/office/hr/{$clientId}/leaves");
            return;
        }

        HrLeaveRequest::approve($id, $this->actorType(), $this->actorId());
        $year = (int) date('Y', strtotime($request['date_from']));
        HrLeaveBalance::adjustUsed($request['employee_id'], $year, $request['leave_type_id'], (float)$request['days_count']);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_leave_approve', json_encode(['request_id' => $id]), 'hr_leave_request', $id);
        Session::flash('success', 'Wniosek urlopowy został zatwierdzony.');
        $this->redirect("/office/hr/{$clientId}/leaves");
    }

    public function leaveReject(string $clientId, string $id): void
    {
        $clientId = (int) $clientId;
        $id       = (int) $id;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/leaves");
            return;
        }

        $request = HrLeaveRequest::findById($id);
        if (!$request || (int)$request['client_id'] !== $clientId || $request['status'] !== 'pending') {
            Session::flash('error', 'Nie można odrzucić tego wniosku.');
            $this->redirect("/office/hr/{$clientId}/leaves");
            return;
        }

        $reason = trim($_POST['rejection_reason'] ?? '');
        HrLeaveRequest::reject($id, $this->actorType(), $this->actorId(), $reason);

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_leave_reject', json_encode(['request_id' => $id]), 'hr_leave_request', $id);
        Session::flash('success', 'Wniosek urlopowy został odrzucony.');
        $this->redirect("/office/hr/{$clientId}/leaves");
    }

    public function leaveCalendar(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
        $year  = max(2020, (int) ($_GET['year'] ?? date('Y')));

        $firstDay = sprintf('%04d-%02d-01', $year, $month);
        $lastDay  = date('Y-m-t', strtotime($firstDay));

        $requests = Database::getInstance()->fetchAll(
            "SELECT lr.*, lt.name_pl AS leave_type, lt.code AS leave_code,
                    e.id AS emp_id, e.first_name, e.last_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name
             FROM hr_leave_requests lr
             JOIN hr_leave_types lt ON lr.leave_type_id = lt.id
             JOIN hr_employees e ON lr.employee_id = e.id
             WHERE lr.client_id = ?
               AND lr.status IN ('pending','approved')
               AND lr.date_from <= ? AND lr.date_to >= ?
             ORDER BY e.last_name, lr.date_from",
            [$clientId, $lastDay, $firstDay]
        );

        $calendarData = [];
        $shortTypes   = ['wypoczynkowy' => 'wys', 'chorobowy' => 'L4', 'macierzynski' => 'mac',
                         'ojcowski' => 'ojc', 'okolicznosciowy' => 'okol', 'bezplatny' => 'bezp',
                         'na_zadanie' => 'żąd', 'wychowawczy' => 'wych'];

        foreach ($requests as $req) {
            $from = max($req['date_from'], $firstDay);
            $to   = min($req['date_to'],   $lastDay);
            $cur  = strtotime($from);
            $end  = strtotime($to);
            while ($cur <= $end) {
                $d = date('Y-m-d', $cur);
                $calendarData[$d][] = [
                    'employee_id'      => $req['emp_id'],
                    'employee_name'    => $req['employee_name'],
                    'first_name'       => $req['first_name'],
                    'last_name'        => $req['last_name'],
                    'leave_type'       => $req['leave_type'],
                    'leave_type_short' => $shortTypes[$req['leave_code']] ?? $req['leave_code'],
                    'status'           => $req['status'],
                ];
                $cur += 86400;
            }
        }

        $employees = Database::getInstance()->fetchAll(
            "SELECT id, CONCAT(first_name, ' ', last_name) AS full_name
             FROM hr_employees WHERE client_id = ? AND is_active = 1 ORDER BY last_name, first_name",
            [$clientId]
        );

        $holidays = HrLeaveService::getPolishHolidays($year);

        $this->render('office/hr/leave_calendar', compact(
            'client', 'clientId', 'month', 'year', 'calendarData', 'employees', 'holidays'
        ));
    }
}
