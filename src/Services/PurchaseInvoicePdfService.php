<?php

namespace App\Services;

use App\Models\Invoice;
use TCPDF;

class PurchaseInvoicePdfService
{
    private static string $storageDir = __DIR__ . '/../../storage/exports';

    private static string $colorBlue = '#2563EB';
    private static string $colorGrayBg = '#F3F4F6';
    private static string $colorGrayBorder = '#D1D5DB';

    /**
     * Generate a single purchase invoice PDF.
     */
    public static function generate(int $invoiceId): string
    {
        $invoice = Invoice::findById($invoiceId);
        if (!$invoice) {
            throw new \RuntimeException("Purchase invoice #{$invoiceId} not found");
        }

        self::ensureDir();

        $pdf = self::createPdf('P');
        $pdf->SetAuthor($invoice['buyer_name'] ?? 'BiLLU');
        $pdf->SetTitle($invoice['invoice_number'] ?? 'Faktura zakupowa');
        $pdf->AddPage();

        self::renderInvoicePage($pdf, $invoice);

        $filename = 'purchase_inv_' . $invoiceId . '_' . time() . '.pdf';
        $path = self::$storageDir . '/' . $filename;
        $pdf->Output($path, 'F');

        return $path;
    }

    /**
     * Generate a multi-page PDF with one invoice per page.
     */
    public static function generateBulk(array $invoiceIds): string
    {
        if (empty($invoiceIds)) {
            throw new \RuntimeException('No invoice IDs provided');
        }

        self::ensureDir();

        $pdf = self::createPdf('P');
        $pdf->SetTitle('Faktury zakupowe - wydruk zbiorczy');

        foreach ($invoiceIds as $id) {
            $invoice = Invoice::findById((int) $id);
            if (!$invoice) {
                continue;
            }
            $pdf->AddPage();
            self::renderInvoicePage($pdf, $invoice);
        }

        $filename = 'purchase_bulk_' . time() . '.pdf';
        $path = self::$storageDir . '/' . $filename;
        $pdf->Output($path, 'F');

        return $path;
    }

