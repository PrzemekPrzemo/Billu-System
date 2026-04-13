<?php

namespace App\Services;

use App\Core\Database;

/**
 * Payroll calculator — brutto → netto for Polish employment contracts.
 * Rates for 2026. Update constants yearly.
 */
class PayrollCalculatorService
{
    // ── ZUS Employee rates ─────────────────────────────────
    private const ZUS_EMERYTALNA_EMPLOYEE = 0.0976;
    private const ZUS_RENTOWA_EMPLOYEE    = 0.015;
    private const ZUS_CHOROBOWA_EMPLOYEE  = 0.0245;

    // ── ZUS Employer rates ─────────────────────────────────
    private const ZUS_EMERYTALNA_EMPLOYER = 0.0976;
    private const ZUS_RENTOWA_EMPLOYER    = 0.065;
    private const ZUS_WYPADKOWA_EMPLOYER  = 0.0167;
    private const ZUS_FP_EMPLOYER         = 0.0245;
    private const ZUS_FGSP_EMPLOYER       = 0.001;

    // ── Health insurance ───────────────────────────────────
    private const HEALTH_RATE_FULL        = 0.09;   // 9% of base

    // ── PIT ────────────────────────────────────────────────
    private const PIT_RATE_1              = 0.12;
    private const PIT_RATE_2              = 0.32;
    private const PIT_THRESHOLD_ANNUAL    = 120000;
    private const KWOTA_WOLNA_ANNUAL      = 30000;
    private const KWOTA_WOLNA_MONTHLY     = 300;    // 3600 / 12 = monthly PIT reduction
    private const KUP_BASIC               = 250;
    private const KUP_ELEVATED            = 300;
    private const ULGA_MLODYCH_LIMIT      = 85528;

    // ── ZUS annual threshold (30 × min wage) ───────────────
    private const MIN_WAGE_2026           = 4666.00;
    private const ZUS_ANNUAL_THRESHOLD    = 139980.00; // 30 × 4666

    // ── PPK default rates ──────────────────────────────────
    private const PPK_EMPLOYEE_DEFAULT    = 0.02;
    private const PPK_EMPLOYER_DEFAULT    = 0.015;

