<?php

namespace App\Services;

/**
 * Fetches exchange rates from NBP (National Bank of Poland) API.
 *
 * Per art. 31a ustawy o VAT, the exchange rate should be the mid rate
 * from the last business day before the invoice issue date.
 * NBP does not publish rates on weekends and public holidays, so we
 * walk backwards until a rate is found.
 */
class NbpExchangeRateService
{
    private const API_URL = 'https://api.nbp.pl/api/exchangerates/rates/a';
    private const MAX_LOOKBACK_DAYS = 10;
    private const ALLOWED_CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'CZK', 'SEK', 'NOK', 'DKK'];

    private static array $cache = [];

    /**
     * Get the NBP mid exchange rate for the given currency and reference date.
     *
     * Returns the rate from the last business day BEFORE $referenceDate,
     * per art. 31a ustawy o VAT. When issuing foreign-currency invoices,
     * pass the invoice issue date so the rate is fetched for the last
     * business day preceding it (D-1).
     *
     * @param string $currency    ISO 4217 currency code (e.g. EUR, USD, GBP)
     * @param string $referenceDate  The date to look back from (typically issue_date), format Y-m-d
     * @return array{rate: float, date: string, table: string}|null
     */
    public static function getRate(string $currency, string $referenceDate): ?array
    {
        $currency = strtoupper(trim($currency));
        if ($currency === 'PLN' || !in_array($currency, self::ALLOWED_CURRENCIES, true)) {
            return null;
        }

        $cacheKey = $currency . '_' . $referenceDate;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // Start from the day before referenceDate (art. 31a VAT - previous business day)
        $date = new \DateTime($referenceDate);
        $date->modify('-1 day');

        for ($i = 0; $i < self::MAX_LOOKBACK_DAYS; $i++) {
            $dateStr = $date->format('Y-m-d');
            $result = self::fetchFromNbp($currency, $dateStr);

            if ($result !== null) {
                self::$cache[$cacheKey] = $result;
                return $result;
            }

            $date->modify('-1 day');
        }

        return null;
    }

    /**
     * Get latest available NBP rates for multiple currencies.
     *
     * Uses today's date +1 day as "sale_date" so getRate() looks back
     * to today (or the most recent business day).
     * Results are cached in-memory per request.
     *
     * @param string[] $currencies e.g. ['EUR', 'USD', 'GBP']
     * @return array<string, array{rate: float, date: string, table: string}>
     */
    public static function getLatestRates(array $currencies = ['EUR', 'USD', 'GBP']): array
    {
        $rates = [];
        // Use tomorrow as "sale_date" so getRate() looks for today's rate (day before sale_date)
        $tomorrow = (new \DateTime('tomorrow'))->format('Y-m-d');

        foreach ($currencies as $currency) {
            $currency = strtoupper(trim($currency));
            $result = self::getRate($currency, $tomorrow);
            if ($result) {
                $rates[$currency] = $result;
            }
        }

        return $rates;
    }

    /**
     * Fetch rate for a specific date from NBP API Table A.
     *
     * @return array{rate: float, date: string, table: string}|null
     */
    private static function fetchFromNbp(string $currency, string $date): ?array
    {
        $url = self::API_URL . '/' . urlencode($currency) . '/' . $date . '/?format=json';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        // Check HTTP status from response headers
        $statusLine = $http_response_header[0] ?? '';
        if (strpos($statusLine, '200') === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || empty($data['rates'][0])) {
            return null;
        }

        $rateData = $data['rates'][0];

        return [
            'rate' => (float)$rateData['mid'],
            'date' => $rateData['effectiveDate'] ?? $date,
            'table' => $rateData['no'] ?? '',
        ];
    }
}
