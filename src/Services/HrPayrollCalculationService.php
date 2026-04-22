<?php

namespace App\Services;

class HrPayrollCalculationService
{
    public const RATE_EMERYTALNE_EMP = 0.0976;
    public const RATE_RENTOWE_EMP   = 0.0150;
    public const RATE_CHOROBOWE_EMP = 0.0245;
    public const RATE_EMERYTALNE_ER = 0.0976;
    public const RATE_RENTOWE_ER    = 0.0650;

    public static function calculate(array $employee, array $contract, array $settings, array $overrides, array $ytd): array
    {
        $contractType = $contract['contract_type'] ?? 'uop';
        $fraction     = (float) ($contract['work_time_fraction'] ?? 1.0);

        $baseSalary       = (float) ($contract['base_salary'] ?? 0);
        $overtimePay      = (float) ($overrides['overtime_pay']      ?? 0);
        $bonus            = (float) ($overrides['bonus']             ?? 0);
        $otherAdditions   = (float) ($overrides['other_additions']   ?? 0);
        $sickPayReduction = (float) ($overrides['sick_pay_reduction'] ?? 0);

        $grossSalary = max(0.0, round(($baseSalary * $fraction) + $overtimePay + $bonus + $otherAdditions - $sickPayReduction, 2));

        $zusLimit     = (float) ($settings['zus_annual_basis_limit'] ?? 260190.00);
        $fpRate       = (float) ($settings['fp_rate']                ?? 0.0245);
        $fgspRate     = (float) ($settings['fgsp_rate']              ?? 0.0010);
        $minWage      = (float) ($settings['min_wage_monthly']       ?? 4666.00);
        $pitThreshold = (float) ($settings['tax_threshold_32pct']    ?? 120000.00);
        $monthlyRelief= (float) ($settings['monthly_tax_relief']     ?? 300.00);

        $pit2Submitted = (bool) ($employee['pit2_submitted'] ?? false);
        $kupAmount     = (float) (($employee['kup_amount'] ?? '250') === '300' ? 300 : 250);
        $ppkEnrolled   = (bool) ($employee['ppk_enrolled'] ?? false);
        $ppkEmpRate    = (float) ($employee['ppk_employee_rate'] ?? 2.0) / 100;
        $ppkErRate     = (float) ($employee['ppk_employer_rate'] ?? 1.5) / 100;

        $zusRetEmp  = (bool) ($contract['zus_emerytalne_employee'] ?? true);
        $zusRetEr   = (bool) ($contract['zus_emerytalne_employer'] ?? true);
        $zusRenEmp  = (bool) ($contract['zus_rentowe_employee']    ?? true);
        $zusRenEr   = (bool) ($contract['zus_rentowe_employer']    ?? true);
        $zusChor    = (bool) ($contract['zus_chorobowe']           ?? true);
        $zusWyp     = (bool) ($contract['zus_wypadkowe']           ?? true);
        $zusFp      = (bool) ($contract['zus_fp']                  ?? true);
        $zusFgsp    = (bool) ($contract['zus_fgsp']                ?? true);
        $wypadkoweRate = (float) ($contract['wypadkowe_rate'] ?? $settings['wypadkowe_rate'] ?? 0.0167);

        $ytdGross   = (float) ($ytd['ytd_gross']    ?? 0);
        $ytdZusBase = (float) ($ytd['ytd_zus_base'] ?? 0);

        if ($contractType === 'uod') {
            return self::calculateUod($grossSalary, $baseSalary, $fraction, $overtimePay, $bonus, $otherAdditions, $sickPayReduction, $settings, $ytdGross, $pitThreshold, $pit2Submitted, $ppkEnrolled, $ppkEmpRate, $ppkErRate);
        }

        if ($contractType === 'uz') {
            $hasOtherEmployment = (bool) ($contract['has_other_employment'] ?? false);
            if ($hasOtherEmployment) {
                $zusRetEmp = false; $zusRetEr = false; $zusRenEmp = false; $zusRenEr = false;
                $zusChor = false; $zusWyp = false; $zusFp = false; $zusFgsp = false;
            }
        }

        $zusBaseForCalc = $grossSalary;
        if ($ytdZusBase >= $zusLimit) {
            $zusRetEmp = false; $zusRetEr = false; $zusRenEmp = false; $zusRenEr = false;
        } elseif ($ytdZusBase + $grossSalary > $zusLimit) {
            $zusBaseForCalc = $zusLimit - $ytdZusBase;
        }

        $zusEmerytalneEmp  = $zusRetEmp ? round($zusBaseForCalc * self::RATE_EMERYTALNE_EMP, 2) : 0.0;
        $zusRentowneEmp    = $zusRenEmp ? round($zusBaseForCalc * self::RATE_RENTOWE_EMP, 2)    : 0.0;
        $zusChoroboweEmp   = $zusChor   ? round($grossSalary    * self::RATE_CHOROBOWE_EMP, 2)  : 0.0;
        $zusTotalEmployee  = $zusEmerytalneEmp + $zusRentowneEmp + $zusChoroboweEmp;

        $taxBase = max(0, (int) round($grossSalary - $zusTotalEmployee - $kupAmount));
        $pitRate      = ($ytdGross >= $pitThreshold) ? 0.32 : 0.12;
        $pitCalculated= round($taxBase * $pitRate, 2);
        $taxRelief    = $pit2Submitted ? $monthlyRelief : 0.0;
        $pitAdvance   = max(0, (int) round($pitCalculated - $taxRelief));

        $ppkEmployee  = $ppkEnrolled ? round($grossSalary * $ppkEmpRate, 2) : 0.0;
        $ppkEmployer  = $ppkEnrolled ? round($grossSalary * $ppkErRate,  2) : 0.0;

        $netSalary = round($grossSalary - $zusTotalEmployee - $pitAdvance - $ppkEmployee, 2);

        $zusEmerytalneEr = $zusRetEr  ? round($zusBaseForCalc * self::RATE_EMERYTALNE_ER, 2) : 0.0;
        $zusRentowneEr   = $zusRenEr  ? round($zusBaseForCalc * self::RATE_RENTOWE_ER, 2)    : 0.0;
        $zusWypadkoweEr  = $zusWyp    ? round($grossSalary    * $wypadkoweRate, 2)            : 0.0;
        $aboveMinWage    = ($grossSalary >= $minWage);
        $zusFpEr         = ($zusFp   && $aboveMinWage) ? round($grossSalary * $fpRate,   2) : 0.0;
        $zusFgspEr       = ($zusFgsp && $aboveMinWage) ? round($grossSalary * $fgspRate, 2) : 0.0;
        $zusFepEr        = 0.0;
        $zusTotalEmployer= round($zusEmerytalneEr + $zusRentowneEr + $zusWypadkoweEr + $zusFpEr + $zusFgspEr, 2);
        $employerTotalCost = round($grossSalary + $zusTotalEmployer + $ppkEmployer, 2);

        $calcParams = [
            'contract_type' => $contractType, 'base_salary' => $baseSalary, 'fraction' => $fraction,
            'rate_emerytalne' => self::RATE_EMERYTALNE_EMP, 'rate_rentowe_emp' => self::RATE_RENTOWE_EMP,
            'rate_chorobowe' => self::RATE_CHOROBOWE_EMP, 'rate_rentowe_er' => self::RATE_RENTOWE_ER,
            'wypadkowe_rate' => $wypadkoweRate, 'fp_rate' => $fpRate, 'fgsp_rate' => $fgspRate,
            'kup_amount' => $kupAmount, 'pit_rate' => $pitRate, 'tax_relief_monthly' => $taxRelief,
            'pit2_submitted' => $pit2Submitted, 'ppk_enrolled' => $ppkEnrolled,
            'ppk_emp_rate' => $ppkEmpRate, 'ppk_er_rate' => $ppkErRate,
            'zus_limit' => $zusLimit, 'ytd_gross_before' => $ytdGross, 'ytd_zus_before' => $ytdZusBase,
        ];

        return [
            'base_salary' => $baseSalary * $fraction, 'overtime_pay' => $overtimePay,
            'bonus' => $bonus, 'other_additions' => $otherAdditions, 'sick_pay_reduction' => $sickPayReduction,
            'gross_salary' => $grossSalary, 'zus_emerytalne_emp' => $zusEmerytalneEmp,
            'zus_rentowe_emp' => $zusRentowneEmp, 'zus_chorobowe_emp' => $zusChoroboweEmp,
            'zus_total_employee' => $zusTotalEmployee, 'kup_amount' => $kupAmount,
            'tax_base' => $taxBase, 'pit_rate' => (int) ($pitRate * 100),
            'pit_calculated' => $pitCalculated, 'tax_relief_monthly' => $taxRelief,
            'pit_advance' => $pitAdvance, 'ppk_employee' => $ppkEmployee, 'ppk_employer' => $ppkEmployer,
            'net_salary' => $netSalary, 'zus_emerytalne_emp2' => $zusEmerytalneEr,
            'zus_rentowe_emp2' => $zusRentowneEr, 'zus_wypadkowe_emp2' => $zusWypadkoweEr,
            'zus_fp_emp2' => $zusFpEr, 'zus_fgsp_emp2' => $zusFgspEr, 'zus_fep_emp2' => $zusFepEr,
            'zus_total_employer' => $zusTotalEmployer, 'employer_total_cost' => $employerTotalCost,
            'ytd_gross' => $ytdGross + $grossSalary, 'ytd_zus_base' => $ytdZusBase + $zusBaseForCalc,
            'calculation_params' => json_encode($calcParams),
        ];
    }

