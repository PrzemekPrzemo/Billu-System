<?php

namespace App\Services;

use App\Models\IssuedInvoice;
use App\Models\Contractor;
use App\Models\KsefConfig;
use App\Core\Language;

class InvoicePdfService
{
    private static string $storageDir = __DIR__ . '/../../storage/invoices';

    public static function generate(int $invoiceId): string
    {
        $invoice = IssuedInvoice::findById($invoiceId);
        if (!$invoice) throw new \RuntimeException('Invoice not found');

        self::ensureDir();

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('BiLLU');
        $pdf->SetAuthor($invoice['seller_name']);
        $pdf->SetTitle($invoice['invoice_number']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->AddPage();

        $lineItems = $invoice['line_items'] ?? '[]';
        if (is_string($lineItems)) $lineItems = json_decode($lineItems, true) ?: [];

        $vatDetails = $invoice['vat_details'] ?? '[]';
        if (is_string($vatDetails)) $vatDetails = json_decode($vatDetails, true) ?: [];

        $contractor = null;
        if (!empty($invoice['contractor_id'])) {
            $contractor = Contractor::findById((int) $invoice['contractor_id']);
        }
        $html = self::buildHtml($invoice, $lineItems, $vatDetails, $contractor);
        $pdf->writeHTML($html, true, false, true, false, '');

        // QR codes: verification data + digital signature + KSeF reference
        self::renderQrCodes($pdf, $invoice);

        $filename = 'FV_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice['invoice_number']) . '.pdf';
        $path = self::$storageDir . '/' . $filename;
        $pdf->Output($path, 'F');

        IssuedInvoice::update($invoiceId, ['pdf_path' => 'storage/invoices/' . $filename]);

        return $path;
    }

    private static function buildHtml(array $inv, array $items, array $vatDetails, ?array $contractor = null): string
    {
        $paymentLabels = [
            'przelew' => 'Przelew bankowy',
            'gotowka' => 'Gotówka',
            'karta' => 'Karta płatnicza',
            'kompensata' => 'Kompensata',
            'barter' => 'Barter',
        ];
        $paymentLabel = $paymentLabels[$inv['payment_method'] ?? 'przelew'] ?? 'Przelew';

        $isCorrection = $inv['invoice_type'] === 'FV_KOR';
        $docTitle = $isCorrection ? 'FAKTURA KORYGUJĄCA' : 'FAKTURA VAT';

        $html = '<style>
            * { font-family: dejavusans; }
            body { font-family: dejavusans; font-size: 9pt; color: #222; }
            .header { font-family: dejavusans; font-size: 16pt; font-weight: bold; margin-bottom: 10px; }
            .sub { font-family: dejavusans; font-size: 8pt; color: #666; }
            table { border-collapse: collapse; font-family: dejavusans; }
            td { font-family: dejavusans; }
            th { font-family: dejavusans; }
            .items th { background-color: #f0f0f0; font-size: 8pt; padding: 4px; border: 0.5px solid #ccc; }
            .items td { padding: 4px; border: 0.5px solid #ccc; font-size: 8pt; }
            .right { text-align: right; }
            .center { text-align: center; }
            .bold { font-weight: bold; }
            .section { margin-bottom: 10px; }
        </style>';

        // Title
        $html .= '<table width="100%" cellpadding="0"><tr>';
        $html .= '<td width="60%"><span class="header">' . $docTitle . '</span><br>';
        $html .= '<span style="font-size:12pt; font-weight:bold;">' . self::e($inv['invoice_number']) . '</span></td>';
        $html .= '<td width="40%" class="right sub">';
        $html .= 'Data wystawienia: <strong>' . self::e($inv['issue_date']) . '</strong><br>';
        if (!empty($inv['sale_date']) && $inv['sale_date'] !== $inv['issue_date']) {
            $html .= 'Data sprzedaży: <strong>' . self::e($inv['sale_date']) . '</strong><br>';
        }
        if (!empty($inv['due_date'])) {
            $html .= 'Termin płatności: <strong>' . self::e($inv['due_date']) . '</strong>';
        }
        $html .= '</td></tr></table>';

        // Exchange rate info for foreign currency invoices (art. 106e ust. 11)
        $pdfCurrency = $inv['currency'] ?? 'PLN';
        if ($pdfCurrency !== 'PLN' && !empty($inv['exchange_rate'])) {
            $html .= '<div style="font-size:7pt; color:#666; margin:4px 0; padding:4px 8px; background:#f0f9ff; border:0.5px solid #bfdbfe; border-radius:3px;">';
            $html .= 'Kurs NBP: 1 ' . self::e($pdfCurrency) . ' = ' . number_format((float)$inv['exchange_rate'], 4, ',', ' ') . ' PLN';
            if (!empty($inv['exchange_rate_table'])) {
                $html .= ' (tabela ' . self::e($inv['exchange_rate_table']);
                if (!empty($inv['exchange_rate_date'])) {
                    $html .= ' z dnia ' . self::e($inv['exchange_rate_date']);
                }
                $html .= ')';
            }
            $html .= '</div>';
        }

        $html .= '<hr style="border: 0.5px solid #ccc; margin: 8px 0;">';

        // Seller / Buyer
        $html .= '<table width="100%" cellpadding="6" cellspacing="0" class="section"><tr>';
        $html .= '<td width="48%" style="background-color:#f8f8f8; border:0.5px solid #ddd;">';
        $html .= '<span class="sub">SPRZEDAWCA</span><br>';
        $html .= '<strong>' . self::e($inv['seller_name']) . '</strong><br>';
        $html .= 'NIP: ' . self::e($inv['seller_nip']) . '<br>';
        if (!empty($inv['seller_address'])) $html .= self::e($inv['seller_address']);
        $html .= '</td>';
        $html .= '<td width="4%">&nbsp;</td>';
        $html .= '<td width="48%" style="background-color:#f8f8f8; border:0.5px solid #ddd;">';
        $html .= '<span class="sub">NABYWCA</span><br>';
        if ($contractor && !empty($contractor['logo_path'])) {
            $logoFullPath = __DIR__ . '/../../' . $contractor['logo_path'];
            if (file_exists($logoFullPath)) {
                $html .= '<img src="' . $logoFullPath . '" style="max-height:30px; margin-bottom:4px;"><br>';
            }
        }
        $html .= '<strong>' . self::e($inv['buyer_name']) . '</strong><br>';
        if (!empty($inv['buyer_nip'])) $html .= 'NIP: ' . self::e($inv['buyer_nip']) . '<br>';
        if (!empty($inv['buyer_address'])) $html .= self::e($inv['buyer_address']);
        $html .= '</td>';
        $html .= '</tr></table>';

        // Line items
        if (!empty($items)) {
            $html .= '<table width="100%" class="items" cellpadding="3" cellspacing="0">';
            $html .= '<thead><tr>';
            $html .= '<th width="5%" class="center">Lp.</th>';
            $html .= '<th width="27%">Nazwa towaru / usługi</th>';
            $html .= '<th width="8%" class="center">Ilość</th>';
            $html .= '<th width="7%" class="center">Jm.</th>';
            $html .= '<th width="12%" class="right">Cena netto</th>';
            $html .= '<th width="8%" class="center">VAT</th>';
            $html .= '<th width="12%" class="right">Netto</th>';
            $html .= '<th width="9%" class="right">VAT</th>';
            $html .= '<th width="12%" class="right">Brutto</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($items as $i => $item) {
                $html .= '<tr>';
                $html .= '<td class="center">' . ($i + 1) . '</td>';
                $html .= '<td>' . self::e($item['name'] ?? '') . '</td>';
                $html .= '<td class="center">' . ($item['quantity'] ?? 1) . '</td>';
                $html .= '<td class="center">' . self::e($item['unit'] ?? 'szt.') . '</td>';
                $html .= '<td class="right">' . self::fmtPl((float)($item['unit_price'] ?? 0)) . '</td>';
                $html .= '<td class="center">' . self::e($item['vat_rate'] ?? '23') . '%</td>';
                $html .= '<td class="right">' . self::fmtPl((float)($item['net'] ?? 0)) . '</td>';
                $html .= '<td class="right">' . self::fmtPl((float)($item['vat'] ?? 0)) . '</td>';
                $html .= '<td class="right bold">' . self::fmtPl((float)($item['gross'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        // VAT summary + totals
        $html .= '<table width="100%" cellpadding="2" cellspacing="0" style="margin-top:8px;"><tr>';
        $html .= '<td width="55%">&nbsp;</td>';
        $html .= '<td width="45%">';
        $html .= '<table width="100%" class="items" cellpadding="3" cellspacing="0">';
        $html .= '<tr style="background-color:#f0f0f0;"><th>Stawka</th><th class="right">Netto</th><th class="right">VAT</th><th class="right">Brutto</th></tr>';

        foreach ($vatDetails as $vd) {
            $net = (float)($vd['net'] ?? 0);
            $vat = (float)($vd['vat'] ?? 0);
            $html .= '<tr>';
            $html .= '<td class="center">' . self::e($vd['rate'] ?? '') . '%</td>';
            $html .= '<td class="right">' . self::fmtPl($net) . '</td>';
            $html .= '<td class="right">' . self::fmtPl($vat) . '</td>';
            $html .= '<td class="right">' . self::fmtPl($net + $vat) . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr style="font-weight:bold; border-top:1px solid #333;">';
        $html .= '<td>RAZEM</td>';
        $html .= '<td class="right">' . self::fmtPl((float)$inv['net_amount']) . '</td>';
        $html .= '<td class="right">' . self::fmtPl((float)$inv['vat_amount']) . '</td>';
        $html .= '<td class="right">' . self::fmtPl((float)$inv['gross_amount']) . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td></tr></table>';

        // Gross amount big
        $html .= '<div style="text-align:right; font-size:12pt; font-weight:bold; margin:10px 0;">';
        $html .= 'Do zapłaty: ' . self::fmtPl((float)$inv['gross_amount']) . ' ' . self::e($pdfCurrency);
        $html .= '</div>';

        // VAT in PLN for foreign currency invoices (art. 106e ust. 11)
        if ($pdfCurrency !== 'PLN' && !empty($inv['exchange_rate'])) {
            $vatPln = !empty($inv['vat_amount_pln'])
                ? (float)$inv['vat_amount_pln']
                : round((float)$inv['vat_amount'] * (float)$inv['exchange_rate'], 2);
            $html .= '<div style="text-align:right; font-size:8pt; color:#666; margin-bottom:6px;">';
            $html .= 'w tym VAT: ' . self::fmtPl($vatPln) . ' PLN';
            $html .= '</div>';
        }

        // Payment info
        $html .= '<table width="100%" cellpadding="6" cellspacing="0" style="background-color:#f8f8f8; border:0.5px solid #ddd;"><tr>';
        $html .= '<td width="50%">';
        $html .= '<span class="sub">Forma płatności:</span> <strong>' . $paymentLabel . '</strong>';
        $html .= '</td>';
        if (!empty($inv['bank_account_number'])) {
            $html .= '<td width="50%">';
            $html .= '<span class="sub">Rachunek bankowy:</span><br>';
            if (!empty($inv['bank_name'])) $html .= self::e($inv['bank_name']) . '<br>';
            $html .= '<strong>' . self::e($inv['bank_account_number']) . '</strong>';
            $html .= '</td>';
        }
        $html .= '</tr></table>';

        // Notes
        if (!empty($inv['notes'])) {
            $html .= '<div style="margin-top:10px; padding:6px; background:#fffbe6; border:0.5px solid #eee;">';
            $html .= '<span class="sub">Uwagi:</span><br>' . nl2br(self::e($inv['notes']));
            $html .= '</div>';
        }

        // Correction info — before/after comparison
        if ($isCorrection) {
            $originalItems = $inv['original_line_items'] ?? null;
            if (is_string($originalItems)) $originalItems = json_decode($originalItems, true) ?: [];

            if (!empty($originalItems)) {
                // "Stan przed korektą" table
                $html .= '<div style="margin-top:12px; padding:6px; background:#fef2f2; border:0.5px solid #fca5a5;">';
                $html .= '<span class="sub" style="color:#991b1b;">STAN PRZED KOREKTĄ</span>';
                $html .= '</div>';
                $html .= '<table width="100%" class="items" cellpadding="3" cellspacing="0">';
                $html .= '<thead><tr>';
                $html .= '<th width="5%" class="center">Lp.</th>';
                $html .= '<th width="31%">Nazwa towaru / usługi</th>';
                $html .= '<th width="8%" class="center">Ilość</th>';
                $html .= '<th width="7%" class="center">Jm.</th>';
                $html .= '<th width="12%" class="right">Cena netto</th>';
                $html .= '<th width="8%" class="center">VAT</th>';
                $html .= '<th width="12%" class="right">Netto</th>';
                $html .= '<th width="9%" class="right">VAT</th>';
                $html .= '<th width="12%" class="right">Brutto</th>';
                $html .= '</tr></thead><tbody>';
                foreach ($originalItems as $i => $item) {
                    $html .= '<tr style="background-color:#fef2f2;">';
                    $html .= '<td class="center">' . ($i + 1) . '</td>';
                    $html .= '<td>' . self::e($item['name'] ?? '') . '</td>';
                    $html .= '<td class="center">' . ($item['quantity'] ?? 1) . '</td>';
                    $html .= '<td class="center">' . self::e($item['unit'] ?? 'szt.') . '</td>';
                    $html .= '<td class="right">' . self::fmtPl((float)($item['unit_price'] ?? 0)) . '</td>';
                    $html .= '<td class="center">' . self::e($item['vat_rate'] ?? '23') . '%</td>';
                    $html .= '<td class="right">' . self::fmtPl((float)($item['net'] ?? 0)) . '</td>';
                    $html .= '<td class="right">' . self::fmtPl((float)($item['vat'] ?? 0)) . '</td>';
                    $html .= '<td class="right bold">' . self::fmtPl((float)($item['gross'] ?? 0)) . '</td>';
                    $html .= '</tr>';
                }
                $origNet = (float)($inv['original_net_amount'] ?? 0);
                $origVat = (float)($inv['original_vat_amount'] ?? 0);
                $origGross = (float)($inv['original_gross_amount'] ?? 0);
                $html .= '<tr style="background-color:#fef2f2; font-weight:bold;">';
                $html .= '<td colspan="6" class="right">Razem przed:</td>';
                $html .= '<td class="right">' . self::fmtPl($origNet) . '</td>';
                $html .= '<td class="right">' . self::fmtPl($origVat) . '</td>';
                $html .= '<td class="right">' . self::fmtPl($origGross) . '</td>';
                $html .= '</tr>';
                $html .= '</tbody></table>';

                // "Stan po korekcie" label
                $html .= '<div style="margin-top:8px; padding:6px; background:#f0fdf4; border:0.5px solid #86efac;">';
                $html .= '<span class="sub" style="color:#166534;">STAN PO KOREKCIE</span>';
                $html .= '</div>';
                $html .= '<table width="100%" class="items" cellpadding="3" cellspacing="0">';
                $html .= '<thead><tr>';
                $html .= '<th width="5%" class="center">Lp.</th>';
                $html .= '<th width="31%">Nazwa towaru / usługi</th>';
                $html .= '<th width="8%" class="center">Ilość</th>';
                $html .= '<th width="7%" class="center">Jm.</th>';
                $html .= '<th width="12%" class="right">Cena netto</th>';
                $html .= '<th width="8%" class="center">VAT</th>';
                $html .= '<th width="12%" class="right">Netto</th>';
                $html .= '<th width="9%" class="right">VAT</th>';
                $html .= '<th width="12%" class="right">Brutto</th>';
                $html .= '</tr></thead><tbody>';
                foreach ($items as $i => $item) {
                    $html .= '<tr style="background-color:#f0fdf4;">';
                    $html .= '<td class="center">' . ($i + 1) . '</td>';
                    $html .= '<td>' . self::e($item['name'] ?? '') . '</td>';
                    $html .= '<td class="center">' . ($item['quantity'] ?? 1) . '</td>';
                    $html .= '<td class="center">' . self::e($item['unit'] ?? 'szt.') . '</td>';
                    $html .= '<td class="right">' . self::fmtPl((float)($item['unit_price'] ?? 0)) . '</td>';
                    $html .= '<td class="center">' . self::e($item['vat_rate'] ?? '23') . '%</td>';
                    $html .= '<td class="right">' . self::fmtPl((float)($item['net'] ?? 0)) . '</td>';
                    $html .= '<td class="right">' . self::fmtPl((float)($item['vat'] ?? 0)) . '</td>';
                    $html .= '<td class="right bold">' . self::fmtPl((float)($item['gross'] ?? 0)) . '</td>';
                    $html .= '</tr>';
                }
                $newNet = (float)$inv['net_amount'];
                $newVat = (float)$inv['vat_amount'];
                $newGross = (float)$inv['gross_amount'];
                $html .= '<tr style="background-color:#f0fdf4; font-weight:bold;">';
                $html .= '<td colspan="6" class="right">Razem po:</td>';
                $html .= '<td class="right">' . self::fmtPl($newNet) . '</td>';
                $html .= '<td class="right">' . self::fmtPl($newVat) . '</td>';
                $html .= '<td class="right">' . self::fmtPl($newGross) . '</td>';
                $html .= '</tr>';
                $html .= '</tbody></table>';

                // Difference row
                $diffNet = $newNet - $origNet;
                $diffVat = $newVat - $origVat;
                $diffGross = $newGross - $origGross;
                $diffColor = ($diffGross < 0) ? '#991b1b' : (($diffGross > 0) ? '#166534' : '#374151');
                $html .= '<table width="100%" class="items" cellpadding="3" cellspacing="0" style="margin-top:4px;">';
                $html .= '<tr style="font-weight:bold; background-color:#fef9c3; border:1px solid #fde68a;">';
                $html .= '<td width="63%" class="right" style="color:#92400e;">RÓŻNICA:</td>';
                $html .= '<td width="12%" class="right" style="color:' . $diffColor . ';">' . ($diffNet >= 0 ? '+' : '') . self::fmtPl($diffNet) . '</td>';
                $html .= '<td width="9%" class="right" style="color:' . $diffColor . ';">' . ($diffVat >= 0 ? '+' : '') . self::fmtPl($diffVat) . '</td>';
                $html .= '<td width="12%" class="right" style="color:' . $diffColor . ';">' . ($diffGross >= 0 ? '+' : '') . self::fmtPl($diffGross) . '</td>';
                $html .= '</tr></table>';
            }

            if (!empty($inv['correction_reason'])) {
                $html .= '<div style="margin-top:10px; padding:6px; background:#fef3c7; border:0.5px solid #eee;">';
                $html .= '<span class="sub">Powód korekty:</span><br>' . self::e($inv['correction_reason']);
                $html .= '</div>';
            }
        }

        // Signatures
        $html .= '<br><br><table width="100%" cellpadding="0"><tr>';
        $html .= '<td width="40%" class="center" style="border-top:0.5px solid #999; padding-top:4px;">';
        $html .= '<span class="sub">Podpis osoby upoważnionej<br>do wystawienia faktury</span></td>';
        $html .= '<td width="20%">&nbsp;</td>';
        $html .= '<td width="40%" class="center" style="border-top:0.5px solid #999; padding-top:4px;">';
        $html .= '<span class="sub">Podpis osoby upoważnionej<br>do odbioru faktury</span></td>';
        $html .= '</tr></table>';

        return $html;
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private static function fmtPl(float $amount): string
    {
        return number_format(round($amount, 2), 2, ',', ' ');
    }

    /**
     * Generate a multi-page PDF with one sales invoice per page (vertical layout).
     */
    public static function generateBulk(array $invoiceIds): string
    {
        if (empty($invoiceIds)) {
            throw new \RuntimeException('No invoice IDs provided');
        }

        self::ensureDir();

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('BiLLU');
        $pdf->SetTitle('Faktury sprzedażowe - wydruk zbiorczy');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 9);

        foreach ($invoiceIds as $id) {
            $invoice = IssuedInvoice::findById((int) $id);
            if (!$invoice) continue;

            $lineItems = $invoice['line_items'] ?? '[]';
            if (is_string($lineItems)) $lineItems = json_decode($lineItems, true) ?: [];

            $vatDetails = $invoice['vat_details'] ?? '[]';
            if (is_string($vatDetails)) $vatDetails = json_decode($vatDetails, true) ?: [];

            $contractor = null;
            if (!empty($invoice['contractor_id'])) {
                $contractor = Contractor::findById((int) $invoice['contractor_id']);
            }

            $pdf->AddPage();
            $html = self::buildHtml($invoice, $lineItems, $vatDetails, $contractor);
            $pdf->writeHTML($html, true, false, true, false, '');

            // QR codes: verification data + digital signature + KSeF reference
            self::renderQrCodes($pdf, $invoice);
        }

        $filename = 'sales_bulk_' . time() . '.pdf';
        $path = self::$storageDir . '/' . $filename;
        $pdf->Output($path, 'F');

        return $path;
    }

    /**
     * Generate a landscape summary table of sales invoices.
     */
    public static function generateSummaryTable(array $invoiceIds): string
    {
        if (empty($invoiceIds)) {
            throw new \RuntimeException('No invoice IDs provided');
        }

        self::ensureDir();

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('BiLLU');
        $pdf->SetTitle('Zestawienie faktur sprzedażowych');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->AddPage();

        $invoices = [];
        foreach ($invoiceIds as $id) {
            $inv = IssuedInvoice::findById((int) $id);
            if ($inv) $invoices[] = $inv;
        }

        if (empty($invoices)) {
            throw new \RuntimeException('No valid invoices found');
        }

        $dates = array_filter(array_column($invoices, 'issue_date'));
        sort($dates);
        $dateFrom = $dates[0] ?? '';
        $dateTo = end($dates) ?: '';
        $dateRange = $dateFrom === $dateTo ? $dateFrom : $dateFrom . ' - ' . $dateTo;

        $blue = '#2563EB';

        $html = '<div style="text-align:center; margin-bottom:10px;">';
        $html .= '<span style="font-size:14pt; font-weight:bold; color:' . $blue . ';">ZESTAWIENIE FAKTUR SPRZEDAŻOWYCH</span><br>';
        $html .= '<span style="font-size:9pt; color:#6B7280;">Okres: ' . self::e($dateRange) . '</span>';
        $html .= '</div>';

        $html .= '<table width="100%" cellpadding="4" cellspacing="0" border="0">';
        $html .= '<thead><tr style="background-color:' . $blue . '; color:#FFFFFF; font-size:8pt; font-weight:bold;">';
        $html .= '<th width="4%" align="center">Lp</th>';
        $html .= '<th width="15%">Nr FV</th>';
        $html .= '<th width="9%" align="center">Data wyst.</th>';
        $html .= '<th width="22%">Nabywca</th>';
        $html .= '<th width="10%">NIP</th>';
        $html .= '<th width="10%" align="right">Netto</th>';
        $html .= '<th width="8%" align="right">VAT</th>';
        $html .= '<th width="10%" align="right">Brutto</th>';
        $html .= '<th width="6%">Waluta</th>';
        $html .= '<th width="6%" align="center">Status</th>';
        $html .= '</tr></thead><tbody>';

        $totalNet = 0; $totalVat = 0; $totalGross = 0;

        foreach ($invoices as $i => $inv) {
            $net = (float) ($inv['net_amount'] ?? 0);
            $vat = (float) ($inv['vat_amount'] ?? 0);
            $gross = (float) ($inv['gross_amount'] ?? 0);
            $totalNet += $net;
            $totalVat += $vat;
            $totalGross += $gross;

            $bgColor = ($i % 2 === 0) ? '#FFFFFF' : '#F3F4F6';
            $statusLabel = match($inv['status'] ?? '') {
                'draft' => 'Szkic',
                'issued' => 'Wystawiona',
                'sent_ksef' => 'KSeF',
                'cancelled' => 'Anulowana',
                default => ucfirst($inv['status'] ?? ''),
            };

            $html .= '<tr style="background-color:' . $bgColor . '; font-size:8pt;">';
            $html .= '<td align="center">' . ($i + 1) . '</td>';
            $html .= '<td>' . self::e($inv['invoice_number'] ?? '') . '</td>';
            $html .= '<td align="center">' . self::e($inv['issue_date'] ?? '') . '</td>';
            $html .= '<td>' . self::e(mb_substr($inv['buyer_name'] ?? '', 0, 45)) . '</td>';
            $html .= '<td>' . self::e($inv['buyer_nip'] ?? '') . '</td>';
            $html .= '<td align="right">' . self::fmtPl($net) . '</td>';
            $html .= '<td align="right">' . self::fmtPl($vat) . '</td>';
            $html .= '<td align="right">' . self::fmtPl($gross) . '</td>';
            $html .= '<td align="center">' . self::e($inv['currency'] ?? 'PLN') . '</td>';
            $html .= '<td align="center">' . $statusLabel . '</td>';
            $html .= '</tr>';
        }

        $html .= '<tr style="background-color:' . $blue . '; color:#FFFFFF; font-size:8pt; font-weight:bold;">';
        $html .= '<td colspan="5" align="right">RAZEM:</td>';
        $html .= '<td align="right">' . self::fmtPl($totalNet) . '</td>';
        $html .= '<td align="right">' . self::fmtPl($totalVat) . '</td>';
        $html .= '<td align="right">' . self::fmtPl($totalGross) . '</td>';
        $html .= '<td colspan="2"></td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';

        $html .= '<div style="margin-top:8px; font-size:7pt; color:#9CA3AF; text-align:right;">';
        $html .= 'Wygenerowano: ' . date('Y-m-d H:i') . ' | BiLLU Financial Solutions';
        $html .= '</div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'sales_summary_' . time() . '.pdf';
        $path = self::$storageDir . '/' . $filename;
        $pdf->Output($path, 'F');

        return $path;
    }

    /**
     * Build verification QR data string (invoice data for QR #1).
     */
    private static function buildVerificationQrData(array $invoice): string
    {
        $nip = preg_replace('/[^0-9]/', '', $invoice['seller_nip'] ?? '');
        $date = str_replace('-', '', $invoice['issue_date'] ?? '');
        $number = $invoice['invoice_number'] ?? '';
        $gross = number_format((float) ($invoice['gross_amount'] ?? 0), 2, '.', '');
        $vat = number_format((float) ($invoice['vat_amount'] ?? 0), 2, '.', '');
        $hashInput = $nip . $date . $number . $gross . $vat;
        $hash = substr(hash('sha256', $hashInput), 0, 16);
        return implode('|', [$nip, $date, $number, $gross, $vat, $hash]);
    }

    /**
     * Build digital signature QR data (QR #2) using client's KSeF certificate.
     * Signs the verification data with the client's private key.
     *
     * @return string|null Signature QR data or null if no certificate available
     */
    private static function buildSignatureQrData(string $verificationData, int $clientId): ?string
    {
        try {
            $config = KsefConfig::findByClientId($clientId);
        } catch (\Exception $e) {
            return null;
        }

        if (!$config || empty($config['cert_ksef_private_key_encrypted'])) {
            return null;
        }

        try {
            $privateKeyPem = KsefCertificateService::decrypt($config['cert_ksef_private_key_encrypted']);
            $privateKey = openssl_pkey_get_private($privateKeyPem);
            if (!$privateKey) return null;

            $signature = '';
            if (!openssl_sign($verificationData, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $fingerprint = substr(
                $config['cert_fingerprint'] ?? hash('sha256', $config['cert_ksef_pem'] ?? ''),
                0, 8
            );

            // Clear private key from memory
            $privateKeyPem = str_repeat("\0", strlen($privateKeyPem));

            return 'SIG|' . $fingerprint . '|' . base64_encode($signature);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Render QR codes section on PDF (up to 3 QR codes).
     * QR #1: Invoice verification data (always)
     * QR #2: Digital signature (when KSeF cert available)
     * QR #3: KSeF reference link (after successful KSeF submission)
     */
    private static function renderQrCodes(\TCPDF $pdf, array $invoice): void
    {
        $clientId = (int) ($invoice['client_id'] ?? 0);
        $ksefRef = $invoice['ksef_reference_number'] ?? '';

        // QR #1: Invoice verification data (always generated)
        $qrVerifyData = self::buildVerificationQrData($invoice);
        $pdf->Ln(4);

        $y = $pdf->GetY() + 2;
        $qrSize = 25;
        $xPos1 = 15;

        $pdf->write2DBarcode($qrVerifyData, 'QRCODE,H', $xPos1, $y, $qrSize, $qrSize, ['border' => false]);
        $pdf->SetXY($xPos1 + $qrSize + 2, $y + 1);
        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->Cell(50, 3, 'Kod weryfikacyjny faktury', 0, 1, 'L');
        $pdf->SetXY($xPos1 + $qrSize + 2, $y + 5);
        $pdf->SetFont('dejavusans', '', 5.5);
        $pdf->MultiCell(50, 2.5, $qrVerifyData, 0, 'L');

        // QR #2: Digital signature (when KSeF cert available)
        $signatureData = $clientId > 0 ? self::buildSignatureQrData($qrVerifyData, $clientId) : null;
        $xPos2 = 90;

        if ($signatureData) {
            $pdf->write2DBarcode($signatureData, 'QRCODE,L', $xPos2, $y, $qrSize, $qrSize, ['border' => false]);
            $pdf->SetXY($xPos2 + $qrSize + 2, $y + 1);
            $pdf->SetFont('dejavusans', 'B', 7);
            $pdf->Cell(30, 3, 'Podpis cyfrowy', 0, 1, 'L');
            $pdf->SetXY($xPos2 + $qrSize + 2, $y + 5);
            $pdf->SetFont('dejavusans', '', 5.5);
            $pdf->MultiCell(30, 2.5, substr($signatureData, 0, 20) . '...', 0, 'L');
        } else {
            $pdf->SetXY($xPos2, $y + 6);
            $pdf->SetFont('dejavusans', '', 6);
            $pdf->Cell(55, 3, 'Podpis cyfrowy niedostępny', 0, 1, 'L');
            $pdf->SetXY($xPos2, $y + 10);
            $pdf->Cell(55, 3, '(brak certyfikatu KSeF)', 0, 1, 'L');
        }

        // QR #3: KSeF reference (after successful submission)
        if (!empty($ksefRef)) {
            $qrUrl = 'https://ksef.mf.gov.pl/web/verify/' . $ksefRef;
            $xPos3 = 148;
            $pdf->write2DBarcode($qrUrl, 'QRCODE,H', $xPos3, $y, $qrSize, $qrSize, ['border' => false]);
            $pdf->SetXY($xPos3 + $qrSize + 2, $y + 1);
            $pdf->SetFont('dejavusans', 'B', 7);
            $pdf->Cell(30, 3, 'KSeF', 0, 1, 'L');
            $pdf->SetXY($xPos3 + $qrSize + 2, $y + 5);
            $pdf->SetFont('dejavusans', '', 5.5);
            $pdf->MultiCell(30, 2.5, $ksefRef, 0, 'L');
        }

        // KSeF reference number text below QR codes
        if (!empty($ksefRef)) {
            $pdf->SetXY(15, $y + $qrSize + 2);
            $pdf->SetFont('dejavusans', '', 7);
            $pdf->Cell(0, 4, 'Numer KSeF: ' . $ksefRef, 0, 1, 'L');
        } elseif (empty($ksefRef)) {
            $pdf->SetXY(15, $y + $qrSize + 2);
            $pdf->SetFont('dejavusans', '', 7);
            $pdf->SetTextColor(180, 80, 0);
            $pdf->Cell(0, 4, 'Faktura wystawiona offline — oczekuje na wysyłkę do KSeF', 0, 1, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->SetFont('dejavusans', '', 9);
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0750, true);
        }
    }
}
