<?php

namespace App\Services;

class TaxCalculatorPdfService
{
    public static function generate(array $results): string
    {
        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('BiLLU');
        $pdf->SetTitle('Kalkulator podatkowy — porównanie form opodatkowania');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->AddPage();

        $html = self::buildHtml($results);
        $pdf->writeHTML($html, true, false, true, false, '');

        $dir = __DIR__ . '/../../storage/exports';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $filename = basename('kalkulator_podatkowy_' . date('Ymd_His') . '.pdf');
        $path = $dir . '/' . $filename;
        $pdf->Output($path, 'F');

        return $path;
    }

    private static function buildHtml(array $r): string
    {
        $fmt = fn($v) => htmlspecialchars(number_format((float)$v, 2, ',', ' '), ENT_QUOTES);
        $input = $r['input'];
        $zus = $r['zus_social'];
        $best = $r['best'];

        $labels = ['ryczalt' => 'Ryczałt', 'liniowy' => 'Podatek liniowy', 'skala' => 'Skala podatkowa', 'ip_box' => 'IP Box'];
        $keys = ['ryczalt', 'liniowy', 'skala', 'ip_box'];

        $revenueLabel = $input['isGross'] ? 'brutto (z VAT)' : 'netto (bez VAT)';

        $html = '<h2 style="color:#1e40af; margin-bottom:4px;">Kalkulator opłacalności form opodatkowania</h2>';
        $html .= '<p style="font-size:9px; color:#6b7280;">BiLLU Financial Solutions — wygenerowano ' . date('d.m.Y H:i') . '</p>';

        // Input summary
        $html .= '<table cellpadding="3" style="font-size:9px; margin-bottom:8px;">';
        $html .= '<tr><td><b>Przychód roczny:</b> ' . $fmt($input['annualRevenue']) . ' PLN (' . $revenueLabel . ')</td>';
        $html .= '<td><b>Przychód netto:</b> ' . $fmt($input['netRevenue']) . ' PLN</td>';
        $html .= '<td><b>Koszty:</b> ' . $fmt($input['costs']) . ' PLN</td>';
        $html .= '<td><b>Stawka ryczałtu:</b> ' . round($input['ryczaltRate'] * 100, 1) . '%</td></tr>';
        $html .= '</table>';

        // ZUS info
        $html .= '<table cellpadding="3" style="font-size:9px; margin-bottom:10px; background-color:#f9fafb;">';
        $html .= '<tr><td><b>ZUS społeczny roczny:</b> ' . $fmt($zus['total_annual']) . ' PLN';
        $html .= ' (baza: ' . $fmt($zus['base_monthly']) . ' PLN/mies.)</td></tr>';
        $html .= '</table>';

        // Comparison table
        $html .= '<table border="1" cellpadding="4" style="font-size:9px; border-color:#d1d5db;">';
        $html .= '<thead><tr style="background-color:#1e40af; color:white;">';
        $html .= '<th width="28%">Pozycja</th>';
        foreach ($keys as $k) {
            $isBest = ($k === $best);
            $html .= '<th width="18%" style="' . ($isBest ? 'background-color:#16a34a;' : '') . '">' . $labels[$k] . ($isBest ? ' ★' : '') . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $rows = [
            ['Przychód netto', 'revenue'],
            ['Koszty', 'costs'],
            ['Dochód (po ZUS społ.)', 'income'],
            ['Stawka podatku', 'tax_rate_label'],
            ['Kwota wolna', 'free_amount'],
            ['Podatek dochodowy', 'tax'],
            ['ZUS społeczny', 'zus_social'],
            ['Składka zdrowotna', 'health_insurance'],
            ['SUMA OBCIĄŻEŃ', 'total_burden'],
            ['Efektywna stawka', 'effective_rate'],
            ['DO WYPŁATY (rocznie)', 'net_income'],
        ];

        foreach ($rows as $i => $row) {
            $label = $row[0];
            $field = $row[1];
            $isBold = in_array($field, ['total_burden', 'net_income']);
            $bg = $i % 2 === 0 ? '#ffffff' : '#f9fafb';
            if ($field === 'net_income') $bg = '#f0fdf4';
            if ($field === 'total_burden') $bg = '#fef2f2';

            $html .= '<tr style="background-color:' . $bg . ';">';
            $html .= '<td' . ($isBold ? ' style="font-weight:bold;"' : '') . '>' . $label . '</td>';

            foreach ($keys as $k) {
                $d = $r[$k];
                $val = $d[$field] ?? '';
                if ($field === 'tax_rate_label') {
                    $display = $val;
                } elseif ($field === 'effective_rate') {
                    $display = number_format((float)$val, 1, ',', '') . '%';
                } elseif ($field === 'free_amount' && (float)$val === 0.0) {
                    $display = '—';
                } elseif ($field === 'costs' && (float)$val === 0.0 && $k === 'ryczalt') {
                    $display = 'n/d';
                } else {
                    $display = $fmt($val) . ' PLN';
                }

                $style = $isBold ? 'font-weight:bold;' : '';
                if ($field === 'net_income' && $k === $best) $style .= 'color:#16a34a;';
                if ($field === 'total_burden') $style .= 'color:#dc2626;';

                $html .= '<td style="text-align:right; ' . $style . '">' . $display . '</td>';
            }
            $html .= '</tr>';
        }

        // Monthly row
        $html .= '<tr style="background-color:#f0fdf4;">';
        $html .= '<td style="font-weight:bold;">DO WYPŁATY (miesięcznie)</td>';
        foreach ($keys as $k) {
            $monthly = round($r[$k]['net_income'] / 12, 2);
            $style = $k === $best ? 'color:#16a34a; font-weight:bold;' : 'font-weight:bold;';
            $html .= '<td style="text-align:right; ' . $style . '">' . $fmt($monthly) . ' PLN</td>';
        }
        $html .= '</tr>';

        $html .= '</tbody></table>';

        // Footer
        $html .= '<p style="font-size:8px; color:#9ca3af; margin-top:8px;">Obliczenia mają charakter poglądowy. Stawki ZUS i podatków na rok 2025. Skonsultuj z doradcą podatkowym.</p>';

        return $html;
    }
}
