<?php

namespace App\Services;

use App\Core\Database;
use App\Models\PayrollDeclaration;
use App\Models\PayrollEntry;
use App\Models\ClientEmployee;
use App\Models\Client;

class PayrollDeclarationService
{
    /**
     * Generate PIT-11 declaration for an employee (annual).
     */
    public static function generatePit11(int $clientId, int $employeeId, int $year): ?int
    {
        $employee = ClientEmployee::findById($employeeId);
        $client = Client::findById($clientId);
        if (!$employee || !$client) return null;

        // Get all payroll entries for this employee in the year
        $db = Database::getInstance();
        $entries = $db->fetchAll(
            "SELECT pe.* FROM payroll_entries pe
             INNER JOIN payroll_lists pl ON pl.id = pe.payroll_list_id
             WHERE pe.employee_id = ? AND pl.year = ?
             ORDER BY pl.month ASC",
            [$employeeId, $year]
        );

        // Aggregate annual totals
        $totalGross = 0;
        $totalZusEmployee = 0;
        $totalHealth = 0;
        $totalKup = 0;
        $totalPit = 0;

        foreach ($entries as $e) {
            $totalGross += (float)$e['total_gross'];
            $totalZusEmployee += (float)$e['zus_total_employee'];
            $totalHealth += (float)$e['health_insurance_full'];
            $totalKup += (float)$e['tax_deductible_costs'];
            $totalPit += (float)$e['pit_advance'];
        }

        $taxBase = max(0, round($totalGross - $totalZusEmployee - $totalKup, 0));

        $xmlContent = self::buildPit11Xml($client, $employee, $year, [
            'total_gross' => round($totalGross, 2),
            'total_zus_employee' => round($totalZusEmployee, 2),
            'total_health' => round($totalHealth, 2),
            'total_kup' => round($totalKup, 2),
            'tax_base' => $taxBase,
            'total_pit' => round($totalPit, 2),
        ]);

        return PayrollDeclaration::create([
            'client_id' => $clientId,
            'declaration_type' => 'PIT-11',
            'year' => $year,
            'employee_id' => $employeeId,
            'status' => 'generated',
            'xml_content' => $xmlContent,
            'generated_at' => date('Y-m-d H:i:s'),
            'created_by_type' => 'office',
        ]);
    }

    /**
     * Generate PIT-4R declaration for employer (annual, all employees).
     */
    public static function generatePit4r(int $clientId, int $year): ?int
    {
        $db = Database::getInstance();

        // Monthly breakdown of PIT advances
        $monthly = $db->fetchAll(
            "SELECT pl.month,
                    SUM(pe.pit_advance) as total_pit,
                    COUNT(DISTINCT pe.employee_id) as employee_count
             FROM payroll_entries pe
             INNER JOIN payroll_lists pl ON pl.id = pe.payroll_list_id
             WHERE pe.client_id = ? AND pl.year = ?
             GROUP BY pl.month
             ORDER BY pl.month ASC",
            [$clientId, $year]
        );

        $client = Client::findById($clientId);
        if (!$client) return null;

        $xmlContent = self::buildPit4rXml($client, $year, $monthly);

        return PayrollDeclaration::create([
            'client_id' => $clientId,
            'declaration_type' => 'PIT-4R',
            'year' => $year,
            'status' => 'generated',
            'xml_content' => $xmlContent,
            'generated_at' => date('Y-m-d H:i:s'),
            'created_by_type' => 'office',
        ]);
    }

    /**
     * Generate ZUS DRA declaration (monthly).
     */
    public static function generateZusDra(int $clientId, int $year, int $month): ?int
    {
        $db = Database::getInstance();
        $client = Client::findById($clientId);
        if (!$client) return null;

        $totals = $db->fetchOne(
            "SELECT COUNT(DISTINCT pe.employee_id) as employee_count,
                    SUM(pe.zus_emerytalna_employee + pe.zus_emerytalna_employer) as emerytalna,
                    SUM(pe.zus_rentowa_employee + pe.zus_rentowa_employer) as rentowa,
                    SUM(pe.zus_chorobowa_employee) as chorobowa,
                    SUM(pe.zus_wypadkowa_employer) as wypadkowa,
                    SUM(pe.zus_fp_employer) as fp,
                    SUM(pe.zus_fgsp_employer) as fgsp,
                    SUM(pe.health_insurance_full) as health
             FROM payroll_entries pe
             INNER JOIN payroll_lists pl ON pl.id = pe.payroll_list_id
             WHERE pe.client_id = ? AND pl.year = ? AND pl.month = ?",
            [$clientId, $year, $month]
        );

        $xmlContent = self::buildZusDraXml($client, $year, $month, $totals ?: []);

        return PayrollDeclaration::create([
            'client_id' => $clientId,
            'declaration_type' => 'ZUS-DRA',
            'year' => $year,
            'month' => $month,
            'status' => 'generated',
            'xml_content' => $xmlContent,
            'generated_at' => date('Y-m-d H:i:s'),
            'created_by_type' => 'office',
        ]);
    }