    /**
     * Generate a landscape summary table of purchase invoices.
     */
    public static function generateSummaryTable(array $invoiceIds): string
    {
        if (empty($invoiceIds)) {
            throw new \RuntimeException('No invoice IDs provided');
        }

        self::ensureDir();

        $pdf = self::createPdf('L');
        $pdf->SetTitle('Zestawienie faktur zakupowych');
        $pdf->AddPage();

        $invoices = [];
        foreach ($invoiceIds as $id) {
            $inv = Invoice::findById((int) $id);
            if ($inv) {
                $invoices[] = $inv;
            }
        }

        if (empty($invoices)) {
            throw new \RuntimeException('No valid invoices found');
        }

        // Determine date range
        $dates = array_filter(array_column($invoices, 'issue_date'));
        sort($dates);
        $dateFrom = $dates[0] ?? '';
        $dateTo = end($dates) ?: '';
        $dateRange = $dateFrom === $dateTo
            ? $dateFrom
            : $dateFrom . ' - ' . $dateTo;

        // Title
        $html = '<div style="text-align:center; margin-bottom:10px;">';
        $html .= '<span style="font-size:14pt; font-weight:bold; color:' . self::$colorBlue . ';">ZESTAWIENIE FAKTUR ZAKUPOWYCH</span><br>';
        $html .= '<span style="font-size:9pt; color:#6B7280;">Okres: ' . self::e($dateRange) . '</span>';
        $html .= '</div>';

        // Table
        $html .= '<table width="100%" cellpadding="4" cellspacing="0" border="0">';
        $html .= '<thead><tr style="background-color:' . self::$colorBlue . '; color:#FFFFFF; font-size:8pt; font-weight:bold;">';
        $html .= '<th width="4%" align="center">Lp</th>';
        $html .= '<th width="14%">Nr FV</th>';
        $html .= '<th width="9%" align="center">Data wyst.</th>';
        $html .= '<th width="19%">Sprzedawca</th>';
        $html .= '<th width="10%">NIP</th>';
        $html .= '<th width="10%" align="right">Netto</th>';
        $html .= '<th width="8%" align="right">VAT</th>';
        $html .= '<th width="10%" align="right">Brutto</th>';
        $html .= '<th width="8%" align="center">Status</th>';
        $html .= '<th width="8%">MPK</th>';
        $html .= '</tr></thead><tbody>';

        $totalNet = 0;
        $totalVat = 0;
        $totalGross = 0;

        foreach ($invoices as $i => $inv) {
            $net = (float) ($inv['net_amount'] ?? 0);
            $vat = (float) ($inv['vat_amount'] ?? 0);
            $gross = (float) ($inv['gross_amount'] ?? 0);
            $totalNet += $net;
            $totalVat += $vat;
            $totalGross += $gross;

            $bgColor = ($i % 2 === 0) ? '#FFFFFF' : self::$colorGrayBg;
            $statusLabel = self::statusLabel($inv['status'] ?? 'pending');

            $html .= '<tr style="background-color:' . $bgColor . '; font-size:8pt;">';
            $html .= '<td align="center">' . ($i + 1) . '</td>';
            $html .= '<td>' . self::e($inv['invoice_number'] ?? '') . '</td>';
            $html .= '<td align="center">' . self::e($inv['issue_date'] ?? '') . '</td>';
            $html .= '<td>' . self::e(mb_substr($inv['seller_name'] ?? '', 0, 40)) . '</td>';
            $html .= '<td>' . self::e($inv['seller_nip'] ?? '') . '</td>';
            $html .= '<td align="right">' . self::fmtPl($net) . '</td>';
            $html .= '<td align="right">' . self::fmtPl($vat) . '</td>';
            $html .= '<td align="right">' . self::fmtPl($gross) . '</td>';
            $html .= '<td align="center">' . $statusLabel . '</td>';
            $html .= '<td>' . self::e($inv['cost_center'] ?? '-') . '</td>';
            $html .= '</tr>';
        }

        // Totals row
        $html .= '<tr style="background-color:' . self::$colorBlue . '; color:#FFFFFF; font-size:8pt; font-weight:bold;">';
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

        $filename = 'purchase_summary_' . time() . '.pdf';
        $path = self::$storageDir . '/' . $filename;
        $pdf->Output($path, 'F');

        return $path;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function createPdf(string $orientation): TCPDF
    {
        $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('BiLLU');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 10);

        return $pdf;
    }

    private static function renderInvoicePage(TCPDF $pdf, array $inv): void
    {
        $lineItems = self::decodeJson($inv['line_items'] ?? null);
        $vatDetails = self::decodeJson($inv['vat_details'] ?? null);

        // If line_items or vatDetails are empty, try parsing ksef_xml
        $parsedXml = [];
        if (!empty($inv['ksef_xml'])) {
            $parsedXml = KsefApiService::parseKsefFaXml($inv['ksef_xml']);
        }

        if (empty($lineItems) && !empty($parsedXml['line_items'])) {
            $lineItems = $parsedXml['line_items'];
        }
        if (empty($vatDetails) && !empty($parsedXml['vat_rates'])) {
            $vatDetails = $parsedXml['vat_rates'];
        }

        $notes = $parsedXml['notes'] ?? '';
        $rawAnnotations = $parsedXml['annotations'] ?? '';
        if (is_array($rawAnnotations)) {
            $labels = [];
            if (!empty($rawAnnotations['self_invoicing'])) $labels[] = 'Samofakturowanie';
            if (!empty($rawAnnotations['reverse_charge'])) $labels[] = 'Odwrotne obciążenie';
            if (!empty($rawAnnotations['split_payment'])) $labels[] = 'Mechanizm podzielonej płatności';
            if (!empty($rawAnnotations['margin'])) $labels[] = 'Procedura marży';
            $annotations = implode("\n", $labels);
        } else {
            $annotations = (string) $rawAnnotations;
        }

        $html = self::buildInvoiceHtml($inv, $lineItems, $vatDetails, $notes, $annotations);
        $pdf->writeHTML($html, true, false, true, false, '');
    }

