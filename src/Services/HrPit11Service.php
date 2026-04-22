<?php

namespace App\Services;

use App\Core\Database;
use App\Core\HrDatabase;
use App\Models\HrEmployee;
use App\Models\HrPitDeclaration;

class HrPit11Service
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/pit';

    public static function generate(int $clientId, int $employeeId, int $year): string
    {
        $employee = HrEmployee::findById($employeeId);
        if (!$employee || (int)$employee['client_id'] !== $clientId) {
            throw new \RuntimeException("Pracownik nie znaleziony: {$employeeId}");
        }

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

        $agg = HrPitDeclaration::aggregateYearForEmployee($employeeId, $year);

        if ((float)$agg['total_gross'] <= 0) {
            throw new \RuntimeException("Brak danych płacowych dla pracownika za rok {$year}.");
        }

        self::ensureDir();

        $pesel4 = substr($employee['pesel'] ?? '0000', -4);
        $nipClean = preg_replace('/[^0-9]/', '', $client['zus_payer_nip'] ?? $client['nip'] ?? '');
        $filename = "PIT11_{$nipClean}_{$pesel4}_{$year}.pdf";
        $filePath = self::$storageDir . '/' . $filename;

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Billu HR');
        $pdf->SetAuthor($client['zus_payer_name'] ?? $client['company_name'] ?? 'Pracodawca');
        $pdf->SetTitle("PIT-11 — {$year}");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->AddPage();

        $html = self::buildHtml($client, $employee, $agg, $year);
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->Output($filePath, 'F');

        return $filePath;
    }

    private static function buildHtml(array $client, array $employee, array $agg, int $year): string
    {
        $employerName = htmlspecialchars($client['zus_payer_name'] ?? $client['company_name'] ?? '');
        $employerNip  = htmlspecialchars($client['zus_payer_nip'] ?? $client['nip'] ?? '');
        $employerAddr = htmlspecialchars(trim(($client['address_street'] ?? '') . ' ' . ($client['address_city'] ?? '')));

        $empName  = htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
        $peselMasked = '***-**-**-' . substr($employee['pesel'] ?? '----', -4);
        $empAddr  = htmlspecialchars(trim(($employee['address_street'] ?? '') . ', ' . ($employee['address_city'] ?? '')));

        $gross   = number_format((float)$agg['total_gross'],        2, ',', ' ');
        $zus     = number_format((float)$agg['total_zus_employee'],  2, ',', ' ');
        $kup     = number_format((float)$agg['total_kup'],           2, ',', ' ');
        $pitAdv  = number_format((float)$agg['total_pit_advances'],  2, ',', ' ');

        $taxBase = max(0, (float)$agg['total_gross'] - (float)$agg['total_zus_employee'] - (float)$agg['total_kup']);
        $taxBaseFmt = number_format($taxBase, 2, ',', ' ');

        return <<<HTML
<table width="100%" cellpadding="4" cellspacing="0" style="font-family:dejavusans;font-size:9px;">
  <tr><td colspan="2" style="background-color:#1e3a5f;color:#ffffff;font-size:13px;font-weight:bold;text-align:center;padding:8px;">INFORMACJA O DOCHODACH ORAZ POBRANYCH ZALICZKACH NA PODATEK DOCHODOWY</td></tr>
  <tr><td colspan="2" style="text-align:center;font-size:11px;font-weight:bold;padding:2px 0 6px;">PIT-11 — Rok podatkowy: {$year}</td></tr>
</table><br/>
<table width="100%" cellpadding="4" cellspacing="0" border="1" style="font-size:9px;border-collapse:collapse;">
  <tr style="background:#f0f0f0;"><td colspan="2" style="font-weight:bold;padding:4px 6px;">A. DANE PRACODAWCY (PŁATNIKA)</td></tr>
  <tr><td width="35%" style="padding:3px 6px;color:#555;">Nazwa / Firma:</td><td style="padding:3px 6px;font-weight:bold;">{$employerName}</td></tr>
  <tr><td style="padding:3px 6px;color:#555;">NIP:</td><td style="padding:3px 6px;">{$employerNip}</td></tr>
  <tr><td style="padding:3px 6px;color:#555;">Adres:</td><td style="padding:3px 6px;">{$employerAddr}</td></tr>
</table><br/>
<table width="100%" cellpadding="4" cellspacing="0" border="1" style="font-size:9px;border-collapse:collapse;">
  <tr style="background:#f0f0f0;"><td colspan="2" style="font-weight:bold;padding:4px 6px;">B. DANE PRACOWNIKA (PODATNIKA)</td></tr>
  <tr><td width="35%" style="padding:3px 6px;color:#555;">Imię i nazwisko:</td><td style="padding:3px 6px;font-weight:bold;">{$empName}</td></tr>
  <tr><td style="padding:3px 6px;color:#555;">PESEL:</td><td style="padding:3px 6px;">{$peselMasked}</td></tr>
  <tr><td style="padding:3px 6px;color:#555;">Adres:</td><td style="padding:3px 6px;">{$empAddr}</td></tr>
</table><br/>
<table width="100%" cellpadding="4" cellspacing="0" border="1" style="font-size:9px;border-collapse:collapse;">
  <tr style="background:#f0f0f0;"><td colspan="3" style="font-weight:bold;padding:4px 6px;">C. PRZYCHODY I KOSZTY</td></tr>
  <tr style="background:#e8eaf6;"><td width="50%" style="padding:3px 6px;">Pozycja</td><td width="25%" style="text-align:right;padding:3px 6px;">Kwota (PLN)</td><td width="25%" style="padding:3px 6px;color:#555;">Opis</td></tr>
  <tr><td style="padding:3px 6px;">Łączne przychody (brutto)</td><td style="text-align:right;padding:3px 6px;font-weight:bold;">{$gross}</td><td style="padding:3px 6px;color:#666;">Suma wynagrodzeń brutto</td></tr>
  <tr><td style="padding:3px 6px;">Koszty uzyskania przychodu (KUP)</td><td style="text-align:right;padding:3px 6px;">{$kup}</td><td style="padding:3px 6px;color:#666;">250 lub 300 PLN × miesiące</td></tr>
  <tr><td style="padding:3px 6px;">Składki ZUS pracownika</td><td style="text-align:right;padding:3px 6px;">{$zus}</td><td style="padding:3px 6px;color:#666;">Emerytalne + rentowe + chorobowe</td></tr>
  <tr style="background:#fafafa;"><td style="padding:3px 6px;font-weight:bold;">Dochód (podstawa podatku)</td><td style="text-align:right;padding:3px 6px;font-weight:bold;">{$taxBaseFmt}</td><td style="padding:3px 6px;color:#666;">Brutto − ZUS − KUP</td></tr>
</table><br/>
<table width="100%" cellpadding="4" cellspacing="0" border="1" style="font-size:9px;border-collapse:collapse;">
  <tr style="background:#f0f0f0;"><td colspan="2" style="font-weight:bold;padding:4px 6px;">D. ZALICZKI NA PODATEK</td></tr>
  <tr><td width="60%" style="padding:3px 6px;">Łączne zaliczki na podatek dochodowy pobrane przez płatnika</td><td style="text-align:right;padding:3px 6px;font-weight:bold;font-size:11px;">{$pitAdv} PLN</td></tr>
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
