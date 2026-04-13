<?php

namespace App\Services;

use App\Models\IssuedInvoice;
use App\Models\Client;

class SalesReportService
{
    private static string $storageDir = __DIR__ . '/../../storage/reports';

    public static function generateMonthlyReport(int $clientId, int $month, int $year): string
    {
        $client = Client::findById($clientId);
        if (!$client) {
            throw new \RuntimeException('Client not found: ' . $clientId);
        }
        $invoices = IssuedInvoice::findByClientAndPeriod($clientId, $month, $year);
        $vatSummary = IssuedInvoice::getVatSummary($clientId, $month, $year);

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('BiLLU');
        $pdf->SetTitle('Raport sprzedaży ' . sprintf('%02d/%04d', $month, $year));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $html = '<style>
            body { font-family: dejavusans; font-size: 8pt; }
            .title { font-size: 14pt; font-weight: bold; }
            .sub { font-size: 9pt; color: #666; }
            table { border-collapse: collapse; }
            th { background: #f0f0f0; font-size: 7pt; padding: 3px; border: 0.5px solid #ccc; }
            td { padding: 3px; border: 0.5px solid #ccc; font-size: 7pt; }
            .right { text-align: right; }
            .bold { font-weight: bold; }
        </style>';

        $html .= '<div class="title">Raport sprzedaży - ' . sprintf('%02d/%04d', $month, $year) . '</div>';
        $html .= '<div class="sub">' . htmlspecialchars($client['company_name']) . ' | NIP: ' . htmlspecialchars($client['nip']) . '</div>';
        $html .= '<div class="sub">Wygenerowano: ' . date('Y-m-d H:i:s') . '</div><br>';

        if (empty($invoices)) {
            $html .= '<p>Brak faktur sprzedaży za wybrany okres.</p>';
        } else {
            $html .= '<table width="100%">';
            $html .= '<tr><th>Lp.</th><th>Nr faktury</th><th>Data wyst.</th><th>Nabywca</th><th>NIP</th>';
            $html .= '<th class="right">Netto</th><th class="right">VAT</th><th class="right">Brutto</th>';
            $html .= '<th>Status</th><th>KSeF</th></tr>';

            $totalNet = 0;
            $totalVat = 0;
            $totalGross = 0;

            foreach ($invoices as $i => $inv) {
                $totalNet += (float) $inv['net_amount'];
                $totalVat += (float) $inv['vat_amount'];
                $totalGross += (float) $inv['gross_amount'];

                $html .= '<tr>';
                $html .= '<td>' . ($i + 1) . '</td>';
                $html .= '<td>' . htmlspecialchars($inv['invoice_number']) . '</td>';
                $html .= '<td>' . htmlspecialchars($inv['issue_date']) . '</td>';
                $html .= '<td>' . htmlspecialchars($inv['buyer_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($inv['buyer_nip'] ?? '') . '</td>';
                $html .= '<td class="right">' . number_format((float) $inv['net_amount'], 2, ',', ' ') . '</td>';
                $html .= '<td class="right">' . number_format((float) $inv['vat_amount'], 2, ',', ' ') . '</td>';
                $html .= '<td class="right bold">' . number_format((float) $inv['gross_amount'], 2, ',', ' ') . '</td>';
                $html .= '<td>' . ($inv['status'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($inv['ksef_reference_number'] ?? '-') . '</td>';
                $html .= '</tr>';
            }

            $html .= '<tr style="font-weight:bold; background:#e8e8e8;">';
            $html .= '<td colspan="5">RAZEM</td>';
            $html .= '<td class="right">' . number_format($totalNet, 2, ',', ' ') . '</td>';
            $html .= '<td class="right">' . number_format($totalVat, 2, ',', ' ') . '</td>';
            $html .= '<td class="right">' . number_format($totalGross, 2, ',', ' ') . '</td>';
            $html .= '<td colspan="2"></td>';
            $html .= '</tr>';
            $html .= '</table>';

            // VAT summary
            if (!empty($vatSummary)) {
                $html .= '<br><div class="sub" style="font-weight:bold;">Podsumowanie VAT:</div>';
                $html .= '<table width="40%">';
                $html .= '<tr><th>Stawka</th><th class="right">Netto</th><th class="right">VAT</th><th class="right">Brutto</th></tr>';

                foreach ($vatSummary as $vs) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($vs['rate']) . '%</td>';
                    $html .= '<td class="right">' . number_format((float) $vs['net'], 2, ',', ' ') . '</td>';
                    $html .= '<td class="right">' . number_format((float) $vs['vat'], 2, ',', ' ') . '</td>';
                    $html .= '<td class="right">' . number_format((float) $vs['gross'], 2, ',', ' ') . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
        }

        $pdf->writeHTML($html, true, false, true, false, '');

        self::ensureDir();
        $filename = sprintf('sales_report_%s_%02d_%04d.pdf', $client['nip'], $month, $year);
        $path = self::$storageDir . '/' . $filename;
        $pdf->Output($path, 'F');

        return $path;
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0750, true);
        }
    }
}