    private static function buildInvoiceHtml(
        array $inv,
        array $lineItems,
        array $vatDetails,
        string $notes,
        string $annotations
    ): string {
        $blue = self::$colorBlue;
        $grayBg = self::$colorGrayBg;
        $grayBorder = self::$colorGrayBorder;
        $currency = self::e($inv['currency'] ?? 'PLN');

        $html = '<style>
            * { font-family: dejavusans; }
            body { font-size: 9pt; color: #1F2937; }
            .sub { font-size: 7pt; color: #6B7280; }
            table { border-collapse: collapse; }
            .items th { background-color: ' . $blue . '; color: #FFFFFF; font-size: 7.5pt; padding: 4px; border: 0.3px solid ' . $grayBorder . '; }
            .items td { padding: 4px; border: 0.3px solid ' . $grayBorder . '; font-size: 8pt; }
            .right { text-align: right; }
            .center { text-align: center; }
            .bold { font-weight: bold; }
        </style>';

        // ---- Header ----
        $html .= '<table width="100%" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td width="60%">';
        $html .= '<span style="font-size:16pt; font-weight:bold; color:' . $blue . ';">FAKTURA ZAKUPOWA</span><br>';
        $html .= '<span style="font-size:11pt; font-weight:bold;">' . self::e($inv['invoice_number'] ?? '') . '</span>';
        $html .= '</td>';
        $html .= '<td width="40%" style="text-align:right;">';

        // KSeF reference
        if (!empty($inv['ksef_reference_number'])) {
            $html .= '<span class="sub">KSeF:</span> <strong style="font-size:8pt;">' . self::e($inv['ksef_reference_number']) . '</strong><br>';
        }

        $html .= '<span class="sub">Data wystawienia:</span> <strong>' . self::e($inv['issue_date'] ?? '') . '</strong><br>';
        if (!empty($inv['sale_date']) && ($inv['sale_date'] ?? '') !== ($inv['issue_date'] ?? '')) {
            $html .= '<span class="sub">Data sprzedaży:</span> <strong>' . self::e($inv['sale_date']) . '</strong><br>';
        }
        if (!empty($inv['payment_due_date'])) {
            $html .= '<span class="sub">Termin płatności:</span> <strong>' . self::e($inv['payment_due_date']) . '</strong>';
        }
        $html .= '</td></tr></table>';

        $html .= '<hr style="border: 0.5px solid ' . $grayBorder . '; margin: 6px 0 8px 0;">';

        // ---- Seller / Buyer ----
        $html .= '<table width="100%" cellpadding="6" cellspacing="0"><tr>';

        // Seller
        $html .= '<td width="48%" style="background-color:' . $grayBg . '; border:0.5px solid ' . $grayBorder . ';">';
        $html .= '<span class="sub">SPRZEDAWCA</span><br>';
        $html .= '<strong>' . self::e($inv['seller_name'] ?? '') . '</strong><br>';
        if (!empty($inv['seller_nip'])) {
            $html .= 'NIP: ' . self::e($inv['seller_nip']) . '<br>';
        }
        if (!empty($inv['seller_address'])) {
            $html .= self::e($inv['seller_address']);
        }
        $html .= '</td>';

        $html .= '<td width="4%">&nbsp;</td>';

        // Buyer
        $html .= '<td width="48%" style="background-color:' . $grayBg . '; border:0.5px solid ' . $grayBorder . ';">';
        $html .= '<span class="sub">NABYWCA</span><br>';
        $html .= '<strong>' . self::e($inv['buyer_name'] ?? '') . '</strong><br>';
        if (!empty($inv['buyer_nip'])) {
            $html .= 'NIP: ' . self::e($inv['buyer_nip']) . '<br>';
        }
        if (!empty($inv['buyer_address'])) {
            $html .= self::e($inv['buyer_address']);
        }
        $html .= '</td>';
        $html .= '</tr></table>';

        $html .= '<br>';

        // ---- Line items table ----
        if (!empty($lineItems)) {
            $html .= '<table width="100%" class="items" cellpadding="3" cellspacing="0">';
            $html .= '<thead><tr>';
            $html .= '<th width="5%" class="center">Lp.</th>';
            $html .= '<th width="29%">Nazwa towaru / usługi</th>';
            $html .= '<th width="8%" class="center">Ilość</th>';
            $html .= '<th width="7%" class="center">Jm.</th>';
            $html .= '<th width="11%" class="right">Cena netto</th>';
            $html .= '<th width="8%" class="center">VAT</th>';
            $html .= '<th width="11%" class="right">Netto</th>';
            $html .= '<th width="9%" class="right">VAT</th>';
            $html .= '<th width="12%" class="right">Brutto</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($lineItems as $i => $item) {
                $bgColor = ($i % 2 === 0) ? '#FFFFFF' : $grayBg;
                $html .= '<tr style="background-color:' . $bgColor . ';">';
                $html .= '<td class="center">' . ($i + 1) . '</td>';
                $html .= '<td>' . self::e($item['name'] ?? $item['description'] ?? '') . '</td>';
                $html .= '<td class="center">' . ($item['quantity'] ?? 1) . '</td>';
                $html .= '<td class="center">' . self::e($item['unit'] ?? 'szt.') . '</td>';
                $html .= '<td class="right">' . self::fmtPl((float) ($item['unit_price'] ?? $item['price_net'] ?? 0)) . '</td>';
                $html .= '<td class="center">' . self::e($item['vat_rate'] ?? $item['rate'] ?? '23') . '%</td>';
                $html .= '<td class="right">' . self::fmtPl((float) ($item['net'] ?? $item['net_amount'] ?? 0)) . '</td>';
                $html .= '<td class="right">' . self::fmtPl((float) ($item['vat'] ?? $item['vat_amount'] ?? 0)) . '</td>';
                $html .= '<td class="right bold">' . self::fmtPl((float) ($item['gross'] ?? $item['gross_amount'] ?? 0)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        // ---- VAT summary + totals ----
        $html .= '<table width="100%" cellpadding="2" cellspacing="0" style="margin-top:8px;"><tr>';
        $html .= '<td width="55%">&nbsp;</td>';
        $html .= '<td width="45%">';
        $html .= '<table width="100%" class="items" cellpadding="3" cellspacing="0">';
        $html .= '<tr><th>Stawka</th><th class="right">Netto</th><th class="right">VAT</th><th class="right">Brutto</th></tr>';

        if (!empty($vatDetails)) {
            foreach ($vatDetails as $vd) {
                $net = (float) ($vd['net'] ?? $vd['net_amount'] ?? 0);
                $vat = (float) ($vd['vat'] ?? $vd['vat_amount'] ?? 0);
                $gross = $net + $vat;
                $rate = $vd['rate'] ?? $vd['vat_rate'] ?? '';
                $html .= '<tr>';
                $html .= '<td class="center">' . self::e((string) $rate) . '%</td>';
                $html .= '<td class="right">' . self::fmtPl($net) . '</td>';
                $html .= '<td class="right">' . self::fmtPl($vat) . '</td>';
                $html .= '<td class="right">' . self::fmtPl($gross) . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '<tr style="font-weight:bold; border-top:1.5px solid #374151;">';
        $html .= '<td>RAZEM</td>';
        $html .= '<td class="right">' . self::fmtPl((float) ($inv['net_amount'] ?? 0)) . '</td>';
        $html .= '<td class="right">' . self::fmtPl((float) ($inv['vat_amount'] ?? 0)) . '</td>';
        $html .= '<td class="right">' . self::fmtPl((float) ($inv['gross_amount'] ?? 0)) . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td></tr></table>';

        // ---- "Do zaplaty" highlighted ----
        $html .= '<div style="text-align:right; margin:10px 0; padding:8px; background-color:' . $blue . '; color:#FFFFFF; font-size:12pt; font-weight:bold;">';
        $html .= 'Do zapłaty: ' . self::fmtPl((float) ($inv['gross_amount'] ?? 0)) . ' ' . $currency;
        $html .= '</div>';

        // ---- Payment info ----
        $paymentMethod = $inv['payment_method_detected'] ?? '';
        if (!empty($paymentMethod) || !empty($inv['payment_due_date'])) {
            $html .= '<table width="100%" cellpadding="6" cellspacing="0" style="background-color:' . $grayBg . '; border:0.5px solid ' . $grayBorder . ';"><tr>';
            if (!empty($paymentMethod)) {
                $html .= '<td width="50%"><span class="sub">Forma płatności:</span> <strong>' . self::e(self::paymentMethodLabel($paymentMethod)) . '</strong></td>';
            }
            if (!empty($inv['payment_due_date'])) {
                $html .= '<td width="50%"><span class="sub">Termin płatności:</span> <strong>' . self::e($inv['payment_due_date']) . '</strong></td>';
            }
            $html .= '</tr></table>';
        }

        // ---- Status badge ----
        $status = $inv['status'] ?? 'pending';
        $statusColors = [
            'accepted' => ['bg' => '#DEF7EC', 'text' => '#03543F'],
            'rejected' => ['bg' => '#FDE8E8', 'text' => '#9B1C1C'],
            'pending'  => ['bg' => '#FEF3C7', 'text' => '#92400E'],
        ];
        $sc = $statusColors[$status] ?? $statusColors['pending'];
        $statusText = self::statusLabel($status);

        $html .= '<table width="100%" cellpadding="4" cellspacing="0" style="margin-top:8px;"><tr>';
        $html .= '<td width="50%">';
        $html .= '<span class="sub">Status:</span> ';
        $html .= '<span style="background-color:' . $sc['bg'] . '; color:' . $sc['text'] . '; padding:2px 8px; font-weight:bold; font-size:9pt;">' . $statusText . '</span>';
        $html .= '</td>';

        // ---- Cost center (MPK) ----
        $html .= '<td width="50%">';
        if (!empty($inv['cost_center'])) {
            $html .= '<span class="sub">MPK:</span> <strong>' . self::e($inv['cost_center']) . '</strong>';
        }
        $html .= '</td>';
        $html .= '</tr></table>';

        // ---- Comment ----
        if (!empty($inv['comment'])) {
            $html .= '<div style="margin-top:8px; padding:6px; background-color:#FEF3C7; border:0.5px solid #FDE68A;">';
            $html .= '<span class="sub">Komentarz:</span><br>' . nl2br(self::e($inv['comment']));
            $html .= '</div>';
        }

        // ---- Notes from XML ----
        if (!empty($notes)) {
            $html .= '<div style="margin-top:8px; padding:6px; background-color:#EFF6FF; border:0.5px solid #BFDBFE;">';
            $html .= '<span class="sub">Uwagi z faktury:</span><br>';
            if (is_array($notes)) {
                $html .= nl2br(self::e(implode("\n", $notes)));
            } else {
                $html .= nl2br(self::e((string) $notes));
            }
            $html .= '</div>';
        }

        if (!empty($annotations)) {
            $html .= '<div style="margin-top:6px; padding:6px; background-color:#EFF6FF; border:0.5px solid #BFDBFE;">';
            $html .= '<span class="sub">Adnotacje:</span><br>';
            if (is_array($annotations)) {
                $html .= nl2br(self::e(implode("\n", $annotations)));
            } else {
                $html .= nl2br(self::e((string) $annotations));
            }
            $html .= '</div>';
        }

        // Footer
        $html .= '<div style="margin-top:12px; font-size:7pt; color:#9CA3AF; text-align:right;">';
        $html .= 'Wygenerowano: ' . date('Y-m-d H:i') . ' | BiLLU Financial Solutions';
        $html .= '</div>';

        return $html;
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'accepted' => 'Zaakceptowana',
            'rejected' => 'Odrzucona',
            'pending'  => 'Oczekuje',
            default    => ucfirst($status),
        };
    }

    private static function paymentMethodLabel(string $method): string
    {
        $labels = [
            'przelew'    => 'Przelew bankowy',
            'transfer'   => 'Przelew bankowy',
            'gotowka'    => 'Gotówka',
            'cash'       => 'Gotówka',
            'karta'      => 'Karta płatnicza',
            'card'       => 'Karta płatnicza',
            'kompensata' => 'Kompensata',
            'barter'     => 'Barter',
        ];

        return $labels[strtolower($method)] ?? $method;
    }

    private static function decodeJson($value): array
    {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            return json_decode($value, true) ?: [];
        }
        return [];
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private static function fmtPl(float $amount): string
    {
        return number_format(round($amount, 2), 2, ',', ' ');
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0750, true);
        }
    }
}
