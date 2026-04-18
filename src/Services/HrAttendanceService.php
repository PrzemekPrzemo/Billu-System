<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrAttendance;

class HrAttendanceService
{
    private static array $monthNames = [
        1  => 'Stycze\u0144',   2  => 'Luty',       3  => 'Marzec',
        4  => 'Kwiecie\u0144',  5  => 'Maj',         6  => 'Czerwiec',
        7  => 'Lipiec',    8  => 'Sierpie\u0144',    9  => 'Wrzesie\u0144',
        10 => 'Pa\u017adziernik', 11 => 'Listopad',  12 => 'Grudzie\u0144',
    ];

    public static function buildMonthGrid(int $clientId, int $month, int $year, array $employees): array
    {
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $holidays    = self::getPolishHolidays($year);
        $weekends    = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = (int) date('N', mktime(0, 0, 0, $month, $d, $year));
            $weekends[$d] = $dow >= 6;
        }

        $holidayDays = [];
        foreach ($holidays as $date) {
            [$hy, $hm, $hd] = explode('-', $date);
            if ((int)$hm === $month && (int)$hy === $year) {
                $holidayDays[(int)$hd] = true;
            }
        }

        $attendanceByEmp = HrAttendance::findByClientMonth($clientId, $month, $year);

        $grid   = [];
        $totals = [];

        foreach ($employees as $emp) {
            $empId        = (int) $emp['id'];
            $empAtt       = $attendanceByEmp[$empId] ?? [];
            $grid[$empId] = [];
            $workMin      = 0;
            $overtimeMin  = 0;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                if (isset($empAtt[$d])) {
                    $row              = $empAtt[$d];
                    $grid[$empId][$d] = $row;
                    $workMin         += (int) $row['work_minutes'];
                    $overtimeMin     += (int) $row['overtime_minutes'];
                } else {
                    $grid[$empId][$d] = null;
                }
            }

            $totals[$empId] = [
                'work_h'     => round($workMin / 60, 2),
                'overtime_h' => round($overtimeMin / 60, 2),
            ];
        }

        return [
            'days'      => $daysInMonth,
            'holidays'  => $holidayDays,
            'weekends'  => $weekends,
            'grid'      => $grid,
            'employees' => $employees,
            'totals'    => $totals,
        ];
    }

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
        $easterMonth = intdiv($h + $l - 7 * $m + 114, 31);
        $easterDay   = (($h + $l - 7 * $m + 114) % 31) + 1;

        $easter        = mktime(0, 0, 0, $easterMonth, $easterDay, $year);
        $easterMonday  = date('Y-m-d', $easter + 86400);
        $pentecost     = date('Y-m-d', $easter + 49 * 86400);
        $corpusChristi = date('Y-m-d', $easter + 60 * 86400);

        return [
            sprintf('%04d-01-01', $year),
            sprintf('%04d-01-06', $year),
            date('Y-m-d', $easter),
            $easterMonday,
            sprintf('%04d-05-01', $year),
            sprintf('%04d-05-03', $year),
            $pentecost,
            $corpusChristi,
            sprintf('%04d-08-15', $year),
            sprintf('%04d-11-01', $year),
            sprintf('%04d-11-11', $year),
            sprintf('%04d-12-25', $year),
            sprintf('%04d-12-26', $year),
        ];
    }

    public static function injectOvertimeToPayroll(int $runId, int $clientId, int $month, int $year): int
    {
        $db = HrDatabase::getInstance();

        $run = $db->fetchOne(
            "SELECT id, status FROM hr_payroll_runs WHERE id = ? AND client_id = ?",
            [$runId, $clientId]
        );
        if (!$run || $run['status'] === 'locked') {
            throw new \RuntimeException('Lista p\u0142ac nie istnieje lub jest zablokowana.');
        }

        $employees = $db->fetchAll(
            "SELECT e.id AS employee_id, c.base_salary
             FROM hr_employees e
             JOIN hr_contracts c ON c.employee_id = e.id AND c.is_current = 1
             WHERE e.client_id = ? AND e.is_active = 1",
            [$clientId]
        );

        $updated = 0;

        foreach ($employees as $emp) {
            $empId       = (int) $emp['employee_id'];
            $baseSalary  = (float) $emp['base_salary'];
            $overtimeMin = HrAttendance::getTotalOvertimeForPeriod($empId, $month, $year);

            if ($overtimeMin <= 0) {
                continue;
            }

            $hourlyRate  = $baseSalary / 160;
            $overtimePay = round(($overtimeMin / 60) * $hourlyRate * 1.5, 2);

            $affected = $db->update(
                'hr_payroll_items',
                ['overtime_pay' => $overtimePay],
                'payroll_run_id = ? AND employee_id = ?',
                [$runId, $empId]
            );

            if ($affected > 0) {
                $updated++;
            }
        }

        return $updated;
    }
}
