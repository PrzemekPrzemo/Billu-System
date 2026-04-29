<?php

namespace App\Services;

use App\Core\Database;
use App\Models\InvoiceBatch;
use App\Models\Setting;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportService
{
    /**
     * Import invoices from XLS/XLSX file for a given client.
     *
     * Expected columns:
     * A: Seller NIP
     * B: Seller Name
     * C: Seller Address
     * D: Seller Contact (optional)
     * E: Buyer NIP
     * F: Buyer Name
     * G: Buyer Address
     * H: Invoice Number
     * I: Issue Date (YYYY-MM-DD)
     * J: Sale Date (YYYY-MM-DD)
     * K: Currency
     * L: Net Amount
     * M: VAT Amount
     * N: Gross Amount
     * O: Line Items (optional, JSON or semicolon-separated)
     * P: VAT Details (optional, JSON)
     */
    public static function importFromExcel(
        string $filePath,
        int $clientId,
        int $importerId,
        int $month,
        int $year,
        string $importerType = 'admin',
        ?int $officeId = null
    ): array {
        $result = ['success' => 0, 'errors' => [], 'total' => 0];

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Skip header row
        $dataRows = array_slice($rows, 1, null, true);
        $result['total'] = count($dataRows);

        if ($result['total'] === 0) {
            $result['errors'][] = 'empty_file';
            return $result;
        }

        // Create or get batch
        $deadlineDate = self::calculateDeadlineDate($month, $year);

        $batch = InvoiceBatch::findByClientAndPeriod($clientId, $month, $year);
        if ($batch) {
            if ($batch['is_finalized']) {
                $result['errors'][] = 'batch_already_finalized';
                return $result;
            }
            $batchId = $batch['id'];
        } else {
            $batchId = InvoiceBatch::create([
                'client_id'             => $clientId,
                'office_id'             => $officeId,
                'period_month'          => $month,
                'period_year'           => $year,
                'import_filename'       => basename($filePath),
                'imported_by_type'      => $importerType,
                'imported_by_id'        => $importerId,
                'verification_deadline' => $deadlineDate,
            ]);
        }

        $db = Database::getInstance();
        $existing = self::loadExistingDuplicateKeys($clientId);
        $buffer   = [];
        $db->beginTransaction();

        try {
            $rowNum = 1;
            foreach ($dataRows as $row) {
                $rowNum++;

                // Validate required fields
                if (empty($row['H'])) {
                    $result['errors'][] = "Wiersz {$rowNum}: brak numeru faktury";
                    continue;
                }

                if (empty($row['A'])) {
                    $result['errors'][] = "Wiersz {$rowNum}: brak NIP sprzedawcy";
                    continue;
                }

                $issueDate = self::parseDate($row['I'] ?? '');
                if (!$issueDate) {
                    $result['errors'][] = "Wiersz {$rowNum}: nieprawidłowa data wystawienia";
                    continue;
                }

                $lineItems = null;
                if (!empty($row['O'])) {
                    $lineItems = self::isJson($row['O']) ? $row['O'] : json_encode(['raw' => $row['O']]);
                }

                $vatDetails = null;
                if (!empty($row['P'])) {
                    $vatDetails = self::isJson($row['P']) ? $row['P'] : json_encode(['raw' => $row['P']]);
                }

                $cleanSellerNip = self::cleanNip($row['A'] ?? '');
                $cleanInvoiceNumber = trim($row['H']);
                $cleanGrossAmount = self::parseAmount($row['N'] ?? 0);

                $dupKey = self::dupKey($cleanInvoiceNumber, $cleanSellerNip, $cleanGrossAmount);
                if (isset($existing[$dupKey])) {
                    $result['errors'][] = "Wiersz {$rowNum}: duplikat faktury {$cleanInvoiceNumber} (NIP: {$cleanSellerNip})";
                    $result['duplicates'] = ($result['duplicates'] ?? 0) + 1;
                    continue;
                }
                $existing[$dupKey] = true;

                $buffer[] = [
                    'batch_id'       => $batchId,
                    'client_id'      => $clientId,
                    'seller_nip'     => $cleanSellerNip,
                    'seller_name'    => trim($row['B'] ?? ''),
                    'seller_address' => trim($row['C'] ?? ''),
                    'seller_contact' => trim($row['D'] ?? ''),
                    'buyer_nip'      => self::cleanNip($row['E'] ?? ''),
                    'buyer_name'     => trim($row['F'] ?? ''),
                    'buyer_address'  => trim($row['G'] ?? ''),
                    'invoice_number' => $cleanInvoiceNumber,
                    'issue_date'     => $issueDate,
                    'sale_date'      => self::parseDate($row['J'] ?? '') ?: null,
                    'currency'       => strtoupper(trim($row['K'] ?? 'PLN')) ?: 'PLN',
                    'net_amount'     => self::parseAmount($row['L'] ?? 0),
                    'vat_amount'     => self::parseAmount($row['M'] ?? 0),
                    'gross_amount'   => $cleanGrossAmount,
                    'line_items'     => $lineItems,
                    'vat_details'    => $vatDetails,
                ];
                $result['success']++;

                if (count($buffer) >= 500) {
                    $db->bulkInsert('invoices', $buffer);
                    $buffer = [];
                }
            }

            if (!empty($buffer)) {
                $db->bulkInsert('invoices', $buffer);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $result['errors'][] = 'db_error: ' . $e->getMessage();
            $result['success']  = 0;
        }

        return $result;
    }

    /**
     * Import invoices from TXT/CSV file.
     * Expected: tab or semicolon separated, same column order as Excel.
     */
    public static function importFromText(
        string $filePath,
        int $clientId,
        int $importerId,
        int $month,
        int $year,
        string $importerType = 'admin',
        ?int $officeId = null
    ): array {
        $result = ['success' => 0, 'errors' => [], 'total' => 0];

        $content = file_get_contents($filePath);
        if ($content === false) {
            $result['errors'][] = 'file_read_error';
            return $result;
        }

        $lines = array_filter(explode("\n", $content), fn($l) => trim($l) !== '');

        // Detect delimiter
        $firstLine = $lines[array_key_first($lines)] ?? '';
        $delimiter = str_contains($firstLine, "\t") ? "\t" : ';';

        // Skip header
        array_shift($lines);
        $result['total'] = count($lines);

        if ($result['total'] === 0) {
            $result['errors'][] = 'empty_file';
            return $result;
        }

        $deadlineDate = self::calculateDeadlineDate($month, $year);

        $batch = InvoiceBatch::findByClientAndPeriod($clientId, $month, $year);
        if ($batch) {
            if ($batch['is_finalized']) {
                $result['errors'][] = 'batch_already_finalized';
                return $result;
            }
            $batchId = $batch['id'];
        } else {
            $batchId = InvoiceBatch::create([
                'client_id'             => $clientId,
                'office_id'             => $officeId,
                'period_month'          => $month,
                'period_year'           => $year,
                'import_filename'       => basename($filePath),
                'imported_by_type'      => $importerType,
                'imported_by_id'        => $importerId,
                'verification_deadline' => $deadlineDate,
            ]);
        }

        $db = Database::getInstance();
        $existing = self::loadExistingDuplicateKeys($clientId);
        $buffer   = [];
        $db->beginTransaction();

        try {
            $rowNum = 1;
            foreach ($lines as $line) {
                $rowNum++;
                $cols = str_getcsv($line, $delimiter);

                if (count($cols) < 14) {
                    $result['errors'][] = "Wiersz {$rowNum}: za mało kolumn (" . count($cols) . ")";
                    continue;
                }

                if (empty($cols[7])) {
                    $result['errors'][] = "Wiersz {$rowNum}: brak numeru faktury";
                    continue;
                }

                $issueDate = self::parseDate($cols[8] ?? '');
                if (!$issueDate) {
                    $result['errors'][] = "Wiersz {$rowNum}: nieprawidłowa data wystawienia";
                    continue;
                }

                $cleanSellerNip = self::cleanNip($cols[0] ?? '');
                $cleanInvoiceNumber = trim($cols[7]);
                $cleanGrossAmount = self::parseAmount($cols[13] ?? 0);

                $dupKey = self::dupKey($cleanInvoiceNumber, $cleanSellerNip, $cleanGrossAmount);
                if (isset($existing[$dupKey])) {
                    $result['errors'][] = "Wiersz {$rowNum}: duplikat faktury {$cleanInvoiceNumber} (NIP: {$cleanSellerNip})";
                    $result['duplicates'] = ($result['duplicates'] ?? 0) + 1;
                    continue;
                }
                $existing[$dupKey] = true;

                $buffer[] = [
                    'batch_id'       => $batchId,
                    'client_id'      => $clientId,
                    'seller_nip'     => $cleanSellerNip,
                    'seller_name'    => trim($cols[1] ?? ''),
                    'seller_address' => trim($cols[2] ?? ''),
                    'seller_contact' => trim($cols[3] ?? ''),
                    'buyer_nip'      => self::cleanNip($cols[4] ?? ''),
                    'buyer_name'     => trim($cols[5] ?? ''),
                    'buyer_address'  => trim($cols[6] ?? ''),
                    'invoice_number' => $cleanInvoiceNumber,
                    'issue_date'     => $issueDate,
                    'sale_date'      => self::parseDate($cols[9] ?? '') ?: null,
                    'currency'       => strtoupper(trim($cols[10] ?? 'PLN')) ?: 'PLN',
                    'net_amount'     => self::parseAmount($cols[11] ?? 0),
                    'vat_amount'     => self::parseAmount($cols[12] ?? 0),
                    'gross_amount'   => $cleanGrossAmount,
                    'line_items'     => isset($cols[14]) && $cols[14] !== '' ? json_encode(['raw' => $cols[14]]) : null,
                    'vat_details'    => isset($cols[15]) && $cols[15] !== '' ? json_encode(['raw' => $cols[15]]) : null,
                ];
                $result['success']++;

                if (count($buffer) >= 500) {
                    $db->bulkInsert('invoices', $buffer);
                    $buffer = [];
                }
            }

            if (!empty($buffer)) {
                $db->bulkInsert('invoices', $buffer);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $result['errors'][] = 'db_error: ' . $e->getMessage();
            $result['success']  = 0;
        }

        return $result;
    }

    private static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) {
            return null;
        }

        // Try YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Try DD.MM.YYYY or DD/MM/YYYY
        if (preg_match('#^(\d{2})[./](\d{2})[./](\d{4})$#', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Try Excel serial date
        if (is_numeric($value)) {
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value);
            return $date->format('Y-m-d');
        }

        return null;
    }

    private static function parseAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        $cleaned = str_replace([' ', ','], ['', '.'], (string) $value);
        return round((float) $cleaned, 2);
    }

    private static function cleanNip(string $nip): string
    {
        return preg_replace('/[^0-9]/', '', $nip);
    }

    private static function isJson(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Wczytuje wszystkie istniejące tuple (invoice_number, seller_nip, gross_amount)
     * dla klienta do tablicy-Setu. Pojedyncze zapytanie zamiast N zapytań w pętli.
     *
     * @return array<string,bool> klucz = "invoiceNumber|sellerNip|gross"
     */
    private static function loadExistingDuplicateKeys(int $clientId): array
    {
        $rows = Database::getInstance()->fetchAll(
            "SELECT i.invoice_number, i.seller_nip, i.gross_amount
               FROM invoices i
               JOIN invoice_batches ib ON i.batch_id = ib.id
              WHERE ib.client_id = ?",
            [$clientId]
        );
        $set = [];
        foreach ($rows as $r) {
            $set[self::dupKey(
                (string)$r['invoice_number'],
                (string)$r['seller_nip'],
                (float)$r['gross_amount']
            )] = true;
        }
        return $set;
    }

    private static function dupKey(string $invoiceNumber, string $sellerNip, float $grossAmount): string
    {
        return $invoiceNumber . '|' . $sellerNip . '|' . number_format($grossAmount, 2, '.', '');
    }

    /**
     * Generate an Excel import template with headers and example row.
     * Returns path to the generated XLSX file.
     */
    public static function generateImportTemplate(): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Szablon importu faktur');

        $headers = [
            'A1' => 'NIP Sprzedawcy',
            'B1' => 'Nazwa Sprzedawcy',
            'C1' => 'Adres Sprzedawcy',
            'D1' => 'Kontakt Sprzedawcy',
            'E1' => 'NIP Nabywcy',
            'F1' => 'Nazwa Nabywcy',
            'G1' => 'Adres Nabywcy',
            'H1' => 'Numer Faktury',
            'I1' => 'Data Wystawienia',
            'J1' => 'Data Sprzedazy',
            'K1' => 'Waluta',
            'L1' => 'Kwota Netto',
            'M1' => 'Kwota VAT',
            'N1' => 'Kwota Brutto',
            'O1' => 'Pozycje (JSON, opcjonalnie)',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B7285']],
            'borders' => ['bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);

        // Example row
        $example = [
            'A2' => '1234567890',
            'B2' => 'ABC Sp. z o.o.',
            'C2' => 'ul. Kwiatowa 5, 00-001 Warszawa',
            'D2' => 'info@abc.pl',
            'E2' => '9876543210',
            'F2' => 'XYZ S.A.',
            'G2' => 'ul. Lesna 10, 30-001 Krakow',
            'H2' => 'FV/2025/001',
            'I2' => '2025-01-15',
            'J2' => '2025-01-14',
            'K2' => 'PLN',
            'L2' => '1000.00',
            'M2' => '230.00',
            'N2' => '1230.00',
            'O2' => '',
        ];

        foreach ($example as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $exampleStyle = ['font' => ['italic' => true, 'color' => ['rgb' => '999999']]];
        $sheet->getStyle('A2:O2')->applyFromArray($exampleStyle);

        // Auto-size columns
        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Data format hints
        $sheet->getComment('I1')->getText()->createTextRun('Format: RRRR-MM-DD (np. 2025-01-15)');
        $sheet->getComment('L1')->getText()->createTextRun('Separator dziesietny: kropka (np. 1000.00)');

        $path = __DIR__ . '/../../storage/exports/szablon_import_faktur.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * Calculate verification deadline: day X of the NEXT month after the batch period.
     * E.g. batch for January 2025 → deadline 25 February 2025.
     */
    public static function calculateDeadlineDate(int $month, int $year): string
    {
        $deadlineDay = Setting::getVerificationDeadlineDay();

        // Next month after batch period
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear++;
        }

        $maxDay = cal_days_in_month(CAL_GREGORIAN, $nextMonth, $nextYear);
        return sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, min($deadlineDay, $maxDay));
    }
}
