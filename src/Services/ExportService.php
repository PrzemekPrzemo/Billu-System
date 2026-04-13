<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExportService
{
    public static function generateAcceptedXls(int $batchId): string
    {
        return self::generateXls($batchId, 'accepted');
    }

    public static function generateRejectedXls(int $batchId): string
    {
        return self::generateXls($batchId, 'rejected');
    }

    private static function generateXls(int $batchId, string $type): string
    {
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $invoices = $type === 'accepted'
            ? Invoice::getAcceptedByBatch($batchId)
            : Invoice::getRejectedByBatch($batchId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $titleLabel = $type === 'accepted' ? 'Zaakceptowane faktury' : 'Odrzucone faktury';
        $sheet->setTitle($titleLabel);

        // Header info
        $sheet->setCellValue('A1', 'Klient:');
        $sheet->setCellValue('B1', $client['company_name']);
        $sheet->setCellValue('A2', 'NIP:');
        $sheet->setCellValue('B2', $client['nip']);
        $sheet->setCellValue('A3', 'Okres:');
        $sheet->setCellValue('B3', sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']));
        $sheet->setCellValue('A4', 'Typ raportu:');
        $sheet->setCellValue('B4', $titleLabel);
        $sheet->setCellValue('A5', 'Data generowania:');
        $sheet->setCellValue('B5', date('Y-m-d H:i'));

        // Column headers - always include comment and cost center
        $headers = [
            'A7' => 'Lp.',
            'B7' => 'Nr faktury',
            'C7' => 'Nr KSeF',
            'D7' => 'Data wystawienia',
            'E7' => 'Data sprzedaży',
            'F7' => 'NIP sprzedawcy',
            'G7' => 'Nazwa sprzedawcy',
            'H7' => 'Adres sprzedawcy',
            'I7' => 'Waluta',
            'J7' => 'Kwota netto',
            'K7' => 'Kwota VAT',
            'L7' => 'Kwota brutto',
            'M7' => 'MPK (miejsce kosztów)',
            'N7' => 'Komentarz',
        ];

        $lastCol = 'N';

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $headerRange = "A7:{$lastCol}7";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $row = 8;
        $totalNet = 0;
        $totalVat = 0;
        $totalGross = 0;

        foreach ($invoices as $i => $inv) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $inv['invoice_number']);
            $sheet->setCellValue("C{$row}", $inv['ksef_reference_number'] ?? '');
            $sheet->setCellValue("D{$row}", $inv['issue_date']);
            $sheet->setCellValue("E{$row}", $inv['sale_date'] ?? '');
            $sheet->setCellValue("F{$row}", $inv['seller_nip']);
            $sheet->setCellValue("G{$row}", $inv['seller_name']);
            $sheet->setCellValue("H{$row}", $inv['seller_address']);
            $sheet->setCellValue("I{$row}", $inv['currency']);
            $sheet->setCellValue("J{$row}", (float) $inv['net_amount']);
            $sheet->setCellValue("K{$row}", (float) $inv['vat_amount']);
            $sheet->setCellValue("L{$row}", (float) $inv['gross_amount']);
            $sheet->setCellValue("M{$row}", $inv['cost_center'] ?? '');
            $sheet->setCellValue("N{$row}", $inv['comment'] ?? '');

            $totalNet += (float) $inv['net_amount'];
            $totalVat += (float) $inv['vat_amount'];
            $totalGross += (float) $inv['gross_amount'];
            $row++;
        }

        // Totals
        $sheet->setCellValue("I{$row}", 'SUMA:');
        $sheet->setCellValue("J{$row}", $totalNet);
        $sheet->setCellValue("K{$row}", $totalVat);
        $sheet->setCellValue("L{$row}", $totalGross);
        $sheet->getStyle("I{$row}:{$lastCol}{$row}")->getFont()->setBold(true);

        // Format numbers
        $sheet->getStyle("J8:L{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        // Auto-size columns
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Borders
        $dataRange = "A7:{$lastCol}{$row}";
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $filename = sprintf(
            '%s_%s_%02d_%04d_%s.xlsx',
            $client['nip'],
            $type === 'rejected' ? 'odrzucone' : $type,
            $batch['period_month'],
            $batch['period_year'],
            date('Ymd_His')
        );

        $path = __DIR__ . '/../../storage/exports/' . $filename;
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    public static function generateCostCenterXls(int $batchId, string $costCenterName, array $invoices): string
    {
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $safeName = mb_substr(preg_replace('/[^\w\s-]/u', '', $costCenterName), 0, 30);
        $titleLabel = "Zaakceptowane - MPK: {$costCenterName}";
        $sheet->setTitle($safeName ?: 'MPK');

        // Header info
        $sheet->setCellValue('A1', 'Klient:');
        $sheet->setCellValue('B1', $client['company_name']);
        $sheet->setCellValue('A2', 'NIP:');
        $sheet->setCellValue('B2', $client['nip']);
        $sheet->setCellValue('A3', 'Okres:');
        $sheet->setCellValue('B3', sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']));
        $sheet->setCellValue('A4', 'MPK:');
        $sheet->setCellValue('B4', $costCenterName);
        $sheet->setCellValue('A5', 'Data generowania:');
        $sheet->setCellValue('B5', date('Y-m-d H:i'));

        // Column headers
        $headers = [
            'A7' => 'Lp.', 'B7' => 'Nr faktury', 'C7' => 'Nr KSeF',
            'D7' => 'Data wystawienia', 'E7' => 'Data sprzedaży',
            'F7' => 'NIP sprzedawcy', 'G7' => 'Nazwa sprzedawcy',
            'H7' => 'Adres sprzedawcy', 'I7' => 'Waluta', 'J7' => 'Kwota netto',
            'K7' => 'Kwota VAT', 'L7' => 'Kwota brutto', 'M7' => 'Komentarz',
        ];
        $lastCol = 'M';

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $headerRange = "A7:{$lastCol}7";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = 8;
        $totalNet = $totalVat = $totalGross = 0;

        foreach ($invoices as $i => $inv) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $inv['invoice_number']);
            $sheet->setCellValue("C{$row}", $inv['ksef_reference_number'] ?? '');
            $sheet->setCellValue("D{$row}", $inv['issue_date']);
            $sheet->setCellValue("E{$row}", $inv['sale_date'] ?? '');
            $sheet->setCellValue("F{$row}", $inv['seller_nip']);
            $sheet->setCellValue("G{$row}", $inv['seller_name']);
            $sheet->setCellValue("H{$row}", $inv['seller_address']);
            $sheet->setCellValue("I{$row}", $inv['currency']);
            $sheet->setCellValue("J{$row}", (float) $inv['net_amount']);
            $sheet->setCellValue("K{$row}", (float) $inv['vat_amount']);
            $sheet->setCellValue("L{$row}", (float) $inv['gross_amount']);
            $sheet->setCellValue("M{$row}", $inv['comment'] ?? '');

            $totalNet += (float) $inv['net_amount'];
            $totalVat += (float) $inv['vat_amount'];
            $totalGross += (float) $inv['gross_amount'];
            $row++;
        }

        $sheet->setCellValue("I{$row}", 'SUMA:');
        $sheet->setCellValue("J{$row}", $totalNet);
        $sheet->setCellValue("K{$row}", $totalVat);
        $sheet->setCellValue("L{$row}", $totalGross);
        $sheet->getStyle("I{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("J8:L{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $dataRange = "A7:{$lastCol}{$row}";
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $safeCC = preg_replace('/[^\w-]/u', '_', $costCenterName);
        $filename = sprintf('%s_accepted_MPK_%s_%02d_%04d_%s.xlsx',
            $client['nip'], $safeCC, $batch['period_month'], $batch['period_year'], date('Ymd_His'));

        $path = __DIR__ . '/../../storage/exports/' . $filename;
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        return $path;
    }
}
