<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Database;
use App\Models\AuditLog;
use App\Models\ClientEmployee;
use App\Models\EmployeeLeave;
use App\Models\PayrollEntry;
use App\Services\PayrollPdfService;

/**
 * Self-service panel for client-employees (workers hired by a client).
 * Strict tenant isolation: every accessor cross-checks employee_id against
 * the session value. An employee never sees another employee's data, even
 * within the same company.
 */
class EmployeeController extends Controller
{
    public function dashboard(): void
    {
        Auth::requireClientEmployee();
        $employeeId = (int) Session::get('client_employee_id');
        $employee   = ClientEmployee::findById($employeeId);

        // Last 3 payslips, last 3 leaves — quick snapshot only.
        $allEntries = PayrollEntry::findByEmployee($employeeId);
        $latestPayslips = array_slice($allEntries, 0, 3);

        $allLeaves = EmployeeLeave::findByEmployee($employeeId);
        $latestLeaves = array_slice($allLeaves, 0, 3);

        $this->render('employee/dashboard', [
            'employee'       => $employee,
            'latestPayslips' => $latestPayslips,
            'latestLeaves'   => $latestLeaves,
        ]);
    }

    public function profile(): void
    {
        Auth::requireClientEmployee();
        $employee = ClientEmployee::findById((int) Session::get('client_employee_id'));
        $this->render('employee/profile', ['employee' => $employee]);
    }

    public function changePasswordForm(): void
    {
        Auth::requireClientEmployee();
        $this->render('employee/change_password');
    }

    public function changePassword(): void
    {
        Auth::requireClientEmployee();
        if (!$this->validateCsrf()) { $this->redirect('/employee/profile'); return; }

        $employeeId = (int) Session::get('client_employee_id');
        $employee   = ClientEmployee::findById($employeeId);

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!Auth::verifyPassword($current, $employee['password_hash'] ?? '')) {
            Session::flash('error', 'invalid_current_password');
            $this->redirect('/employee/change-password');
            return;
        }
        if ($new !== $confirm) {
            Session::flash('error', 'passwords_not_match');
            $this->redirect('/employee/change-password');
            return;
        }
        $errors = Auth::validatePasswordStrength($new);
        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            $this->redirect('/employee/change-password');
            return;
        }

        ClientEmployee::updatePassword($employeeId, Auth::hashPassword($new));
        AuditLog::log('client_employee', $employeeId, 'password_changed',
            'Self-service password change', 'client_employee', $employeeId);
        Session::flash('success', 'password_changed');
        Session::remove('force_password_change');
        $this->redirect('/employee/profile');
    }

    public function payslips(): void
    {
        Auth::requireClientEmployee();
        $employeeId = (int) Session::get('client_employee_id');
        $entries = PayrollEntry::findByEmployee($employeeId);
        $this->render('employee/payslips', ['entries' => $entries]);
    }

    public function payslipPdf(int $entryId): void
    {
        Auth::requireClientEmployee();
        $employeeId = (int) Session::get('client_employee_id');

        // Ownership check: the entry must belong to this employee.
        $entry = PayrollEntry::findById($entryId);
        if (!$entry || (int) ($entry['employee_id'] ?? 0) !== $employeeId) {
            $this->redirect('/employee/payslips');
            return;
        }

        $path = PayrollPdfService::generatePayslip($entryId);
        if ($path === null || !file_exists($path)) {
            Session::flash('error', 'pdf_not_available');
            $this->redirect('/employee/payslips');
            return;
        }

        AuditLog::log('client_employee', $employeeId, 'payslip_downloaded',
            "Payslip {$entryId}", 'payroll_entry', $entryId);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="payslip-' . $entryId . '.pdf"');
        readfile($path);
        exit;
    }

    public function leaves(): void
    {
        Auth::requireClientEmployee();
        $employeeId = (int) Session::get('client_employee_id');
        $leaves = EmployeeLeave::findByEmployee($employeeId);
        $this->render('employee/leaves', ['leaves' => $leaves]);
    }

    public function leaveRequestForm(): void
    {
        Auth::requireClientEmployee();
        $employeeId = (int) Session::get('client_employee_id');

        // Find active contract (required for leave request — used to compute balance).
        $contract = Database::getInstance()->fetchOne(
            "SELECT id FROM employee_contracts WHERE employee_id = ? AND status = 'active' ORDER BY start_date DESC LIMIT 1",
            [$employeeId]
        );

        $this->render('employee/leave_form', [
            'contract' => $contract,
        ]);
    }

    public function leaveRequest(): void
    {
        Auth::requireClientEmployee();
        if (!$this->validateCsrf()) { $this->redirect('/employee/leaves'); return; }

        $employeeId = (int) Session::get('client_employee_id');
        $clientId   = (int) Session::get('client_employee_client_id');

        $startDate = $_POST['start_date'] ?? '';
        $endDate   = $_POST['end_date']   ?? '';
        $type      = $_POST['leave_type'] ?? 'wypoczynkowy';
        $notes     = trim($_POST['notes'] ?? '');

        $allowedTypes = ['wypoczynkowy', 'chorobowy', 'macierzynski', 'ojcowski', 'wychowawczy', 'bezplatny', 'okolicznosciowy', 'na_zadanie', 'opieka_art188'];
        if (!in_array($type, $allowedTypes, true)) {
            Session::flash('error', 'invalid_leave_type');
            $this->redirect('/employee/leaves/request');
            return;
        }

        if (!self::isValidDate($startDate) || !self::isValidDate($endDate) || $endDate < $startDate) {
            Session::flash('error', 'invalid_dates');
            $this->redirect('/employee/leaves/request');
            return;
        }

        // Find active contract (required by FK in employee_leaves).
        $contract = Database::getInstance()->fetchOne(
            "SELECT id FROM employee_contracts WHERE employee_id = ? AND status = 'active' ORDER BY start_date DESC LIMIT 1",
            [$employeeId]
        );
        if (!$contract) {
            Session::flash('error', 'no_active_contract');
            $this->redirect('/employee/leaves');
            return;
        }

        $businessDays = self::countBusinessDays($startDate, $endDate);

        EmployeeLeave::create([
            'client_id'     => $clientId,
            'employee_id'   => $employeeId,
            'contract_id'   => (int) $contract['id'],
            'leave_type'    => $type,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'business_days' => $businessDays,
            'status'        => 'pending',
            'notes'         => $notes,
        ]);

        AuditLog::log('client_employee', $employeeId, 'leave_requested',
            "Leave {$type}: {$startDate} → {$endDate} ({$businessDays} dni)", 'employee_leave', null);
        Session::flash('success', 'leave_request_submitted');
        $this->redirect('/employee/leaves');
    }

    private static function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
    }

    /** Inclusive business days between two dates (Mon-Fri only, no holiday calendar). */
    private static function countBusinessDays(string $start, string $end): int
    {
        $startTs = strtotime($start);
        $endTs   = strtotime($end);
        if ($startTs === false || $endTs === false || $endTs < $startTs) {
            return 0;
        }
        $days = 0;
        for ($t = $startTs; $t <= $endTs; $t = strtotime('+1 day', $t)) {
            $dow = (int) date('N', $t);
            if ($dow >= 1 && $dow <= 5) {
                $days++;
            }
        }
        return $days;
    }
}
