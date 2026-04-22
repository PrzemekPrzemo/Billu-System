<?php

namespace App\Services;

use App\Core\Database;
use App\Core\HrDatabase;
use App\Models\HrPayrollItem;
use App\Models\HrPayrollRun;
use App\Models\HrZusDeclaration;
use App\Services\HrEncryptionService;

class HrZusDraService
{
    private static string $storageDir = __DIR__ . '/../../storage/hr/zus';

    public static function generate(int $clientId, int $runId): string
    {
        $run = HrPayrollRun::findById($runId);
        if (!$run || (int)$run['client_id'] !== $clientId) {
            throw new \RuntimeException('Payroll run not found or access denied');
        }
        if ($run['status'] === 'draft') {
            throw new \RuntimeException('Lista płac musi być co najmniej obliczona przed wygenerowaniem deklaracji ZUS');
        }

        $items = HrPayrollItem::findByRun($runId);
        if (empty($items)) {
            throw new \RuntimeException('Brak pozycji listy płac');
        }

        $hrDb = HrDatabase::hrDbName();
        $client = Database::getInstance()->fetchOne(
            "SELECT c.*, hs.zus_payer_nip, hs.zus_payer_name
             FROM clients c
             LEFT JOIN {$hrDb}.hr_client_settings hs ON hs.client_id = c.id
             WHERE c.id = ?",
            [$clientId]
        );
        if (!$client) throw new \RuntimeException('Client not found');

        $payerNip  = $client['zus_payer_nip']  ?: $client['nip'];
        $payerName = $client['zus_payer_name'] ?: $client['company_name'];

        self::ensureDir();

        $month     = (int) $run['period_month'];
        $year      = (int) $run['period_year'];
        $periodStr = sprintf('%04d%02d', $year, $month);
        $filename  = sprintf('DRA_%s_%04d_%02d.xml', preg_replace('/[^0-9]/', '', $payerNip), $year, $month);
        $path      = self::$storageDir . '/' . $filename;

        $xml = self::buildXml($run, $items, $client, $payerNip, $payerName, $periodStr, $month, $year);
        file_put_contents($path, $xml);

        return $path;
    }