    /**
     * Calculate payroll for Umowa o Pracę (employment contract).
     */
    public static function calculateUmowaPrace(array $contract, float $totalGross, array $ytd = []): array
    {
        $ytdGross = (float)($ytd['gross'] ?? 0);
        $ytdPitPaid = (float)($ytd['pit_paid'] ?? 0);

        // 1. ZUS Employee contributions (check annual threshold)
        $zusEmerytalna = self::calcZusWithThreshold($totalGross, self::ZUS_EMERYTALNA_EMPLOYEE, $ytdGross);
        $zusRentowa = self::calcZusWithThreshold($totalGross, self::ZUS_RENTOWA_EMPLOYEE, $ytdGross);
        $zusChorobowa = round($totalGross * self::ZUS_CHOROBOWA_EMPLOYEE, 2); // No threshold for chorobowa
        $zusTotalEmployee = $zusEmerytalna + $zusRentowa + $zusChorobowa;

        // 2. Health insurance
        $healthBase = round($totalGross - $zusTotalEmployee, 2);
        $healthBase = max(0, $healthBase);
        $healthFull = round($healthBase * self::HEALTH_RATE_FULL, 2);

        // 3. Tax deductible costs (KUP)
        $kup = ($contract['tax_deductible_costs'] ?? 'basic') === 'elevated' ? self::KUP_ELEVATED : self::KUP_BASIC;

        // 4. Tax base
        $taxBase = $healthBase - $kup;
        $taxBase = max(0, floor($taxBase)); // Rounded down to full PLN

        // 5. PIT advance (cumulative method simplified to monthly)
        $pitExempt = !empty($contract['pit_exempt']);
        $usesKwotaWolna = !empty($contract['uses_kwota_wolna']);

        $pitAdvance = 0;
        if (!$pitExempt) {
            // Check if cumulative income exceeds threshold
            $ytdIncome = $ytdGross + $totalGross;
            if ($ytdIncome > self::PIT_THRESHOLD_ANNUAL) {
                $pitAdvance = round($taxBase * self::PIT_RATE_2, 0);
            } else {
                $pitAdvance = round($taxBase * self::PIT_RATE_1, 0);
            }

            // Apply kwota wolna reduction (monthly PIT-2 deduction)
            if ($usesKwotaWolna) {
                $pitAdvance = max(0, $pitAdvance - self::KWOTA_WOLNA_MONTHLY);
            }

            $pitAdvance = max(0, round($pitAdvance, 0));
        }

        // 6. PPK
        $ppkActive = !empty($contract['ppk_active']);
        $ppkEmployeeRate = $ppkActive ? ((float)($contract['ppk_employee_rate'] ?? 2.0)) / 100 : 0;
        $ppkEmployerRate = $ppkActive ? ((float)($contract['ppk_employer_rate'] ?? 1.5)) / 100 : 0;
        $ppkEmployee = round($totalGross * $ppkEmployeeRate, 2);
        $ppkEmployer = round($totalGross * $ppkEmployerRate, 2);

        // 7. Net salary
        $netSalary = round($totalGross - $zusTotalEmployee - $healthFull - $pitAdvance - $ppkEmployee, 2);

        // 8. Employer costs
        $zusEmerytalnaEmployer = self::calcZusWithThreshold($totalGross, self::ZUS_EMERYTALNA_EMPLOYER, $ytdGross);
        $zusRentowaEmployer = self::calcZusWithThreshold($totalGross, self::ZUS_RENTOWA_EMPLOYER, $ytdGross);
        $zusWypadkowaEmployer = round($totalGross * self::ZUS_WYPADKOWA_EMPLOYER, 2);
        $zusFpEmployer = round($totalGross * self::ZUS_FP_EMPLOYER, 2);
        $zusFgspEmployer = round($totalGross * self::ZUS_FGSP_EMPLOYER, 2);
        $totalEmployerZus = $zusEmerytalnaEmployer + $zusRentowaEmployer + $zusWypadkowaEmployer + $zusFpEmployer + $zusFgspEmployer;
        $totalEmployerCost = round($totalGross + $totalEmployerZus + $ppkEmployer, 2);

        return [
            'contract_type' => 'umowa_o_prace',
            'total_gross' => $totalGross,
            'zus_emerytalna_employee' => $zusEmerytalna,
            'zus_rentowa_employee' => $zusRentowa,
            'zus_chorobowa_employee' => $zusChorobowa,
            'zus_total_employee' => $zusTotalEmployee,
            'health_insurance_base' => $healthBase,
            'health_insurance_full' => $healthFull,
            'health_insurance_deductible' => 0,
            'tax_deductible_costs' => $kup,
            'tax_base' => $taxBase,
            'pit_advance' => $pitAdvance,
            'ppk_employee' => $ppkEmployee,
            'ppk_employer' => $ppkEmployer,
            'net_salary' => $netSalary,
            'zus_emerytalna_employer' => $zusEmerytalnaEmployer,
            'zus_rentowa_employer' => $zusRentowaEmployer,
            'zus_wypadkowa_employer' => $zusWypadkowaEmployer,
            'zus_fp_employer' => $zusFpEmployer,
            'zus_fgsp_employer' => $zusFgspEmployer,
            'total_employer_cost' => $totalEmployerCost,
        ];
    }

