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
        if (!$contract) {
            throw new \RuntimeException("Contract not found: {$contractId}");
        }

        $employee = HrEmployee::findById((int)$contract['employee_id']);
        if (!$employee) {
            throw new \RuntimeException('Employee not found: ' . $contract['employee_id']);
        }

        $clientId = (int) $employee['client_id'];
        self::ensureDir($clientId);

        $mainDb  = HrDatabase::mainDbName();
        $company = HrDatabase::getInstance()->fetchOne(
            "SELECT company_name, nip, krs, address_street, address_city, address_zip, regon
             FROM `{$mainDb}`.clients WHERE id = ?",
            [$clientId]
        );
        if (!$company) {
            throw new \RuntimeException("Client not found: {$clientId}");
        }

        $contractTypeLabel = HrContract::getContractTypeLabel($contract['contract_type'] ?? 'uop');
        $workFraction      = (float)($contract['work_time_fraction'] ?? 1.0);
        $workFractionLabel = self::fractionLabel($workFraction);

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('BiLLU HR');
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

    private static function buildHtml(
        array $company, array $employee, array $contract,
        string $contractTypeLabel, string $workFractionLabel
    ): string {
        $h  = '<h2 style="text-align:center;font-size:13pt;margin:0 0 6px;">' . strtoupper(htmlspecialchars($contractTypeLabel)) . '</h2>';
        $h .= '<p style="text-align:center;font-size:9pt;margin:0 0 16px;">zawarta w ' . htmlspecialchars($company['address_city'] ?? '____') . ', dnia ' . date('d.m.Y') . ' r.</p>';

        $h .= '<p><strong>Strony umowy:</strong></p>';
        $h .= '<p><u>Pracodawca:</u><br/>' . htmlspecialchars($company['company_name']);
        if (!empty($company['address_street'])) {
            $h .= '<br/>' . htmlspecialchars($company['address_street']) . ', ' . htmlspecialchars(($company['address_zip'] ?? '') . ' ' . ($company['address_city'] ?? ''));
        }
        $h .= '<br/>NIP: ' . htmlspecialchars($company['nip'] ?? '\u2014');
        if (!empty($company['regon'])) $h .= ', REGON: ' . htmlspecialchars($company['regon']);
        if (!empty($company['krs']))   $h .= ', KRS: '   . htmlspecialchars($company['krs']);
        $h .= '</p>';

        $h .= '<p><u>Pracownik:</u><br/>' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']);
        if (!empty($employee['pesel'])) $h .= '<br/>PESEL: ' . htmlspecialchars($employee['pesel']);
        if (!empty($employee['address_street'])) {
            $h .= '<br/>' . htmlspecialchars($employee['address_street']) . ', ' . htmlspecialchars(($employee['address_zip'] ?? '') . ' ' . ($employee['address_city'] ?? ''));
        }
        $h .= '</p>';

        $h .= '<p><strong>Warunki zatrudnienia:</strong></p>';
        $h .= '<table border="0" cellpadding="3" width="100%" style="font-size:10pt;">';

        $rows = [
            ['Rodzaj umowy',        htmlspecialchars($contractTypeLabel)],
            ['Stanowisko',          htmlspecialchars($contract['position'] ?? '\u2014')],
            ['Dzia\u0142 / departament', htmlspecialchars($contract['department'] ?? '\u2014')],
            ['Wymiar czasu pracy',  htmlspecialchars($workFractionLabel)],
            ['Data rozpocz\u0119cia',    htmlspecialchars($contract['start_date'] ?? '\u2014')],
            ['Data zako\u0144czenia',    !empty($contract['end_date']) ? htmlspecialchars($contract['end_date']) : 'bezterminowa'],
            ['Wynagrodzenie brutto', number_format((float)($contract['base_salary'] ?? 0), 2, ',', ' ') . ' PLN miesi\u0119cznie'],
        ];

        foreach ($rows as [$label, $value]) {
            $h .= '<tr><td width="40%" style="color:#555;">' . $label . ':</td><td width="60%"><strong>' . $value . '</strong></td></tr>';
        }
        $h .= '</table>';

        $h .= '<br/><p><strong>\u00a7 1. Zakres obowi\u0105zk\u00f3w</strong><br/>Pracownik zobowi\u0105zuje si\u0119 do wykonywania pracy na stanowisku okre\u015blonym w \u00a71, zgodnie z zakresem czynno\u015bci ustalonym przez Pracodawc\u0119.</p>';
        $h .= '<p><strong>\u00a7 2. Wynagrodzenie</strong><br/>Pracownikowi przys\u0142uguje wynagrodzenie zasadnicze w wysoko\u015bci wskazanej powy\u017cej, p\u0142atne z do\u0142u do ostatniego dnia miesi\u0105ca.</p>';
        $h .= '<p><strong>\u00a7 3. Urlopy</strong><br/>Pracownikowi przys\u0142uguje prawo do urlopu wypoczynkowego na zasadach okre\u015blonych w Kodeksie Pracy (art. 152 i n. KP).</p>';
        $h .= '<p><strong>\u00a7 4. Postanowienia ko\u0144cowe</strong><br/>W sprawach nieuregulowanych zastosowanie maj\u0105 przepisy Kodeksu Pracy. Umow\u0119 sporz\u0105dzono w dw\u00f3ch jednobrzmi\u0105cych egzemplarzach, po jednym dla ka\u017cdej ze stron.</p>';

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
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private static function fractionLabel(float $fraction): string
    {
        return match(true) {
            $fraction >= 1.0  => 'pe\u0142ny etat (1/1)',
            $fraction >= 0.75 => '3/4 etatu',
            $fraction >= 0.5  => '1/2 etatu',
            $fraction >= 0.25 => '1/4 etatu',
            default           => round($fraction * 100) . '% etatu',
        };
    }
}