    private static function buildXml(array $run, array $items, array $client, string $payerNip, string $payerName, string $periodStr, int $month, int $year): string
    {
        $now = date('Y-m-d\TH:i:s');
        $payerNipClean = preg_replace('/[^0-9]/', '', $payerNip);

        $totalEmerEmp = 0.0; $totalRentEmp = 0.0; $totalChorEmp = 0.0;
        $totalEmerEr = 0.0; $totalRentEr = 0.0; $totalWypEr = 0.0;
        $totalFpEr = 0.0; $totalFgspEr = 0.0;
        $totalGross = 0.0; $totalZusEmp = 0.0; $totalZusEr = 0.0;

        foreach ($items as $item) {
            $totalEmerEmp += (float) $item['zus_emerytalne_emp'];
            $totalRentEmp += (float) $item['zus_rentowe_emp'];
            $totalChorEmp += (float) $item['zus_chorobowe_emp'];
            $totalEmerEr  += (float) $item['zus_emerytalne_emp2'];
            $totalRentEr  += (float) $item['zus_rentowe_emp2'];
            $totalWypEr   += (float) $item['zus_wypadkowe_emp2'];
            $totalFpEr    += (float) $item['zus_fp_emp2'];
            $totalFgspEr  += (float) $item['zus_fgsp_emp2'];
            $totalGross   += (float) $item['gross_salary'];
            $totalZusEmp  += (float) $item['zus_total_employee'];
            $totalZusEr   += (float) $item['zus_total_employer'];
        }

        $totalZusAll = round($totalZusEmp + $totalZusEr, 2);
        $fn = fn($v) => number_format((float)$v, 2, '.', '');
        $esc = fn(string $s) => htmlspecialchars($s, ENT_XML1, 'UTF-8');

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<dokumenty xmlns="http://www.zus.pl/2013/DRA" wersja="1.0">' . "\n";

        $xml .= '  <dokument typ="DRA">' . "\n";
        $xml .= '    <naglowek>' . "\n";
        $xml .= '      <nip-platnika>' . $esc($payerNipClean) . '</nip-platnika>' . "\n";
        $xml .= '      <nazwa-platnika>' . $esc($payerName) . '</nazwa-platnika>' . "\n";
        $xml .= '      <okres-rozliczeniowy>' . $periodStr . '</okres-rozliczeniowy>' . "\n";
        $xml .= '      <liczba-ubezpieczonych>' . count($items) . '</liczba-ubezpieczonych>' . "\n";
        $xml .= '    </naglowek>' . "\n";
        $xml .= '    <blok-I><NIP>' . $esc($payerNipClean) . '</NIP><nazwa-skrocona>' . $esc(mb_substr($payerName, 0, 50)) . '</nazwa-skrocona></blok-I>' . "\n";
        $xml .= '    <blok-III>' . "\n";
        $xml .= '      <podstawa-wymiaru-skladek-emerytalna-rentowa>' . $fn($totalGross) . '</podstawa-wymiaru-skladek-emerytalna-rentowa>' . "\n";
        $xml .= '      <skladki-na-ubezpieczenie-emerytalne>' . $fn($totalEmerEmp + $totalEmerEr) . '</skladki-na-ubezpieczenie-emerytalne>' . "\n";
        $xml .= '      <skladki-na-ubezpieczenie-rentowe>' . $fn($totalRentEmp + $totalRentEr) . '</skladki-na-ubezpieczenie-rentowe>' . "\n";
        $xml .= '      <skladki-na-ubezpieczenie-chorobowe>' . $fn($totalChorEmp) . '</skladki-na-ubezpieczenie-chorobowe>' . "\n";
        $xml .= '      <skladki-na-ubezpieczenie-wypadkowe>' . $fn($totalWypEr) . '</skladki-na-ubezpieczenie-wypadkowe>' . "\n";
        $xml .= '      <fundusz-pracy>' . $fn($totalFpEr) . '</fundusz-pracy>' . "\n";
        $xml .= '      <fgsp>' . $fn($totalFgspEr) . '</fgsp>' . "\n";
        $xml .= '      <suma-skladek-zus>' . $fn($totalZusAll) . '</suma-skladek-zus>' . "\n";
        $xml .= '    </blok-III>' . "\n";
        $xml .= '  </dokument>' . "\n\n";

        foreach ($items as $item) {
            $emp = HrDatabase::getInstance()->fetchOne("SELECT pesel, first_name, last_name FROM hr_employees WHERE id = ?", [$item['employee_id']]);
            $pesel = '';
            if ($emp) {
                $emp = HrEncryptionService::decryptFields($emp, ['pesel']);
                $pesel = $emp['pesel'] ?? '';
            }

            $xml .= '  <dokument typ="RCA">' . "\n";
            $xml .= '    <naglowek><nip-platnika>' . $esc($payerNipClean) . '</nip-platnika><okres-rozliczeniowy>' . $periodStr . '</okres-rozliczeniowy></naglowek>' . "\n";
            $xml .= '    <dane-ubezpieczonego><pesel>' . $esc($pesel) . '</pesel><imie>' . $esc($item['first_name'] ?? '') . '</imie><nazwisko>' . $esc($item['last_name'] ?? '') . '</nazwisko></dane-ubezpieczonego>' . "\n";
            $xml .= '    <dane-o-skladkach>' . "\n";
            $xml .= '      <wynagrodzenie-brutto>' . $fn($item['gross_salary']) . '</wynagrodzenie-brutto>' . "\n";
            $xml .= '      <skladka-emerytalna-pracownik>' . $fn($item['zus_emerytalne_emp']) . '</skladka-emerytalna-pracownik>' . "\n";
            $xml .= '      <skladka-emerytalna-pracodawca>' . $fn($item['zus_emerytalne_emp2']) . '</skladka-emerytalna-pracodawca>' . "\n";
            $xml .= '      <skladka-rentowa-pracownik>' . $fn($item['zus_rentowe_emp']) . '</skladka-rentowa-pracownik>' . "\n";
            $xml .= '      <skladka-rentowa-pracodawca>' . $fn($item['zus_rentowe_emp2']) . '</skladka-rentowa-pracodawca>' . "\n";
            $xml .= '      <skladka-chorobowa>' . $fn($item['zus_chorobowe_emp']) . '</skladka-chorobowa>' . "\n";
            $xml .= '      <skladka-wypadkowa>' . $fn($item['zus_wypadkowe_emp2']) . '</skladka-wypadkowa>' . "\n";
            $xml .= '      <fundusz-pracy>' . $fn($item['zus_fp_emp2']) . '</fundusz-pracy>' . "\n";
            $xml .= '      <fgsp>' . $fn($item['zus_fgsp_emp2']) . '</fgsp>' . "\n";
            $xml .= '      <zus-pracownik-lacznie>' . $fn($item['zus_total_employee']) . '</zus-pracownik-lacznie>' . "\n";
            $xml .= '      <zus-pracodawca-lacznie>' . $fn($item['zus_total_employer']) . '</zus-pracodawca-lacznie>' . "\n";
            $xml .= '    </dane-o-skladkach>' . "\n";
            $xml .= '  </dokument>' . "\n\n";
        }

        $xml .= '</dokumenty>' . "\n";
        return $xml;
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) mkdir(self::$storageDir, 0750, true);
    }
}
