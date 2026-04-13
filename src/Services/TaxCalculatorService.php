<?php

namespace App\Services;

/**
 * Tax profitability calculator — compares ryczałt, liniowy, skala, IP Box.
 * Rates for 2026. Update constants yearly.
 */
class TaxCalculatorService
{
    // ── Podatek dochodowy ──────────────────────────────────
    private const SKALA_RATE_1 = 0.12;
    private const SKALA_RATE_2 = 0.32;
    private const SKALA_THRESHOLD = 120000;
    private const SKALA_FREE_AMOUNT = 30000;

    private const LINIOWY_RATE = 0.19;
    private const IP_BOX_RATE = 0.05;

    private const RYCZALT_RATES = [0.02, 0.03, 0.055, 0.085, 0.10, 0.12, 0.125, 0.14, 0.15, 0.17];

    // ── ZUS społeczny — stawki procentowe (bez zmian) ──────
    private const ZUS_EMERYTALNA_RATE = 0.1952;
    private const ZUS_RENTOWA_RATE = 0.08;
    private const ZUS_CHOROBOWA_RATE = 0.0245;
    private const ZUS_WYPADKOWA_RATE = 0.0167;
    private const ZUS_FP_RATE = 0.0245;

    // ── ZUS — bazy wymiaru 2026 ────────────────────────────
    // Pełny: 60% × prognozowane przeciętne wynagrodzenie 9420 PLN
    private const ZUS_BASE_FULL = 5652.00;
    // Preferencyjny: 30% × płaca minimalna 4806 PLN
    private const ZUS_BASE_PREFERENTIAL = 1441.80;
    // Mały ZUS Plus: przykładowa baza (zależna od dochodu)
    private const ZUS_BASE_MALY_PLUS = 2826.00;

    // ── ZUS zdrowotny 2026 ─────────────────────────────────
    private const HEALTH_RATE_SKALA = 0.09;     // 9% dochodu
    private const HEALTH_RATE_LINIOWY = 0.049;  // 4.9% dochodu
    // Minimum zdrowotna: 9% × płaca minimalna 4806 PLN
    private const HEALTH_MIN_MONTHLY = 432.54;

    // Ryczałt — progi zdrowotne 2026 (baza: przeciętne wynagrodzenie IV kw. 2025 = 9228.64 PLN)
    private const HEALTH_RYCZALT_TIERS = [
        ['max_revenue' => 60000,  'monthly' => 498.35],   // 60% × 9228.64 × 9%
        ['max_revenue' => 300000, 'monthly' => 830.58],   // 100% × 9228.64 × 9%
        ['max_revenue' => PHP_FLOAT_MAX, 'monthly' => 1495.04],  // 180% × 9228.64 × 9%
    ];

    // ── Dostępne warianty ZUS ──────────────────────────────
    public const ZUS_VARIANTS = [
        'full'                      => ['label_key' => 'zus_full',                      'has_social' => true,  'has_fp' => true,  'has_chorobowa' => true],
        'full_no_chorobowa'         => ['label_key' => 'zus_full_no_chorobowa',         'has_social' => true,  'has_fp' => true,  'has_chorobowa' => false],
        'preferential'              => ['label_key' => 'zus_preferential',              'has_social' => true,  'has_fp' => false, 'has_chorobowa' => true],
        'preferential_no_chorobowa' => ['label_key' => 'zus_preferential_no_chorobowa', 'has_social' => true,  'has_fp' => false, 'has_chorobowa' => false],
        'ulga_na_start'             => ['label_key' => 'zus_ulga_na_start',             'has_social' => false, 'has_fp' => false, 'has_chorobowa' => false],
        'maly_plus'                 => ['label_key' => 'zus_maly_plus',                 'has_social' => true,  'has_fp' => true,  'has_chorobowa' => true],
    ];

    public static function calculateAll(float $annualRevenue, bool $isGross, float $ryczaltRate, float $costs = 0, string $zusVariant = 'full'): array
    {
        $netRevenue = $isGross ? round($annualRevenue / 1.23, 2) : $annualRevenue;
        $zusSocial = self::calculateZusSocial($zusVariant);
        $zusTotal = $zusSocial['total_annual'];

        $incomeBase = max(0, $netRevenue - $costs - $zusTotal);

        $skala    = self::calcSkala($incomeBase, $netRevenue, $costs, $zusTotal);
        $liniowy  = self::calcLiniowy($incomeBase, $netRevenue, $costs, $zusTotal);
        $ipBox    = self::calcIpBox($incomeBase, $netRevenue, $costs, $zusTotal);
        $ryczalt  = self::calcRyczalt($netRevenue, $ryczaltRate, $zusTotal);

        $options = ['skala' => $skala, 'liniowy' => $liniowy, 'ip_box' => $ipBox, 'ryczalt' => $ryczalt];
        $bestKey = 'skala';
        $bestNet = -PHP_FLOAT_MAX;
        foreach ($options as $key => $opt) {
            if ($opt['net_income'] > $bestNet) {
                $bestNet = $opt['net_income'];
                $bestKey = $key;
            }
        }

        return [
            'input' => compact('annualRevenue', 'netRevenue', 'isGross', 'ryczaltRate', 'costs', 'zusVariant'),
            'zus_social' => $zusSocial,
            'skala' => $skala, 'liniowy' => $liniowy, 'ip_box' => $ipBox, 'ryczalt' => $ryczalt,
            'best' => $bestKey,
        ];
    }

