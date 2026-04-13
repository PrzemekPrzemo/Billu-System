<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Client;
use TCPDF;

class PdfService
{
    public static function generateAcceptedPdf(int $batchId): string
    {
        return self::generatePdf($batchId, 'accepted');
    }

    public static function generateRejectedPdf(int $batchId): string
    {
        return self::generatePdf($batchId, 'rejected');
    }

    private static function generatePdf(int $batchId, string $type): string
    {
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $invoices = $type === 'accepted'
            ? Invoice::getAcceptedByBatch($batchId)
            : Invoice::getRejectedByBatch($batchId);

        $titleLabel = $type === 'accepted' ? 'Zaakceptowane faktury' : 'Odrzucone faktury';

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('BiLLU');
        $pdf->SetAuthor($client['company_name']);
        $pdf->SetTitle("{$titleLabel} - {$client['company_name']} - {$batch['period_month']}/{$batch['period_year']}");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, $titleLabel, 0, 1, 'C');
        $pdf->Ln(2);

        // Client info
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(30, 6, 'Klient:', 0, 0);
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 6, $client['company_name'], 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(30, 6, 'NIP:', 0, 0);
        $pdf->Cell(0, 6, $client['nip'], 0, 1);
        $pdf->Cell(30, 6, 'Okres:', 0, 0);
        $pdf->Cell(0, 6, sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']), 0, 1);
        $pdf->Cell(30, 6, 'Data raportu:', 0, 0);
        $pdf->Cell(0, 6, date('Y-m-d H:i'), 0, 1);
        $pdf->Cell(30, 6, 'Liczba faktur:', 0, 0);
        $pdf->Cell(0, 6, (string) count($invoices), 0, 1);
        $pdf->Ln(5);

        // Table headers
        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->SetFillColor(37, 99, 235);
        $pdf->SetTextColor(255, 255, 255);

        $colWidths = [6, 25, 30, 18, 18, 20, 40, 12, 20, 18, 20, 24, 26];
        $headers = ['Lp.', 'Nr faktury', 'Nr KSeF', 'Data wyst.', 'Data sprz.', 'NIP sprz.', 'Sprzedawca', 'Waluta', 'Netto', 'VAT', 'Brutto', 'MPK', 'Komentarz'];

        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Data
        $pdf->SetFont('dejavusans', '', 6);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;
        $totalNet = $totalVat = $totalGross = 0;

        foreach ($invoices as $i => $inv) {
            if ($fill) $pdf->SetFillColor(243, 244, 246);

            $rowData = [
                (string) ($i + 1),
                $inv['invoice_number'],
                $inv['ksef_reference_number'] ?? '',
                $inv['issue_date'],
                $inv['sale_date'] ?? '',
                $inv['seller_nip'],
                mb_substr($inv['seller_name'], 0, 30),
                $inv['currency'],
                number_format((float) $inv['net_amount'], 2, ',', ' '),
                number_format((float) $inv['vat_amount'], 2, ',', ' '),
                number_format((float) $inv['gross_amount'], 2, ',', ' '),
                mb_substr($inv['cost_center'] ?? '', 0, 16),
                mb_substr($inv['comment'] ?? '', 0, 18),
            ];

            foreach ($rowData as $j => $val) {
                $align = in_array($j, [8, 9, 10]) ? 'R' : 'L';
                if ($j === 0) $align = 'C';
                $pdf->Cell($colWidths[$j], 6, $val, 1, 0, $align, $fill);
            }
            $pdf->Ln();

            $totalNet += (float) $inv['net_amount'];
            $totalVat += (float) $inv['vat_amount'];
            $totalGross += (float) $inv['gross_amount'];
            $fill = !$fill;
        }

        // Totals
        $pdf->SetFont('dejavusans', 'B', 7);
        $sumOffset = array_sum(array_slice($colWidths, 0, 8));
        $pdf->Cell($sumOffset, 7, 'SUMA:', 1, 0, 'R');
        $pdf->Cell($colWidths[8], 7, number_format($totalNet, 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($colWidths[9], 7, number_format($totalVat, 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($colWidths[10], 7, number_format($totalGross, 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($colWidths[11] + $colWidths[12], 7, '', 1, 0, 'C');
        $pdf->Ln();

        $filename = sprintf(
            '%s_%s_%02d_%04d_%s.pdf',
            $client['nip'],
            $type === 'rejected' ? 'odrzucone' : $type,
            $batch['period_month'],
            $batch['period_year'],
            date('Ymd_His')
        );

        $path = __DIR__ . '/../../storage/exports/' . $filename;
        $pdf->Output($path, 'F');
        return $path;
    }

    public static function generateCostCenterPdf(int $batchId, string $costCenterName, array $invoices): string
    {
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $titleLabel = "Zaakceptowane faktury - MPK: {$costCenterName}";

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('BiLLU');
        $pdf->SetAuthor($client['company_name']);
        $pdf->SetTitle("{$titleLabel} - {$client['company_name']} - {$batch['period_month']}/{$batch['period_year']}");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Cell(0, 10, $titleLabel, 0, 1, 'C');
        $pdf->Ln(2);

        // Client info
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(30, 6, 'Klient:', 0, 0);
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 6, $client['company_name'], 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(30, 6, 'NIP:', 0, 0);
        $pdf->Cell(0, 6, $client['nip'], 0, 1);
        $pdf->Cell(30, 6, 'Okres:', 0, 0);
        $pdf->Cell(0, 6, sprintf('%02d/%04d', $batch['period_month'], $batch['period_year']), 0, 1);
        $pdf->Cell(30, 6, 'MPK:', 0, 0);
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 6, $costCenterName, 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(30, 6, 'Data raportu:', 0, 0);
        $pdf->Cell(0, 6, date('Y-m-d H:i'), 0, 1);
        $pdf->Cell(30, 6, 'Liczba faktur:', 0, 0);
        $pdf->Cell(0, 6, (string) count($invoices), 0, 1);
        $pdf->Ln(5);

        // Table (no MPK column since it's per-MPK report, but add Comment)
        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->SetFillColor(37, 99, 235);
        $pdf->SetTextColor(255, 255, 255);

        $colWidths = [6, 25, 30, 18, 18, 20, 45, 12, 22, 20, 22, 30];
        $headers = ['Lp.', 'Nr faktury', 'Nr KSeF', 'Data wyst.', 'Data sprz.', 'NIP sprz.', 'Sprzedawca', 'Waluta', 'Netto', 'VAT', 'Brutto', 'Komentarz'];

        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], 7, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('dejavusans', '', 6);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;
        $totalNet = $totalVat = $totalGross = 0;

        foreach ($invoices as $i => $inv) {
            if ($fill) $pdf->SetFillColor(243, 244, 246);
            $rowData = [
                (string) ($i + 1),
                $inv['invoice_number'],
                $inv['ksef_reference_number'] ?? '',
                $inv['issue_date'],
                $inv['sale_date'] ?? '',
                $inv['seller_nip'],
                mb_substr($inv['seller_name'], 0, 32),
                $inv['currency'],
                number_format((float) $inv['net_amount'], 2, ',', ' '),
                number_format((float) $inv['vat_amount'], 2, ',', ' '),
                number_format((float) $inv['gross_amount'], 2, ',', ' '),
                mb_substr($inv['comment'] ?? '', 0, 22),
            ];

            foreach ($rowData as $j => $val) {
                $align = in_array($j, [8, 9, 10]) ? 'R' : 'L';
                if ($j === 0) $align = 'C';
                $pdf->Cell($colWidths[$j], 6, $val, 1, 0, $align, $fill);
            }
            $pdf->Ln();

            $totalNet += (float) $inv['net_amount'];
            $totalVat += (float) $inv['vat_amount'];
            $totalGross += (float) $inv['gross_amount'];
            $fill = !$fill;
        }

        // Totals
        $pdf->SetFont('dejavusans', 'B', 7);
        $sumOffset = array_sum(array_slice($colWidths, 0, 8));
        $pdf->Cell($sumOffset, 7, 'SUMA:', 1, 0, 'R');
        $pdf->Cell($colWidths[8], 7, number_format($totalNet, 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($colWidths[9], 7, number_format($totalVat, 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($colWidths[10], 7, number_format($totalGross, 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($colWidths[11], 7, '', 1, 0, 'C');
        $pdf->Ln();

        $safeCC = preg_replace('/[^\w-]/u', '_', $costCenterName);
        $filename = sprintf('%s_accepted_MPK_%s_%02d_%04d_%s.pdf',
            $client['nip'], $safeCC, $batch['period_month'], $batch['period_year'], date('Ymd_His'));

        $path = __DIR__ . '/../../storage/exports/' . $filename;
        $pdf->Output($path, 'F');
        return $path;
    }

    /**
     * Generate an aggregate PDF report across multiple clients.
     */
    public static function generateAggregateReportPdf(array $results, array $totals, string $dateFrom, string $dateTo): string
    {
        $appConfig = require __DIR__ . '/../../config/app.php';
        $systemName = $appConfig['name'] ?? 'BiLLU Financial Solutions';

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator($systemName);
        $pdf->SetAuthor($systemName);
        $pdf->SetTitle("Raport zbiorczy - {$dateFrom} - {$dateTo}");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Logo
        $logoPath = __DIR__ . '/../../public' . (\App\Models\Setting::get('logo_path') ?? '/assets/img/logo.svg');
        if (file_exists($logoPath) && !str_ends_with($logoPath, '.svg')) {
            $pdf->Image($logoPath, 10, 10, 40, 0, '', '', '', true);
            $pdf->SetY(30);
        }

        // Title page
        $pdf->SetFont('dejavusans', 'B', 18);
        $pdf->Cell(0, 12, 'Raport zbiorczy', 0, 1, 'C');
        $pdf->Ln(4);

        $pdf->SetFont('dejavusans', '', 11);
        $pdf->Cell(0, 7, "System: {$systemName}", 0, 1, 'C');
        $pdf->Cell(0, 7, "Okres: {$dateFrom} — {$dateTo}", 0, 1, 'C');
        $pdf->Cell(0, 7, "Wygenerowano: " . date('Y-m-d H:i'), 0, 1, 'C');
        $pdf->Cell(0, 7, "Klientów w raporcie: " . count($results), 0, 1, 'C');
        $pdf->Ln(8);

        // Summary table
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 8, 'Podsumowanie', 0, 1, 'L');

        $colW = [80, 25, 30, 30, 30, 30, 50];
        $headers = ['Klient', 'NIP', 'Łącznie', 'Zaakc.', 'Odrz.', 'Oczek.', 'Brutto'];

        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetFillColor(37, 99, 235);
        $pdf->SetTextColor(255);
        foreach ($headers as $i => $h) {
            $pdf->Cell($colW[$i], 7, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetTextColor(0);
        $pdf->SetFont('dejavusans', '', 9);
        $fill = false;

        foreach ($results as $r) {
            $c = $r['client'];
            if ($fill) $pdf->SetFillColor(240, 240, 240);
            else $pdf->SetFillColor(255, 255, 255);

            $pdf->Cell($colW[0], 7, mb_substr($c['company_name'], 0, 40), 1, 0, 'L', true);
            $pdf->Cell($colW[1], 7, $c['nip'], 1, 0, 'C', true);
            $pdf->Cell($colW[2], 7, $r['total'], 1, 0, 'C', true);
            $pdf->Cell($colW[3], 7, $r['accepted'], 1, 0, 'C', true);
            $pdf->Cell($colW[4], 7, $r['rejected'], 1, 0, 'C', true);
            $pdf->Cell($colW[5], 7, $r['pending'], 1, 0, 'C', true);
            $pdf->Cell($colW[6], 7, number_format($r['gross'], 2, ',', ' ') . ' PLN', 1, 0, 'R', true);
            $pdf->Ln();
            $fill = !$fill;
        }

        // Totals row
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell($colW[0] + $colW[1], 7, 'RAZEM', 1, 0, 'R', true);
        $totalInvoices = array_sum(array_column($results, 'total'));
        $pdf->Cell($colW[2], 7, $totalInvoices, 1, 0, 'C', true);
        $pdf->Cell($colW[3], 7, $totals['accepted'], 1, 0, 'C', true);
        $pdf->Cell($colW[4], 7, $totals['rejected'], 1, 0, 'C', true);
        $pdf->Cell($colW[5], 7, $totals['pending'], 1, 0, 'C', true);
        $pdf->Cell($colW[6], 7, number_format($totals['gross'], 2, ',', ' ') . ' PLN', 1, 0, 'R', true);
        $pdf->Ln();

        // Per-client detail pages
        foreach ($results as $r) {
            if ($r['total'] === 0) continue;

            $c = $r['client'];
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', 'B', 14);
            $pdf->Cell(0, 10, $c['company_name'] . ' (NIP: ' . $c['nip'] . ')', 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->Cell(0, 6, "Faktur: {$r['total']}, Zaakc.: {$r['accepted']}, Odrz.: {$r['rejected']}, Oczek.: {$r['pending']}", 0, 1, 'L');
            $pdf->Cell(0, 6, "Brutto: " . number_format($r['gross'], 2, ',', ' ') . ' PLN', 0, 1, 'L');
            $pdf->Ln(4);

            // Invoice table for this client
            $invoices = Invoice::findByClient((int) $c['id']);
            if (!empty($invoices)) {
                $icW = [7, 25, 28, 18, 40, 22, 24, 22, 24, 18];
                $iHeaders = ['Lp', 'Nr faktury', 'Nr KSeF', 'Data', 'Sprzedawca', 'NIP sprz.', 'Netto', 'VAT', 'Brutto', 'Status'];

                $pdf->SetFont('dejavusans', 'B', 8);
                $pdf->SetFillColor(37, 99, 235);
                $pdf->SetTextColor(255);
                foreach ($iHeaders as $i => $h) {
                    $pdf->Cell($icW[$i], 6, $h, 1, 0, 'C', true);
                }
                $pdf->Ln();

                $pdf->SetTextColor(0);
                $pdf->SetFont('dejavusans', '', 7);
                $lp = 0;
                foreach ($invoices as $inv) {
                    $lp++;
                    $iFill = $lp % 2 === 0;
                    if ($iFill) $pdf->SetFillColor(245, 245, 245);
                    else $pdf->SetFillColor(255, 255, 255);

                    $pdf->Cell($icW[0], 6, $lp, 1, 0, 'C', true);
                    $pdf->Cell($icW[1], 6, mb_substr($inv['invoice_number'], 0, 16), 1, 0, 'L', true);
                    $pdf->Cell($icW[2], 6, $inv['ksef_reference_number'] ?? '', 1, 0, 'L', true);
                    $pdf->Cell($icW[3], 6, $inv['issue_date'], 1, 0, 'C', true);
                    $pdf->Cell($icW[4], 6, mb_substr($inv['seller_name'], 0, 24), 1, 0, 'L', true);
                    $pdf->Cell($icW[5], 6, $inv['seller_nip'], 1, 0, 'C', true);
                    $pdf->Cell($icW[6], 6, number_format((float)$inv['net_amount'], 2, ',', ' '), 1, 0, 'R', true);
                    $pdf->Cell($icW[7], 6, number_format((float)$inv['vat_amount'], 2, ',', ' '), 1, 0, 'R', true);
                    $pdf->Cell($icW[8], 6, number_format((float)$inv['gross_amount'], 2, ',', ' '), 1, 0, 'R', true);
                    $pdf->Cell($icW[9], 6, strtoupper(substr($inv['status'], 0, 3)), 1, 0, 'C', true);
                    $pdf->Ln();
                }
            }
        }

        $dir = __DIR__ . '/../../storage/exports';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $filename = 'raport_zbiorczy_' . date('Ymd_His') . '.pdf';
        $path = $dir . '/' . $filename;
        $pdf->Output($path, 'F');
        return $path;
    }
}
