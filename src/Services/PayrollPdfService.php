<?php

namespace App\Services;

use App\Models\PayrollList;
use App\Models\PayrollEntry;
use App\Models\ClientEmployee;
use App\Models\Client;

class PayrollPdfService
{
    /**
     * Generate payroll list PDF.
     */
    public static function generatePayrollList(int $listId): ?string
    {
        $list = PayrollList::findById($listId);
        if (!$list) return null;

        $entries = PayrollEntry::findByPayrollList($listId);
        $client = Client::findById((int)$list['client_id']);

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('BiLLU');
        $pdf->SetTitle('Lista płac ' . ($list['title'] ?? ''));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->AddPage();

        // Header
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Cell(0, 8, 'LISTA PŁAC', 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->Cell(0, 5, ($list['title'] ?? sprintf('%02d/%d', $list['month'], $list['year'])), 0, 1, 'C');
        $pdf->Ln(2);

        // Company info
        $pdf->SetFont('dejavusans', '', 8);
        $companyName = $client['company_name'] ?? $client['name'] ?? '';
        $pdf->Cell(0, 4, 'Firma: ' . $companyName . '  |  NIP: ' . ($client['nip'] ?? ''), 0, 1, 'L');
        $pdf->Cell(0, 4, 'Status: ' . strtoupper($list['status']) . '  |  Data: ' . date('d.m.Y'), 0, 1, 'L');
        $pdf->Ln(3);

        // Table header
        $pdf->SetFont('dejavusans', 'B', 6);
        $pdf->SetFillColor(240, 240, 240);
        $colW = [5, 35, 20, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 20, 20, 20];
        $headers = ['Lp', 'Pracownik', 'Brutto', 'Em.pr.', 'Ren.pr.', 'Chor.pr.', 'ZUS pr.', 'Podst.zdr.', 'Zdrow.', 'KUP', 'Podst.PIT', 'Zal.PIT', 'PPK pr.', 'Netto', 'ZUS prac.', 'Koszt'];

        foreach ($headers as $i => $h) {
            $pdf->Cell($colW[$i], 5, $h, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Table rows
        $pdf->SetFont('dejavusans', '', 6);
        $lp = 0;
        foreach ($entries as $entry) {
            $lp++;
            $name = ($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? '');
            $pdf->Cell($colW[0], 4, $lp, 1, 0, 'C');
            $pdf->Cell($colW[1], 4, $name, 1, 0, 'L');
            $pdf->Cell($colW[2], 4, number_format((float)$entry['total_gross'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[3], 4, number_format((float)$entry['zus_emerytalna_employee'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[4], 4, number_format((float)$entry['zus_rentowa_employee'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[5], 4, number_format((float)$entry['zus_chorobowa_employee'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[6], 4, number_format((float)$entry['zus_total_employee'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[7], 4, number_format((float)$entry['health_insurance_base'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[8], 4, number_format((float)$entry['health_insurance_full'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[9], 4, number_format((float)$entry['tax_deductible_costs'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[10], 4, number_format((float)$entry['tax_base'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[11], 4, number_format((float)$entry['pit_advance'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[12], 4, number_format((float)$entry['ppk_employee'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[13], 4, number_format((float)$entry['net_salary'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[14], 4, number_format((float)($entry['zus_emerytalna_employer'] + $entry['zus_rentowa_employer'] + $entry['zus_wypadkowa_employer'] + $entry['zus_fp_employer'] + $entry['zus_fgsp_employer']), 2, ',', ' '), 1, 0, 'R');
            $pdf->Cell($colW[15], 4, number_format((float)$entry['total_employer_cost'], 2, ',', ' '), 1, 0, 'R');
            $pdf->Ln();
        }

        // Totals row
        $pdf->SetFont('dejavusans', 'B', 6);
        $pdf->Cell($colW[0] + $colW[1], 5, 'RAZEM:', 1, 0, 'R', true);
        $pdf->Cell($colW[2], 5, number_format((float)$list['total_gross'], 2, ',', ' '), 1, 0, 'R', true);
        // Fill remaining cells with empty
        for ($i = 3; $i < 13; $i++) {
            $pdf->Cell($colW[$i], 5, '', 1, 0, 'C', true);
        }
        $pdf->Cell($colW[13], 5, number_format((float)$list['total_net'], 2, ',', ' '), 1, 0, 'R', true);
        $pdf->Cell($colW[14], 5, '', 1, 0, 'C', true);
        $pdf->Cell($colW[15], 5, number_format((float)$list['total_employer_cost'], 2, ',', ' '), 1, 0, 'R', true);

        // Approval info
        if ($list['approved_at']) {
            $pdf->Ln(8);
            $pdf->SetFont('dejavusans', '', 8);
            $pdf->Cell(0, 5, 'Zatwierdzona: ' . date('d.m.Y H:i', strtotime($list['approved_at'])), 0, 1);
        }

        // Signatures
        $pdf->Ln(15);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->Cell(90, 5, '........................................', 0, 0, 'C');
        $pdf->Cell(90, 5, '........................................', 0, 1, 'C');
        $pdf->Cell(90, 5, 'Sporządził', 0, 0, 'C');
        $pdf->Cell(90, 5, 'Zatwierdził', 0, 1, 'C');

        $storagePath = __DIR__ . '/../../storage/payroll';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filename = sprintf('lista_plac_%d_%02d_%d.pdf', $list['client_id'], $list['month'], $list['year']);
        $filepath = $storagePath . '/' . $filename;
        $pdf->Output($filepath, 'F');

        // Best-effort SFTP push for the payslip-list PDF.
        $client = \App\Models\Client::findById((int) $list['client_id']);
        if ($client && !empty($client['office_id'])) {
            \App\Services\SftpUploadService::enqueue(
                (int) $client['office_id'], (int) $list['client_id'],
                'payslips', $filepath, 'list:' . (int) $listId
            );
        }

        return $filepath;
    }

    /**
     * Generate individual payslip PDF (pasek wynagrodzenia).
     */
    public static function generatePayslip(int $entryId): ?string
    {
        $entry = PayrollEntry::findById($entryId);
        if (!$entry) return null;

        $employee = ClientEmployee::findById((int)$entry['employee_id']);
        $client = Client::findById((int)$entry['client_id']);

        // Get period from payroll list
        $list = PayrollList::findById((int)$entry['payroll_list_id']);
        if (!$list) return null;

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('BiLLU');
        $pdf->SetTitle('Pasek wynagrodzenia');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Header
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Cell(0, 8, 'PASEK WYNAGRODZENIA', 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 5, sprintf('Okres: %02d/%d', $list['month'], $list['year']), 0, 1, 'C');
        $pdf->Ln(5);

        // Employee info
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Cell(0, 6, 'Pracownik:', 0, 1);
        $pdf->SetFont('dejavusans', '', 10);
        $empName = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');
        $pdf->Cell(0, 5, $empName, 0, 1);
        $pdf->Cell(0, 5, 'Firma: ' . ($client['company_name'] ?? $client['name'] ?? ''), 0, 1);
        $pdf->Ln(5);

        // Breakdown table
        $w1 = 120; $w2 = 60;
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);

        $rows = [
            ['Wynagrodzenie brutto', $entry['total_gross']],
            ['', ''],
            ['SKŁADKI ZUS (pracownik)', ''],
            ['  Emerytalna (9,76%)', $entry['zus_emerytalna_employee']],
            ['  Rentowa (1,50%)', $entry['zus_rentowa_employee']],
            ['  Chorobowa (2,45%)', $entry['zus_chorobowa_employee']],
            ['  Razem ZUS pracownik', $entry['zus_total_employee']],
            ['', ''],
            ['UBEZPIECZENIE ZDROWOTNE', ''],
            ['  Podstawa', $entry['health_insurance_base']],
            ['  Składka zdrowotna (9%)', $entry['health_insurance_full']],
            ['', ''],
            ['PODATEK DOCHODOWY', ''],
            ['  Koszty uzyskania przychodu', $entry['tax_deductible_costs']],
            ['  Podstawa opodatkowania', $entry['tax_base']],
            ['  Zaliczka na PIT', $entry['pit_advance']],
            ['', ''],
            ['PPK pracownik', $entry['ppk_employee']],
        ];

        foreach ($rows as $row) {
            if ($row[0] === '' && $row[1] === '') {
                $pdf->Ln(2);
                continue;
            }
            $isBold = str_starts_with($row[0], 'SKŁADKI') || str_starts_with($row[0], 'UBEZPIECZENIE')
                || str_starts_with($row[0], 'PODATEK') || str_starts_with($row[0], 'Wynagrodzenie')
                || str_contains($row[0], 'Razem');
            $pdf->SetFont('dejavusans', $isBold ? 'B' : '', 9);

            $pdf->Cell($w1, 5, $row[0], 0, 0, 'L');
            if ($row[1] !== '') {
                $pdf->Cell($w2, 5, number_format((float)$row[1], 2, ',', ' ') . ' PLN', 0, 1, 'R');
            } else {
                $pdf->Cell($w2, 5, '', 0, 1);
            }
        }

        // NET SALARY
        $pdf->Ln(3);
        $pdf->SetDrawColor(0, 143, 143);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(3);
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell($w1, 8, 'WYNAGRODZENIE NETTO (DO WYPŁATY)', 0, 0, 'L');
        $pdf->Cell($w2, 8, number_format((float)$entry['net_salary'], 2, ',', ' ') . ' PLN', 0, 1, 'R');

        // Employer costs section
        $pdf->Ln(8);
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell(0, 6, 'KOSZTY PRACODAWCY (ponad brutto):', 0, 1);
        $pdf->SetFont('dejavusans', '', 9);

        $employerRows = [
            ['  ZUS emerytalna (9,76%)', $entry['zus_emerytalna_employer']],
            ['  ZUS rentowa (6,50%)', $entry['zus_rentowa_employer']],
            ['  ZUS wypadkowa (1,67%)', $entry['zus_wypadkowa_employer']],
            ['  Fundusz Pracy (2,45%)', $entry['zus_fp_employer']],
            ['  FGŚP (0,10%)', $entry['zus_fgsp_employer']],
            ['  PPK pracodawca', $entry['ppk_employer']],
        ];

        foreach ($employerRows as $row) {
            $pdf->Cell($w1, 5, $row[0], 0, 0, 'L');
            $pdf->Cell($w2, 5, number_format((float)$row[1], 2, ',', ' ') . ' PLN', 0, 1, 'R');
        }

        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->Ln(2);
        $pdf->Cell($w1, 6, 'CAŁKOWITY KOSZT PRACODAWCY', 0, 0, 'L');
        $pdf->Cell($w2, 6, number_format((float)$entry['total_employer_cost'], 2, ',', ' ') . ' PLN', 0, 1, 'R');

        $storagePath = __DIR__ . '/../../storage/payroll';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filename = sprintf('pasek_%d_%d_%02d_%d.pdf', $entry['employee_id'], $entry['client_id'], $list['month'], $list['year']);
        $filepath = $storagePath . '/' . $filename;
        $pdf->Output($filepath, 'F');

        // Best-effort SFTP push of the individual payslip.
        $client = \App\Models\Client::findById((int) $entry['client_id']);
        if ($client && !empty($client['office_id'])) {
            \App\Services\SftpUploadService::enqueue(
                (int) $client['office_id'], (int) $entry['client_id'],
                'payslips', $filepath, 'entry:' . (int) $entryId
            );
        }

        return $filepath;
    }
}