    /**
     * Generate ZUS RCA declaration (monthly, per-employee).
     */
    public static function generateZusRca(int $clientId, int $year, int $month): ?int
    {
        $db = Database::getInstance();
        $client = Client::findById($clientId);
        if (!$client) return null;

        $entries = $db->fetchAll(
            "SELECT pe.*, ce.first_name, ce.last_name, ce.pesel
             FROM payroll_entries pe
             INNER JOIN payroll_lists pl ON pl.id = pe.payroll_list_id
             INNER JOIN client_employees ce ON ce.id = pe.employee_id
             WHERE pe.client_id = ? AND pl.year = ? AND pl.month = ?",
            [$clientId, $year, $month]
        );

        $xmlContent = self::buildZusRcaXml($client, $year, $month, $entries);

        return PayrollDeclaration::create([
            'client_id' => $clientId,
            'declaration_type' => 'ZUS-RCA',
            'year' => $year,
            'month' => $month,
            'status' => 'generated',
            'xml_content' => $xmlContent,
            'generated_at' => date('Y-m-d H:i:s'),
            'created_by_type' => 'office',
        ]);
    }

    // ── XML builders ───────────────────────────────────────

    private static function buildPit11Xml(array $client, array $employee, int $year, array $totals): string
    {
        $empName = htmlspecialchars(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
        $companyName = htmlspecialchars($client['company_name'] ?? $client['name'] ?? '');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Deklaracja xmlns="http://crd.gov.pl/wzor/2024/12/16/13798/">' . "\n"
            . '  <Naglowek><KodFormularza>PIT-11</KodFormularza><WariantFormularza>30</WariantFormularza>'
            . '<Rok>' . $year . '</Rok></Naglowek>' . "\n"
            . '  <Podmiot><NIP>' . htmlspecialchars($client['nip'] ?? '') . '</NIP>'
            . '<Nazwa>' . $companyName . '</Nazwa></Podmiot>' . "\n"
            . '  <Podatnik><Imie>' . htmlspecialchars($employee['first_name'] ?? '') . '</Imie>'
            . '<Nazwisko>' . htmlspecialchars($employee['last_name'] ?? '') . '</Nazwisko>'
            . '<PESEL>' . htmlspecialchars($employee['pesel'] ?? '') . '</PESEL></Podatnik>' . "\n"
            . '  <PozycjeSzczegolowe>' . "\n"
            . '    <Przychod>' . number_format($totals['total_gross'], 2, '.', '') . '</Przychod>' . "\n"
            . '    <SkladkiZUS>' . number_format($totals['total_zus_employee'], 2, '.', '') . '</SkladkiZUS>' . "\n"
            . '    <KosztyUzyskania>' . number_format($totals['total_kup'], 2, '.', '') . '</KosztyUzyskania>' . "\n"
            . '    <PodstawaOpodatkowania>' . number_format($totals['tax_base'], 2, '.', '') . '</PodstawaOpodatkowania>' . "\n"
            . '    <ZaliczkaPIT>' . number_format($totals['total_pit'], 2, '.', '') . '</ZaliczkaPIT>' . "\n"
            . '    <SkladkaZdrowotna>' . number_format($totals['total_health'], 2, '.', '') . '</SkladkaZdrowotna>' . "\n"
            . '  </PozycjeSzczegolowe>' . "\n"
            . '</Deklaracja>';
    }

    private static function buildPit4rXml(array $client, int $year, array $monthly): string
    {
        $companyName = htmlspecialchars($client['company_name'] ?? $client['name'] ?? '');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Deklaracja xmlns="http://crd.gov.pl/wzor/2024/12/16/13798/">' . "\n"
            . '  <Naglowek><KodFormularza>PIT-4R</KodFormularza><Rok>' . $year . '</Rok></Naglowek>' . "\n"
            . '  <Podmiot><NIP>' . htmlspecialchars($client['nip'] ?? '') . '</NIP>'
            . '<Nazwa>' . $companyName . '</Nazwa></Podmiot>' . "\n"
            . '  <Miesiace>' . "\n";

        foreach ($monthly as $m) {
            $xml .= '    <Miesiac nr="' . $m['month'] . '">'
                . '<LiczbaPodatnikow>' . ($m['employee_count'] ?? 0) . '</LiczbaPodatnikow>'
                . '<ZaliczkaPIT>' . number_format((float)($m['total_pit'] ?? 0), 2, '.', '') . '</ZaliczkaPIT>'
                . '</Miesiac>' . "\n";
        }

        $xml .= '  </Miesiace>' . "\n" . '</Deklaracja>';
        return $xml;
    }

    private static function buildZusDraXml(array $client, int $year, int $month, array $totals): string
    {
        $companyName = htmlspecialchars($client['company_name'] ?? $client['name'] ?? '');
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<KEDU>' . "\n"
            . '  <ZUS_DRA>' . "\n"
            . '    <Rok>' . $year . '</Rok><Miesiac>' . $month . '</Miesiac>' . "\n"
            . '    <Platnik><NIP>' . htmlspecialchars($client['nip'] ?? '') . '</NIP>'
            . '<Nazwa>' . $companyName . '</Nazwa></Platnik>' . "\n"
            . '    <LiczbaUbezpieczonych>' . ($totals['employee_count'] ?? 0) . '</LiczbaUbezpieczonych>' . "\n"
            . '    <Emerytalna>' . number_format((float)($totals['emerytalna'] ?? 0), 2, '.', '') . '</Emerytalna>' . "\n"
            . '    <Rentowa>' . number_format((float)($totals['rentowa'] ?? 0), 2, '.', '') . '</Rentowa>' . "\n"
            . '    <Chorobowa>' . number_format((float)($totals['chorobowa'] ?? 0), 2, '.', '') . '</Chorobowa>' . "\n"
            . '    <Wypadkowa>' . number_format((float)($totals['wypadkowa'] ?? 0), 2, '.', '') . '</Wypadkowa>' . "\n"
            . '    <FP>' . number_format((float)($totals['fp'] ?? 0), 2, '.', '') . '</FP>' . "\n"
            . '    <FGSP>' . number_format((float)($totals['fgsp'] ?? 0), 2, '.', '') . '</FGSP>' . "\n"
            . '    <Zdrowotna>' . number_format((float)($totals['health'] ?? 0), 2, '.', '') . '</Zdrowotna>' . "\n"
            . '  </ZUS_DRA>' . "\n"
            . '</KEDU>';
    }

    private static function buildZusRcaXml(array $client, int $year, int $month, array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<KEDU>' . "\n"
            . '  <ZUS_RCA Rok="' . $year . '" Miesiac="' . $month . '">' . "\n";

        foreach ($entries as $e) {
            $xml .= '    <Ubezpieczony>' . "\n"
                . '      <PESEL>' . htmlspecialchars($e['pesel'] ?? '') . '</PESEL>' . "\n"
                . '      <Imie>' . htmlspecialchars($e['first_name'] ?? '') . '</Imie>' . "\n"
                . '      <Nazwisko>' . htmlspecialchars($e['last_name'] ?? '') . '</Nazwisko>' . "\n"
                . '      <PodstawaWymiaru>' . number_format((float)$e['total_gross'], 2, '.', '') . '</PodstawaWymiaru>' . "\n"
                . '      <EmerytalnaP>' . number_format((float)$e['zus_emerytalna_employee'], 2, '.', '') . '</EmerytalnaP>' . "\n"
                . '      <EmerytalnaF>' . number_format((float)$e['zus_emerytalna_employer'], 2, '.', '') . '</EmerytalnaF>' . "\n"
                . '      <RentowaP>' . number_format((float)$e['zus_rentowa_employee'], 2, '.', '') . '</RentowaP>' . "\n"
                . '      <RentowaF>' . number_format((float)$e['zus_rentowa_employer'], 2, '.', '') . '</RentowaF>' . "\n"
                . '      <Chorobowa>' . number_format((float)$e['zus_chorobowa_employee'], 2, '.', '') . '</Chorobowa>' . "\n"
                . '      <Wypadkowa>' . number_format((float)$e['zus_wypadkowa_employer'], 2, '.', '') . '</Wypadkowa>' . "\n"
                . '      <PodstawaZdrow>' . number_format((float)$e['health_insurance_base'], 2, '.', '') . '</PodstawaZdrow>' . "\n"
                . '      <Zdrowotna>' . number_format((float)$e['health_insurance_full'], 2, '.', '') . '</Zdrowotna>' . "\n"
                . '    </Ubezpieczony>' . "\n";
        }

        $xml .= '  </ZUS_RCA>' . "\n" . '</KEDU>';
        return $xml;
    }
}
