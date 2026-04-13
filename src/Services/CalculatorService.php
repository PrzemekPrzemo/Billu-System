<?php

namespace App\Services;

/**
 * General-purpose business calculators: brutto-netto, VAT, margin, salary.
 * Rates for 2026.
 */
class CalculatorService
{
    // Polish VAT rates
    public const VAT_RATES = [23, 8, 5, 0];

    /**
     * Brutto → Netto (remove VAT)
     */
    public static function bruttoToNetto(float $brutto, int $vatRate): array
    {
        $netto = round($brutto / (1 + $vatRate / 100), 2);
        $vat = round($brutto - $netto, 2);
        return ['brutto' => $brutto, 'netto' => $netto, 'vat' => $vat, 'vat_rate' => $vatRate];
    }

    /**
     * Netto → Brutto (add VAT)
     */
    public static function nettoToBrutto(float $netto, int $vatRate): array
    {
        $vat = round($netto * $vatRate / 100, 2);
        $brutto = round($netto + $vat, 2);
        return ['brutto' => $brutto, 'netto' => $netto, 'vat' => $vat, 'vat_rate' => $vatRate];
    }

    /**
     * VAT calculator — calculate VAT from any known amount
     */
    public static function calculateVat(float $amount, int $vatRate, string $amountType = 'netto'): array
    {
        if ($amountType === 'brutto') {
            return self::bruttoToNetto($amount, $vatRate);
        } elseif ($amountType === 'vat') {
            $netto = $vatRate > 0 ? round($amount * 100 / $vatRate, 2) : 0;
            $brutto = round($netto + $amount, 2);
            return ['brutto' => $brutto, 'netto' => $netto, 'vat' => $amount, 'vat_rate' => $vatRate];
        }
        return self::nettoToBrutto($amount, $vatRate);
    }

    /**
     * Margin calculator
     * Margin = (sell_price - buy_price) / sell_price × 100
     * Markup = (sell_price - buy_price) / buy_price × 100
     */
    public static function calculateMargin(float $buyPrice, float $sellPrice = 0, float $marginPercent = 0, string $calcMode = 'from_prices'): array
    {
        if ($calcMode === 'from_margin' && $buyPrice > 0 && $marginPercent > 0 && $marginPercent < 100) {
            $sellPrice = round($buyPrice / (1 - $marginPercent / 100), 2);
        } elseif ($calcMode === 'from_markup' && $buyPrice > 0 && $marginPercent > 0) {
            $sellPrice = round($buyPrice * (1 + $marginPercent / 100), 2);
        }

        $profit = round($sellPrice - $buyPrice, 2);
        $margin = $sellPrice > 0 ? round(($profit / $sellPrice) * 100, 2) : 0;
        $markup = $buyPrice > 0 ? round(($profit / $buyPrice) * 100, 2) : 0;

        return [
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'profit' => $profit,
            'margin_percent' => $margin,
            'markup_percent' => $markup,
        ];
    }

