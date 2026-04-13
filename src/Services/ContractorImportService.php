<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Contractor;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContractorImportService
{
    public static function import(string $filePath, int $clientId): array
    {
        $result = ['success' => 0, 'skipped' => 0, 'errors' => []];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        try {
            if (in_array($ext, ['xlsx', 'xls'])) {
                $rows = self::parseExcel($filePath);
            } else {
                $rows = self::parseCsv($filePath);
            }
        } catch (\Throwable $e) {
            $result['errors'][] = 'Błąd odczytu pliku: ' . $e->getMessage();
            return $result;
        }

        if (empty($rows)) {
            $result['errors'][] = 'Plik nie zawiera danych.';
            return $result;
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rowNum = $i + 2; // +2: 1-based + header row
                $nip = self::cleanNip($row['nip'] ?? '');
                $companyName = trim($row['company_name'] ?? '');

                if ($companyName === '') {
                    $result['errors'][] = "Wiersz {$rowNum}: brak nazwy firmy";
                    continue;
                }

                // Duplicate check by NIP
                if ($nip !== '') {
                    $existing = Contractor::findByClientAndNip($clientId, $nip);
                    if ($existing) {
                        $result['skipped']++;
                        continue;
                    }
                }

                Contractor::create($clientId, [
                    'company_name' => $companyName,
                    'nip' => $nip,
                    'address_street' => trim($row['street'] ?? ''),
                    'address_postal' => trim($row['postal'] ?? ''),
                    'address_city' => trim($row['city'] ?? ''),
                    'email' => trim($row['email'] ?? ''),
                    'phone' => trim($row['phone'] ?? ''),
                    'contact_person' => trim($row['contact_person'] ?? ''),
                    'default_payment_days' => !empty($row['payment_days']) ? (int) $row['payment_days'] : null,
                ]);
                $result['success']++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $result['errors'][] = 'Błąd bazy danych: ' . $e->getMessage();
        }

        return $result;
    }

    private static function parseExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $allRows = $sheet->toArray(null, true, true, true);

        if (count($allRows) < 2) return [];

        $headers = self::mapHeaders(array_values(array_shift($allRows)));
        $data = [];
        foreach ($allRows as $row) {
            $vals = array_values($row);
            $mapped = self::mapRow($headers, $vals);
            if ($mapped) $data[] = $mapped;
        }
        return $data;
    }

    private static function parseCsv(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r?\n/', $content);
        $lines = array_filter($lines, fn($l) => trim($l) !== '');

        if (count($lines) < 2) return [];

        $firstLine = $lines[0];
        $delimiter = str_contains($firstLine, "\t") ? "\t" : (str_contains($firstLine, ';') ? ';' : ',');

        $headerLine = array_shift($lines);
        $headers = self::mapHeaders(str_getcsv($headerLine, $delimiter));

        $data = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line, $delimiter);
            $mapped = self::mapRow($headers, $cols);
            if ($mapped) $data[] = $mapped;
        }
        return $data;
    }

    private static function mapHeaders(array $raw): array
    {
        $map = [];
        $aliases = [
            'nip' => 'nip',
            'company_name' => 'company_name', 'nazwa firmy' => 'company_name', 'nazwa' => 'company_name', 'company name' => 'company_name',
            'ulica' => 'street', 'street' => 'street', 'adres' => 'street', 'address' => 'street',
            'kod pocztowy' => 'postal', 'kod' => 'postal', 'postal' => 'postal', 'postal code' => 'postal',
            'miasto' => 'city', 'city' => 'city',
            'email' => 'email', 'e-mail' => 'email',
            'telefon' => 'phone', 'phone' => 'phone', 'tel' => 'phone',
            'osoba kontaktowa' => 'contact_person', 'contact' => 'contact_person', 'contact person' => 'contact_person', 'kontakt' => 'contact_person',
            'dni płatności' => 'payment_days', 'payment days' => 'payment_days', 'domyślne dni płatności' => 'payment_days',
        ];

        foreach ($raw as $i => $header) {
            $key = mb_strtolower(trim($header ?? ''));
            if (isset($aliases[$key])) {
                $map[$i] = $aliases[$key];
            }
        }
        return $map;
    }

    private static function mapRow(array $headers, array $values): ?array
    {
        $row = [];
        foreach ($headers as $i => $field) {
            $row[$field] = $values[$i] ?? '';
        }
        if (empty(trim($row['company_name'] ?? '')) && empty(trim($row['nip'] ?? ''))) {
            return null;
        }
        return $row;
    }

    private static function cleanNip(string $nip): string
    {
        return preg_replace('/[^0-9]/', '', $nip);
    }

    public static function generateTemplate(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kontrahenci');

        $headers = ['NIP', 'Nazwa firmy', 'Ulica', 'Kod pocztowy', 'Miasto', 'Email', 'Telefon', 'Osoba kontaktowa', 'Domyślne dni płatności'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i); // A, B, C...
            $sheet->setCellValue($col . '1', $h);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Example row
        $example = ['1234567890', 'Przykładowa Firma Sp. z o.o.', 'ul. Testowa 1', '00-001', 'Warszawa', 'kontakt@firma.pl', '123456789', 'Jan Kowalski', '14'];
        foreach ($example as $i => $val) {
            $sheet->setCellValue(chr(65 + $i) . '2', $val);
        }

        return $spreadsheet;
    }
}
