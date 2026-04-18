<?php

namespace App\Services;

use App\Models\HrPayrollItem;
use App\Models\HrPayrollRun;

class HrPayslipPdfService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/payslips';

    public static function generate(int $runId, int $employeeId): string
    {
        $item = HrPayrollItem::findForPayslip($runId, $employeeId);
        if (!$item) {
            throw new \RuntimeException("Payroll item not found for run={$runId} employee={$employeeId}");
        }

        $run = HrPayrollRun::findById($runId);
        if (!$run) {
            throw new \RuntimeException("Payroll run not found: {$runId}");
        }

        self::ensureDir();

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('BiLLU HR');
        $pdf->SetAuthor($item['company_name'] ?? 'Pracodawca');
        $pdf->SetTitle('Odcinek p\u0142acowy');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->AddPage();

        $html = self::buildHtml($item, $run);
        $pdf->writeHTML($html, true, false, true, false, '');

        $monthStr = sprintf('%02d', $item['period_month']);
        $yearStr  = $item['period_year'];
        $empName  = preg_replace('/[^a-zA-Z0-9_]/', '_', mb_convert_encoding($item['employee_name'], 'ASCII', 'UTF-8'));
        $filename = "odcinek_{$empName}_{$yearStr}_{$monthStr}.pdf";
        $path     = self::$storageDir . '/' . $filename;

        $pdf->Output($path, 'F');

        \App\Core\HrDatabase::getInstance()->update(
            'hr_payroll_items',
            ['payslip_pdf_path' => 'storage/hr/payslips/' . $filename],
            'payroll_run_id = ? AND employee_id = ?',
            [$runId, $employeeId]
        );

        return $path;
    }

    private static function buildHtml(array $item, array $run): string
    {
        $months = ['','Stycze\u0144','Luty','Marzec','Kwiecie\u0144','Maj','Czerwiec',
                   'Lipiec','Sierpie\u0144','Wrzesie\u0144','Pa\u017adziernik','Listopad','Grudzie\u0144'];

        $period       = $months[$item['period_month']] . ' ' . $item['period_year'];
        $peselDisplay = $item['pesel'] ? self::maskPesel($item['pesel']) : '\u2014';
        $address      = implode(', ', array_filter([
            $item['address_street'] ?? '',
            ($item['address_zip'] ?? '') . ' ' . ($item['address_city'] ?? ''),
        ]));

        $contractLabel = match($item['contract_type'] ?? 'uop') {
            'uz'  => 'Umowa zlecenie',
            'uod' => 'Umowa o dzie\u0142o',
            default => 'Umowa o prac\u0119',
        };

        $n = fn($v) => number_format((float) $v, 2, ',', ' ');

        $calcParams = [];
        if (!empty($item['calculation_params'])) {
            $calcParams = json_decode($item['calculation_params'], true) ?? [];
        }

        $html  = '<style>* { font-family: dejavusans; } body { font-size: 9pt; color: #222; } ';
        $html .= 'h1 { font-size: 14pt; font-weight: bold; text-align: center; margin-bottom: 2px; } ';
        $html .= 'h2 { font-size: 10pt; font-weight: bold; margin: 8px 0 4px; color: #333; border-bottom: 0.5pt solid #999; padding-bottom: 2px; } ';
        $html .= 'table { border-collapse: collapse; width: 100%; } ';
        $html .= '.label { width: 55%; padding: 3px 6px; font-size: 8.5pt; color: #444; } ';
        $html .= '.value { width: 45%; padding: 3px 6px; font-size: 8.5pt; text-align: right; } ';
        $html .= '.value-bold { font-weight: bold; } .line-sep { border-top: 0.3pt solid #ddd; } ';
        $html .= '.total-row { background-color: #f5f5f5; font-weight: bold; } ';
        $html .= '.section-box { border: 0.5pt solid #ccc; padding: 4px; margin-bottom: 8px; }</style>';

        $html .= '<h1>ODCINEK P\u0141ACOWY</h1>';
        $html .= '<p style="text-align:center;font-size:9pt;color:#555;margin-top:0;">Okres: <strong>' . self::e($period) . '</strong></p>';

        $html .= '<table><tr valign="top">';
        $html .= '<td width="50%" style="padding-right:8pt;"><h2>Pracodawca</h2><table>';
        $html .= '<tr><td style="font-size:8.5pt;"><strong>' . self::e($item['company_name']) . '</strong></td></tr>';
        $html .= '<tr><td style="font-size:8pt;color:#555;">NIP: ' . self::e($item['client_nip'] ?? '\u2014') . '</td></tr>';
        $html .= '</table></td>';
        $html .= '<td width="50%"><h2>Pracownik</h2><table>';
        $html .= '<tr><td style="font-size:8.5pt;"><strong>' . self::e($item['employee_name']) . '</strong></td></tr>';
        if ($item['position']) {
            $html .= '<tr><td style="font-size:8pt;color:#555;">' . self::e($item['position']) . '</td></tr>';
        }
        $html .= '<tr><td style="font-size:8pt;color:#555;">PESEL: ' . self::e($peselDisplay) . '</td></tr>';
        $html .= '<tr><td style="font-size:8pt;color:#555;">Typ umowy: ' . self::e($contractLabel) . '</td></tr>';
        if ($address) {
            $html .= '<tr><td style="font-size:8pt;color:#555;">' . self::e($address) . '</td></tr>';
        }
        $html .= '</table></td></tr></table>';

        $html .= '<h2>Sk\u0142adniki wynagrodzenia \u2014 Brutto</h2><div class="section-box"><table>';
        $html .= self::row('Wynagrodzenie zasadnicze', $n($item['base_salary']));
        if ((float)$item['overtime_pay'] > 0) $html .= self::row('Nadgodziny', $n($item['overtime_pay']));
        if ((float)$item['bonus'] > 0)        $html .= self::row('Premia', $n($item['bonus']));
        if ((float)$item['other_additions'] > 0) $html .= self::row('Inne dodatki', $n($item['other_additions']));
        if ((float)$item['sick_pay_reduction'] > 0) $html .= self::row('Potr\u0105cenie (nieobecno\u015b\u0107)', '\u2212' . $n($item['sick_pay_reduction']), 'color:#c00;');
        $html .= '<tr class="total-row"><td class="label">Wynagrodzenie brutto</td><td class="value value-bold">' . $n($item['gross_salary']) . ' z\u0142</td></tr>';
        $html .= '</table></div>';

        $html .= '<h2>Sk\u0142adki ZUS Pracownika</h2><div class="section-box"><table>';
        $html .= self::row('Emerytalne (' . (float)($calcParams['rate_emerytalne'] ?? 0.0976) * 100 . '%)', $n($item['zus_emerytalne_emp']));
        $html .= self::row('Rentowe (' . (float)($calcParams['rate_rentowe_emp'] ?? 0.015) * 100 . '%)', $n($item['zus_rentowe_emp']));
        $html .= self::row('Chorobowe (' . (float)($calcParams['rate_chorobowe'] ?? 0.0245) * 100 . '%)', $n($item['zus_chorobowe_emp']));
        $html .= '<tr class="total-row"><td class="label">ZUS pracownika \u0142\u0105cznie</td><td class="value value-bold">' . $n($item['zus_total_employee']) . ' z\u0142</td></tr>';
        $html .= '</table></div>';

        $html .= '<h2>Obliczenie zaliczki na podatek dochodowy</h2><div class="section-box"><table>';
        $html .= self::row('Wynagrodzenie brutto', $n($item['gross_salary']));
        $html .= self::row('minus ZUS pracownika', '\u2212' . $n($item['zus_total_employee']));
        $html .= self::row('minus KUP', '\u2212' . $n($item['kup_amount']));
        $html .= self::row('Podstawa opodatkowania', $n($item['tax_base']), 'font-weight:bold;');
        $html .= self::row('Stawka PIT', $item['pit_rate'] . '%');
        $html .= self::row('Podatek obliczony', $n($item['pit_calculated']));
        if ((float)($item['tax_relief_monthly'] ?? 0) > 0) {
            $html .= self::row('Ulga podatkowa (PIT-2)', '\u2212' . $n($item['tax_relief_monthly']));
        }
        $html .= '<tr class="total-row"><td class="label">Zaliczka na PIT</td><td class="value value-bold">' . $n($item['pit_advance']) . ' z\u0142</td></tr>';
        $html .= '</table></div>';

        if ((float)$item['ppk_employee'] > 0 || (float)$item['ppk_employer'] > 0) {
            $html .= '<h2>PPK</h2><div class="section-box"><table>';
            $html .= self::row('Sk\u0142adka pracownika PPK', $n($item['ppk_employee']));
            $html .= self::row('Sk\u0142adka pracodawcy PPK', $n($item['ppk_employer']));
            $html .= '</table></div>';
        }

        $html .= '<table style="margin:8pt 0;"><tr>';
        $html .= '<td style="width:55%;font-size:13pt;font-weight:bold;color:#222;padding:8pt;background:#f0f0f0;border:1pt solid #ccc;">WYNAGRODZENIE NETTO</td>';
        $html .= '<td style="width:45%;font-size:13pt;font-weight:bold;color:#1a6b3c;text-align:right;padding:8pt;background:#f0f0f0;border:1pt solid #ccc;">' . $n($item['net_salary']) . ' z\u0142</td>';
        $html .= '</tr></table>';

        $html .= '<h2>Koszty Pracodawcy</h2><div class="section-box"><table>';
        $html .= self::row('Wynagrodzenie brutto', $n($item['gross_salary']));
        $html .= self::row('Emerytalne pracodawcy (' . (float)($calcParams['rate_emerytalne'] ?? 0.0976) * 100 . '%)', $n($item['zus_emerytalne_emp2']));
        $html .= self::row('Rentowe pracodawcy (' . (float)($calcParams['rate_rentowe_er'] ?? 0.065) * 100 . '%)', $n($item['zus_rentowe_emp2']));
        $html .= self::row('Wypadkowe', $n($item['zus_wypadkowe_emp2']));
        $html .= self::row('Fundusz Pracy', $n($item['zus_fp_emp2']));
        $html .= self::row('FG\u015aP', $n($item['zus_fgsp_emp2']));
        if ((float)$item['ppk_employer'] > 0) {
            $html .= self::row('PPK pracodawcy', $n($item['ppk_employer']));
        }
        $html .= '<tr class="total-row"><td class="label">\u0141\u0105czny koszt pracodawcy</td><td class="value value-bold">' . $n($item['employer_total_cost']) . ' z\u0142</td></tr>';
        $html .= '</table></div>';

        $html .= '<p style="font-size:7pt;color:#888;margin-top:16pt;text-align:center;">Wygenerowano przez BiLLU HR &bull; ' . date('d.m.Y H:i') . ' &bull; Dokument informacyjny \u2014 nie jest dowodem ksi\u0119gowym</p>';

        return $html;
    }

    private static function row(string $label, string $value, string $style = ''): string
    {
        $styleAttr = $style ? ' style="' . $style . '"' : '';
        return '<tr class="line-sep"><td class="label"' . $styleAttr . '>' . self::e($label) . '</td><td class="value"' . $styleAttr . '>' . $value . ' z\u0142</td></tr>';
    }

    private static function maskPesel(string $pesel): string
    {
        if (strlen($pesel) !== 11) return $pesel;
        return str_repeat('\u2022', 7) . substr($pesel, -4);
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0750, true);
        }
    }
}
