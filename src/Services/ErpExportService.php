<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\Client;
use App\Core\Database;

class ErpExportService
{
    /**
     * Export invoices in Comarch Optima CSV format.
     * Semicolon-separated, Windows-1250 encoding.
     */
    public static function exportComarchOptima(int $batchId, bool $onlyAccepted = true): string
    {
        $invoices = self::getInvoices($batchId, $onlyAccepted);
        $batch = InvoiceBatch::findById($batchId);
        $client = $batch ? Client::findById($batch['client_id']) : null;

        if (empty($invoices)) {
            return '';
        }

        $headers = [
            'Lp', 'Typ dokumentu', 'Numer faktury', 'NrKSeF', 'Data wystawienia', 'Data sprzedaży',
            'NIP sprzedawcy', 'Nazwa sprzedawcy', 'Adres sprzedawcy',
            'Kwota netto', 'Stawka VAT', 'Kwota VAT', 'Kwota brutto',
            'Waluta', 'MPK'
        ];

        $rows = [];
        $lp = 1;

        foreach ($invoices as $inv) {
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                // One row per VAT rate
                foreach ($vatDetails as $vd) {
                    $rows[] = self::buildComarchRow($lp, $inv, $vd);
                }
            } else {
                // Single row with total amounts
                $rows[] = self::buildComarchRow($lp, $inv, [
                    'rate' => self::guessVatRate($inv),
                    'net' => $inv['net_amount'],
                    'vat' => $inv['vat_amount'],
                    'gross' => $inv['gross_amount'],
                ]);
            }
            $lp++;
        }

        $csv = self::buildCsv($headers, $rows, ';');
        $encoded = self::convertEncoding($csv, 'Windows-1250');

