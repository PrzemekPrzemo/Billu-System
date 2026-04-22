<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrContract;
use App\Models\HrEmployee;

class HrUmowaPdfService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/umowy';

    public static function generate(int $contractId): string
    {
        $contract = HrContract::findById($contractId);
        if (!$contract) throw new \RuntimeException("Contract not found: {$contractId}");

        $employee = HrEmployee::findById((int)$contract['employee_id']);
        if (!$employee) throw new \RuntimeException("Employee not found: " . $contract['employee_id']);

        $clientId = (int) $employee['client_id'];
        self::ensureDir($clientId);

        $mainDb  = HrDatabase::mainDbName();
        $company = HrDatabase::getInstance()->fetchOne(
            "SELECT company_name, nip, krs, address_street, address_city, address_zip, regon FROM `{$mainDb}`.clients WHERE id = ?",
            [$clientId]
        );
        if (!$company) throw new \RuntimeException("Client not found: {$clientId}");

        $contractTypeLabel = HrContract::getContractTypeLabel($contract['contract_type'] ?? 'uop');
        $workFraction      = (float)($contract['work_time_fraction'] ?? 1.0);
        $workFractionLabel = self::fractionLabel($workFraction);

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Billu HR');
        $pdf->SetAuthor($company['company_name'] ?? 'Pracodawca');
        $pdf->SetTitle($contractTypeLabel);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();

        $html = self::buildHtml($company, $employee, $contract, $contractTypeLabel, $workFractionLabel);
        $pdf->writeHTML($html, true, false, true, false, '');

        $lastName = preg_replace('/[^a-zA-Z0-9]/', '_', $employee['last_name'] ?? 'pracownik');
        $filename = "umowa_{$lastName}_{$contractId}_" . date('Ymd') . '.pdf';
        $path     = self::$storageDir . '/' . $clientId . '/' . $filename;
        $pdf->Output($path, 'F');

        HrContract::markWithPdf($contractId, 'storage/hr/umowy/' . $clientId . '/' . $filename);

        return $path;
    }

    private static function buildHtml(array $company, array $employee, array $contract, string $contractTypeLabel, string $workFractionLabel): string
    {
        $h = '';
        $h .= '<h2 style="text-align:center;font-size:13pt;margin:0 0 6px;">' . strtoupper(htmlspecialchars($contractTypeLabel)) . '</h2>';
        $h .= '<p style="text-align:center;font-size:9pt;margin:0 0 16px;">zawarta w ' . htmlspecialchars($company['address_city'] ?? '____') . ', dnia ' . date('d.m.Y') . ' r.</p>';

        $h .= '<p><strong>Strony umowy:</strong></p>';
        $h .= '<p><u>Pracodawca:</u><br/>' . htmlspecialchars($company['company_name']) . '<br/>';
        if (!empty($company['address_street'])) {
            $h .= htmlspecialchars($company['address_street']) . ', ' . htmlspecialchars(($company['address_zip'] ?? '') . ' ' . ($company['address_city'] ?? '')) . '<br/>';
        }
        $h .= 'NIP: ' . htmlspecialchars($company['nip'] ?? '—');
        if (!empty($company['regon'])) $h .= ', REGON: ' . htmlspecialchars($company['regon']);
        if (!empty($company['krs']))   $h .= ', KRS: ' . htmlspecialchars($company['krs']);
        $h .= '</p>';

        $h .= '<p><u>Pracownik:</u><br/>' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '<br/>';
        if (!empty($employee['pesel'])) $h .= 'PESEL: ' . htmlspecialchars($employee['pesel']) . '<br/>';
        if (!empty($employee['address_street'])) {
            $h .= htmlspecialchars($employee['address_street']) . ', ' . htmlspecialchars(($employee['address_zip'] ?? '') . ' ' . ($employee['address_city'] ?? ''));
        }
        $h .= '</p>';

        $h .= '<p><strong>Warunki zatrudnienia:</strong></p>';
        $h .= '<table border="0" cellpadding="3" width="100%" style="font-size:10pt;">';
        $rows = [
            ['Rodzaj umowy', htmlspecialchars($contractTypeLabel)],
            ['Stanowisko', htmlspecialchars($contract['position'] ?? '—')],
            ['Dział / departament', htmlspecialchars($contract['department'] ?? '—')],
            ['Wymiar czasu pracy', htmlspecialchars($workFractionLabel)],
            ['Data rozpoczęcia', htmlspecialchars($contract['start_date'] ?? '—')],
            ['Data zakończenia', !empty($contract['end_date']) ? htmlspecialchars($contract['end_date']) : 'bezterminowa'],
            ['Wynagrodzenie brutto', number_format((float)($contract['base_salary'] ?? 0), 2, ',', ' ') . ' PLN miesięcznie'],
        ];
        foreach ($rows as [$label, $value]) {
            $h .= '<tr><td width="40%" style="color:#555;">' . $label . ':</td><td width="60%"><strong>' . $value . '</strong></td></tr>';
        }
        $h .= '</table>';

        $h .= '<br/><p><strong>§ 1. Zakres obowiązków</strong><br/>Pracownik zobowiązuje się do wykonywania pracy na stanowisku określonym w §1 niniejszej umowy, zgodnie z zakresem czynności ustalonym przez Pracodawcę.</p>';
        $h .= '<p><strong>§ 2. Wynagrodzenie</strong><br/>Pracownikowi przysługuje wynagrodzenie zasadnicze w wysokości wskazanej powyżej, płatne z dołu do ostatniego dnia miesiąca, na rachunek bankowy wskazany przez Pracownika.</p>';
        $h .= '<p><strong>§ 3. Urlopy</strong><br/>Pracownikowi przysługuje prawo do urlopu wypoczynkowego na zasadach określonych w Kodeksie Pracy (art. 152 i n. KP).</p>';
        $h .= '<p><strong>§ 4. Postanowienia końcowe</strong><br/>W sprawach nieuregulowanych niniejszą umową zastosowanie mają przepisy Kodeksu Pracy oraz inne powszechnie obowiązujące przepisy prawa pracy. Umowę sporządzono w dwóch jednobrzmiących egzemplarzach, po jednym dla każdej ze stron.</p>';

        $h .= '<br/><br/><table border="0" width="100%"><tr>';
        $h .= '<td width="45%" align="center"><strong>Pracodawca</strong><br/><br/><br/>.....................................<br/><small>' . htmlspecialchars($company['company_name']) . '</small></td>';
        $h .= '<td width="10%"></td>';
        $h .= '<td width="45%" align="center"><strong>Pracownik</strong><br/><br/><br/>.....................................<br/><small>' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</small></td>';
        $h .= '</tr></table>';

        return $h;
    }

    private static function ensureDir(int $clientId): void
    {
        $dir = self::$storageDir . '/' . $clientId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    private static function fractionLabel(float $fraction): string
    {
        return match(true) {
            $fraction >= 1.0  => 'pełny etat (1/1)',
            $fraction >= 0.75 => '3/4 etatu',
            $fraction >= 0.5  => '1/2 etatu',
            $fraction >= 0.25 => '1/4 etatu',
            default           => round($fraction * 100) . '% etatu',
        };
    }
}
