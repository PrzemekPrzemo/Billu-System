<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrAttendance;

class HrAttendancePdfService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/attendance';

    private static array $typeColors = [
        'work'     => [255, 255, 255],
        'vacation' => [198, 239, 206],
        'sick'     => [255, 235, 156],
        'holiday'  => [189, 215, 238],
        'remote'   => [226, 239, 218],
        'other'    => [242, 242, 242],
    ];

    public static function generate(int $clientId, int $month, int $year): string
    {
        self::ensureDir($clientId);

        $db        = HrDatabase::getInstance();
        $employees = $db->fetchAll(
            "SELECT id, first_name, last_name FROM hr_employees WHERE client_id = ? AND is_active = 1 ORDER BY last_name, first_name",
            [$clientId]
        );

        $companyName = self::getCompanyName($clientId);
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $holidays    = HrAttendanceService::getPolishHolidays($year);
        $holidayDays = self::holidayDaySet($holidays, $month, $year);
        $attendanceByEmp = HrAttendance::findByClientMonth($clientId, $month, $year);

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Billu HR');
        $pdf->SetAuthor($companyName);
        $pdf->SetTitle(sprintf('Ewidencja czasu pracy %02d/%04d', $month, $year));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(6, 6, 6);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->AddPage();

        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 6, 'Ewidencja czasu pracy — ' . $companyName, 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 9);
        $monthNames = [1=>'Styczeń',2=>'Luty',3=>'Marzec',4=>'Kwiecień',5=>'Maj',6=>'Czerwiec',7=>'Lipiec',8=>'Sierpień',9=>'Wrzesień',10=>'Październik',11=>'Listopad',12=>'Grudzień'];
        $pdf->Cell(0, 5, $monthNames[$month] . ' ' . $year, 0, 1, 'C');
        $pdf->Ln(2);

        $nameColW = 38.0;
        $dayColW  = 5.5;
        $sumColW  = 13.0;
        $otColW   = 13.0;
        $emptyDays = 31 - $daysInMonth;

        $pdf->SetFont('dejavusans', 'B', 6.5);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell($nameColW, 6, 'Pracownik', 1, 0, 'C', true);
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = (int) date('N', mktime(0, 0, 0, $month, $d, $year));
            if ($dow >= 6 || isset($holidayDays[$d])) {
                $pdf->SetFillColor(189, 215, 238);
            } else {
                $pdf->SetFillColor(220, 220, 220);
            }
            $pdf->Cell($dayColW, 6, (string)$d, 1, 0, 'C', true);
        }
        for ($i = 0; $i < $emptyDays; $i++) {
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($dayColW, 6, '', 1, 0, 'C', true);
        }
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell($sumColW, 6, 'Godz.', 1, 0, 'C', true);
        $pdf->Cell($otColW,  6, 'Nadg.', 1, 1, 'C', true);

        $dowShort = ['', 'Pn','Wt','Śr','Cz','Pt','Sb','Nd'];
        $pdf->SetFont('dejavusans', '', 5.5);
        $pdf->Cell($nameColW, 4, '', 1, 0, 'C');
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = (int) date('N', mktime(0, 0, 0, $month, $d, $year));
            if ($dow >= 6 || isset($holidayDays[$d])) {
                $pdf->SetFillColor(189, 215, 238);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            $pdf->Cell($dayColW, 4, $dowShort[$dow], 1, 0, 'C', true);
        }
        for ($i = 0; $i < $emptyDays; $i++) {
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($dayColW, 4, '', 1, 0, 'C', true);
        }
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell($sumColW, 4, '', 1, 0, 'C');
        $pdf->Cell($otColW,  4, '', 1, 1, 'C');

        $pdf->SetFont('dejavusans', '', 6.5);
        $rowH = 6;

        foreach ($employees as $emp) {
            $empId   = (int) $emp['id'];
            $empAtt  = $attendanceByEmp[$empId] ?? [];
            $workMin = 0;
            $otMin   = 0;

            $pdf->SetFillColor(255, 255, 255);
            $fullName = mb_substr($emp['last_name'] . ' ' . $emp['first_name'], 0, 22);
            $pdf->Cell($nameColW, $rowH, $fullName, 1, 0, 'L');

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dow = (int) date('N', mktime(0, 0, 0, $month, $d, $year));
                $isWeekend = $dow >= 6;
                $isHoliday = isset($holidayDays[$d]);

                if (isset($empAtt[$d])) {
                    $row  = $empAtt[$d];
                    $type = $row['type'] ?? 'work';
                    $code = HrAttendance::TYPE_CODES[$type] ?? 'P';
                    $workMin += (int) $row['work_minutes'];
                    $otMin   += (int) $row['overtime_minutes'];
                    [$r, $g, $b] = self::$typeColors[$type] ?? [255, 255, 255];
                    $pdf->SetFillColor($r, $g, $b);
                    $pdf->Cell($dayColW, $rowH, $code, 1, 0, 'C', true);
                } elseif ($isWeekend || $isHoliday) {
                    $pdf->SetFillColor(235, 235, 235);
                    $pdf->Cell($dayColW, $rowH, '—', 1, 0, 'C', true);
                } else {
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->Cell($dayColW, $rowH, '', 1, 0, 'C');
                }
            }

            $pdf->SetFillColor(240, 240, 240);
            for ($i = 0; $i < $emptyDays; $i++) {
                $pdf->Cell($dayColW, $rowH, '', 1, 0, 'C', true);
            }

            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell($sumColW, $rowH, number_format($workMin / 60, 1), 1, 0, 'C');
            $pdf->Cell($otColW,  $rowH, $otMin > 0 ? number_format($otMin / 60, 1) : '', 1, 1, 'C');
        }

        $pdf->Ln(3);
        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->Cell(0, 4, 'Legenda:', 0, 1);
        $pdf->SetFont('dejavusans', '', 6.5);
        $legendItems = ['P'=>'Praca','U'=>'Urlop','L'=>'L4 (chorobowe)','Ś'=>'Święto','Z'=>'Praca zdalna','I'=>'Inne'];
        $pdf->Cell(0, 4, implode('   ', array_map(fn($c,$l) => "{$c} = {$l}", array_keys($legendItems), $legendItems)), 0, 1);

        $pdf->Ln(6);
        $sigW = 80;
        $pdf->Cell($sigW, 4, 'Data wydruku: ' . date('d.m.Y'), 0, 0, 'L');
        $pdf->Cell($sigW, 4, '', 0, 0);
        $pdf->Cell($sigW, 4, 'Podpis pracodawcy / osoby upoważnionej', 0, 1, 'C');
        $pdf->Ln(8);
        $pdf->Cell($sigW, 4, '', 0, 0);
        $pdf->Cell($sigW, 4, '', 0, 0);
        $pdf->Cell($sigW, 4, '..................................', 0, 1, 'C');

        $filename = sprintf('ewidencja_%d_%04d_%02d.pdf', $clientId, $year, $month);
        $path     = self::$storageDir . '/' . $clientId . '/' . $filename;
        $pdf->Output($path, 'F');

        return $path;
    }

    private static function ensureDir(int $clientId): void
    {
        $dir = self::$storageDir . '/' . $clientId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    private static function getCompanyName(int $clientId): string
    {
        $mainDb = \App\Core\HrDatabase::mainDbName();
        $row = HrDatabase::getInstance()->fetchOne("SELECT company_name FROM `{$mainDb}`.clients WHERE id = ?", [$clientId]);
        return $row['company_name'] ?? 'Pracodawca';
    }

    private static function holidayDaySet(array $holidays, int $month, int $year): array
    {
        $set = [];
        foreach ($holidays as $date) {
            [$hy, $hm, $hd] = explode('-', $date);
            if ((int)$hm === $month && (int)$hy === $year) $set[(int)$hd] = true;
        }
        return $set;
    }
}