    /**
     * Calculate payroll for Umowa Zlecenie (civil law contract).
     */
    public static function calculateUmowaZlecenie(array $contract, float $amount, array $ytd = []): array
    {
        $ytdGross = (float)($ytd['gross'] ?? 0);

        // ZUS contributions (optional per contract flags)
        $zusEmerytalna = !empty($contract['zus_emerytalna'])
            ? self::calcZusWithThreshold($amount, self::ZUS_EMERYTALNA_EMPLOYEE, $ytdGross) : 0;
        $zusRentowa = !empty($contract['zus_rentowa'])
            ? self::calcZusWithThreshold($amount, self::ZUS_RENTOWA_EMPLOYEE, $ytdGross) : 0;
        $zusChorobowa = !empty($contract['zus_chorobowa'])
            ? round($amount * self::ZUS_CHOROBOWA_EMPLOYEE, 2) : 0;
        $zusTotalEmployee = $zusEmerytalna + $zusRentowa + $zusChorobowa;

        // Health insurance
        $healthBase = 0;
        $healthFull = 0;
        if (!empty($contract['zus_zdrowotna'])) {
            $healthBase = round($amount - $zusTotalEmployee, 2);
            $healthBase = max(0, $healthBase);
            $healthFull = round($healthBase * self::HEALTH_RATE_FULL, 2);
        }

        // KUP = 20% of (amount - ZUS employee)
        $kupBase = $amount - $zusTotalEmployee;
        $kup = round(max(0, $kupBase) * 0.20, 2);

        // Tax base
        $taxBase = max(0, floor($kupBase - $kup));

        // PIT
        $pitExempt = !empty($contract['pit_exempt']);
        $usesKwotaWolna = !empty($contract['uses_kwota_wolna']);
        $pitAdvance = 0;
        if (!$pitExempt) {
            $ytdIncome = $ytdGross + $amount;
            $rate = ($ytdIncome > self::PIT_THRESHOLD_ANNUAL) ? self::PIT_RATE_2 : self::PIT_RATE_1;
            $pitAdvance = round($taxBase * $rate, 0);
            if ($usesKwotaWolna) {
                $pitAdvance = max(0, $pitAdvance - self::KWOTA_WOLNA_MONTHLY);
            }
            $pitAdvance = max(0, round($pitAdvance, 0));
        }

        // PPK
        $ppkActive = !empty($contract['ppk_active']);
        $ppkEmployee = $ppkActive ? round($amount * ((float)($contract['ppk_employee_rate'] ?? 2.0)) / 100, 2) : 0;
        $ppkEmployer = $ppkActive ? round($amount * ((float)($contract['ppk_employer_rate'] ?? 1.5)) / 100, 2) : 0;

        // Net
        $netSalary = round($amount - $zusTotalEmployee - $healthFull - $pitAdvance - $ppkEmployee, 2);

        // Employer costs
        $zusEmerytalnaEmployer = !empty($contract['zus_emerytalna'])
            ? self::calcZusWithThreshold($amount, self::ZUS_EMERYTALNA_EMPLOYER, $ytdGross) : 0;
        $zusRentowaEmployer = !empty($contract['zus_rentowa'])
            ? self::calcZusWithThreshold($amount, self::ZUS_RENTOWA_EMPLOYER, $ytdGross) : 0;
        $zusWypadkowaEmployer = !empty($contract['zus_wypadkowa'])
            ? round($amount * self::ZUS_WYPADKOWA_EMPLOYER, 2) : 0;
        $zusFpEmployer = !empty($contract['zus_fp']) ? round($amount * self::ZUS_FP_EMPLOYER, 2) : 0;
        $zusFgspEmployer = !empty($contract['zus_fgsp']) ? round($amount * self::ZUS_FGSP_EMPLOYER, 2) : 0;
        $totalEmployerZus = $zusEmerytalnaEmployer + $zusRentowaEmployer + $zusWypadkowaEmployer + $zusFpEmployer + $zusFgspEmployer;
        $totalEmployerCost = round($amount + $totalEmployerZus + $ppkEmployer, 2);

        return [
            'contract_type' => 'umowa_zlecenie',
            'total_gross' => $amount,
            'zus_emerytalna_employee' => $zusEmerytalna,
            'zus_rentowa_employee' => $zusRentowa,
            'zus_chorobowa_employee' => $zusChorobowa,
            'zus_total_employee' => $zusTotalEmployee,
            'health_insurance_base' => $healthBase,
            'health_insurance_full' => $healthFull,
            'health_insurance_deductible' => 0,
            'tax_deductible_costs' => $kup,
            'tax_base' => $taxBase,
            'pit_advance' => $pitAdvance,
            'ppk_employee' => $ppkEmployee,
            'ppk_employer' => $ppkEmployer,
            'net_salary' => $netSalary,
            'zus_emerytalna_employer' => $zusEmerytalnaEmployer,
            'zus_rentowa_employer' => $zusRentowaEmployer,
            'zus_wypadkowa_employer' => $zusWypadkowaEmployer,
            'zus_fp_employer' => $zusFpEmployer,
            'zus_fgsp_employer' => $zusFgspEmployer,
            'total_employer_cost' => $totalEmployerCost,
        ];
    }

