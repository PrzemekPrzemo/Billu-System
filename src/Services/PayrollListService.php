<?php

namespace App\Services;

use App\Core\Database;
use App\Models\PayrollList;
use App\Models\PayrollEntry;
use App\Models\EmployeeContract;
use App\Models\ClientEmployee;

class PayrollListService
{
    /**
     * Generate payroll list for a client for a given month.
     * Creates the list header and calculates entries for all active contracts.
     */
    public static function generateForMonth(int $clientId, int $year, int $month, string $createdByType = 'office', ?int $createdById = null): ?int
    {
        // Check if list already exists
        $existing = PayrollList::findByClientPeriod($clientId, $year, $month);
        if ($existing) {
            // Recalculate existing list
            return self::recalculate((int)$existing['id']);
        }

        // Create list header
        $listId = PayrollList::create([
            'client_id' => $clientId,
            'year' => $year,
            'month' => $month,
            'title' => sprintf('Lista płac %02d/%d', $month, $year),
            'status' => 'draft',
            'created_by_type' => $createdByType,
            'created_by_id' => $createdById,
        ]);

        if (!$listId) {
            return null;
        }

        self::calculateEntries($listId, $clientId, $year, $month);
        self::updateTotals($listId);

        PayrollList::update($listId, ['status' => 'calculated']);

        return $listId;
    }

    /**
     * Recalculate all entries for an existing payroll list.
     */
    public static function recalculate(int $listId): ?int
    {
        $list = PayrollList::findById($listId);
        if (!$list || $list['status'] === 'approved') {
            return null;
        }

        // Delete existing entries and recalculate
        PayrollEntry::deleteByPayrollList($listId);
        self::calculateEntries($listId, (int)$list['client_id'], (int)$list['year'], (int)$list['month']);
        self::updateTotals($listId);

        PayrollList::update($listId, ['status' => 'calculated']);

        return $listId;
    }

    /**
     * Calculate payroll entries for all active contracts.
     */
    private static function calculateEntries(int $listId, int $clientId, int $year, int $month): void
    {
        $contracts = EmployeeContract::findActiveByClient($clientId);

        foreach ($contracts as $contract) {
            $employee = ClientEmployee::findById((int)$contract['employee_id']);
            if (!$employee || !$employee['is_active']) {
                continue;
            }

            // Check if contract is active in this month
            $periodStart = sprintf('%04d-%02d-01', $year, $month);
            $periodEnd = date('Y-m-t', strtotime($periodStart));

            if ($contract['start_date'] > $periodEnd) continue;
            if ($contract['end_date'] && $contract['end_date'] < $periodStart) continue;
            if ($contract['termination_date'] && $contract['termination_date'] < $periodStart) continue;

            $totalGross = (float)$contract['gross_salary'];
            $contractType = $contract['contract_type'];

            // Get YTD data for cumulative calculations
            $ytd = PayrollCalculatorService::getYtdTotals(
                (int)$contract['employee_id'],
                $year,
                $month - 1
            );

            // Calculate based on contract type
            $calc = match ($contractType) {
                'umowa_o_prace' => PayrollCalculatorService::calculateUmowaPrace($contract, $totalGross, $ytd),
                'umowa_zlecenie' => PayrollCalculatorService::calculateUmowaZlecenie($contract, $totalGross, $ytd),
                'umowa_o_dzielo' => PayrollCalculatorService::calculateUmowaDzielo($contract, $totalGross),
                default => null,
            };

            if (!$calc) continue;

            PayrollEntry::create([
                'payroll_list_id' => $listId,
                'client_id' => $clientId,
                'employee_id' => (int)$contract['employee_id'],
                'contract_id' => (int)$contract['id'],
                'gross_salary' => $totalGross,
                'overtime_amount' => 0,
                'bonus_amount' => 0,
                'other_additions' => 0,
                'total_gross' => $calc['total_gross'],
                'zus_emerytalna_employee' => $calc['zus_emerytalna_employee'],
                'zus_rentowa_employee' => $calc['zus_rentowa_employee'],
                'zus_chorobowa_employee' => $calc['zus_chorobowa_employee'],
                'zus_total_employee' => $calc['zus_total_employee'],
                'health_insurance_base' => $calc['health_insurance_base'],
                'health_insurance_full' => $calc['health_insurance_full'],
                'health_insurance_deductible' => $calc['health_insurance_deductible'] ?? 0,
                'tax_deductible_costs' => $calc['tax_deductible_costs'],
                'tax_base' => $calc['tax_base'],
                'pit_advance' => $calc['pit_advance'],
                'ppk_employee' => $calc['ppk_employee'],
                'ppk_employer' => $calc['ppk_employer'],
                'net_salary' => $calc['net_salary'],
                'zus_emerytalna_employer' => $calc['zus_emerytalna_employer'],
                'zus_rentowa_employer' => $calc['zus_rentowa_employer'],
                'zus_wypadkowa_employer' => $calc['zus_wypadkowa_employer'],
                'zus_fp_employer' => $calc['zus_fp_employer'],
                'zus_fgsp_employer' => $calc['zus_fgsp_employer'],
                'total_employer_cost' => $calc['total_employer_cost'],
                'calculation_json' => json_encode($calc, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * Update payroll list totals from entries.
     */
    private static function updateTotals(int $listId): void
    {
        $db = Database::getInstance();
        $totals = $db->fetchOne(
            "SELECT COALESCE(SUM(total_gross), 0) as total_gross,
                    COALESCE(SUM(net_salary), 0) as total_net,
                    COALESCE(SUM(total_employer_cost), 0) as total_employer_cost
             FROM payroll_entries WHERE payroll_list_id = ?",
            [$listId]
        );

        PayrollList::update($listId, [
            'total_gross' => $totals['total_gross'],
            'total_net' => $totals['total_net'],
            'total_employer_cost' => $totals['total_employer_cost'],
        ]);
    }
}
