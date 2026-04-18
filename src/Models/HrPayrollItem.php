<?php

namespace App\Models;

use App\Core\HrDatabase;
use App\Services\HrEncryptionService;

class HrPayrollItem
{
    public static function findByRun(int $runId): array
    {
        return HrDatabase::getInstance()->fetchAll(
            "SELECT pi.*,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.first_name, e.last_name,
                    c.contract_type, c.position
             FROM hr_payroll_items pi
             JOIN hr_employees e ON pi.employee_id = e.id
             JOIN hr_contracts c ON pi.contract_id  = c.id
             WHERE pi.payroll_run_id = ?
             ORDER BY e.last_name, e.first_name",
            [$runId]
        );
    }

    public static function findByEmployee(int $employeeId, int $runId): ?array
    {
        return HrDatabase::getInstance()->fetchOne(
            "SELECT pi.*,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name
             FROM hr_payroll_items pi
             JOIN hr_employees e ON pi.employee_id = e.id
             WHERE pi.employee_id = ? AND pi.payroll_run_id = ?",
            [$employeeId, $runId]
        );
    }

    public static function findForPayslip(int $runId, int $employeeId): ?array
    {
        $mainDb = HrDatabase::mainDbName();
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT pi.*,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.first_name, e.last_name, e.pesel, e.address_street,
                    e.address_city, e.address_zip, e.bank_account_iban,
                    c.contract_type, c.position, c.department,
                    cl.company_name, cl.nip AS client_nip,
                    r.period_month, r.period_year
             FROM hr_payroll_items pi
             JOIN hr_employees e  ON pi.employee_id = e.id
             JOIN hr_contracts c  ON pi.contract_id  = c.id
             JOIN {$mainDb}.clients cl ON pi.client_id = cl.id
             JOIN hr_payroll_runs r ON pi.payroll_run_id = r.id
             WHERE pi.payroll_run_id = ? AND pi.employee_id = ?",
            [$runId, $employeeId]
        );

        if ($row) {
            $row = HrEncryptionService::decryptFields($row, [
                'pesel', 'address_street', 'address_city', 'address_zip', 'bank_account_iban',
            ]);
        }
        return $row;
    }

    public static function upsert(array $data): int
    {
        $db = HrDatabase::getInstance();

        $existing = $db->fetchOne(
            "SELECT id FROM hr_payroll_items WHERE payroll_run_id = ? AND employee_id = ?",
            [$data['payroll_run_id'], $data['employee_id']]
        );

        if ($existing) {
            $id = (int) $existing['id'];
            unset($data['payroll_run_id'], $data['employee_id']);
            $db->update('hr_payroll_items', $data, 'id = ?', [$id]);
            return $id;
        }

        return $db->insert('hr_payroll_items', $data);
    }

    public static function deleteByRun(int $runId): void
    {
        HrDatabase::getInstance()->query(
            "DELETE FROM hr_payroll_items WHERE payroll_run_id = ?",
            [$runId]
        );
    }

    public static function getYtd(int $employeeId, int $year, int $beforeMonth): array
    {
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT COALESCE(SUM(pi.gross_salary), 0) AS ytd_gross,
                    COALESCE(SUM(
                        CASE WHEN c.zus_emerytalne_employee = 1 OR c.zus_rentowe_employee = 1
                             THEN pi.gross_salary ELSE 0 END
                    ), 0) AS ytd_zus_base
             FROM hr_payroll_items pi
             JOIN hr_payroll_runs r ON pi.payroll_run_id = r.id
             JOIN hr_contracts c    ON pi.contract_id    = c.id
             WHERE pi.employee_id = ?
               AND r.period_year  = ?
               AND r.period_month < ?
               AND r.status NOT IN ('draft')",
            [$employeeId, $year, $beforeMonth]
        );
        return [
            'ytd_gross'    => (float) ($row['ytd_gross']    ?? 0),
            'ytd_zus_base' => (float) ($row['ytd_zus_base'] ?? 0),
        ];
    }
}