    /**
     * Calculate payroll for Umowa o Dzieło (contract for specific work).
     * No ZUS, no health insurance.
     */
    public static function calculateUmowaDzielo(array $contract, float $amount): array
    {
        // KUP = dzielo_kup_rate% of amount (default 20%, can be 50%)
        $kupRate = ((float)($contract['dzielo_kup_rate'] ?? 20.0)) / 100;
        $kup = round($amount * $kupRate, 2);

        // Tax base
        $taxBase = max(0, floor($amount - $kup));

        // PIT = 12% flat (no kwota wolna for dzielo)
        $pitAdvance = max(0, round($taxBase * self::PIT_RATE_1, 0));

        // Net
        $netSalary = round($amount - $pitAdvance, 2);

        return [
            'contract_type' => 'umowa_o_dzielo',
            'total_gross' => $amount,
            'zus_emerytalna_employee' => 0,
            'zus_rentowa_employee' => 0,
            'zus_chorobowa_employee' => 0,
            'zus_total_employee' => 0,
            'health_insurance_base' => 0,
            'health_insurance_full' => 0,
            'health_insurance_deductible' => 0,
            'tax_deductible_costs' => $kup,
            'tax_base' => $taxBase,
            'pit_advance' => $pitAdvance,
            'ppk_employee' => 0,
            'ppk_employer' => 0,
            'net_salary' => $netSalary,
            'zus_emerytalna_employer' => 0,
            'zus_rentowa_employer' => 0,
            'zus_wypadkowa_employer' => 0,
            'zus_fp_employer' => 0,
            'zus_fgsp_employer' => 0,
            'total_employer_cost' => $amount, // No employer ZUS for dzielo
        ];
    }

    /**
     * Get year-to-date totals for an employee from payroll_entries.
     */
    public static function getYtdTotals(int $employeeId, int $year, int $upToMonth): array
    {
        if ($upToMonth < 1) {
            return ['gross' => 0, 'pit_paid' => 0];
        }

        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT COALESCE(SUM(pe.total_gross), 0) as gross,
                    COALESCE(SUM(pe.pit_advance), 0) as pit_paid,
                    COALESCE(SUM(pe.zus_emerytalna_employee), 0) as zus_emerytalna,
                    COALESCE(SUM(pe.zus_rentowa_employee), 0) as zus_rentowa,
                    COALESCE(SUM(pe.health_insurance_base), 0) as health_base
             FROM payroll_entries pe
             INNER JOIN payroll_lists pl ON pl.id = pe.payroll_list_id
             WHERE pe.employee_id = ? AND pl.year = ? AND pl.month <= ?",
            [$employeeId, $year, $upToMonth]
        );

        return [
            'gross' => (float)($row['gross'] ?? 0),
            'pit_paid' => (float)($row['pit_paid'] ?? 0),
            'zus_emerytalna' => (float)($row['zus_emerytalna'] ?? 0),
            'zus_rentowa' => (float)($row['zus_rentowa'] ?? 0),
            'health_base' => (float)($row['health_base'] ?? 0),
        ];
    }

    /**
     * Calculate ZUS contribution with annual threshold check.
     * Emerytalna + Rentowa stop when YTD gross exceeds threshold.
     */
    private static function calcZusWithThreshold(float $gross, float $rate, float $ytdGross): float
    {
        $threshold = self::ZUS_ANNUAL_THRESHOLD;
        if ($ytdGross >= $threshold) {
            return 0; // Already exceeded threshold
        }

        $remaining = $threshold - $ytdGross;
        $base = min($gross, $remaining);
        return round($base * $rate, 2);
    }

    // ── Public helpers ─────────────────────────────────────

    public static function getMinWage(): float { return self::MIN_WAGE_2026; }
    public static function getZusThreshold(): float { return self::ZUS_ANNUAL_THRESHOLD; }
}
