<?php

namespace App\Services;

use App\Core\Database;
use App\Core\HrDatabase;
use App\Models\HrPitDeclaration;

class HrPit4rService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/pit';

    private static array $monthNames = [
        1  => 'Styczeń',   2  => 'Luty',       3  => 'Marzec',
        4  => 'Kwiecień',  5  => 'Maj',         6  => 'Czerwiec',
        7  => 'Lipiec',    8  => 'Sierpień',    9  => 'Wrzesień',
        10 => 'Październik', 11 => 'Listopad',  12 => 'Grudzień',
    ];

    public static function generate(int $clientId, int $year): string
    {
        $hrDb = HrDatabase::hrDbName();
        $client = Database::getInstance()->fetchOne(
            "SELECT c.*, hs.zus_payer_nip, hs.zus_payer_name
             FROM clients c
             LEFT JOIN {$hrDb}.hr_client_settings hs ON hs.client_id = c.id
             WHERE c.id = ?",
            [$clientId]
        );
        if (!$client) {
            throw new \RuntimeException("Klient nie znaleziony: {$clientId}");
        }

        $agg = HrPitDeclaration::aggregateYearForClient($clientId, $year);

        if ((float)$agg['totals']['total_gross'] <= 0) {
            throw new \RuntimeException("Brak danych płacowych za rok {$year}.");
        }

        self::ensureDir();

        $nipClean = preg_replace('/[^0-9]/', '', $client['zus_payer_nip'] ?? $client['nip'] ?? '');
        $filename = "PIT4R_{$nipClean}_{$year}.pdf";
        $filePath = self::$storageDir . '/' . $filename;

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Billu HR');
        $pdf->SetAuthor($client['zus_payer_name'] ?? $client['company_name'] ?? 'Pracodawca');
        $pdf->SetTitle("PIT-4R — {$year}");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->AddPage();

        $html = self::buildHtml($client, $agg, $year);
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->Output($filePath, 'F');

        return $filePath;
    }

    private static function buildHtml(array $client, array $agg, int $year): string
    {
        $employerName = htmlspecialchars($client['zus_payer_name'] ?? $client['company_name'] ?? '');
        $employerNip  = htmlspecialchars($client['zus_payer_nip'] ?? $client['nip'] ?? '');
        $employerAddr = htmlspecialchars(trim(($client['address_street'] ?? '') . ' ' . ($client['address_city'] ?? '')));

        $byMonth = [];
        foreach ($agg['monthly'] as $row) {
            $byMonth[(int)$row['period_month']] = $row;
        }

        $totalPit   = (float)$agg['totals']['total_pit_advances'];
        $totalGross = (float)$agg['totals']['total_gross'];

        $monthRows = '';
        $cumulative = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $pit = (float)($byMonth[$m]['pit_advances'] ?? 0);
            $gross = (float)($byMonth[$m]['gross_total'] ?? 0);
            $cumulative += $pit;
            $monthName = self::$monthNames[$m];
            $pitFmt  = number_format($pit, 2, ',', ' ');
            $grossFmt = number_format($gross, 2, ',', ' ');
            $cumFmt  = number_format($cumulative, 2, ',', ' ');
            $bg = $m % 2 === 0 ? '#f9f9f9' : '#ffffff';
            $monthRows .= "<tr style=\"background:{$bg};\"><td style=\"padding:3px 6px;\">{$m}. {$monthName}</td><td style=\"text-align:right;padding:3px 6px;\">{$grossFmt}</td><td style=\"text-align:right;padding:3px 6px;font-weight:bold;\">{$pitFmt}</td><td style=\"text-align:right;padding:3px 6px;color:#555;\">{$cumFmt}</td></tr>";
        }

        $totalPitFmt   = number_format($totalPit, 2, ',', ' ');
        $totalGrossFmt = number_format($totalGross, 2, ',', ' ');

        return <<<HTML
<table width="100%" cellpadding="4" cellspacing="0" style="font-family:dejavusans;font-size:9px;">
  <tr><td colspan="2" style="background-color:#1e3a5f;color:#ffffff;font-size:13px;font-weight:bold;text-align:center;padding:8px;">DEKLARACJA O POBRANYCH ZALICZKACH NA PODATEK DOCHODOWY</td></tr>
  <tr><td colspan="2" style="text-align:center;font-size:11px;font-weight:bold;padding:2px 0 6px;">PIT-4R — Rok podatkowy: {$year}</td></tr>
</table><br/>
<table width="100%" cellpadding="4" cellspacing="0" border="1" style="font-size:9px;border-collapse:collapse;">
  <tr style="background:#f0f0f0;"><td colspan="2" style="font-weight:bold;padding:4px 6px;">A. DANE PŁATNIKA (PRACODAWCY)</td></tr>
  <tr><td width="35%" style="padding:3px 6px;color:#555;">Nazwa / Firma:</td><td style="padding:3px 6px;font-weight:bold;">{$employerName}</td></tr>
  <tr><td style="padding:3px 6px;color:#555;">NIP:</td><td style="padding:3px 6px;">{$employerNip}</td></tr>
  <tr><td style="padding:3px 6px;color:#555;">Adres:</td><td style="padding:3px 6px;">{$employerAddr}</td></tr>
</table><br/>
<table width="100%" cellpadding="4" cellspacing="0" border="1" style="font-size:9px;border-collapse:collapse;">
  <tr style="background:#e8eaf6;"><td colspan="4" style="font-weight:bold;padding:4px 6px;">B. ZALICZKI POBRANE W ROKU {$year}</td></tr>
  <tr style="background:#f0f0f0;"><td width="30%" style="padding:3px 6px;font-weight:bold;">Miesiąc</td><td width="23%" style="text-align:right;padding:3px 6px;font-weight:bold;">Brutto (PLN)</td><td width="23%" style="text-align:right;padding:3px 6px;font-weight:bold;">Zaliczka PIT (PLN)</td><td width="24%" style="text-align:right;padding:3px 6px;font-weight:bold;">Narastająco (PLN)</td></tr>
  {$monthRows}
  <tr style="background:#e3f2fd;font-weight:bold;"><td style="padding:4px 6px;">RAZEM (rok {$year})</td><td style="text-align:right;padding:4px 6px;">{$totalGrossFmt}</td><td style="text-align:right;padding:4px 6px;font-size:11px;">{$totalPitFmt}</td><td style="text-align:right;padding:4px 6px;">—</td></tr>
</table>
HTML;
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0755, true);
        }
    }
}
