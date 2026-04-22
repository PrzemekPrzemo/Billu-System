<?php

namespace App\Services;

use App\Models\HrPayrollBudget;
use App\Models\HrPayrollRun;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class HrBudgetExportService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/reports';

    private static array $monthNames = [
        1  => 'Styczeń',   2  => 'Luty',       3  => 'Marzec',
        4  => 'Kwiecień',  5  => 'Maj',         6  => 'Czerwiec',
        7  => 'Lipiec',    8  => 'Sierpień',    9  => 'Wrzesień',
        10 => 'Październik', 11 => 'Listopad',  12 => 'Grudzień',
    ];

    public static function export(int $clientId, int $year, array $actualByMonth): string
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0755, true);
        }

        $budget = HrPayrollBudget::findByClientAndYear($clientId, $year);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Budżet {$year}");

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', "Plan budżetu płac vs. wykonanie — {$year}");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $headers = [
            'A2' => 'Miesiąc',
            'B2' => 'Plan brutto (PLN)',
            'C2' => 'Plan koszt prac. (PLN)',
            'D2' => 'Wykonanie brutto (PLN)',
            'E2' => 'Wykonanie koszt prac. (PLN)',
            'F2' => 'Δ Brutto (PLN)',
            'G2' => 'Δ Koszt (PLN)',
        ];
        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(30);

        $numFmt = '#,##0.00';
        $row = 3;
        $totals = ['plan_gross' => 0, 'plan_cost' => 0, 'actual_gross' => 0, 'actual_cost' => 0];

        for ($m = 1; $m <= 12; $m++) {
            $plan = $budget[$m] ?? null;
            $actual = $actualByMonth[$m] ?? null;

            $planGross  = (float) ($plan['planned_gross'] ?? 0);
            $planCost   = (float) ($plan['planned_cost']  ?? 0);
            $actGross   = (float) ($actual['gross_salary']         ?? 0);
            $actCost    = (float) ($actual['employer_total_cost']  ?? 0);
            $deltaGross = $actGross - $planGross;
            $deltaCost  = $actCost  - $planCost;

            $sheet->setCellValue("A{$row}", self::$monthNames[$m]);
            $sheet->setCellValue("B{$row}", $planGross);
            $sheet->setCellValue("C{$row}", $planCost);
            $sheet->setCellValue("D{$row}", $actGross);
            $sheet->setCellValue("E{$row}", $actCost);
            $sheet->setCellValue("F{$row}", $deltaGross);
            $sheet->setCellValue("G{$row}", $deltaCost);

            $isOver = ($planGross > 0 && $actGross > $planGross * 1.10)
                   || ($planCost  > 0 && $actCost  > $planCost  * 1.10);

            if ($isOver) {
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                ]);
            } elseif ($m % 2 === 0) {
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
                ]);
            }

            $totals['plan_gross']   += $planGross;
            $totals['plan_cost']    += $planCost;
            $totals['actual_gross'] += $actGross;
            $totals['actual_cost']  += $actCost;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'RAZEM');
        $sheet->setCellValue("B{$row}", $totals['plan_gross']);
        $sheet->setCellValue("C{$row}", $totals['plan_cost']);
        $sheet->setCellValue("D{$row}", $totals['actual_gross']);
        $sheet->setCellValue("E{$row}", $totals['actual_cost']);
        $sheet->setCellValue("F{$row}", $totals['actual_gross'] - $totals['plan_gross']);
        $sheet->setCellValue("G{$row}", $totals['actual_cost']  - $totals['plan_cost']);
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
        ]);

        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->getStyle("{$col}3:{$col}{$row}")->getNumberFormat()->setFormatCode($numFmt);
        }

        $sheet->getStyle("A2:G{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Budzet_{$year}_export_" . date('Ymd_His') . '.xlsx';
        $filePath = self::$storageDir . '/' . $filename;
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }
}