        $filename = self::buildFilename($batch, $client, 'comarch_optima', 'csv');
        return self::saveExport($filename, $encoded);
    }

    /**
     * Export invoices in Sage Symfonia FK CSV format.
     * Semicolon-separated, Windows-1250 encoding.
     */
    public static function exportSage(int $batchId, bool $onlyAccepted = true): string
    {
        $invoices = self::getInvoices($batchId, $onlyAccepted);
        $batch = InvoiceBatch::findById($batchId);
        $client = $batch ? Client::findById($batch['client_id']) : null;

        if (empty($invoices)) {
            return '';
        }

        $headers = [
            'Numer faktury', 'NrKSeF', 'Data wystawienia', 'NIP sprzedawcy', 'Nazwa sprzedawcy',
            'Adres sprzedawcy', 'Kwota netto', 'Kwota VAT', 'Kwota brutto',
            'Forma płatności', 'Waluta'
        ];

        $rows = [];
        foreach ($invoices as $inv) {
            $rows[] = [
                $inv['invoice_number'] ?? '',
                $inv['ksef_reference_number'] ?? '',
                self::formatDate($inv['issue_date'] ?? '', 'd.m.Y'),
                $inv['seller_nip'] ?? '',
                $inv['seller_name'] ?? '',
                $inv['seller_address'] ?? '',
                self::formatAmount($inv['net_amount'] ?? 0),
                self::formatAmount($inv['vat_amount'] ?? 0),
                self::formatAmount($inv['gross_amount'] ?? 0),
                'przelew',
                $inv['currency'] ?? 'PLN',
            ];
        }

        $csv = self::buildCsv($headers, $rows, ';');
        $encoded = self::convertEncoding($csv, 'Windows-1250');

        $filename = self::buildFilename($batch, $client, 'sage', 'csv');
        return self::saveExport($filename, $encoded);
    }

    /**
     * Export invoices in enova365 XML format.
     */
    public static function exportEnova(int $batchId, bool $onlyAccepted = true): string
    {
        $invoices = self::getInvoices($batchId, $onlyAccepted);
        $batch = InvoiceBatch::findById($batchId);
        $client = $batch ? Client::findById($batch['client_id']) : null;

        if (empty($invoices)) {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('DokumentyHandlowe');
        $root->setAttribute('xmlns', 'http://www.enova.pl/schema/import');
        $dom->appendChild($root);

        foreach ($invoices as $inv) {
            $doc = $dom->createElement('DokumentHandlowy');

            $doc->appendChild(self::xmlElement($dom, 'TypDokumentu', self::getDocumentType($inv)));
            $doc->appendChild(self::xmlElement($dom, 'NumerObcy', $inv['invoice_number'] ?? ''));
            if (!empty($inv['ksef_reference_number'])) {
                $doc->appendChild(self::xmlElement($dom, 'NrKSeF', $inv['ksef_reference_number']));
            }
            $doc->appendChild(self::xmlElement($dom, 'DataWystawienia', self::formatDate($inv['issue_date'] ?? '', 'Y-m-d')));
            $doc->appendChild(self::xmlElement($dom, 'DataSprzedazy', self::formatDate($inv['sale_date'] ?? $inv['issue_date'] ?? '', 'Y-m-d')));
            $doc->appendChild(self::xmlElement($dom, 'Waluta', $inv['currency'] ?? 'PLN'));

            // Kontrahent
            $kontrahent = $dom->createElement('Kontrahent');
            $kontrahent->appendChild(self::xmlElement($dom, 'NIP', $inv['seller_nip'] ?? ''));
            $kontrahent->appendChild(self::xmlElement($dom, 'Nazwa', $inv['seller_name'] ?? ''));
            $kontrahent->appendChild(self::xmlElement($dom, 'Adres', $inv['seller_address'] ?? ''));
            $doc->appendChild($kontrahent);

            // Pozycje — split by VAT rate
            $pozycje = $dom->createElement('Pozycje');
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $poz = $dom->createElement('Pozycja');
                    $poz->appendChild(self::xmlElement($dom, 'StawkaVAT', ($vd['rate'] ?? '23') . '%'));
                    $poz->appendChild(self::xmlElement($dom, 'Netto', self::formatAmountDot($vd['net'] ?? 0)));
                    $poz->appendChild(self::xmlElement($dom, 'VAT', self::formatAmountDot($vd['vat'] ?? 0)));
                    $poz->appendChild(self::xmlElement($dom, 'Brutto', self::formatAmountDot($vd['gross'] ?? 0)));
                    $pozycje->appendChild($poz);
                }
            } else {
                $poz = $dom->createElement('Pozycja');
                $poz->appendChild(self::xmlElement($dom, 'StawkaVAT', self::guessVatRate($inv) . '%'));
                $poz->appendChild(self::xmlElement($dom, 'Netto', self::formatAmountDot($inv['net_amount'] ?? 0)));
                $poz->appendChild(self::xmlElement($dom, 'VAT', self::formatAmountDot($inv['vat_amount'] ?? 0)));
                $poz->appendChild(self::xmlElement($dom, 'Brutto', self::formatAmountDot($inv['gross_amount'] ?? 0)));
                $pozycje->appendChild($poz);
            }
            $doc->appendChild($pozycje);

            $root->appendChild($doc);
        }

        $xml = $dom->saveXML();
        $filename = self::buildFilename($batch, $client, 'enova', 'xml');
        return self::saveExport($filename, $xml);
    }

    // ── InsERT GT / Nexo CSV ────────────────────────────

    /**
     * Export for InsERT GT / Subiekt / Nexo — semicolon-separated CSV, Windows-1250.
     */
    public static function exportInsertGt(int $batchId, bool $onlyAccepted = true): string
    {
        $invoices = self::getInvoices($batchId, $onlyAccepted);
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $headers = ['Typ', 'NrDokumentu', 'NrKSeF', 'DataWystawienia', 'DataOperacji', 'NIPKontrahenta',
                     'NazwaKontrahenta', 'AdresKontrahenta', 'OpisOperacji', 'KwotaNetto',
                     'StawkaVAT', 'KwotaVAT', 'KwotaBrutto', 'RodzajDokumentu'];

        $rows = [];
        foreach ($invoices as $inv) {
            $docType = self::getDocumentType($inv);
            $rodzaj = $docType === 'FK' ? 'KOR' : 'FV';
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $rows[] = [
                        'ZK', $inv['invoice_number'] ?? '', $inv['ksef_reference_number'] ?? '',
                        self::formatDate($inv['issue_date'] ?? '', 'd.m.Y'),
                        self::formatDate($inv['sale_date'] ?? $inv['issue_date'] ?? '', 'd.m.Y'),
                        $inv['seller_nip'] ?? '', $inv['seller_name'] ?? '', $inv['seller_address'] ?? '',
                        'Zakup - ' . ($inv['seller_name'] ?? ''),
                        self::formatAmount($vd['net'] ?? 0), ($vd['rate'] ?? '23') . '%',
                        self::formatAmount($vd['vat'] ?? 0), self::formatAmount($vd['gross'] ?? 0),
                        $rodzaj,
                    ];
                }
            } else {
                $rows[] = [
                    'ZK', $inv['invoice_number'] ?? '', $inv['ksef_reference_number'] ?? '',
                    self::formatDate($inv['issue_date'] ?? '', 'd.m.Y'),
                    self::formatDate($inv['sale_date'] ?? $inv['issue_date'] ?? '', 'd.m.Y'),
                    $inv['seller_nip'] ?? '', $inv['seller_name'] ?? '', $inv['seller_address'] ?? '',
                    'Zakup - ' . ($inv['seller_name'] ?? ''),
                    self::formatAmount($inv['net_amount'] ?? 0), self::guessVatRate($inv) . '%',
                    self::formatAmount($inv['vat_amount'] ?? 0), self::formatAmount($inv['gross_amount'] ?? 0),
                    $rodzaj,
                ];
            }
        }

        $csv = self::buildCsv($headers, $rows);
        $encoded = self::convertEncoding($csv, 'Windows-1250');
        $filename = self::buildFilename($batch, $client, 'insert_gt', 'csv');
        return self::saveExport($filename, $encoded);
    }

    // ── Rewizor GT / Rachmistrz GT CSV ──────────────────

    /**
     * Export for Rewizor GT / Rachmistrz GT — accounting-style CSV with debit/credit accounts.
     * Semicolon-separated, Windows-1250.
     */
    public static function exportRewizor(int $batchId, bool $onlyAccepted = true): string
    {
        $invoices = self::getInvoices($batchId, $onlyAccepted);
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $headers = ['LpDekretacji', 'DataDokumentu', 'DataOperacji', 'TypDokumentu', 'NumerDokumentu',
                     'NrKSeF', 'KontrahentNIP', 'KontrahentNazwa', 'OpisOperacji', 'KontoWn', 'KontoMa',
                     'Kwota', 'StawkaVAT'];

        $rows = [];
        $lp = 0;
        foreach ($invoices as $inv) {
            $docType = self::getDocumentType($inv);
            $typDok = $docType === 'FK' ? 'KOR' : 'FZ';
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    // Net amount row: debit costs (402), credit liabilities (201)
                    $rows[] = [
                        ++$lp,
                        self::formatDate($inv['issue_date'] ?? '', 'd.m.Y'),
                        self::formatDate($inv['sale_date'] ?? $inv['issue_date'] ?? '', 'd.m.Y'),
                        $typDok, $inv['invoice_number'] ?? '',
                        $inv['ksef_reference_number'] ?? '',
                        $inv['seller_nip'] ?? '', $inv['seller_name'] ?? '',
                        'Zakup - ' . ($inv['seller_name'] ?? ''),
                        '402', '201',
                        self::formatAmount($vd['net'] ?? 0),
                        ($vd['rate'] ?? '23') . '%',
                    ];
                    // VAT amount row: debit VAT input (221), credit liabilities (201)
                    if ((float)($vd['vat'] ?? 0) > 0) {
                        $rows[] = [
                            ++$lp,
                            self::formatDate($inv['issue_date'] ?? '', 'd.m.Y'),
                            self::formatDate($inv['sale_date'] ?? $inv['issue_date'] ?? '', 'd.m.Y'),
                            $typDok, $inv['invoice_number'] ?? '',
                            $inv['ksef_reference_number'] ?? '',
                            $inv['seller_nip'] ?? '', $inv['seller_name'] ?? '',
                            'VAT naliczony',
                            '221', '201',
                            self::formatAmount($vd['vat'] ?? 0),
                            ($vd['rate'] ?? '23') . '%',
                        ];
                    }
                }
            } else {
                $rows[] = [
                    ++$lp,
                    self::formatDate($inv['issue_date'] ?? '', 'd.m.Y'),
                    self::formatDate($inv['sale_date'] ?? $inv['issue_date'] ?? '', 'd.m.Y'),
                    $typDok, $inv['invoice_number'] ?? '',
                    $inv['ksef_reference_number'] ?? '',
                    $inv['seller_nip'] ?? '', $inv['seller_name'] ?? '',
                    'Zakup - ' . ($inv['seller_name'] ?? ''),
                    '402', '201',
                    self::formatAmount($inv['net_amount'] ?? 0),
                    self::guessVatRate($inv) . '%',
                ];
                if ((float)($inv['vat_amount'] ?? 0) > 0) {
                    $rows[] = [
                        ++$lp,
                        self::formatDate($inv['issue_date'] ?? '', 'd.m.Y'),
                        self::formatDate($inv['sale_date'] ?? $inv['issue_date'] ?? '', 'd.m.Y'),
                        $typDok, $inv['invoice_number'] ?? '',
                        $inv['ksef_reference_number'] ?? '',
                        $inv['seller_nip'] ?? '', $inv['seller_name'] ?? '',
                        'VAT naliczony',
                        '221', '201',
                        self::formatAmount($inv['vat_amount'] ?? 0),
                        self::guessVatRate($inv) . '%',
                    ];
                }
            }
        }

        $csv = self::buildCsv($headers, $rows);
        $encoded = self::convertEncoding($csv, 'Windows-1250');
        $filename = self::buildFilename($batch, $client, 'rewizor', 'csv');
        return self::saveExport($filename, $encoded);
    }

    // ── wFirma XML ──────────────────────────────────────

    /**
     * Export for wFirma.pl — purchase invoices as XML.
     */
    public static function exportWfirma(int $batchId, bool $onlyAccepted = true): string
    {
        $invoices = self::getInvoices($batchId, $onlyAccepted);
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('Wydatki');
        $root->setAttribute('generator', 'BiLLU');
        $root->setAttribute('data_eksportu', date('Y-m-d'));
        $dom->appendChild($root);

        foreach ($invoices as $inv) {
            $wydatek = $dom->createElement('Wydatek');
            $root->appendChild($wydatek);

            $wydatek->appendChild(self::xmlElement($dom, 'NrDokumentu', $inv['invoice_number'] ?? ''));
            $wydatek->appendChild(self::xmlElement($dom, 'DataWystawienia', $inv['issue_date'] ?? ''));
            $wydatek->appendChild(self::xmlElement($dom, 'DataSprzedazy', $inv['sale_date'] ?? $inv['issue_date'] ?? ''));
            $wydatek->appendChild(self::xmlElement($dom, 'TypDokumentu', self::getDocumentType($inv)));

            $kontrahent = $dom->createElement('Kontrahent');
            $kontrahent->appendChild(self::xmlElement($dom, 'NIP', $inv['seller_nip'] ?? ''));
            $kontrahent->appendChild(self::xmlElement($dom, 'Nazwa', $inv['seller_name'] ?? ''));
            $kontrahent->appendChild(self::xmlElement($dom, 'Adres', $inv['seller_address'] ?? ''));
            $wydatek->appendChild($kontrahent);

            $pozycje = $dom->createElement('Pozycje');
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $poz = $dom->createElement('Pozycja');
                    $poz->appendChild(self::xmlElement($dom, 'Netto', self::formatAmountDot($vd['net'] ?? 0)));
                    $poz->appendChild(self::xmlElement($dom, 'VAT', self::formatAmountDot($vd['vat'] ?? 0)));
                    $poz->appendChild(self::xmlElement($dom, 'Brutto', self::formatAmountDot($vd['gross'] ?? 0)));
                    $poz->appendChild(self::xmlElement($dom, 'StawkaVAT', ($vd['rate'] ?? '23') . '%'));
                    $pozycje->appendChild($poz);
                }
            } else {
                $poz = $dom->createElement('Pozycja');
                $poz->appendChild(self::xmlElement($dom, 'Netto', self::formatAmountDot($inv['net_amount'] ?? 0)));
                $poz->appendChild(self::xmlElement($dom, 'VAT', self::formatAmountDot($inv['vat_amount'] ?? 0)));
                $poz->appendChild(self::xmlElement($dom, 'Brutto', self::formatAmountDot($inv['gross_amount'] ?? 0)));
                $poz->appendChild(self::xmlElement($dom, 'StawkaVAT', self::guessVatRate($inv) . '%'));
                $pozycje->appendChild($poz);
            }

            $wydatek->appendChild($pozycje);

            $wydatek->appendChild(self::xmlElement($dom, 'Waluta', $inv['currency'] ?? 'PLN'));
            if (!empty($inv['ksef_reference_number'])) {
                $wydatek->appendChild(self::xmlElement($dom, 'NrKSeF', $inv['ksef_reference_number']));
            }
        }

        $xml = $dom->saveXML();
        $filename = self::buildFilename($batch, $client, 'wfirma', 'xml');
        return self::saveExport($filename, $xml);
    }

    // ── Uniwersalny CSV ─────────────────────────────────

    /**
     * Universal CSV export — all fields, UTF-8 with BOM, ISO dates.
     * Compatible with most systems after minor column mapping.
     */
    public static function exportUniversalCsv(int $batchId, bool $onlyAccepted = true): string
    {
        $invoices = self::getInvoices($batchId, $onlyAccepted);
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $headers = ['Lp', 'TypDokumentu', 'NumerFaktury', 'DataWystawienia', 'DataSprzedazy',
                     'NIPSprzedawcy', 'NazwaSprzedawcy', 'AdresSprzedawcy', 'NIPNabywcy', 'NazwaNabywcy',
                     'KwotaNetto', 'StawkaVAT', 'KwotaVAT', 'KwotaBrutto', 'Waluta',
                     'NrKSeF', 'MPK', 'Status', 'Komentarz'];

        $rows = [];
        $lp = 0;
        foreach ($invoices as $inv) {
            $costCenter = $inv['cost_center'] ?? '';
            if (empty($costCenter) && !empty($inv['cost_center_id'])) {
                $cc = Database::getInstance()->fetchOne(
                    "SELECT name FROM client_cost_centers WHERE id = ?",
                    [(int) $inv['cost_center_id']]
                );
                $costCenter = $cc['name'] ?? '';
            }

            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $rows[] = [
                        ++$lp, self::getDocumentType($inv), $inv['invoice_number'] ?? '',
                        $inv['issue_date'] ?? '', $inv['sale_date'] ?? '',
                        $inv['seller_nip'] ?? '', $inv['seller_name'] ?? '', $inv['seller_address'] ?? '',
                        $inv['buyer_nip'] ?? '', $inv['buyer_name'] ?? '',
                        self::formatAmountDot($vd['net'] ?? 0), ($vd['rate'] ?? '23') . '%',
                        self::formatAmountDot($vd['vat'] ?? 0), self::formatAmountDot($vd['gross'] ?? 0),
                        $inv['currency'] ?? 'PLN', $inv['ksef_reference_number'] ?? '',
                        $costCenter, $inv['status'] ?? '', $inv['comment'] ?? '',
                    ];
                }
            } else {
                $rows[] = [
                    ++$lp, self::getDocumentType($inv), $inv['invoice_number'] ?? '',
                    $inv['issue_date'] ?? '', $inv['sale_date'] ?? '',
                    $inv['seller_nip'] ?? '', $inv['seller_name'] ?? '', $inv['seller_address'] ?? '',
                    $inv['buyer_nip'] ?? '', $inv['buyer_name'] ?? '',
                    self::formatAmountDot($inv['net_amount'] ?? 0), self::guessVatRate($inv) . '%',
                    self::formatAmountDot($inv['vat_amount'] ?? 0), self::formatAmountDot($inv['gross_amount'] ?? 0),
                    $inv['currency'] ?? 'PLN', $inv['ksef_reference_number'] ?? '',
                    $costCenter, $inv['status'] ?? '', $inv['comment'] ?? '',
                ];
            }
        }

        $csv = self::buildCsv($headers, $rows);
        // UTF-8 BOM for Excel compatibility
        $bom = "\xEF\xBB\xBF";
        $filename = self::buildFilename($batch, $client, 'universal', 'csv');
        return self::saveExport($filename, $bom . $csv);
    }

    // ── Helpers ──────────────────────────────────────────

    private static function xmlElement(\DOMDocument $dom, string $name, string $value): \DOMElement
    {
        $el = $dom->createElement($name);
        $el->appendChild($dom->createTextNode($value));
        return $el;
    }

    private static function formatAmountDot(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private static function buildComarchRow(int $lp, array $inv, array $vat): array
    {
        $costCenter = '';
        if (!empty($inv['cost_center'])) {
            $costCenter = $inv['cost_center'];
        } elseif (!empty($inv['cost_center_id'])) {
            $cc = Database::getInstance()->fetchOne(
                "SELECT name FROM client_cost_centers WHERE id = ?",
                [(int) $inv['cost_center_id']]
            );
            $costCenter = $cc['name'] ?? '';
        }

        return [
            $lp,
            self::getDocumentType($inv),
            $inv['invoice_number'] ?? '',
            $inv['ksef_reference_number'] ?? '',
            self::formatDate($inv['issue_date'] ?? '', 'd.m.Y'),
            self::formatDate($inv['sale_date'] ?? '', 'd.m.Y'),
            $inv['seller_nip'] ?? '',
            $inv['seller_name'] ?? '',
            $inv['seller_address'] ?? '',
            self::formatAmount($vat['net'] ?? 0),
            ($vat['rate'] ?? '23') . '%',
            self::formatAmount($vat['vat'] ?? 0),
            self::formatAmount($vat['gross'] ?? 0),
            $inv['currency'] ?? 'PLN',
            $costCenter,
        ];
    }

    /**
     * Parse VAT details from JSON field.
     * Expected format: [{"rate":"23","net":"1000.00","vat":"230.00","gross":"1230.00"}, ...]
     */
    private static function parseVatDetails(array $invoice): array
    {
        $json = $invoice['vat_details'] ?? null;
        if (empty($json)) {
            return [];
        }

        $details = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($details) || empty($details)) {
            return [];
        }

        return $details;
    }

    private static function guessVatRate(array $invoice): string
    {
        $net = (float) ($invoice['net_amount'] ?? 0);
        if ($net <= 0) return '23';

        $vat = (float) ($invoice['vat_amount'] ?? 0);
        $ratio = $vat / $net;

        if (abs($ratio - 0.23) < 0.01) return '23';
        if (abs($ratio - 0.08) < 0.01) return '8';
        if (abs($ratio - 0.05) < 0.01) return '5';
        if ($ratio < 0.01) return '0';
        return '23';
    }

    private static function getDocumentType(array $invoice): string
    {
        $number = strtolower($invoice['invoice_number'] ?? '');
        if (str_contains($number, 'kor') || str_contains($number, 'cor')) {
            return 'FK'; // Faktura korygująca
        }
        return 'FZ'; // Faktura zakupu
    }

    protected static function getInvoices(int $batchId, bool $onlyAccepted): array
    {
        if ($onlyAccepted) {
            return Invoice::getAcceptedByBatch($batchId);
        }
        return Invoice::findByBatch($batchId);
    }

    protected static function formatDate(string $date, string $format = 'd.m.Y'): string
    {
        if (empty($date)) return '';
        try {
            return (new \DateTime($date))->format($format);
        } catch (\Exception) {
            return $date;
        }
    }

    protected static function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, ',', '');
    }

    protected static function buildCsv(array $headers, array $rows, string $separator = ';'): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers, $separator);
        foreach ($rows as $row) {
            fputcsv($output, $row, $separator);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }

    protected static function convertEncoding(string $data, string $toEncoding): string
    {
        if ($toEncoding === 'UTF-8') {
            return $data;
        }
        // PHP 8.1+ mbstring doesn't support "Windows-1250" — use iconv with CP1250
        $iconvEncoding = str_replace('Windows-', 'CP', $toEncoding);
        $result = @iconv('UTF-8', $iconvEncoding . '//TRANSLIT', $data);
        if ($result !== false) {
            return $result;
        }
        // Fallback to mbstring
        return mb_convert_encoding($data, $toEncoding, 'UTF-8');
    }

    protected static function buildFilename(?array $batch, ?array $client, string $format, string $ext): string
    {
        $nip = $client['nip'] ?? 'export';
        $month = $batch['period_month'] ?? date('m');
        $year = $batch['period_year'] ?? date('Y');
        $ts = date('His');
        return "{$nip}_{$format}_{$month}_{$year}_{$ts}.{$ext}";
    }

    protected static function saveExport(string $filename, string $content): string
    {
        $dir = rtrim((require __DIR__ . '/../../config/app.php')['storage'], '/') . '/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }
}
