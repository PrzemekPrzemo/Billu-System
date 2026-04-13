<?php

namespace App\Services;

use App\Models\InvoiceBatch;
use App\Models\Invoice;
use App\Models\Client;

/**
 * JPK_FA(4) Generator — Jednolity Plik Kontrolny: Faktury.
 *
 * Schema: http://crd.gov.pl/wzor/2022/02/01/11574/
 *
 * Generates the purchase invoice register for tax audit purposes.
 * Contains full invoice data with VAT rates.
 */
class JpkFaService
{
    private static string $storageDir = __DIR__ . '/../../storage/jpk';

    private const NS = 'http://crd.gov.pl/wzor/2022/02/01/11574/';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Generate JPK_FA(4) XML for a given batch.
     * Returns path to saved file.
     */
    public static function generate(int $batchId, bool $onlyAccepted = true): string
    {
        $batch = InvoiceBatch::findById($batchId);
        $client = Client::findById($batch['client_id']);

        $invoices = $onlyAccepted
            ? Invoice::getAcceptedByBatch($batchId)
            : Invoice::findByBatch($batchId);

        $periodMonth = (int) $batch['period_month'];
        $periodYear = (int) $batch['period_year'];

        $xml = self::buildXml($client, $invoices, $periodYear, $periodMonth);

        self::ensureDir();
        $filename = sprintf(
            'JPK_FA_%s_%02d_%04d.xml',
            $client['nip'],
            $periodMonth,
            $periodYear
        );
        $path = self::$storageDir . '/' . $filename;
        file_put_contents($path, $xml);

        return $path;
    }

    private static function buildXml(array $client, array $invoices, int $year, int $month): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Root element with namespace
        $root = $dom->createElementNS(self::NS, 'tns:JPK');
        $root->setAttributeNS(
            self::XSI,
            'xsi:schemaLocation',
            self::NS . ' ' . self::NS . 'schemat.xsd'
        );
        $dom->appendChild($root);

        // ── Naglowek ────────────────────────────────────
        $header = self::el($dom, $root, 'tns:Naglowek');

        $kodForm = $dom->createElementNS(self::NS, 'tns:KodFormularza', 'JPK_FA');
        $kodForm->setAttribute('kodSystemowy', 'JPK_FA (4)');
        $kodForm->setAttribute('wersjaSchemy', '4-0');
        $header->appendChild($kodForm);

