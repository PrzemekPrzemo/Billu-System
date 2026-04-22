<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrPpkEnrollment;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class HrPpkReportService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/ppk';

    private static array $monthNames = [
        1  => 'Styczeń',   2  => 'Luty',       3  => 'Marzec',
        4  => 'Kwiecień',  5  => 'Maj',         6  => 'Czerwiec',
        7  => 'Lipiec',    8  => 'Sierpień',    9  => 'Wrzesień',
        10 => 'Październik', 11 => 'Listopad',  12 => 'Grudzień',
    ];

    public static function generateMonthlyReport(int $clientId, int $month, int $year): string
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0755, true);
        }

        $db   = HrDatabase::getInstance();

        $run = $db->fetchOne(
            "SELECT id FROM hr_payroll_runs
             WHERE client_id = ? AND period_month = ? AND period_year = ?
               AND status != 'draft'",
            [$clientId, $month, $year]
        );

        $items = [];
        if ($run) {
            $runId = (int)$run['id'];
            $items = $db->fetchAll(
                "SELECT pi.employee_id, pi.gross_salary,
                        pi.ppk_employee_contribution, pi.ppk_employer_contribution,
                        e.first_name, e.last_name, e.pesel,
                        e.ppk_enrolled, e.ppk_institution,
                        c.ppk_employee_rate, c.ppk_employer_rate
                 FROM hr_payroll_items pi
                 JOIN hr_employees e ON pi.employee_id = e.id
                 LEFT JOIN hr_contracts c ON c.employee_id = e.id AND c.is_current = 1
                 WHERE pi.payroll_run_id = ? AND e.ppk_enrolled = 1
                 ORDER BY e.last_name, e.first_name",
                [$runId]
            );
        }

        $mainDb  = HrDatabase::mainDbName();
        $company = $db->fetchOne("SELECT company_name FROM `{$mainDb}`.clients WHERE id = ?", [$clientId]);
        $companyName = $company['company_name'] ?? 'Pracodawca';

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PPK ' . sprintf('%02d', $month) . '.' . $year);

        $monthLabel = self::$monthNames[$month] . ' ' . $year;

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', "Raport PPK — {$companyName} — {$monthLabel}");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $headers = ['A'=>'PESEL','B'=>'Nazwisko','C'=>'Imię','D'=>'Stawka prac. (%)','E'=>'Stawka pracodawcy (%)','F'=>'Podstawa (PLN)','G'=>'Składka prac. (PLN)','H'=>'Składka pracodawcy (PLN)'];
        foreach ($headers as $col => $label) { $sheet->setCellValue($col . '2', $label); }
        $sheet->getStyle('A2:H2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);

        $numFmt = '#,##0.00';
        $row = 3;
        $totEmpContrib = 0.0;
        $totEmplContrib = 0.0;

        foreach ($items as $item) {
            $pesel = $item['pesel'] ?? '';
            try {
                if (strlen($pesel) !== 11) {
                    $decrypted = HrEncryptionService::decrypt($pesel);
                    $pesel = $decrypted ?? $pesel;
                }
            } catch (\Throwable $e) { $pesel = '***'; }

            $empContrib  = (float)($item['ppk_employee_contribution'] ?? 0);
            $emplContrib = (float)($item['ppk_employer_contribution'] ?? 0);

            $sheet->setCellValue("A{$row}", $pesel);
            $sheet->setCellValue("B{$row}", $item['last_name']);
            $sheet->setCellValue("C{$row}", $item['first_name']);
            $sheet->setCellValue("D{$row}", (float)($item['ppk_employee_rate'] ?? 2.00));
            $sheet->setCellValue("E{$row}", (float)($item['ppk_employer_rate'] ?? 1.50));
            $sheet->setCellValue("F{$row}", (float)$item['gross_salary']);
            $sheet->setCellValue("G{$row}", $empContrib);
            $sheet->setCellValue("H{$row}", $emplContrib);
            $sheet->getStyle("D{$row}:H{$row}")->getNumberFormat()->setFormatCode($numFmt);

            $totEmpContrib  += $empContrib;
            $totEmplContrib += $emplContrib;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'RAZEM');
        $sheet->setCellValue("G{$row}", $totEmpContrib);
        $sheet->setCellValue("H{$row}", $totEmplContrib);
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
        ]);
        $sheet->getStyle("G{$row}:H{$row}")->getNumberFormat()->setFormatCode($numFmt);

        $colWidths = ['A'=>14,'B'=>18,'C'=>14,'D'=>14,'E'=>18,'F'=>16,'G'=>18,'H'=>20];
        foreach ($colWidths as $col => $w) { $sheet->getColumnDimension($col)->setWidth($w); }

        $sheet->getStyle("A2:H{$row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        $filename = sprintf('ppk_%d_%04d_%02d.xlsx', $clientId, $year, $month);
        $path     = self::$storageDir . '/' . $filename;
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public static function getYtdSummary(int $clientId, int $year): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT pi.employee_id,
                    CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                    COALESCE(SUM(pi.ppk_employee_contribution), 0) AS employee_ytd,
                    COALESCE(SUM(pi.ppk_employer_contribution), 0) AS employer_ytd
             FROM hr_payroll_items pi
             JOIN hr_payroll_runs pr ON pi.payroll_run_id = pr.id
             JOIN hr_employees e ON pi.employee_id = e.id
             WHERE pr.client_id = ? AND pr.period_year = ? AND pr.status != 'draft'
               AND e.ppk_enrolled = 1
             GROUP BY pi.employee_id, e.first_name, e.last_name
             ORDER BY e.last_name, e.first_name",
            [$clientId, $year]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['employee_id']] = [
                'name'         => $row['full_name'],
                'employee_ytd' => (float)$row['employee_ytd'],
                'employer_ytd' => (float)$row['employer_ytd'],
            ];
        }
        return $result;
    }
}