    /**
     * Salary calculator (UoP — umowa o pracę) 2026
     * Brutto pracownika → netto, koszt pracodawcy
     */
    public static function calculateSalary(float $brutto, bool $under26 = false, bool $ppk = false, float $ppkEmployeeRate = 2.0, float $ppkEmployerRate = 1.5, int $costType = 1): array
    {
        // Koszty uzyskania przychodu
        $kup = ($costType === 2) ? 300.00 : 250.00; // podwyższone lub standardowe

        // Składki pracownika
        $emerytalnaPrac = round($brutto * 0.0976, 2);
        $rentowaPrac    = round($brutto * 0.015, 2);
        $chorobowaPrac  = round($brutto * 0.0245, 2);
        $sumaSpolecznaPrac = $emerytalnaPrac + $rentowaPrac + $chorobowaPrac;

        // Podstawa zdrowotnego
        $baseZdrowotne = $brutto - $sumaSpolecznaPrac;
        $zdrowotnePrac = round($baseZdrowotne * 0.09, 2);

        // Podstawa podatku
        $basePodatek = max(0, round($brutto - $sumaSpolecznaPrac - $kup, 0));

        // Podatek PIT (12% — skala podatkowa, z kwotą wolną 300 PLN/mies. ulgi)
        $pit12 = round($basePodatek * 0.12, 0);
        $ulgaPodatkowa = 300; // miesięczna ulga (kwota zmniejszająca podatek)
        $pit = max(0, $pit12 - $ulgaPodatkowa);

        // Ulga dla młodych (do 26 lat) — brak PIT
        if ($under26) {
            $pit = 0;
        }

        // PPK pracownik
        $ppkPrac = $ppk ? round($brutto * $ppkEmployeeRate / 100, 2) : 0;

        // Netto
        $netto = round($brutto - $sumaSpolecznaPrac - $zdrowotnePrac - $pit - $ppkPrac, 2);

        // Składki pracodawcy
        $emerytalnaPracodawca = round($brutto * 0.0976, 2);
        $rentowaPracodawca    = round($brutto * 0.065, 2);
        $wypadkowaPracodawca  = round($brutto * 0.0167, 2);
        $fpPracodawca         = round($brutto * 0.0245, 2);
        $fgspPracodawca       = round($brutto * 0.001, 2);
        $ppkPracodawca        = $ppk ? round($brutto * $ppkEmployerRate / 100, 2) : 0;

        $sumaPracodawca = $emerytalnaPracodawca + $rentowaPracodawca + $wypadkowaPracodawca
            + $fpPracodawca + $fgspPracodawca + $ppkPracodawca;

        $kosztPracodawcy = round($brutto + $sumaPracodawca, 2);

        return [
            'brutto' => $brutto,
            'netto' => $netto,
            'koszt_pracodawcy' => $kosztPracodawcy,
            // Składki pracownika
            'prac_emerytalna' => $emerytalnaPrac,
            'prac_rentowa' => $rentowaPrac,
            'prac_chorobowa' => $chorobowaPrac,
            'prac_spoleczne_suma' => round($sumaSpolecznaPrac, 2),
            'prac_zdrowotna' => $zdrowotnePrac,
            'prac_pit' => $pit,
            'prac_ppk' => $ppkPrac,
            'kup' => $kup,
            // Składki pracodawcy
            'pracodawca_emerytalna' => $emerytalnaPracodawca,
            'pracodawca_rentowa' => $rentowaPracodawca,
            'pracodawca_wypadkowa' => $wypadkowaPracodawca,
            'pracodawca_fp' => $fpPracodawca,
            'pracodawca_fgsp' => $fgspPracodawca,
            'pracodawca_ppk' => $ppkPracodawca,
            'pracodawca_suma' => round($sumaPracodawca, 2),
            // Flags
            'under26' => $under26,
            'ppk_enabled' => $ppk,
        ];
    }

    /**
     * Mileage reimbursement calculator (kilometrówka).
     * Rates per km — Rozporządzenie MI, obowiązujące 2026 (bez zmian od 2023).
     */
    public static function calculateMileage(string $vehicleType, float $km): array
    {
        $rates = [
            'car_over_900'  => 1.15,
            'car_under_900' => 0.89,
            'motorcycle'    => 0.69,
            'moped'         => 0.42,
        ];

        $rate = $rates[$vehicleType] ?? $rates['car_over_900'];
        $amount = round($km * $rate, 2);

        return ['vehicle_type' => $vehicleType, 'km' => $km, 'rate_per_km' => $rate, 'amount' => $amount];
    }

    public const VEHICLE_TYPES = [
        'car_over_900'  => 'vehicle_car_over_900',
        'car_under_900' => 'vehicle_car_under_900',
        'motorcycle'    => 'vehicle_motorcycle',
        'moped'         => 'vehicle_moped',
    ];