        self::txt($dom, $header, 'tns:WariantFormularza', '4');
        self::txt($dom, $header, 'tns:CelZlozenia', '1');

        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $dateTo = sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));

        self::txt($dom, $header, 'tns:DataWytworzeniaJPK', date('Y-m-d\TH:i:s'));
        self::txt($dom, $header, 'tns:DataOd', $dateFrom);
        self::txt($dom, $header, 'tns:DataDo', $dateTo);
        self::txt($dom, $header, 'tns:NazwaSystemu', 'BiLLU');

        // ── Podmiot1 (taxpayer = buyer = our client) ────
        $podmiot = self::el($dom, $root, 'tns:Podmiot1');
        self::txt($dom, $podmiot, 'tns:NIP', $client['nip']);
        self::txt($dom, $podmiot, 'tns:PelnaNazwa', $client['company_name']);
        if (!empty($client['address'])) {
            self::txt($dom, $podmiot, 'tns:AdresPodmiotu', $client['address']);
        }

        // ── Faktura (invoice rows) ──────────────────────
        $fakturaCount = 0;
        $totalNet = 0.0;
        $totalVat = 0.0;

        foreach ($invoices as $inv) {
            $faktura = self::el($dom, $root, 'tns:Faktura');
            $fakturaCount++;

            // P_1 — Invoice number
            self::txt($dom, $faktura, 'tns:P_1', $inv['invoice_number'] ?? '');
            // P_2A — Issue date
            self::txt($dom, $faktura, 'tns:P_2A', $inv['issue_date'] ?? '');
            // P_2B — Sale date (if different)
            if (!empty($inv['sale_date']) && $inv['sale_date'] !== $inv['issue_date']) {
                self::txt($dom, $faktura, 'tns:P_2B', $inv['sale_date']);
            }

            // P_3A — Seller name
            self::txt($dom, $faktura, 'tns:P_3A', $inv['seller_name'] ?? '');
            // P_3B — Seller address
            if (!empty($inv['seller_address'])) {
                self::txt($dom, $faktura, 'tns:P_3B', $inv['seller_address']);
            }
            // P_3C — Buyer name
            self::txt($dom, $faktura, 'tns:P_3C', $inv['buyer_name'] ?? $client['company_name']);
            // P_3D — Buyer address
            if (!empty($inv['buyer_address']) || !empty($client['address'])) {
                self::txt($dom, $faktura, 'tns:P_3D', $inv['buyer_address'] ?? $client['address'] ?? '');
            }

            // P_4A — Seller NIP prefix (PL)
            self::txt($dom, $faktura, 'tns:P_4A', 'PL');
            // P_4B — Seller NIP
            self::txt($dom, $faktura, 'tns:P_4B', $inv['seller_nip'] ?? '');
            // P_5A — Buyer NIP prefix
            self::txt($dom, $faktura, 'tns:P_5A', 'PL');
            // P_5B — Buyer NIP
            self::txt($dom, $faktura, 'tns:P_5B', $inv['buyer_nip'] ?? $client['nip']);

            // VAT rate fields (P_13_1..P_14_4)
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $rate = (int) ($vd['rate'] ?? 23);
                    $net = (float) ($vd['net'] ?? 0);
                    $vat = (float) ($vd['vat'] ?? 0);
                    self::addVatFields($dom, $faktura, $rate, $net, $vat);
                    $totalNet += $net;
                    $totalVat += $vat;
                }
            } else {
                $rate = self::guessVatRateInt($inv);
                $net = (float) ($inv['net_amount'] ?? 0);
                $vat = (float) ($inv['vat_amount'] ?? 0);
                self::addVatFields($dom, $faktura, $rate, $net, $vat);
                $totalNet += $net;
                $totalVat += $vat;
            }

            // P_15 — Gross amount (differential for corrections)
            $grossAmount = (float) ($inv['gross_amount'] ?? 0);
            $isKorekta = ($inv['invoice_type'] ?? '') === 'FV_KOR';
            if ($isKorekta && !empty($inv['original_gross_amount'])) {
                $grossAmount -= (float) $inv['original_gross_amount'];
            }
            self::txt($dom, $faktura, 'tns:P_15', self::fmt($grossAmount));

            // Currency
            $currency = $inv['currency'] ?? 'PLN';
            if ($currency !== 'PLN') {
                self::txt($dom, $faktura, 'tns:KodWaluty', $currency);
            }

            // KSeF reference number if available
            if (!empty($inv['ksef_reference_number'])) {
                self::txt($dom, $faktura, 'tns:NrKSeF', $inv['ksef_reference_number']);
            }

            // RodzajFaktury — document type
            if ($isKorekta) {
                self::txt($dom, $faktura, 'tns:RodzajFaktury', 'KOREKTA');
                // Reference to corrected invoice
                if (!empty($inv['corrected_invoice_id'])) {
                    $origInv = \App\Models\IssuedInvoice::findById((int)$inv['corrected_invoice_id']);
                    if ($origInv) {
                        self::txt($dom, $faktura, 'tns:NumerFakturyPierwotnej', $origInv['invoice_number']);
                        self::txt($dom, $faktura, 'tns:DataFakturyPierwotnej', $origInv['issue_date']);
                    }
                }
                if (!empty($inv['correction_reason'])) {
                    self::txt($dom, $faktura, 'tns:PrzyczynaKorekty', $inv['correction_reason']);
                }
            } else {
                self::txt($dom, $faktura, 'tns:RodzajFaktury', 'VAT');
            }
        }

        // ── FakturaCtrl ─────────────────────────────────
        $fakturaCtrl = self::el($dom, $root, 'tns:FakturaCtrl');
        self::txt($dom, $fakturaCtrl, 'tns:LiczbaFaktur', (string) $fakturaCount);
        self::txt($dom, $fakturaCtrl, 'tns:WartoscFaktur', self::fmt($totalNet + $totalVat));

        return $dom->saveXML();
    }

    /**
     * Add P_13/P_14 VAT rate fields to Faktura element.
     */
    private static function addVatFields(\DOMDocument $dom, \DOMElement $faktura, int $rate, float $net, float $vat): void
    {
        switch ($rate) {
            case 23:
                self::txt($dom, $faktura, 'tns:P_13_1', self::fmt($net));
                self::txt($dom, $faktura, 'tns:P_14_1', self::fmt($vat));
                break;
            case 8:
            case 7:
                self::txt($dom, $faktura, 'tns:P_13_2', self::fmt($net));
                self::txt($dom, $faktura, 'tns:P_14_2', self::fmt($vat));
                break;
            case 5:
                self::txt($dom, $faktura, 'tns:P_13_3', self::fmt($net));
                self::txt($dom, $faktura, 'tns:P_14_3', self::fmt($vat));
                break;
            case 0:
                self::txt($dom, $faktura, 'tns:P_13_6', self::fmt($net));
                break;
        }
    }

    private static function parseVatDetails(array $invoice): array
    {
        $json = $invoice['vat_details'] ?? null;
        if (empty($json)) return [];
        $details = is_string($json) ? json_decode($json, true) : $json;
        return is_array($details) ? $details : [];
    }

    private static function guessVatRateInt(array $invoice): int
    {
        $net = (float) ($invoice['net_amount'] ?? 0);
        if ($net <= 0) return 23;
        $vat = (float) ($invoice['vat_amount'] ?? 0);
        $ratio = $vat / $net;

        if (abs($ratio - 0.23) < 0.01) return 23;
        if (abs($ratio - 0.08) < 0.01) return 8;
        if (abs($ratio - 0.05) < 0.01) return 5;
        if ($ratio < 0.01) return 0;
        return 23;
    }

    private static function getDocumentType(array $invoice): string
    {
        $number = strtolower($invoice['invoice_number'] ?? '');
        if (str_contains($number, 'kor') || str_contains($number, 'cor')) {
            return 'FK';
        }
        return 'FZ';
    }

    /**
     * Create a child element and append it to parent. Returns the new element.
     */
    private static function el(\DOMDocument $dom, \DOMElement $parent, string $tag): \DOMElement
    {
        $el = $dom->createElementNS(self::NS, $tag);
        $parent->appendChild($el);
        return $el;
    }

    /**
     * Create a child element with text content and append it to parent.
     */
    private static function txt(\DOMDocument $dom, \DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElementNS(self::NS, $tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private static function fmt(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0755, true);
        }
    }
}
