<?php

namespace App\Services;

use App\Core\HrDatabase;
use App\Models\HrEmployee;
use App\Models\HrContract;
use App\Models\HrLeaveBalance;

class HrSwiadectwoPdfService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/swiadectwa';

    public static function generate(int $employeeId, int $contractId): string
    {
        $employee = HrEmployee::findById($employeeId);
        if (!$employee) throw new \RuntimeException("Employee not found: {$employeeId}");

        $contract = HrContract::findById($contractId);
        if (!$contract) throw new \RuntimeException("Contract not found: {$contractId}");

        $clientId = (int) $employee['client_id'];
        self::ensureDir($clientId);

        $mainDb  = HrDatabase::mainDbName();
        $company = HrDatabase::getInstance()->fetchOne(
            "SELECT company_name, nip, address_street, address_city, address_zip FROM `{$mainDb}`.clients WHERE id = ?",
            [$clientId]
        );
        if (!$company) throw new \RuntimeException("Client not found: {$clientId}");

        $year     = date('Y');
        $balances = HrLeaveBalance::findByEmployeeYear($employeeId, (int)$year);

        $startDate  = $contract['start_date'] ?? '';
        $endDate    = $employee['employment_end'] ?? $employee['archived_at'] ?? date('Y-m-d');
        $duration   = self::employmentDuration($startDate, $endDate);
        $contractTypeLabel = HrContract::getContractTypeLabel($contract['contract_type'] ?? 'uop');
        $workFractionLabel = self::fractionLabel((float)($contract['work_time_fraction'] ?? 1.0));
        $archiveReason = self::archiveReasonLabel($employee['archive_reason'] ?? '');

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Billu HR');
        $pdf->SetAuthor($company['company_name'] ?? 'Pracodawca');
        $pdf->SetTitle('Świadectwo pracy');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();

        $html = self::buildHtml($company, $employee, $contract, $contractTypeLabel, $workFractionLabel, $startDate, $endDate, $duration, $archiveReason, $balances, $year);
        $pdf->writeHTML($html, true, false, true, false, '');

        $lastName = preg_replace('/[^a-zA-Z0-9]/', '_', $employee['last_name'] ?? 'pracownik');
        $filename = "swiadectwo_{$lastName}_{$employeeId}_" . date('Ymd') . '.pdf';
        $path     = self::$storageDir . '/' . $clientId . '/' . $filename;
        $pdf->Output($path, 'F');

        HrEmployee::setSwiadectwoPdfPath($employeeId, 'storage/hr/swiadectwa/' . $clientId . '/' . $filename);

        return $path;
    }

    private static function buildHtml(array $company, array $employee, array $contract, string $contractTypeLabel, string $workFractionLabel, string $startDate, string $endDate, string $duration, string $archiveReason, array $balances, string $year): string
    {
        $h = '';
        $h .= '<table border="0" width="100%"><tr>';
        $h .= '<td width="60%"><strong>' . htmlspecialchars($company['company_name']) . '</strong><br/>';
        if (!empty($company['address_street'])) {
            $h .= htmlspecialchars($company['address_street']) . '<br/>' . htmlspecialchars($company['address_zip'] . ' ' . $company['address_city']);
        }
        $h .= '</td>';
        $h .= '<td width="40%" align="right" style="font-size:9pt;color:#555;">NIP: ' . htmlspecialchars($company['nip'] ?? '—') . '</td>';
        $h .= '</tr></table><br/>';

        $h .= '<h2 style="text-align:center;font-size:14pt;margin:10px 0;">ŚWIADECTWO PRACY</h2>';
        $h .= '<p style="text-align:center;font-size:9pt;">(art. 97 Kodeksu Pracy)</p><br/>';

        $h .= '<p><strong>1. Pracodawca:</strong><br/>' . htmlspecialchars($company['company_name']);
        if (!empty($company['address_street'])) $h .= ', ' . htmlspecialchars($company['address_street'] . ', ' . $company['address_zip'] . ' ' . $company['address_city']);
        $h .= '</p>';

        $h .= '<p><strong>2. Pracownik:</strong><br/>' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '<br/>';
        if (!empty($employee['birth_date'])) $h .= 'Data urodzenia: ' . htmlspecialchars($employee['birth_date']) . '<br/>';
        if (!empty($employee['pesel'])) $h .= 'PESEL: ' . htmlspecialchars($employee['pesel']);
        $h .= '</p>';

        $h .= '<p><strong>3. Okres zatrudnienia:</strong><br/>od ' . htmlspecialchars($startDate) . ' do ' . htmlspecialchars($endDate) . ' (' . htmlspecialchars($duration) . ')</p>';

        $h .= '<p><strong>4. Stanowisko / rodzaj pracy:</strong><br/>' . htmlspecialchars($contract['position'] ?? '—') . '<br/>Wymiar czasu pracy: ' . htmlspecialchars($workFractionLabel) . '</p>';

        $h .= '<p><strong>5. Rodzaj umowy:</strong><br/>' . htmlspecialchars($contractTypeLabel) . '</p>';

        $h .= '<p><strong>6. Sposób rozwiązania stosunku pracy:</strong><br/>' . htmlspecialchars($archiveReason ?: '—') . '</p>';

        $h .= '<p><strong>7. Urlopy i inne należności:</strong></p>';
        if (!empty($balances)) {
            $h .= '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:9pt;">';
            $h .= '<tr style="background:#f0f0f0;"><th>Rodzaj urlopu</th><th>Wymiar</th><th>Wykorzystano</th><th>Pozostało</th></tr>';
            foreach ($balances as $b) {
                $remaining = max(0, (float)($b['remaining_days'] ?? 0));
                $h .= '<tr><td>' . htmlspecialchars($b['name_pl'] ?? '—') . '</td><td align="center">' . (float)$b['limit_days'] . ' dni</td><td align="center">' . (float)$b['used_days'] . ' dni</td><td align="center">' . $remaining . ' dni</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<p style="font-size:9pt;color:#555;">Brak danych o urlopach za rok ' . $year . '.</p>';
        }

        $h .= '<br/><p><strong>8. Inne informacje:</strong><br/>Dokument wystawiony na podstawie art. 97 ustawy z dnia 26 czerwca 1974 r. Kodeks pracy.</p>';

        $h .= '<br/><br/><table border="0" width="100%"><tr>';
        $h .= '<td width="50%" align="center">Data wystawienia:<br/><br/>' . date('d.m.Y') . '</td>';
        $h .= '<td width="50%" align="center">Podpis pracodawcy:<br/><br/>.....................................</td>';
        $h .= '</tr></table>';

        return $h;
    }

    private static function ensureDir(int $clientId): void
    {
        $dir = self::$storageDir . '/' . $clientId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    private static function employmentDuration(string $start, string $end): string
    {
        if (!$start || !$end) return '—';
        try {
            $s = new \DateTime($start);
            $e = new \DateTime($end);
            $diff = $s->diff($e);
            $parts = [];
            if ($diff->y > 0) $parts[] = $diff->y . ' ' . ($diff->y === 1 ? 'rok' : ($diff->y < 5 ? 'lata' : 'lat'));
            if ($diff->m > 0) $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'miesiąc' : ($diff->m < 5 ? 'miesiące' : 'miesięcy'));
            if ($diff->d > 0) $parts[] = $diff->d . ' ' . ($diff->d === 1 ? 'dzień' : 'dni');
            return implode(', ', $parts) ?: '0 dni';
        } catch (\Throwable $e) { return '—'; }
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

    private static function archiveReasonLabel(string $reason): string
    {
        return match($reason) {
            'end_of_contract' => 'Wygaśnięcie umowy o pracę',
            'resignation'     => 'Rozwiązanie umowy przez pracownika (wypowiedzenie)',
            'dismissal'       => 'Rozwiązanie umowy przez pracodawcę (wypowiedzenie)',
            'other'           => 'Inne (porozumienie stron)',
            default           => 'Rozwiązanie stosunku pracy',
        };
    }
}
