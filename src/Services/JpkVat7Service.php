<?php

namespace App\Services;

use App\Models\InvoiceBatch;
use App\Models\Invoice;
use App\Models\IssuedInvoice;
use App\Models\Client;

/**
 * JPK_V7M(3) Generator — Jednolity Plik Kontrolny: deklaracja VAT-7 + ewidencja.
 *
 * Schema: http://crd.gov.pl/wzor/2025/12/19/14090/
 *
 * Structure (per schema):
 *   JPK → Naglowek → Podmiot1 → Deklaracja → Ewidencja
 *   Ewidencja contains: SprzedazWiersz*, SprzedazCtrl, ZakupWiersz*, ZakupCtrl
 */
class JpkVat7Service
{
    private static string $storageDir = __DIR__ . '/../../storage/jpk';

    private const NS = 'http://crd.gov.pl/wzor/2025/12/19/14090/';
    private const ETD_NS = 'http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/09/13/eD/DefinicjeTypy/';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * Generate JPK_V7M(3) XML for a given batch (period).
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
            'JPK_V7M_%s_%02d_%04d.xml',
            $client['nip'],
            $periodMonth,
            $periodYear
        );
        $path = self::$storageDir . '/' . $filename;
        if (file_put_contents($path, $xml) === false) {
            throw new \RuntimeException('Failed to write JPK file: ' . $path);
        }

        return $path;
    }

    private static function buildXml(array $client, array $invoices, int $year, int $month): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Root element with namespace
        $root = $dom->createElementNS(self::NS, 'tns:JPK');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:etd', self::ETD_NS);
        $root->setAttributeNS(
            self::XSI,
            'xsi:schemaLocation',
            self::NS . ' ' . self::NS . 'schemat.xsd'
        );
        $dom->appendChild($root);

        // ── Naglowek ────────────────────────────────────
        $header = self::el($dom, $root, 'tns:Naglowek');

        $kodForm = $dom->createElementNS(self::NS, 'tns:KodFormularza', 'JPK_VAT');
        $kodForm->setAttribute('kodSystemowy', 'JPK_V7M (3)');
        $kodForm->setAttribute('wersjaSchemy', '1-0E');
        $header->appendChild($kodForm);

        self::txt($dom, $header, 'tns:WariantFormularza', '3');
        self::txt($dom, $header, 'tns:DataWytworzeniaJPK', date('Y-m-d\TH:i:s'));
        self::txt($dom, $header, 'tns:NazwaSystemu', 'BiLLU');

        $cel = $dom->createElementNS(self::NS, 'tns:CelZlozenia', '1');
        $cel->setAttribute('poz', 'P_7');
        $header->appendChild($cel);

        // KodUrzedu — tax office code; use client's if available, otherwise blank
        $kodUrzedu = $client['kod_urzedu'] ?? '';
        if ($kodUrzedu !== '') {
            self::txt($dom, $header, 'tns:KodUrzedu', $kodUrzedu);
        }

        self::txt($dom, $header, 'tns:Rok', (string) $year);
        self::txt($dom, $header, 'tns:Miesiac', (string) $month);

        // ── Podmiot1 (taxpayer = buyer = our client) ────
        $podmiot = $dom->createElementNS(self::NS, 'tns:Podmiot1');
        $podmiot->setAttribute('rola', 'Podatnik');
        $root->appendChild($podmiot);

        $osoba = self::el($dom, $podmiot, 'tns:OsobaNiefizyczna');
        self::etdTxt($dom, $osoba, 'etd:NIP', $client['nip']);
        self::etdTxt($dom, $osoba, 'etd:PelnaNazwa', $client['company_name']);
        if (!empty($client['email'])) {
            self::txt($dom, $osoba, 'tns:Email', $client['email']);
        }
        if (!empty($client['phone'])) {
            self::txt($dom, $osoba, 'tns:Telefon', $client['phone']);
        }

        // ── Deklaracja ──────────────────────────────────
        $deklaracja = self::el($dom, $root, 'tns:Deklaracja');

        // Deklaracja > Naglowek
        $deklHeader = self::el($dom, $deklaracja, 'tns:Naglowek');

        $kodFormDekl = $dom->createElementNS(self::NS, 'tns:KodFormularzaDekl', 'VAT-7');
        $kodFormDekl->setAttribute('kodSystemowy', 'VAT-7 (23)');
        $kodFormDekl->setAttribute('kodPodatku', 'VAT');
        $kodFormDekl->setAttribute('rodzajZobowiazania', 'Z');
        $kodFormDekl->setAttribute('wersjaSchemy', '1-0E');
        $deklHeader->appendChild($kodFormDekl);

        self::txt($dom, $deklHeader, 'tns:WariantFormularzaDekl', '23');

        // Deklaracja > PozycjeSzczegolowe
        $pozSzcz = self::el($dom, $deklaracja, 'tns:PozycjeSzczegolowe');

        // Calculate totals
        $totals = self::calculateTotals($invoices);

        // Purchase totals for declaration
        $totalPurchaseNet = $totals['net_other_23'] + $totals['net_fixed_23']
                          + $totals['net_8'] + $totals['net_5'] + $totals['net_0'];
        $totalPurchaseVat = $totals['vat_other_23'] + $totals['vat_fixed_23']
                          + $totals['vat_8'] + $totals['vat_5'];

        // P_40..P_47: purchase breakdown by rate
        if ($totals['net_fixed_23'] > 0 || $totals['vat_fixed_23'] > 0) {
            self::txt($dom, $pozSzcz, 'tns:P_40', self::fmt($totals['net_fixed_23']));
            self::txt($dom, $pozSzcz, 'tns:P_41', self::fmt($totals['vat_fixed_23']));
        }
        if ($totals['net_other_23'] > 0 || $totals['vat_other_23'] > 0) {
            self::txt($dom, $pozSzcz, 'tns:P_42', self::fmt($totals['net_other_23']));
            self::txt($dom, $pozSzcz, 'tns:P_43', self::fmt($totals['vat_other_23']));
        }
        if ($totals['net_8'] > 0 || $totals['vat_8'] > 0) {
            self::txt($dom, $pozSzcz, 'tns:P_44', self::fmt($totals['net_8']));
            self::txt($dom, $pozSzcz, 'tns:P_45', self::fmt($totals['vat_8']));
        }
        if ($totals['net_5'] > 0 || $totals['vat_5'] > 0) {
            self::txt($dom, $pozSzcz, 'tns:P_46', self::fmt($totals['net_5']));
            self::txt($dom, $pozSzcz, 'tns:P_47', self::fmt($totals['vat_5']));
        }

        // P_48 = total purchase net, P_51 = tax difference (output - input)
        self::txt($dom, $pozSzcz, 'tns:P_48', self::fmt($totalPurchaseNet));
        self::txt($dom, $pozSzcz, 'tns:P_51', '0');

        // P_53 = VAT to pay/refund (P_51 is 0 because no sales tracked)
        self::txt($dom, $pozSzcz, 'tns:P_53', self::fmt($totalPurchaseVat));

        // P_62 = total VAT to settle
        self::txt($dom, $pozSzcz, 'tns:P_62', self::fmt($totalPurchaseVat));

        // Deklaracja > Pouczenia
        self::txt($dom, $deklaracja, 'tns:Pouczenia', '1');

        // ── Ewidencja ───────────────────────────────────
        $ewidencja = self::el($dom, $root, 'tns:Ewidencja');

        // SprzedazCtrl (empty — no sales tracked in this system)
        $sprzCtrl = self::el($dom, $ewidencja, 'tns:SprzedazCtrl');
        self::txt($dom, $sprzCtrl, 'tns:LiczbaWierszySprzedazy', '0');
        self::txt($dom, $sprzCtrl, 'tns:PodatekNalezny', '0.00');

        // ZakupWiersz (purchase rows)
        $zakupCount = 0;
        $zakupVatTotal = 0.0;

        foreach ($invoices as $inv) {
            $wiersz = self::el($dom, $ewidencja, 'tns:ZakupWiersz');

            self::txt($dom, $wiersz, 'tns:LpZakupu', (string) (++$zakupCount));
            self::txt($dom, $wiersz, 'tns:NrDostawcy', $inv['seller_nip'] ?? '');
            self::txt($dom, $wiersz, 'tns:NazwaDostawcy', $inv['seller_name'] ?? '');
            self::txt($dom, $wiersz, 'tns:DowodZakupu', $inv['invoice_number'] ?? '');
            self::txt($dom, $wiersz, 'tns:DataZakupu', $inv['sale_date'] ?? $inv['issue_date'] ?? '');

            // NrKSeF — numer referencyjny KSeF
            if (!empty($inv['ksef_reference_number'])) {
                self::txt($dom, $wiersz, 'tns:NrKSeF', $inv['ksef_reference_number']);
            }

            // Distribute amounts by VAT rate (K_40..K_47)
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $rate = (int) ($vd['rate'] ?? 23);
                    self::addZakupKwoty($dom, $wiersz, $rate, (float) ($vd['net'] ?? 0), (float) ($vd['vat'] ?? 0));
                    $zakupVatTotal += (float) ($vd['vat'] ?? 0);
                }
            } else {
                $rate = self::guessVatRateInt($inv);
                $net = (float) ($inv['net_amount'] ?? 0);
                $vat = (float) ($inv['vat_amount'] ?? 0);
                self::addZakupKwoty($dom, $wiersz, $rate, $net, $vat);
                $zakupVatTotal += $vat;
            }
        }

        // ZakupCtrl
        $zakupCtrl = self::el($dom, $ewidencja, 'tns:ZakupCtrl');
        self::txt($dom, $zakupCtrl, 'tns:LiczbaWierszyZakupow', (string) $zakupCount);
        self::txt($dom, $zakupCtrl, 'tns:PodatekNaliczony', self::fmt($zakupVatTotal));

        return $dom->saveXML();
    }

    // ── Helpers ─────────────────────────────────────────

    /**
     * Add K_40..K_47 fields to ZakupWiersz based on VAT rate.
     */
    private static function addZakupKwoty(\DOMDocument $dom, \DOMElement $wiersz, int $rate, float $net, float $vat): void
    {
        switch ($rate) {
            case 23:
                self::txt($dom, $wiersz, 'tns:K_42', self::fmt($net));
                self::txt($dom, $wiersz, 'tns:K_43', self::fmt($vat));
                break;
            case 8:
            case 7:
                self::txt($dom, $wiersz, 'tns:K_44', self::fmt($net));
                self::txt($dom, $wiersz, 'tns:K_45', self::fmt($vat));
                break;
            case 5:
                self::txt($dom, $wiersz, 'tns:K_46', self::fmt($net));
                self::txt($dom, $wiersz, 'tns:K_47', self::fmt($vat));
                break;
            case 0:
                // 0% purchases — no VAT fields
                break;
        }
    }

    /**
     * Calculate aggregate totals by VAT rate for the declaration section.
     */
    private static function calculateTotals(array $invoices): array
    {
        $totals = [
            'net_fixed_23' => 0.0, 'vat_fixed_23' => 0.0,
            'net_other_23' => 0.0, 'vat_other_23' => 0.0,
            'net_8' => 0.0, 'vat_8' => 0.0,
            'net_5' => 0.0, 'vat_5' => 0.0,
            'net_0' => 0.0,
        ];

        foreach ($invoices as $inv) {
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $rate = (int) ($vd['rate'] ?? 23);
                    $net = (float) ($vd['net'] ?? 0);
                    $vat = (float) ($vd['vat'] ?? 0);
                    self::addToTotals($totals, $rate, $net, $vat);
                }
            } else {
                $rate = self::guessVatRateInt($inv);
                self::addToTotals($totals, $rate, (float) ($inv['net_amount'] ?? 0), (float) ($inv['vat_amount'] ?? 0));
            }
        }

        return $totals;
    }

    private static function addToTotals(array &$totals, int $rate, float $net, float $vat): void
    {
        match ($rate) {
            23 => (function () use (&$totals, $net, $vat) {
                $totals['net_other_23'] += $net;
                $totals['vat_other_23'] += $vat;
            })(),
            8, 7 => (function () use (&$totals, $net, $vat) {
                $totals['net_8'] += $net;
                $totals['vat_8'] += $vat;
            })(),
            5 => (function () use (&$totals, $net, $vat) {
                $totals['net_5'] += $net;
                $totals['vat_5'] += $vat;
            })(),
            0 => $totals['net_0'] += $net,
            default => (function () use (&$totals, $net, $vat) {
                $totals['net_other_23'] += $net;
                $totals['vat_other_23'] += $vat;
            })(),
        };
    }

    private static function parseVatDetails(array $invoice): array
    {
        $json = $invoice['vat_details'] ?? null;
        if (empty($json)) return [];
        $details = is_string($json) ? json_decode($json, true) : $json;
        return is_array($details) ? $details : [];
    }

    private static function getDocumentType(array $invoice): string
    {
        $number = strtolower($invoice['invoice_number'] ?? '');
        if (str_contains($number, 'kor') || str_contains($number, 'cor')) {
            return 'FK';
        }
        return 'FZ';
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

    /**
     * Create a child element in the etd: namespace with text content.
     */
    private static function etdTxt(\DOMDocument $dom, \DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElementNS(self::ETD_NS, $tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private static function fmt(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    /**
     * Generate JPK_V7M(3) XML for sales (issued invoices).
     * Generates SprzedazWiersz entries from issued_invoices table.
     */
    public static function generateForSales(int $clientId, int $year, int $month): string
    {
        $client = Client::findById($clientId);
        $invoices = IssuedInvoice::getSalesForJpk($clientId, $month, $year);

        $xml = self::buildSalesXml($client, $invoices, $year, $month);

        self::ensureDir();
        $filename = sprintf(
            'JPK_V7M_SALES_%s_%02d_%04d.xml',
            $client['nip'],
            $month,
            $year
        );
        $path = self::$storageDir . '/' . $filename;
        if (file_put_contents($path, $xml) === false) {
            throw new \RuntimeException('Failed to write JPK sales file: ' . $path);
        }

        return $path;
    }

    private static function buildSalesXml(array $client, array $invoices, int $year, int $month): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS, 'tns:JPK');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:etd', self::ETD_NS);
        $root->setAttributeNS(
            self::XSI,
            'xsi:schemaLocation',
            self::NS . ' ' . self::NS . 'schemat.xsd'
        );
        $dom->appendChild($root);

        // Naglowek
        $header = self::el($dom, $root, 'tns:Naglowek');

        $kodForm = $dom->createElementNS(self::NS, 'tns:KodFormularza', 'JPK_VAT');
        $kodForm->setAttribute('kodSystemowy', 'JPK_V7M (3)');
        $kodForm->setAttribute('wersjaSchemy', '1-0E');
        $header->appendChild($kodForm);

        self::txt($dom, $header, 'tns:WariantFormularza', '3');
        self::txt($dom, $header, 'tns:DataWytworzeniaJPK', date('Y-m-d\TH:i:s'));
        self::txt($dom, $header, 'tns:NazwaSystemu', 'BiLLU');

        $cel = $dom->createElementNS(self::NS, 'tns:CelZlozenia', '1');
        $cel->setAttribute('poz', 'P_7');
        $header->appendChild($cel);

        $kodUrzedu = $client['kod_urzedu'] ?? '';
        if ($kodUrzedu !== '') {
            self::txt($dom, $header, 'tns:KodUrzedu', $kodUrzedu);
        }

        self::txt($dom, $header, 'tns:Rok', (string) $year);
        self::txt($dom, $header, 'tns:Miesiac', (string) $month);

        // Podmiot1
        $podmiot = $dom->createElementNS(self::NS, 'tns:Podmiot1');
        $podmiot->setAttribute('rola', 'Podatnik');
        $root->appendChild($podmiot);

        $osoba = self::el($dom, $podmiot, 'tns:OsobaNiefizyczna');
        self::etdTxt($dom, $osoba, 'etd:NIP', $client['nip']);
        self::etdTxt($dom, $osoba, 'etd:PelnaNazwa', $client['company_name']);
        if (!empty($client['email'])) {
            self::txt($dom, $osoba, 'tns:Email', $client['email']);
        }

        // Deklaracja
        $deklaracja = self::el($dom, $root, 'tns:Deklaracja');
        $deklHeader = self::el($dom, $deklaracja, 'tns:Naglowek');

        $kodFormDekl = $dom->createElementNS(self::NS, 'tns:KodFormularzaDekl', 'VAT-7');
        $kodFormDekl->setAttribute('kodSystemowy', 'VAT-7 (23)');
        $kodFormDekl->setAttribute('kodPodatku', 'VAT');
        $kodFormDekl->setAttribute('rodzajZobowiazania', 'Z');
        $kodFormDekl->setAttribute('wersjaSchemy', '1-0E');
        $deklHeader->appendChild($kodFormDekl);

        self::txt($dom, $deklHeader, 'tns:WariantFormularzaDekl', '23');

        // PozycjeSzczegolowe — sales totals
        $pozSzcz = self::el($dom, $deklaracja, 'tns:PozycjeSzczegolowe');

        $salesTotals = self::calculateSalesTotals($invoices);

        // P_10..P_20: sales breakdown by rate
        if ($salesTotals['net_23'] > 0 || $salesTotals['vat_23'] > 0) {
            self::txt($dom, $pozSzcz, 'tns:P_10', self::fmt($salesTotals['net_23']));
            self::txt($dom, $pozSzcz, 'tns:P_11', self::fmt($salesTotals['vat_23']));
        }
        if ($salesTotals['net_8'] > 0 || $salesTotals['vat_8'] > 0) {
            self::txt($dom, $pozSzcz, 'tns:P_12', self::fmt($salesTotals['net_8']));
            self::txt($dom, $pozSzcz, 'tns:P_13', self::fmt($salesTotals['vat_8']));
        }
        if ($salesTotals['net_5'] > 0 || $salesTotals['vat_5'] > 0) {
            self::txt($dom, $pozSzcz, 'tns:P_14', self::fmt($salesTotals['net_5']));
            self::txt($dom, $pozSzcz, 'tns:P_15', self::fmt($salesTotals['vat_5']));
        }
        if ($salesTotals['net_0'] > 0) {
            self::txt($dom, $pozSzcz, 'tns:P_16', self::fmt($salesTotals['net_0']));
        }

        $totalSalesVat = $salesTotals['vat_23'] + $salesTotals['vat_8'] + $salesTotals['vat_5'];
        // P_38 = total output VAT
        self::txt($dom, $pozSzcz, 'tns:P_38', self::fmt($totalSalesVat));
        // P_51 = tax to pay
        self::txt($dom, $pozSzcz, 'tns:P_51', self::fmt($totalSalesVat));
        self::txt($dom, $pozSzcz, 'tns:P_53', self::fmt($totalSalesVat));
        self::txt($dom, $pozSzcz, 'tns:P_62', self::fmt($totalSalesVat));

        // Pouczenia
        self::txt($dom, $deklaracja, 'tns:Pouczenia', '1');

        // Ewidencja
        $ewidencja = self::el($dom, $root, 'tns:Ewidencja');

        // SprzedazWiersz
        $sprzCount = 0;
        $sprzVatTotal = 0.0;

        foreach ($invoices as $inv) {
            $wiersz = self::el($dom, $ewidencja, 'tns:SprzedazWiersz');

            self::txt($dom, $wiersz, 'tns:LpSprzedazy', (string) (++$sprzCount));
            self::txt($dom, $wiersz, 'tns:NrKontrahenta', $inv['buyer_nip'] ?? '');
            self::txt($dom, $wiersz, 'tns:NazwaKontrahenta', $inv['buyer_name'] ?? '');
            self::txt($dom, $wiersz, 'tns:DowodSprzedazy', $inv['invoice_number'] ?? '');
            self::txt($dom, $wiersz, 'tns:DataWystawienia', $inv['issue_date'] ?? '');

            // BFK — faktura do celów działalności gospodarczej
            self::txt($dom, $wiersz, 'tns:BFK', '1');

            // NrKSeF — numer referencyjny KSeF
            if (!empty($inv['ksef_reference_number'])) {
                self::txt($dom, $wiersz, 'tns:NrKSeF', $inv['ksef_reference_number']);
            }

            // K_19..K_20 (23%), K_21..K_22 (8%), K_23..K_24 (5%), K_25 (0%)
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $rate = (int) ($vd['rate'] ?? 23);
                    $net = (float) ($vd['net'] ?? 0);
                    $vat = (float) ($vd['vat'] ?? 0);
                    self::addSprzedazKwoty($dom, $wiersz, $rate, $net, $vat);
                    $sprzVatTotal += $vat;
                }
            } else {
                $rate = self::guessVatRateInt($inv);
                $net = (float) ($inv['net_amount'] ?? 0);
                $vat = (float) ($inv['vat_amount'] ?? 0);
                self::addSprzedazKwoty($dom, $wiersz, $rate, $net, $vat);
                $sprzVatTotal += $vat;
            }
        }

        // SprzedazCtrl
        $sprzCtrl = self::el($dom, $ewidencja, 'tns:SprzedazCtrl');
        self::txt($dom, $sprzCtrl, 'tns:LiczbaWierszySprzedazy', (string) $sprzCount);
        self::txt($dom, $sprzCtrl, 'tns:PodatekNalezny', self::fmt($sprzVatTotal));

        // ZakupCtrl (empty — no purchases in sales register)
        $zakupCtrl = self::el($dom, $ewidencja, 'tns:ZakupCtrl');
        self::txt($dom, $zakupCtrl, 'tns:LiczbaWierszyZakupow', '0');
        self::txt($dom, $zakupCtrl, 'tns:PodatekNaliczony', '0.00');

        return $dom->saveXML();
    }

    /**
     * Add K_19..K_25 fields to SprzedazWiersz based on VAT rate.
     */
    private static function addSprzedazKwoty(\DOMDocument $dom, \DOMElement $wiersz, int $rate, float $net, float $vat): void
    {
        switch ($rate) {
            case 23:
                self::txt($dom, $wiersz, 'tns:K_19', self::fmt($net));
                self::txt($dom, $wiersz, 'tns:K_20', self::fmt($vat));
                break;
            case 8:
            case 7:
                self::txt($dom, $wiersz, 'tns:K_21', self::fmt($net));
                self::txt($dom, $wiersz, 'tns:K_22', self::fmt($vat));
                break;
            case 5:
                self::txt($dom, $wiersz, 'tns:K_23', self::fmt($net));
                self::txt($dom, $wiersz, 'tns:K_24', self::fmt($vat));
                break;
            case 0:
                self::txt($dom, $wiersz, 'tns:K_25', self::fmt($net));
                break;
        }
    }

    private static function calculateSalesTotals(array $invoices): array
    {
        $totals = [
            'net_23' => 0.0, 'vat_23' => 0.0,
            'net_8' => 0.0, 'vat_8' => 0.0,
            'net_5' => 0.0, 'vat_5' => 0.0,
            'net_0' => 0.0,
        ];

        foreach ($invoices as $inv) {
            $vatDetails = self::parseVatDetails($inv);

            if (!empty($vatDetails)) {
                foreach ($vatDetails as $vd) {
                    $rate = (int) ($vd['rate'] ?? 23);
                    $net = (float) ($vd['net'] ?? 0);
                    $vat = (float) ($vd['vat'] ?? 0);

                    match ($rate) {
                        23 => (function () use (&$totals, $net, $vat) {
                            $totals['net_23'] += $net;
                            $totals['vat_23'] += $vat;
                        })(),
                        8, 7 => (function () use (&$totals, $net, $vat) {
                            $totals['net_8'] += $net;
                            $totals['vat_8'] += $vat;
                        })(),
                        5 => (function () use (&$totals, $net, $vat) {
                            $totals['net_5'] += $net;
                            $totals['vat_5'] += $vat;
                        })(),
                        0 => $totals['net_0'] += $net,
                        default => (function () use (&$totals, $net, $vat) {
                            $totals['net_23'] += $net;
                            $totals['vat_23'] += $vat;
                        })(),
                    };
                }
            } else {
                $rate = self::guessVatRateInt($inv);
                $net = (float) ($inv['net_amount'] ?? 0);
                $vat = (float) ($inv['vat_amount'] ?? 0);

                match ($rate) {
                    23 => (function () use (&$totals, $net, $vat) {
                        $totals['net_23'] += $net;
                        $totals['vat_23'] += $vat;
                    })(),
                    8, 7 => (function () use (&$totals, $net, $vat) {
                        $totals['net_8'] += $net;
                        $totals['vat_8'] += $vat;
                    })(),
                    5 => (function () use (&$totals, $net, $vat) {
                        $totals['net_5'] += $net;
                        $totals['vat_5'] += $vat;
                    })(),
                    0 => $totals['net_0'] += $net,
                    default => (function () use (&$totals, $net, $vat) {
                        $totals['net_23'] += $net;
                        $totals['vat_23'] += $vat;
                    })(),
                };
            }
        }

        return $totals;
    }

    private static function ensureDir(): void
    {
        if (!is_dir(self::$storageDir)) {
            mkdir(self::$storageDir, 0755, true);
        }
    }
}
