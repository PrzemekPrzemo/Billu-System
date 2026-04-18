<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrLeaveBalance;
use App\Models\HrLeaveType;

class HrLeaveService
{
    private static array $fixedHolidays = [
        [1,  1],
        [6,  1],
        [1,  5],
        [3,  5],
        [15, 8],
        [1,  11],
        [11, 11],
        [25, 12],
        [26, 12],
    ];

    public static function getPolishHolidays(int $year): array
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        $easter = mktime(0, 0, 0, $month, $day, $year);

        $holidays = [];

        foreach (self::$fixedHolidays as [$d2, $m2]) {
            $holidays[] = date('Y-m-d', mktime(0, 0, 0, $m2, $d2, $year));
        }

        $holidays[] = date('Y-m-d', $easter);
        $holidays[] = date('Y-m-d', $easter + 86400);
        $holidays[] = date('Y-m-d', $easter + 49 * 86400);
        $holidays[] = date('Y-m-d', $easter + 60 * 86400);

        return array_unique($holidays);
    }

    public static function countBusinessDays(string $from, string $to): float
    {
        $yearFrom = (int) substr($from, 0, 4);
        $yearTo   = (int) substr($to,   0, 4);

        $holidays = self::getPolishHolidays($yearFrom);
        if ($yearTo !== $yearFrom) {
            $holidays = array_merge($holidays, self::getPolishHolidays($yearTo));
        }

        $count   = 0;
        $current = strtotime($from);
        $end     = strtotime($to);

        while ($current <= $end) {
            $dow     = (int) date('N', $current);
            $dateStr = date('Y-m-d', $current);
            if ($dow <= 5 && !in_array($dateStr, $holidays, true)) {
                $count++;
            }
            $current += 86400;
        }

        return (float) $count;
    }

    public static function rolloverBalances(int $newYear): int
    {
        $db       = HrDatabase::getInstance();
        $prevYear = $newYear - 1;

        $employees = $db->fetchAll(
            "SELECT DISTINCT e.id, e.client_id, e.annual_leave_days
             FROM hr_employees e
             JOIN hr_contracts c ON c.employee_id = e.id AND c.is_current = 1
             WHERE e.is_active = 1"
        );

        $count = 0;
        foreach ($employees as $emp) {
            $prevBalances = HrLeaveBalance::findByEmployeeYear($emp['id'], $prevYear);

            $carriedOver = 0.0;
            foreach ($prevBalances as $bal) {
                if ($bal['code'] === 'wypoczynkowy') {
                    $remaining   = (float) $bal['remaining_days'];
                    $carriedOver = max(0.0, min(10.0, $remaining));
                    break;
                }
            }

            $annualDays = (int) ($emp['annual_leave_days'] ?? 26);
            HrLeaveBalance::initForYearWithCarryover(
                $emp['id'],
                $emp['client_id'],
                $newYear,
                $annualDays,
                $carriedOver
            );
            $count++;
        }

        return $count;
    }
}