    // ── Tax form calculators ───────────────────────────────

    private static function calcSkala(float $income, float $rev, float $costs, float $zus): array
    {
        $taxable = max(0, $income - self::SKALA_FREE_AMOUNT);
        $bracket1 = self::SKALA_THRESHOLD - self::SKALA_FREE_AMOUNT;
        $tax = $taxable <= $bracket1
            ? round($taxable * self::SKALA_RATE_1, 2)
            : round($bracket1 * self::SKALA_RATE_1 + ($taxable - $bracket1) * self::SKALA_RATE_2, 2);

        $health = self::healthSkala($income);
        return self::buildResult($rev, $costs, $zus, $income, $tax, $health, '12% / 32%', self::SKALA_FREE_AMOUNT);
    }

    private static function calcLiniowy(float $income, float $rev, float $costs, float $zus): array
    {
        $tax = round($income * self::LINIOWY_RATE, 2);
        $health = self::healthLiniowy($income);
        return self::buildResult($rev, $costs, $zus, $income, $tax, $health, '19%', 0);
    }

    private static function calcIpBox(float $income, float $rev, float $costs, float $zus): array
    {
        $tax = round($income * self::IP_BOX_RATE, 2);
        $health = self::healthLiniowy($income); // IP Box uses same health rate as liniowy
        return self::buildResult($rev, $costs, $zus, $income, $tax, $health, '5% (IP Box)', 0);
    }

    private static function calcRyczalt(float $rev, float $rate, float $zus): array
    {
        $taxBase = max(0, $rev - $zus);
        $tax = round($taxBase * $rate, 2);
        $health = self::healthRyczalt($rev);
        $result = self::buildResult($rev, 0, $zus, $rev, $tax, $health, round($rate * 100, 1) . '%', 0);
        $result['quarterly_tax'] = round($tax / 4, 2);
        $result['monthly_tax'] = round($tax / 12, 2);
        return $result;
    }

    private static function buildResult(float $rev, float $costs, float $zus, float $income, float $tax, float $health, string $rateLabel, float $free): array
    {
        $total = $tax + $zus + $health;
        $net = $rev - $costs - $total;
        return [
            'revenue' => $rev, 'costs' => $costs, 'zus_social' => $zus, 'income' => $income,
            'tax' => $tax, 'health_insurance' => $health,
            'total_burden' => round($total, 2), 'net_income' => round($net, 2),
            'effective_rate' => $rev > 0 ? round($total / $rev * 100, 1) : 0,
            'tax_rate_label' => $rateLabel, 'free_amount' => $free,
        ];
    }

    // ── ZUS społeczny ──────────────────────────────────────

    public static function calculateZusSocial(string $variant = 'full'): array
    {
        $config = self::ZUS_VARIANTS[$variant] ?? self::ZUS_VARIANTS['full'];

        if (!$config['has_social']) {
            return ['variant' => $variant, 'variant_label' => $config['label_key'], 'base_monthly' => 0,
                'emerytalna' => 0, 'rentowa' => 0, 'chorobowa' => 0, 'wypadkowa' => 0, 'fundusz_pracy' => 0,
                'total_monthly' => 0, 'total_annual' => 0];
        }

        $base = match ($variant) {
            'preferential', 'preferential_no_chorobowa' => self::ZUS_BASE_PREFERENTIAL,
            'maly_plus' => self::ZUS_BASE_MALY_PLUS,
            default => self::ZUS_BASE_FULL,
        };

        $e = round($base * self::ZUS_EMERYTALNA_RATE, 2);
        $r = round($base * self::ZUS_RENTOWA_RATE, 2);
        $c = $config['has_chorobowa'] ? round($base * self::ZUS_CHOROBOWA_RATE, 2) : 0;
        $w = round($base * self::ZUS_WYPADKOWA_RATE, 2);
        $f = $config['has_fp'] ? round($base * self::ZUS_FP_RATE, 2) : 0;
        $m = $e + $r + $c + $w + $f;

        return ['variant' => $variant, 'variant_label' => $config['label_key'], 'base_monthly' => $base,
            'emerytalna' => $e, 'rentowa' => $r, 'chorobowa' => $c, 'wypadkowa' => $w, 'fundusz_pracy' => $f,
            'total_monthly' => round($m, 2), 'total_annual' => round($m * 12, 2)];
    }

    // ── ZUS zdrowotny ──────────────────────────────────────

    private static function healthSkala(float $annualIncome): float
    {
        $monthly = max(self::HEALTH_MIN_MONTHLY, round(($annualIncome / 12) * self::HEALTH_RATE_SKALA, 2));
        return round($monthly * 12, 2);
    }

    private static function healthLiniowy(float $annualIncome): float
    {
        $monthly = max(self::HEALTH_MIN_MONTHLY, round(($annualIncome / 12) * self::HEALTH_RATE_LINIOWY, 2));
        return round($monthly * 12, 2);
    }

    private static function healthRyczalt(float $annualRevenue): float
    {
        foreach (self::HEALTH_RYCZALT_TIERS as $tier) {
            if ($annualRevenue <= $tier['max_revenue']) {
                return round($tier['monthly'] * 12, 2);
            }
        }
        return round(end(self::HEALTH_RYCZALT_TIERS)['monthly'] * 12, 2);
    }

    // ── Helpers ────────────────────────────────────────────

    public static function getAvailableRyczaltRates(): array { return self::RYCZALT_RATES; }
    public static function getZusVariants(): array { return self::ZUS_VARIANTS; }
}