    private static function calculateUod(float $grossSalary, float $baseSalary, float $fraction, float $overtimePay, float $bonus, float $otherAdditions, float $sickPayReduction, array $settings, float $ytdGross, float $pitThreshold, bool $pit2Submitted, bool $ppkEnrolled, float $ppkEmpRate, float $ppkErRate): array
    {
        $monthlyRelief = (float) ($settings['monthly_tax_relief'] ?? 300.00);
        $kupAmount  = round($grossSalary * 0.50, 2);
        $taxBase    = max(0, (int) round($grossSalary - $kupAmount));
        $pitRate    = ($ytdGross >= $pitThreshold) ? 0.32 : 0.12;
        $taxRelief  = $pit2Submitted ? $monthlyRelief : 0.0;
        $pitAdvance = max(0, (int) round($taxBase * $pitRate - $taxRelief));
        $netSalary  = round($grossSalary - $pitAdvance, 2);

        $calcParams = ['contract_type' => 'uod', 'kup_pct' => 50, 'pit_rate' => $pitRate, 'tax_relief' => $taxRelief, 'pit2_submitted' => $pit2Submitted];

        return [
            'base_salary' => $baseSalary * $fraction, 'overtime_pay' => $overtimePay,
            'bonus' => $bonus, 'other_additions' => $otherAdditions, 'sick_pay_reduction' => $sickPayReduction,
            'gross_salary' => $grossSalary, 'zus_emerytalne_emp' => 0.0, 'zus_rentowe_emp' => 0.0,
            'zus_chorobowe_emp' => 0.0, 'zus_total_employee' => 0.0, 'kup_amount' => $kupAmount,
            'tax_base' => $taxBase, 'pit_rate' => (int) ($pitRate * 100),
            'pit_calculated' => round($taxBase * $pitRate, 2), 'tax_relief_monthly' => $taxRelief,
            'pit_advance' => $pitAdvance, 'ppk_employee' => 0.0, 'ppk_employer' => 0.0,
            'net_salary' => $netSalary, 'zus_emerytalne_emp2' => 0.0, 'zus_rentowe_emp2' => 0.0,
            'zus_wypadkowe_emp2' => 0.0, 'zus_fp_emp2' => 0.0, 'zus_fgsp_emp2' => 0.0, 'zus_fep_emp2' => 0.0,
            'zus_total_employer' => 0.0, 'employer_total_cost' => $grossSalary,
            'ytd_gross' => $ytdGross + $grossSalary, 'ytd_zus_base' => 0.0,
            'calculation_params' => json_encode($calcParams),
        ];
    }
}
