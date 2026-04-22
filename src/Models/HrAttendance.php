<?php

namespace App\Models;

use App\Core\HrDatabase;

class HrAttendance
{
    public const TYPE_LABELS = [
        'work'     => 'Praca',
        'vacation' => 'Urlop',
        'sick'     => 'L4',
        'holiday'  => 'Swięto',
        'remote'   => 'Zdalna',
        'other'    => 'Inne',
    ];

    public const TYPE_CODES = [
        'work'     => 'P',
        'vacation' => 'U',
        'sick'     => 'L',
        'holiday'  => 'Ś',
        'remote'   => 'Z',
        'other'    => 'I',
    ];

    public static function findByEmployeeMonth(int $empId, int $month, int $year): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_attendance
             WHERE employee_id = ? AND work_date BETWEEN ? AND ?
             ORDER BY work_date",
            [$empId, $from, $to]
        );

        $indexed = [];
        foreach ($rows as $row) {
            $day           = (int) date('j', strtotime($row['work_date']));
            $indexed[$day] = $row;
        }
        return $indexed;
    }

    public static function findByClientMonth(int $clientId, int $month, int $year): array
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT * FROM hr_attendance
             WHERE client_id = ? AND work_date BETWEEN ? AND ?
             ORDER BY employee_id, work_date",
            [$clientId, $from, $to]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $empId = (int) $row['employee_id'];
            $day   = (int) date('j', strtotime($row['work_date']));
            $grouped[$empId][$day] = $row;
        }
        return $grouped;
    }

    public static function upsert(int $empId, int $clientId, string $date, array $data): void
    {
        $type          = in_array($data['type'] ?? '', array_keys(self::TYPE_LABELS), true)
                         ? $data['type']
                         : 'work';
        $workMinutes   = max(0, min(1440, (int) ($data['work_minutes'] ?? 480)));
        $overtimeMin   = max(0, min(480, (int) ($data['overtime_minutes'] ?? 0)));
        $notes         = isset($data['notes']) ? trim($data['notes']) : null;

        HrDatabase::getInstance()->query(
            "INSERT INTO hr_attendance
                (employee_id, client_id, work_date, type, work_minutes, overtime_minutes, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                type = VALUES(type),
                work_minutes = VALUES(work_minutes),
                overtime_minutes = VALUES(overtime_minutes),
                notes = VALUES(notes)",
            [$empId, $clientId, $date, $type, $workMinutes, $overtimeMin, $notes]
        );
    }

    public static function getTotalOvertimeForPeriod(int $empId, int $month, int $year): int
    {
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT COALESCE(SUM(overtime_minutes), 0) AS total
             FROM hr_attendance
             WHERE employee_id = ? AND work_date BETWEEN ? AND ?",
            [$empId, $from, $to]
        );
        return (int) ($row['total'] ?? 0);
    }

    public static function deleteDay(int $empId, string $date): void
    {
        HrDatabase::getInstance()->query(
            "DELETE FROM hr_attendance WHERE employee_id = ? AND work_date = ?",
            [$empId, $date]
        );
    }
}
