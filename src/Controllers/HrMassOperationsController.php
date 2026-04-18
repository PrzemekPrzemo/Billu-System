<?php

namespace App\Controllers;

use App\Core\HrDatabase;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\HrContract;
use App\Models\HrEmployee;

class HrMassOperationsController extends HrController
{
    public function massSalaryUpdate(string $clientId): void
    {
        $clientId = (int) $clientId;
        $client   = $this->authorizeClientHr($clientId);

        $employees = HrEmployee::findByClient($clientId, true);
        $contracts = HrContract::findByClient($clientId, true);

        $contractMap = [];
        foreach ($contracts as $c) {
            $contractMap[$c['employee_id']] = $c;
        }

        $this->render('office/hr/mass_salary_update', compact('client', 'clientId', 'employees', 'contractMap'));
    }

    public function massSalaryApply(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        if (!$this->validateCsrf()) {
            $this->redirect("/office/hr/{$clientId}/mass-salary");
            return;
        }

        $mode   = $_POST['mode'] ?? 'percentage';
        if (!in_array($mode, ['percentage', 'fixed'], true)) {
            $mode = 'percentage';
        }
        $amount      = (float) ($_POST['amount'] ?? 0);
        $selectedIds = is_array($_POST['employee_ids'] ?? null) ? $_POST['employee_ids'] : [];

        if (!$amount || empty($selectedIds)) {
            Session::flash('error', 'Podaj kwot\u0119/procent i wybierz pracownik\u00f3w.');
            $this->redirect("/office/hr/{$clientId}/mass-salary");
            return;
        }

        $db      = HrDatabase::getInstance();
        $updated = 0;

        foreach ($selectedIds as $empId) {
            $empId    = (int) $empId;
            $contract = HrContract::findCurrentByEmployee($empId);
            if (!$contract || (int)$contract['client_id'] !== $clientId) continue;

            $oldSalary = (float) $contract['base_salary'];
            if ($mode === 'percentage') {
                $newSalary = round($oldSalary * (1 + $amount / 100), 2);
            } else {
                $newSalary = round($oldSalary + $amount, 2);
            }

            if ($newSalary <= 0) continue;

            $db->update('hr_contracts', ['base_salary' => $newSalary], 'id = ?', [$contract['id']]);
            $updated++;
        }

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_mass_salary_update',
            json_encode(['client_id' => $clientId, 'mode' => $mode, 'amount' => $amount, 'updated' => $updated]),
            'hr_client', $clientId);

        Session::flash('success', "Zaktualizowano wynagrodzenia dla {$updated} pracownik\u00f3w.");
        $this->redirect("/office/hr/{$clientId}/mass-salary");
    }

    public function massExport(string $clientId): void
    {
        $clientId = (int) $clientId;
        $this->authorizeClientHr($clientId);

        $employees = HrEmployee::findByClient($clientId);
        $contracts = HrContract::findByClient($clientId, true);

        $contractMap = [];
        foreach ($contracts as $c) {
            $contractMap[$c['employee_id']] = $c;
        }

        $decrypted = \App\Services\HrEncryptionService::decryptRows($employees, [
            'email', 'phone', 'bank_account_iban'
        ]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pracownicy');

        $headers = ['Imi\u0119', 'Nazwisko', 'Stanowisko', 'Typ umowy', 'Brutto', 'Email', 'Telefon', 'IBAN', 'Data zatrudnienia', 'Status'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . '1', $h);
            $sheet->getStyle(chr(65 + $i) . '1')->getFont()->setBold(true);
        }

        $row = 2;
        foreach ($decrypted as $emp) {
            $ct = $contractMap[$emp['id']] ?? null;
            $sheet->setCellValue('A' . $row, $emp['first_name']);
            $sheet->setCellValue('B' . $row, $emp['last_name']);
            $sheet->setCellValue('C' . $row, $ct['position'] ?? '');
            $sheet->setCellValue('D' . $row, $ct ? HrContract::getContractTypeLabel($ct['contract_type']) : '');
            $sheet->setCellValue('E' . $row, $ct['base_salary'] ?? '');
            $sheet->setCellValue('F' . $row, $emp['email'] ?? '');
            $sheet->setCellValue('G' . $row, $emp['phone'] ?? '');
            $sheet->setCellValue('H' . $row, $emp['bank_account_iban'] ?? '');
            $sheet->setCellValue('I' . $row, $emp['employment_start'] ?? '');
            $sheet->setCellValue('J' . $row, $emp['is_active'] ? 'Aktywny' : 'Archiwalny');
            $row++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        AuditLog::log($this->actorType(), $this->actorId(), 'hr_mass_export',
            json_encode(['client_id' => $clientId, 'count' => count($employees)]),
            'hr_client', $clientId);

        $dir = 'storage/hr/exports';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = $dir . "/pracownicy_{$clientId}_" . date('Ymd_His') . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="pracownicy_export.xlsx"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        @unlink($path);
        exit;
    }
}
