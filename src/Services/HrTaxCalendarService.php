<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\TaxCalendarAlert;

class HrTaxCalendarService
{
    public static function generateDeadlinesForMonth(int $year, int $month): int
    {
        $count = 0;

        $clients = HrDatabase::getInstance()->fetchAll(
            "SELECT DISTINCT r.client_id
             FROM hr_payroll_runs r
             WHERE r.period_year = ? AND r.period_month = ?
               AND r.status IN ('calculated', 'approved', 'locked')",
            [$year, $month]
        );

        if (empty($clients)) return 0;

        $nextMonth = $month === 12 ? 1    : $month + 1;
        $nextYear  = $month === 12 ? $year + 1 : $year;

        $zusDeadline = self::adjustForWeekend(sprintf('%04d-%02d-15', $nextYear, $nextMonth));
        $pitDeadline = self::adjustForWeekend(sprintf('%04d-%02d-20', $nextYear, $nextMonth));

        foreach ($clients as $row) {
            $clientId = (int)$row['client_id'];

            if (!TaxCalendarAlert::wasAlertSent($clientId, 'hr_zus_dra', $zusDeadline)) {
                TaxCalendarAlert::markSent($clientId, 'hr_zus_dra', $zusDeadline);
                $count++;
            }

            if (!TaxCalendarAlert::wasAlertSent($clientId, 'hr_pit4', $pitDeadline)) {
                TaxCalendarAlert::markSent($clientId, 'hr_pit4', $pitDeadline);
                $count++;
            }
        }

        return $count;
    }

    public static function generateAnnualDeadlines(int $taxYear): int
    {
        $count = 0;

        $clients = HrDatabase::getInstance()->fetchAll(
            "SELECT DISTINCT r.client_id
             FROM hr_payroll_runs r
             WHERE r.period_year = ?
               AND r.status IN ('calculated', 'approved', 'locked')",
            [$taxYear]
        );

        if (empty($clients)) return 0;

        $nextYear = $taxYear + 1;
        $pit4rDeadline = self::adjustForWeekend(sprintf('%04d-01-31', $nextYear));
        $febDays = (($nextYear % 4 === 0 && $nextYear % 100 !== 0) || $nextYear % 400 === 0) ? 29 : 28;
        $pit11Deadline = self::adjustForWeekend(sprintf('%04d-02-%02d', $nextYear, $febDays));

        foreach ($clients as $row) {
            $clientId = (int)$row['client_id'];

            if (!TaxCalendarAlert::wasAlertSent($clientId, 'hr_pit4r', $pit4rDeadline)) {
                TaxCalendarAlert::markSent($clientId, 'hr_pit4r', $pit4rDeadline);
                $count++;
            }

            if (!TaxCalendarAlert::wasAlertSent($clientId, 'hr_pit11', $pit11Deadline)) {
                TaxCalendarAlert::markSent($clientId, 'hr_pit11', $pit11Deadline);
                $count++;
            }
        }

        return $count;
    }

    private static function adjustForWeekend(string $date): string
    {
        $ts  = strtotime($date);
        $dow = (int) date('N', $ts);

        if ($dow === 6) return date('Y-m-d', $ts + 2 * 86400);
        if ($dow === 7) return date('Y-m-d', $ts + 86400);

        return $date;
    }
}
