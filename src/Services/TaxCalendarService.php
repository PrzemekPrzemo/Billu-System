<?php

namespace App\Services;

use App\Models\ClientTaxConfig;
use App\Models\TaxCalendarAlert;
use App\Models\TaxPayment;
use App\Models\Notification;
use App\Models\Client;

class TaxCalendarService
{
    /**
     * Polish tax obligation definitions.
     * Each entry: [day => deadline day, months => 'monthly'|'quarterly'|specific month, condition => callback on config]
     */
    private const OBLIGATIONS = [
        'VAT-7' => [
            'day' => 25,
            'type' => 'monthly',
            'condition' => ['vat_period' => 'monthly'],
            'tax_type' => 'VAT',
        ],
        'VAT-7K' => [
            'day' => 25,
            'type' => 'quarterly',
            'quarter_months' => [1, 4, 7, 10],
            'condition' => ['vat_period' => 'quarterly'],
            'tax_type' => 'VAT',
        ],
        'JPK_VAT' => [
            'day' => 25,
            'type' => 'monthly',
            'condition' => ['jpk_vat_required' => 1],
        ],
        'PIT-5/PIT-5L' => [
            'day' => 20,
            'type' => 'monthly',
            'condition' => ['taxation_type' => 'PIT'],
            'tax_type' => 'PIT',
        ],
        'PIT-28' => [
            'day' => 20,
            'type' => 'monthly',
            'condition' => ['tax_form' => 'ryczalt'],
            'tax_type' => 'PIT',
        ],
        'CIT-8A' => [
            'day' => 20,
            'type' => 'monthly',
            'condition' => ['taxation_type' => 'CIT'],
            'tax_type' => 'CIT',
        ],
        'ZUS-DRA' => [
            'day' => 20,
            'type' => 'monthly',
            'condition' => ['zus_payer_type' => 'self_employed'],
        ],
        'ZUS-pracodawca' => [
            'day' => 15,
            'type' => 'monthly',
            'condition' => ['zus_payer_type' => 'employer'],
        ],
        'PIT-36/PIT-36L roczny' => [
            'day' => 30,
            'type' => 'annual',
            'month' => 4,
            'condition' => ['taxation_type' => 'PIT'],
        ],
        'PIT-28 roczny' => [
            'day' => 28,
            'type' => 'annual',
            'month' => 2,
            'condition' => ['tax_form' => 'ryczalt'],
        ],
        'CIT-8 roczny' => [
            'day' => 31,
            'type' => 'annual',
            'month' => 3,
            'condition' => ['taxation_type' => 'CIT'],
        ],
    ];

