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
        if (!$item) throw new \RuntimeException("Payroll item not found for run={$runId} employee={$employeeId}");

        $run = HrPayrollRun::findById($runId);
        if (!$run) throw new \RuntimeException("Payroll run not found: {$runId}");

        self::ensureDir();

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Billu HR');
        $pdf->SetAuthor($item['company_name'] ?? 'Pracodawca');
        $pdf->SetTitle('Odcinek płacowy');
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
        $months = ['','Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec','Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
        $period   = $months[$item['period_month']] . ' ' . $item['period_year'];
        $peselDisplay = $item['pesel'] ? self::maskPesel($item['pesel']) : '—';
        $address  = implode(', ', array_filter([$item['address_street'] ?? '', ($item['address_zip'] ?? '') . ' ' . ($item['address_city'] ?? '')]));
        $contractLabel = match($item['contract_type'] ?? 'uop') { 'uz' => 'Umowa zlecenie', 'uod' => 'Umowa o dzieło', default => 'Umowa o pracę' };
        $n = fn($v) => number_format((float) $v, 2, ',', ' ');
        $calcParams = !empty($item['calculation_params']) ? (json_decode($item['calculation_params'], true) ?? []) : [];

        $html = '<h1 style="font-size:14pt;font-weight:bold;text-align:center;">ODCINEK PŁACOWY</h1>';
        $html .= '<p style="text-align:center;font-size:9pt;color:#555;">Okres: <strong>' . self::e($period) . '</strong></p>';

        $html .= '<table><tr valign="top">';
        $html .= '<td width="50%" style="padding-right:8pt;"><h2 style="font-size:10pt;border-bottom:0.5pt solid #999;">Pracodawca</h2><strong>' . self::e($item['company_name']) . '</strong><br/><span style="font-size:8pt;color:#555;">NIP: ' . self::e($item['client_nip'] ?? '—') . '</span></td>';
        $html .= '<td width="50%"><h2 style="font-size:10pt;border-bottom:0.5pt solid #999;">Pracownik</h2><strong>' . self::e($item['employee_name']) . '</strong>';
        if ($item['position']) $html .= '<br/><span style="font-size:8pt;color:#555;">' . self::e($item['position']) . '</span>';
        $html .= '<br/><span style="font-size:8pt;color:#555;">PESEL: ' . self::e($peselDisplay) . ' | ' . self::e($contractLabel) . '</span>';
        if ($address) $html .= '<br/><span style="font-size:8pt;color:#555;">' . self::e($address) . '</span>';
        $html .= '</td></tr></table>';

        $html .= '<h2 style="font-size:10pt;border-bottom:0.5pt solid #999;">Składniki wynagrodzenia</h2>';
        $html .= '<table border="0" cellpadding="3" width="100%" style="font-size:8.5pt;">';
        $html .= self::row('Wynagrodzenie zasadnicze', $n($item['base_salary']));
        if ((float)$item['overtime_pay'] > 0) $html .= self::row('Nadgodziny', $n($item['overtime_pay']));
        if ((float)$item['bonus'] > 0) $html .= self::row('Premia', $n($item['bonus']));
        if ((float)$item['other_additions'] > 0) $html .= self::row('Inne dodatki', $n($item['other_additions']));
        if ((float)$item['sick_pay_reduction'] > 0) $html .= self::row('Potrącenie', '−' . $n($item['sick_pay_reduction']));
        $html .= '<tr style="background:#f5f5f5;font-weight:bold;"><td style="width:55%;padding:3px 6px;">Wynagrodzenie brutto</td><td style="width:45%;padding:3px 6px;text-align:right;">' . $n($item['gross_salary']) . ' zł</td></tr>';
        $html .= '</table>';

        $html .= '<h2 style="font-size:10pt;border-bottom:0.5pt solid #999;">Składki ZUS Pracownika</h2>';
        $html .= '<table border="0" cellpadding="3" width="100%" style="font-size:8.5pt;">';
        $html .= self::row('Emerytalne', $n($item['zus_emerytalne_emp']));
        $html .= self::row('Rentowe', $n($item['zus_rentowe_emp']));
        $html .= self::row('Chorobowe', $n($item['zus_chorobowe_emp']));
        $html .= '<tr style="background:#f5f5f5;font-weight:bold;"><td style="width:55%;padding:3px 6px;">ZUS pracownika łącznie</td><td style="width:45%;padding:3px 6px;text-align:right;">' . $n($item['zus_total_employee']) . ' zł</td></tr>';
        $html .= '</table>';

        $html .= '<h2 style="font-size:10pt;border-bottom:0.5pt solid #999;">Zaliczka na podatek</h2>';
        $html .= '<table border="0" cellpadding="3" width="100%" style="font-size:8.5pt;">';
        $html .= self::row('Podstawa opodatkowania', $n($item['tax_base']));
        $html .= self::row('Stawka PIT', $item['pit_rate'] . '%');
        if ((float)($item['tax_relief_monthly'] ?? 0) > 0) $html .= self::row('Ulga podatkowa (PIT-2)', '−' . $n($item['tax_relief_monthly']));
        $html .= '<tr style="background:#f5f5f5;font-weight:bold;"><td style="width:55%;padding:3px 6px;">Zaliczka na PIT</td><td style="width:45%;padding:3px 6px;text-align:right;">' . $n($item['pit_advance']) . ' zł</td></tr>';
        $html .= '</table>';

        if ((float)$item['ppk_employee'] > 0 || (float)$item['ppk_employer'] > 0) {
            $html .= '<h2 style="font-size:10pt;border-bottom:0.5pt solid #999;">PPK</h2>';
            $html .= '<table border="0" cellpadding="3" width="100%" style="font-size:8.5pt;">';
            $html .= self::row('Składka pracownika PPK', $n($item['ppk_employee']));
            $html .= self::row('Składka pracodawcy PPK', $n($item['ppk_employer']));
            $html .= '</table>';
        }

        $html .= '<table style="margin:8pt 0;"><tr>';
        $html .= '<td style="width:55%;font-size:13pt;font-weight:bold;padding:8pt;background:#f0f0f0;border:1pt solid #ccc;">WYNAGRODZENIE NETTO</td>';
        $html .= '<td style="width:45%;font-size:13pt;font-weight:bold;color:#1a6b3c;text-align:right;padding:8pt;background:#f0f0f0;border:1pt solid #ccc;">' . $n($item['net_salary']) . ' zł</td>';
        $html .= '</tr></table>';

        $html .= '<h2 style="font-size:10pt;border-bottom:0.5pt solid #999;">Koszty Pracodawcy</h2>';
        $html .= '<table border="0" cellpadding="3" width="100%" style="font-size:8.5pt;">';
        $html .= self::row('Emerytalne pracodawcy', $n($item['zus_emerytalne_emp2']));
        $html .= self::row('Rentowe pracodawcy', $n($item['zus_rentowe_emp2']));
        $html .= self::row('Wypadkowe', $n($item['zus_wypadkowe_emp2']));
        $html .= self::row('Fundusz Pracy', $n($item['zus_fp_emp2']));
        $html .= self::row('FGŚP', $n($item['zus_fgsp_emp2']));
        if ((float)$item['ppk_employer'] > 0) $html .= self::row('PPK pracodawcy', $n($item['ppk_employer']));
        $html .= '<tr style="background:#f5f5f5;font-weight:bold;"><td style="width:55%;padding:3px 6px;">Łączny koszt pracodawcy</td><td style="width:45%;padding:3px 6px;text-align:right;">' . $n($item['employer_total_cost']) . ' zł</td></tr>';
        $html .= '</table>';

        $html .= '<p style="font-size:7pt;color:#888;margin-top:16pt;text-align:center;">Wygenerowano przez Billu HR &bull; ' . date('d.m.Y H:i') . '</p>';

        return $html;
    }

    private static function row(string $label, string $value, string $style = ''): string
    {
        $s = $style ? ' style="' . $style . '"' : '';
        return '<tr style="border-top:0.3pt solid #ddd;"><td style="width:55%;padding:3px 6px;color:#444;"' . $s . '>' . self::e($label) . '</td><td style="width:45%;padding:3px 6px;text-align:right;"' . $s . '>' . $value . ' zł</td></tr>';
    }

    private static function maskPesel(string $pesel): string
    {
        if (strlen($pesel) !== 11) return $pesel;
        return str_repeat('•', 7) . substr($pesel, -4);
    }

    private static function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) mkdir(self::$storageDir, 0750, true);
    }
}
