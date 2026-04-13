<?php

namespace App\Services;

use App\Models\InvoiceBatch;
use App\Models\Invoice;
use App\Models\Client;

/**
 * JPK_FA(3) Generator - Jednolity Plik Kontrolny Faktury v3.
 *
 * Schema: http://jpk.mf.gov.pl/wzor/2022/02/17/02171/
 * Plik generowany dla faktur zakupowych pobranych z KSeF.
 */
class JpkV3Service
{
    private static string $storageDir = __DIR__ . '/../../storage/jpk';
    private const NS = 'http://jpk.mf.gov.pl/wzor/2022/02/17/02171/';
    private const SCHEMA = 'http://jpk.mf.gov.pl/wzor/2022/02/17/02171/ http://jpk.mf.gov.pl/wzor/2022/02/17/02171/JPK_FA(3)_v1-1.xsd';

    /**
     * Generate JPK_FA(3) XML file for accepted invoices in a batch.
     * Returns path to the generated file.
     */
    public static function generateAcceptedJpk(int $batchId): string
    {
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);
        $invoices = Invoice::getAcceptedByBatch($batchId);

        $dateFrom = sprintf('%04d-%02d-01', $batch['period_year'], $batch['period_month']);
        $lastDay = cal_days_in_month(CAL_GREGORIAN, $batch['period_month'], $batch['period_year']);
        $dateTo = sprintf('%04d-%02d-%02d', $batch['period_year'], $batch['period_month'], $lastDay);

        $xml = self::buildJpkXml($client, $invoices, $dateFrom, $dateTo);

        self::ensureDir();
        $filename = sprintf('JPK_FA_%s_%s_%02d%04d.xml',
            preg_replace('/[^a-zA-Z0-9]/', '_', $client['company_name']),
            $client['nip'],
            $batch['period_month'],
            $batch['period_year']
        );
        $path = self::$storageDir . '/' . $filename;
        file_put_contents($path, $xml);