    /**
     * Check if an obligation applies to the given client config.
     */
    private static function obligationApplies(array $obligation, array $config): bool
    {
        foreach ($obligation['condition'] as $key => $expectedValue) {
            $actual = $config[$key] ?? null;
            if ($actual === null) {
                $defaults = ClientTaxConfig::getDefaults();
                $actual = $defaults[$key] ?? null;
            }
            if ($actual != $expectedValue) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get deadlines for a specific client in a given month.
     * Returns array of ['type' => 'VAT-7', 'day' => 25, 'date' => '2026-04-25', 'tax_type' => 'VAT'|null]
     */
    public static function getDeadlinesForClient(int $clientId, int $year, int $month): array
    {
        $config = ClientTaxConfig::findByClientOrDefaults($clientId);
        return self::calculateDeadlines($config, $year, $month);
    }

    /**
     * Calculate deadlines based on config for a given month.
     */
    private static function calculateDeadlines(array $config, int $year, int $month): array
    {
        $deadlines = [];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        foreach (self::OBLIGATIONS as $name => $obligation) {
            if (!self::obligationApplies($obligation, $config)) {
                continue;
            }

            if ($obligation['type'] === 'annual') {
                if ($month !== $obligation['month']) {
                    continue;
                }
            } elseif ($obligation['type'] === 'quarterly') {
                if (!in_array($month, $obligation['quarter_months'])) {
                    continue;
                }
            }
            // 'monthly' always applies

            $day = min($obligation['day'], $daysInMonth);
            // Adjust for weekends: if deadline falls on Saturday, move to Friday; Sunday -> Monday
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dow = date('N', strtotime($date)); // 1=Mon, 7=Sun
            if ($dow == 6) { // Saturday -> Friday
                $day--;
            } elseif ($dow == 7) { // Sunday -> Monday
                $day += 1;
                if ($day > $daysInMonth) {
                    $day = $daysInMonth;
                }
            }

            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

            $deadlines[] = [
                'type' => $name,
                'day' => $day,
                'date' => $date,
                'tax_type' => $obligation['tax_type'] ?? null,
            ];
        }

        // Sort by day
        usort($deadlines, fn($a, $b) => $a['day'] <=> $b['day']);
        return $deadlines;
    }

    /**
     * Get all deadlines for all clients of an office in a given month.
     * Returns array grouped by client: [clientId => ['client' => [...], 'deadlines' => [...]]]
     */
    public static function getDeadlinesForOffice(int $officeId, int $year, int $month): array
    {
        $clients = Client::findByOffice($officeId, true);
        $result = [];

        foreach ($clients as $client) {
            $config = ClientTaxConfig::findByClientOrDefaults((int) $client['id']);
            $deadlines = self::calculateDeadlines($config, $year, $month);

            if (!empty($deadlines)) {
                $result[(int) $client['id']] = [
                    'client' => $client,
                    'deadlines' => $deadlines,
                ];
            }
        }

        return $result;
    }

    /**
     * Get upcoming deadlines for a client within the next N days.
     */
    public static function getUpcomingDeadlines(int $clientId, int $daysAhead = 30): array
    {
        $today = new \DateTime();
        $end = (clone $today)->modify("+{$daysAhead} days");
        $result = [];

        // Check current month and next month
        $current = new \DateTime($today->format('Y-m-01'));
        $nextMonth = (clone $current)->modify('+1 month');

        foreach ([$current, $nextMonth] as $period) {
            $y = (int) $period->format('Y');
            $m = (int) $period->format('n');
            $deadlines = self::getDeadlinesForClient($clientId, $y, $m);

            foreach ($deadlines as $d) {
                $dDate = new \DateTime($d['date']);
                if ($dDate >= $today && $dDate <= $end) {
                    $d['days_left'] = (int) $today->diff($dDate)->days;
                    $result[] = $d;
                }
            }
        }

        usort($result, fn($a, $b) => $a['date'] <=> $b['date']);
        return $result;
    }

    /**
     * Build a month calendar grid: [dayNumber => [deadlines]]
     */
    public static function buildMonthCalendar(int $year, int $month, array $deadlines): array
    {
        $grid = [];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $grid[$d] = [];
        }
        foreach ($deadlines as $deadline) {
            $day = $deadline['day'];
            if (isset($grid[$day])) {
                $grid[$day][] = $deadline;
            }
        }
        return $grid;
    }

    /**
     * Process alerts for all active clients. Called by cron.
     * Creates notifications for upcoming deadlines within each client's alert_days_before.
     */
    public static function processAlerts(): array
    {
        $result = ['checked' => 0, 'alerts_sent' => 0, 'errors' => []];
        $clients = Client::findAll(true);
        $today = date('Y-m-d');

        foreach ($clients as $client) {
            $clientId = (int) $client['id'];
            $config = ClientTaxConfig::findByClientOrDefaults($clientId);
            $alertDays = (int) ($config['alert_days_before'] ?? 5);
            $result['checked']++;

            // Check current and next month
            $now = new \DateTime();
            $nextMonth = (clone $now)->modify('+1 month');

            foreach ([$now, $nextMonth] as $period) {
                $y = (int) $period->format('Y');
                $m = (int) $period->format('n');
                $deadlines = self::calculateDeadlines($config, $y, $m);

                foreach ($deadlines as $d) {
                    $daysUntil = (int) ((strtotime($d['date']) - strtotime($today)) / 86400);

                    if ($daysUntil >= 0 && $daysUntil <= $alertDays) {
                        if (TaxCalendarAlert::wasAlertSent($clientId, $d['type'], $d['date'])) {
                            continue;
                        }

                        // Get tax payment amount if applicable
                        $amountStr = '';
                        if (!empty($d['tax_type'])) {
                            $payments = TaxPayment::findByClientAndYear($clientId, $y);
                            $grid = TaxPayment::buildGrid($payments);
                            if (isset($grid[$m][$d['tax_type']])) {
                                $amt = $grid[$m][$d['tax_type']];
                                if ($amt['status'] === 'do_zaplaty' && (float) $amt['amount'] > 0) {
                                    $amountStr = ' (' . number_format((float) $amt['amount'], 2, ',', ' ') . ' PLN)';
                                }
                            }
                        }

                        $title = "Termin: {$d['type']} - {$d['date']}";
                        $message = "Zbliża się termin {$d['type']} ({$d['date']}){$amountStr}";

                        // Notify client
                        Notification::create('client', $clientId, $title, $message, 'warning', '/client/tax-calendar');

                        // Notify office
                        if (!empty($client['office_id'])) {
                            Notification::create(
                                'office',
                                (int) $client['office_id'],
                                "{$client['company_name']}: {$d['type']} - {$d['date']}",
                                $message,
                                'warning',
                                '/office/tax-calendar'
                            );
                        }

                        TaxCalendarAlert::markSent($clientId, $d['type'], $d['date']);
                        $result['alerts_sent']++;
                    }
                }
            }
        }

        // Clean old alerts
        TaxCalendarAlert::cleanOld(180);

        return $result;
    }
}
