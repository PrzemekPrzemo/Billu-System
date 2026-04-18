<?php

namespace App\Services;

use App\Core\HrDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class HrReportService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/reports';

    private static array $monthNames = [
        1  => 'Stycze\u0144',   2  => 'Luty',       3  => 'Marzec',
        4  => 'Kwiecie\u0144',  5  => 'Maj',         6  => 'Czerwiec',
        7  => 'Lipiec',    8  => 'Sierpie\u0144',    9  => 'Wrzesie\u0144',
        10 => 'Pa\u017adziernik', 11 => 'Listopad',  12 => 'Grudzie\u0144',
    ];

    public static function getMonthlySummary(int $clientId, int $year, int $month): array
    {
        $employees = HrDatabase::getInstance()->fetchAll(
            "SELECT
               pi.employee_id,
               CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
               e.position,
               pi.gross_salary, pi.zus_total_employee, pi.tax_base,
               pi.pit_advance, pi.ppk_employee, pi.net_salary,
               pi.zus_total_employer, pi.ppk_employer, pi.employer_total_cost
             FROM hr_payroll_items pi
             JOIN hr_employees e ON e.id = pi.employee_id
             JOIN hr_payroll_runs r ON r.id = pi.payroll_run_id
             WHERE r.client_id = ? AND r.period_year = ? AND r.period_month = ?
               AND r.status NOT IN ('draft')
             ORDER BY e.last_name, e.first_name",
            [$clientId, $year, $month]
        );

        $totals = [
            'gross_salary' => 0, 'zus_total_employee' => 0, 'tax_base' => 0,
            'pit_advance' => 0, 'ppk_employee' => 0, 'net_salary' => 0,
            'zus_total_employer' => 0, 'ppk_employer' => 0, 'employer_total_cost' => 0,
        ];
        foreach ($employees as $e) {
            foreach ($totals as $k => $_) {
                $totals[$k] += (float)($e[$k] ?? 0);
            }
        }

        return ['employees' => $employees, 'totals' => $totals];
    }

    public static function getAnnualSummary(int $clientId, int $year): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT
               pi.employee_id,
               CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
               r.period_month,
               pi.gross_salary, pi.net_salary, pi.zus_total_employee,
               pi.zus_total_employer, pi.pit_advance, pi.ppk_employee,
               pi.ppk_employer, pi.employer_total_cost
             FROM hr_payroll_items pi
             JOIN hr_employees e ON e.id = pi.employee_id
             JOIN hr_payroll_runs r ON r.id = pi.payroll_run_id
             WHERE r.client_id = ? AND r.period_year = ?
               AND r.status NOT IN ('draft')
             ORDER BY e.last_name, e.first_name, r.period_month",
            [$clientId, $year]
        );

        $employees = [];
        foreach ($rows as $row) {
            $eid = $row['employee_id'];
            if (!isset($employees[$eid])) {
                $employees[$eid] = ['name' => $row['employee_name'], 'months' => []];
            }
            $employees[$eid]['months'][(int)$row['period_month']] = $row;
        }

        $monthly = [];
        foreach ($rows as $row) {
            $m = (int)$row['period_month'];
            if (!isset($monthly[$m])) {
                $monthly[$m] = [
                    'gross_salary' => 0, 'net_salary' => 0, 'pit_advance' => 0,
                    'zus_total_employer' => 0, 'ppk_employer' => 0, 'employer_total_cost' => 0,
                ];
            }
            $monthly[$m]['gross_salary']       += (float)$row['gross_salary'];
            $monthly[$m]['net_salary']          += (float)$row['net_salary'];
            $monthly[$m]['pit_advance']         += (float)$row['pit_advance'];
            $monthly[$m]['zus_total_employer']  += (float)$row['zus_total_employer'];
            $monthly[$m]['ppk_employer']        += (float)$row['ppk_employer'];
            $monthly[$m]['employer_total_cost'] += (float)$row['employer_total_cost'];
        }

        $totals = [
            'gross_salary' => array_sum(array_column($rows, 'gross_salary')),
            'net_salary'   => array_sum(array_column($rows, 'net_salary')),
            'pit_advance'  => array_sum(array_column($rows, 'pit_advance')),
            'employer_total_cost' => array_sum(array_column($rows, 'employer_total_cost')),
        ];

        return ['employees' => $employees, 'monthly' => $monthly, 'totals' => $totals];
    }

    public static function exportMonthlyExcel(int $clientId, int $year, int $month): string
    {
        self::ensureDir();
        $data = self::getMonthlySummary($clientId, $year, $month);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lista p\u0142ac');

        $monthName = self::$monthNames[$month] ?? $month;
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', "Lista p\u0142ac \u2014 {$monthName} {$year}");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = [
            'A'=>'Pracownik','B'=>'Stanowisko','C'=>'Brutto (PLN)','D'=>'ZUS prac. (PLN)',
            'E'=>'Podst. PIT (PLN)','F'=>'Zaliczka PIT (PLN)','G'=>'PPK prac. (PLN)',
            'H'=>'Netto (PLN)','I'=>'ZUS pracodawcy (PLN)','J'=>'Koszt pracodawcy (PLN)',
        ];
        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}2", $label);
        }
        $sheet->getStyle('A2:J2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 3;
        foreach ($data['employees'] as $emp) {
            $sheet->setCellValue("A{$row}", $emp['employee_name']);
            $sheet->setCellValue("B{$row}", $emp['position'] ?? '');
            $sheet->setCellValue("C{$row}", (float)$emp['gross_salary']);
            $sheet->setCellValue("D{$row}", (float)$emp['zus_total_employee']);
            $sheet->setCellValue("E{$row}", (float)$emp['tax_base']);
            $sheet->setCellValue("F{$row}", (float)$emp['pit_advance']);
            $sheet->setCellValue("G{$row}", (float)$emp['ppk_employee']);
            $sheet->setCellValue("H{$row}", (float)$emp['net_salary']);
            $sheet->setCellValue("I{$row}", (float)$emp['zus_total_employer']);
            $sheet->setCellValue("J{$row}", (float)$emp['employer_total_cost']);
            $row++;
        }

        $t = $data['totals'];
        $sheet->setCellValue("A{$row}", 'RAZEM');
        $sheet->setCellValue("C{$row}", (float)$t['gross_salary']);
        $sheet->setCellValue("D{$row}", (float)$t['zus_total_employee']);
        $sheet->setCellValue("E{$row}", (float)$t['tax_base']);
        $sheet->setCellValue("F{$row}", (float)$t['pit_advance']);
        $sheet->setCellValue("G{$row}", (float)$t['ppk_employee']);
        $sheet->setCellValue("H{$row}", (float)$t['net_salary']);
        $sheet->setCellValue("I{$row}", (float)$t['zus_total_employer']);
        $sheet->setCellValue("J{$row}", (float)$t['employer_total_cost']);
        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
        ]);

        $numFmt = '#,##0.00';
        foreach (['C','D','E','F','G','H','I','J'] as $col) {
            $sheet->getStyle("{$col}3:{$col}{$row}")->getNumberFormat()->setFormatCode($numFmt);
        }
        $sheet->getStyle("A2:J{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "RaportMiesieczny_{$year}_{$month}.xlsx";
        $filePath = self::$storageDir . '/' . $filename;
        (new Xlsx($spreadsheet))->save($filePath);
        return $filePath;
    }

    public static function exportAnnualExcel(int $clientId, int $year): string
    {
        self::ensureDir();
        $data = self::getAnnualSummary($clientId, $year);

        $spreadsheet = new Spreadsheet();
        self::buildSummarySheet($spreadsheet->getActiveSheet(), $data, $year);

        $sheet2 = $spreadsheet->createSheet();
        self::buildPerEmployeeSheet($sheet2, $data, $year);

        $sheet3 = $spreadsheet->createSheet();
        self::buildEmployerCostSheet($sheet3, $data, $year);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = "RaportRoczny_{$year}.xlsx";
        $filePath = self::$storageDir . '/' . $filename;
        (new Xlsx($spreadsheet))->save($filePath);
        return $filePath;
    }

    private static function buildSummarySheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $data, int $year): void
    {
        $sheet->setTitle('Podsumowanie');
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', "Podsumowanie roczne {$year}");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = ['Miesi\u0105c','Brutto (PLN)','Netto (PLN)','Zaliczka PIT (PLN)','ZUS pracodawcy (PLN)','PPK pracodawcy (PLN)','Koszt pracodawcy (PLN)'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65+$i).'2', $h);
        }
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
        ]);

        $row = 3;
        for ($m = 1; $m <= 12; $m++) {
            $md = $data['monthly'][$m] ?? null;
            $sheet->setCellValue("A{$row}", self::$monthNames[$m]);
            $sheet->setCellValue("B{$row}", $md ? (float)$md['gross_salary'] : 0);
            $sheet->setCellValue("C{$row}", $md ? (float)$md['net_salary'] : 0);
            $sheet->setCellValue("D{$row}", $md ? (float)$md['pit_advance'] : 0);
            $sheet->setCellValue("E{$row}", $md ? (float)$md['zus_total_employer'] : 0);
            $sheet->setCellValue("F{$row}", $md ? (float)$md['ppk_employer'] : 0);
            $sheet->setCellValue("G{$row}", $md ? (float)$md['employer_total_cost'] : 0);
            $row++;
        }

        $t = $data['totals'];
        $sheet->setCellValue("A{$row}", 'RAZEM');
        $sheet->setCellValue("B{$row}", (float)$t['gross_salary']);
        $sheet->setCellValue("C{$row}", (float)$t['net_salary']);
        $sheet->setCellValue("D{$row}", (float)$t['pit_advance']);
        $sheet->setCellValue("G{$row}", (float)$t['employer_total_cost']);
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
        ]);

        $numFmt = '#,##0.00';
        foreach (['B','C','D','E','F','G'] as $col) {
            $sheet->getStyle("{$col}3:{$col}{$row}")->getNumberFormat()->setFormatCode($numFmt);
        }
        $sheet->getStyle("A2:G{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        foreach (range('A','G') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
    }

    private static function buildPerEmployeeSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $data, int $year): void
    {
        $sheet->setTitle('Per pracownik');
        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', "Brutto per pracownik \u2014 {$year}");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', 'Pracownik');
        for ($m = 1; $m <= 12; $m++) {
            $sheet->setCellValue(chr(65+$m).'2', self::$monthNames[$m]);
        }
        $sheet->setCellValue('N2', 'RAZEM');
        $sheet->getStyle('A2:N2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
        ]);

        $row = 3;
        foreach ($data['employees'] as $empData) {
            $sheet->setCellValue("A{$row}", $empData['name']);
            $annual = 0;
            for ($m = 1; $m <= 12; $m++) {
                $gross = (float)($empData['months'][$m]['gross_salary'] ?? 0);
                $sheet->setCellValue(chr(65+$m)."{$row}", $gross);
                $annual += $gross;
            }
            $sheet->setCellValue("N{$row}", $annual);
            $row++;
        }

        $sheet->getStyle("B3:N{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A2:N{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        $sheet->getColumnDimension('A')->setAutoSize(true);
    }

    private static function buildEmployerCostSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $data, int $year): void
    {
        $sheet->setTitle('Koszty pracodawcy');
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', "Koszty pracodawcy \u2014 {$year}");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = ['Miesi\u0105c','ZUS pracodawcy (PLN)','PPK pracodawcy (PLN)','\u0141\u0105czny koszt prac. (PLN)'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65+$i).'2', $h);
        }
        $sheet->getStyle('A2:D2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
        ]);

        $row = 3;
        $totalZus = $totalPpk = $totalCost = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $md = $data['monthly'][$m] ?? null;
            $zus  = $md ? (float)$md['zus_total_employer'] : 0;
            $ppk  = $md ? (float)$md['ppk_employer'] : 0;
            $cost = $md ? (float)$md['employer_total_cost'] : 0;
            $sheet->setCellValue("A{$row}", self::$monthNames[$m]);
            $sheet->setCellValue("B{$row}", $zus);
            $sheet->setCellValue("C{$row}", $ppk);
            $sheet->setCellValue("D{$row}", $cost);
            $totalZus += $zus; $totalPpk += $ppk; $totalCost += $cost;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'RAZEM');
        $sheet->setCellValue("B{$row}", $totalZus);
        $sheet->setCellValue("C{$row}", $totalPpk);
        $sheet->setCellValue("D{$row}", $totalCost);
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
        ]);

        foreach (['B','C','D'] as $col) {
            $sheet->getStyle("{$col}3:{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getStyle("A2:D{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        foreach (['A','B','C','D'] as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0755, true);
        }
    }
}