        return $path;
    }

    /**
     * Generate JPK_FA(3) XML file for accepted invoices of a specific cost center.
     */
    public static function generateCostCenterJpk(int $batchId, string $costCenterName, array $invoices): string
    {
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $dateFrom = sprintf('%04d-%02d-01', $batch['period_year'], $batch['period_month']);
        $lastDay = cal_days_in_month(CAL_GREGORIAN, $batch['period_month'], $batch['period_year']);
        $dateTo = sprintf('%04d-%02d-%02d', $batch['period_year'], $batch['period_month'], $lastDay);

        $xml = self::buildJpkXml($client, $invoices, $dateFrom, $dateTo, $costCenterName);

        self::ensureDir();
        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $costCenterName);
        $filename = sprintf('JPK_FA_%s_%s_%s_%02d%04d.xml',
            preg_replace('/[^a-zA-Z0-9]/', '_', $client['company_name']),
            $client['nip'],
            $safeName,
            $batch['period_month'],
            $batch['period_year']
        );
        $path = self::$storageDir . '/' . $filename;
        file_put_contents($path, $xml);

        return $path;
    }

    /**
     * Build JPK_FA(3) XML document.
     */
    private static function buildJpkXml(array $client, array $invoices, string $dateFrom, string $dateTo, string $costCenter = ''): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS, 'JPK');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', self::SCHEMA);
        $dom->appendChild($root);

        // ── Nagłówek ──────────────────────────────────
        $header = $dom->createElement('Naglowek');
        $root->appendChild($header);

        $kodForm = $dom->createElement('KodFormularza', 'JPK_FA');
        $kodForm->setAttribute('kodSystemowy', 'JPK_FA (3)');
        $kodForm->setAttribute('wersjaSchemy', '1-1');
        $header->appendChild($kodForm);

        self::el($dom, $header, 'WariantFormularza', '3');
        $cel = $dom->createElement('CelZlozenia', '1');
        $cel->setAttribute('poz', 'P_1');
        $header->appendChild($cel);
        self::el($dom, $header, 'DataWytworzeniaJPK', date('Y-m-d\TH:i:s'));
        self::el($dom, $header, 'DataOd', $dateFrom);
        self::el($dom, $header, 'DataDo', $dateTo);
        self::el($dom, $header, 'DomyslnyKodWaluty', 'PLN');
        self::el($dom, $header, 'KodUrzedu', '0000');

        // ── Podmiot1 (Podatnik - kupujący) ────────────
        $podmiot = $dom->createElement('Podmiot1');
        $podmiot->setAttribute('rola', 'Podatnik');
        $root->appendChild($podmiot);

        self::el($dom, $podmiot, 'NIP', $client['nip']);
        self::el($dom, $podmiot, 'PelnaNazwa', $client['company_name']);
        if (!empty($client['email'])) {
            self::el($dom, $podmiot, 'Email', $client['email']);
        }
        if (!empty($client['phone'])) {
            self::el($dom, $podmiot, 'Telefon', $client['phone']);
        }

        // ── Faktury ────────────────────────────────────
        $totalGross = 0.0;
        $invoiceCount = 0;

        foreach ($invoices as $inv) {
            $faktura = $dom->createElement('Faktura');
            $root->appendChild($faktura);

            self::el($dom, $faktura, 'KodWaluty', $inv['currency'] ?? 'PLN');
            self::el($dom, $faktura, 'DataWystawienia', $inv['issue_date']);
            self::el($dom, $faktura, 'TypFaktury', 'VAT');
            self::el($dom, $faktura, 'P_2A', $inv['invoice_number']);         // Numer faktury

            // NrKSeF — numer referencyjny KSeF
            if (!empty($inv['ksef_reference_number'])) {
                self::el($dom, $faktura, 'NrKSeF', $inv['ksef_reference_number']);
            }

            // Nabywca (buyer = nasz klient)
            self::el($dom, $faktura, 'P_3A', $client['company_name']);
            self::el($dom, $faktura, 'P_3B', $client['address'] ?? '');
            self::el($dom, $faktura, 'P_3C', $client['nip']);

            // Sprzedawca
            self::el($dom, $faktura, 'P_4A', $inv['seller_name']);
            self::el($dom, $faktura, 'P_4B', $inv['seller_address'] ?? '');
            self::el($dom, $faktura, 'P_5A', $inv['seller_nip']);

            // Daty
            if (!empty($inv['sale_date'])) {
                self::el($dom, $faktura, 'P_6', $inv['sale_date']);
            }

            // Kwoty VAT - podstawa i podatek (23% - uproszczenie)
            $net = round((float)$inv['net_amount'], 2);
            $vat = round((float)$inv['vat_amount'], 2);
            $gross = round((float)$inv['gross_amount'], 2);

            self::el($dom, $faktura, 'P_13_1', number_format($net, 2, '.', ''));
            self::el($dom, $faktura, 'P_14_1', number_format($vat, 2, '.', ''));
            self::el($dom, $faktura, 'P_15', number_format($gross, 2, '.', ''));

            // MPK (komentarz/pole dodatkowe)
            if (!empty($inv['cost_center'])) {
                self::el($dom, $faktura, 'DodatkowyOpis', $inv['cost_center']);
            } elseif (!empty($costCenter)) {
                self::el($dom, $faktura, 'DodatkowyOpis', $costCenter);
            }

            // Status weryfikacji
            self::el($dom, $faktura, 'StatusFaktury', 'zaakceptowana');

            $totalGross += $gross;
            $invoiceCount++;
        }

        // ── Kontrola ────────────────────────────────────
        $ctrl = $dom->createElement('FakturaCtrl');
        $root->appendChild($ctrl);
        self::el($dom, $ctrl, 'LiczbaFaktur', (string)$invoiceCount);
        self::el($dom, $ctrl, 'WartoscFaktur', number_format($totalGross, 2, '.', ''));

        return $dom->saveXML();
    }

    private static function el(\DOMDocument $dom, \DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElement($tag, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($el);
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0755, true);
        }
    }
}