    /**
     * Currency converter using NBP rates.
     */
    public static function convertCurrency(float $amount, string $fromCurrency, string $toCurrency, ?string $date = null): ?array
    {
        if ($fromCurrency === $toCurrency) {
            return ['amount' => $amount, 'result' => $amount, 'rate' => 1.0, 'from' => $fromCurrency, 'to' => $toCurrency, 'date' => $date ?? date('Y-m-d')];
        }

        // Convert via PLN
        $amountPln = $amount;
        $rateFrom = 1.0;
        if ($fromCurrency !== 'PLN') {
            $nbp = NbpExchangeRateService::getRate($fromCurrency, $date ?? date('Y-m-d'));
            if (!$nbp) return null;
            $rateFrom = (float) $nbp['rate'];
            $amountPln = $amount * $rateFrom;
        }

        $result = $amountPln;
        $rateTo = 1.0;
        if ($toCurrency !== 'PLN') {
            $nbp = NbpExchangeRateService::getRate($toCurrency, $date ?? date('Y-m-d'));
            if (!$nbp) return null;
            $rateTo = (float) $nbp['rate'];
            $result = $amountPln / $rateTo;
        }

        $crossRate = $rateTo > 0 ? round($rateFrom / $rateTo, 6) : 0;
        if ($fromCurrency === 'PLN' && $rateTo > 0) $crossRate = round(1 / $rateTo, 6);
        if ($toCurrency === 'PLN') $crossRate = round($rateFrom, 6);

        return [
            'amount' => $amount,
            'result' => round($result, 2),
            'rate' => $crossRate,
            'from' => $fromCurrency,
            'to' => $toCurrency,
            'date' => $date ?? date('Y-m-d'),
            'rate_from_pln' => $rateFrom,
            'rate_to_pln' => $rateTo,
        ];
    }

    public const CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP', 'CHF', 'CZK', 'SEK', 'NOK', 'DKK', 'JPY', 'CAD', 'AUD'];

    /**
     * Car lump-sum allowance (ryczałt samochodowy).
     * Monthly limit × rate per km, reduced by absence days (max 22 working days).
     */
    public static function calculateCarAllowance(string $vehicleType, float $monthlyKmLimit, int $absenceDays = 0): array
    {
        $rates = [
            'car_over_900'  => 1.15,
            'car_under_900' => 0.89,
            'motorcycle'    => 0.69,
            'moped'         => 0.42,
        ];

        $rate = $rates[$vehicleType] ?? $rates['car_over_900'];
        $workingDays = 22;
        $absenceDays = max(0, min($absenceDays, $workingDays));
        $fullAmount = round($monthlyKmLimit * $rate, 2);
        $reduction = $workingDays > 0 ? round($fullAmount * $absenceDays / $workingDays, 2) : 0;
        $finalAmount = round($fullAmount - $reduction, 2);

        return [
            'vehicle_type' => $vehicleType,
            'monthly_km_limit' => $monthlyKmLimit,
            'rate_per_km' => $rate,
            'full_amount' => $fullAmount,
            'absence_days' => $absenceDays,
            'reduction' => $reduction,
            'final_amount' => $finalAmount,
        ];
    }

    /**
     * Business profit calculator — revenue - costs = profit/loss.
     */
    public static function calculateProfit(float $revenue, float $costOfSales, float $fixedCosts): array
    {
        $grossProfit = round($revenue - $costOfSales, 2);
        $netProfit = round($grossProfit - $fixedCosts, 2);
        $marginPct = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0;

        return [
            'revenue' => $revenue,
            'cost_of_sales' => $costOfSales,
            'fixed_costs' => $fixedCosts,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'total_costs' => round($costOfSales + $fixedCosts, 2),
            'margin_percent' => $marginPct,
            'is_loss' => $netProfit < 0,
        ];
    }
}
