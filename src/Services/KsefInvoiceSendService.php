<?php

namespace App\Services;

use App\Models\IssuedInvoice;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\KsefConfig;

/**
 * Handles sending issued invoices to KSeF.
 *
 * Uses existing KsefApiService for authentication, then submits
 * the invoice as FA(3) XML to the KSeF API.
 */
class KsefInvoiceSendService
{
    private const FA_NS = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    /**
     * Send an issued invoice to KSeF.
     *
     * @return array ['success' => bool, 'reference' => ?string, 'error' => ?string]
     */
    public static function sendInvoice(int $invoiceId): array
    {
        $invoice = IssuedInvoice::findById($invoiceId);
        if (!$invoice) {
            return ['success' => false, 'reference' => null, 'error' => 'Invoice not found'];
        }

        // Proforma invoices are not sent to KSeF
        if (($invoice['invoice_type'] ?? 'FV') === 'FP') {
            return ['success' => false, 'reference' => null, 'error' => 'Faktura proforma nie jest wysyłana do KSeF'];
        }

        $client = Client::findById($invoice['client_id']);
        if (!$client) {
            return ['success' => false, 'reference' => null, 'error' => 'Client not found'];
        }

        // Enrich invoice with contractor data (country code for KodKraju)
        if (!empty($invoice['contractor_id'])) {
            $contractor = Contractor::findById((int) $invoice['contractor_id']);
            if ($contractor) {
                $invoice['buyer_country'] = $contractor['address_country'] ?? 'PL';
            }
        }

        // Auto-fetch exchange rate for foreign currency invoices if missing
        // Per art. 31a ustawy o VAT: use the last business day before the issue date
        $currency = $invoice['currency'] ?? 'PLN';
        if ($currency !== 'PLN' && empty($invoice['exchange_rate'])) {
            $rateRefDate = $invoice['issue_date'] ?? $invoice['sale_date'];
            $nbpRate = NbpExchangeRateService::getRate($currency, $rateRefDate);
            if ($nbpRate) {
                $invoice['exchange_rate'] = round($nbpRate['rate'], 6);
                $invoice['exchange_rate_date'] = $nbpRate['date'];
                $invoice['exchange_rate_table'] = $nbpRate['table'];
                IssuedInvoice::update($invoiceId, [
                    'exchange_rate' => $invoice['exchange_rate'],
                    'exchange_rate_date' => $invoice['exchange_rate_date'],
                    'exchange_rate_table' => $invoice['exchange_rate_table'],
                ]);
            } else {
                return ['success' => false, 'reference' => null, 'error' => "Nie można pobrać kursu NBP dla $currency. Wprowadź kurs ręcznie."];
            }
        }

        // Authenticate with KSeF
        $ksef = KsefApiService::forClient($client);
        if (!$ksef->isConfigured()) {
            return ['success' => false, 'reference' => null, 'error' => 'KSeF not configured'];
        }

        try {
            if (!$ksef->authenticate()) {
                return ['success' => false, 'reference' => null, 'error' => 'KSeF authentication failed'];
            }

            // Build FA(3) XML
            $xml = self::buildKsefXml($invoice, $client);

            // Validate XML against XSD before sending
            $validationErrors = self::validateXml($xml);
            if (!empty($validationErrors)) {
                $errorMsg = 'XSD validation failed: ' . implode('; ', $validationErrors);
                error_log('[KSeF] ' . $errorMsg);
                // Save invalid XML for debugging
                $debugDir = __DIR__ . '/../../storage/ksef_send/xml';
                @mkdir($debugDir, 0755, true);
                file_put_contents($debugDir . '/invalid_' . date('Ymd_His') . '.xml', $xml);
                file_put_contents($debugDir . '/invalid_' . date('Ymd_His') . '_errors.txt', implode("\n", $validationErrors));
                return ['success' => false, 'reference' => null, 'error' => $errorMsg];
            }

            // Submit to KSeF
            $result = $ksef->submitInvoice($xml);

            if (!empty($result['referenceNumber'])) {
                IssuedInvoice::updateKsefStatus($invoiceId, 'sent', $result['referenceNumber']);
                IssuedInvoice::updateStatus($invoiceId, 'sent_ksef');
                return ['success' => true, 'reference' => $result['referenceNumber'], 'error' => null];
            }

            $error = $result['error'] ?? 'Unknown error';
            IssuedInvoice::updateKsefStatus($invoiceId, 'error', null, $error);
            return ['success' => false, 'reference' => null, 'error' => $error];

        } catch (\Throwable $e) {
            IssuedInvoice::updateKsefStatus($invoiceId, 'error', null, $e->getMessage());
            return ['success' => false, 'reference' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build FA(3) XML for KSeF submission.
     *
     * Conforms to FA(3) schema: http://crd.gov.pl/wzor/2025/06/25/13775/
     * Element ordering strictly follows XSD xs:sequence definition.
     *
     * Root-level order: Naglowek, Podmiot1, Podmiot2, Fa
     * Fa-level order: KodWaluty, P_1, P_2, P_6, P_13_x/P_14_x, P_15,
     *                 Adnotacje, RodzajFaktury, [KOR fields], FaWiersz[], Platnosc
     */
    public static function buildKsefXml(array $invoice, array $client): string
    {
        $lineItems = $invoice['line_items'] ?? '[]';
        if (is_string($lineItems)) $lineItems = json_decode($lineItems, true) ?: [];

        $vatDetails = $invoice['vat_details'] ?? '[]';
        if (is_string($vatDetails)) $vatDetails = json_decode($vatDetails, true) ?: [];

        // Sanitize NIPs — must be exactly 10 digits, no dashes/spaces
        $sellerNip = self::cleanNip($invoice['seller_nip'] ?? '');
        $buyerNip = self::cleanNip($invoice['buyer_nip'] ?? '');

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::FA_NS, 'Faktura');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:etd', 'http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $dom->appendChild($root);

        // === Naglowek (Header) ===
        $naglowek = $dom->createElement('Naglowek');
        $root->appendChild($naglowek);

        $kodForm = $dom->createElement('KodFormularza', 'FA');
        $kodForm->setAttribute('kodSystemowy', 'FA (3)');
        $kodForm->setAttribute('wersjaSchemy', '1-0E');
        $naglowek->appendChild($kodForm);

        self::addText($dom, $naglowek, 'WariantFormularza', '3');
        self::addText($dom, $naglowek, 'DataWytworzeniaFa', date('c')); // xs:dateTime with timezone
        self::addText($dom, $naglowek, 'SystemInfo', 'BiLLU');

        // === Podmiot1 (Seller) — Adres is required in FA(3) ===
        $podmiot1 = $dom->createElement('Podmiot1');
        $root->appendChild($podmiot1);

        $danePodmiotu1 = $dom->createElement('DaneIdentyfikacyjne');
        $podmiot1->appendChild($danePodmiotu1);
        self::addText($dom, $danePodmiotu1, 'NIP', $sellerNip);
        self::addText($dom, $danePodmiotu1, 'Nazwa', $invoice['seller_name']);

        $adres1 = $dom->createElement('Adres');
        $podmiot1->appendChild($adres1);
        self::addText($dom, $adres1, 'KodKraju', 'PL');
        // Fallback chain: invoice seller_address → client address → seller name (must not be empty)
        $sellerAddress = !empty($invoice['seller_address']) ? $invoice['seller_address']
            : (!empty($client['address']) ? $client['address'] : $invoice['seller_name']);
        self::addText($dom, $adres1, 'AdresL1', $sellerAddress);

        // === Podmiot2 (Buyer) ===
        $podmiot2 = $dom->createElement('Podmiot2');
        $root->appendChild($podmiot2);

        $danePodmiotu2 = $dom->createElement('DaneIdentyfikacyjne');
        $podmiot2->appendChild($danePodmiotu2);

        // Detect EU VAT number (e.g. DE123456789, FR12345678901)
        $rawBuyerNip = trim($invoice['buyer_nip'] ?? '');
        $isEuVat = preg_match('/^[A-Z]{2}\d/', $rawBuyerNip);

        if ($isEuVat) {
            // EU VAT number — emit NrVatUE with country prefix intact
            self::addText($dom, $danePodmiotu2, 'NrVatUE', $rawBuyerNip);
        } elseif (!empty($buyerNip)) {
            self::addText($dom, $danePodmiotu2, 'NIP', $buyerNip);
        } else {
            self::addText($dom, $danePodmiotu2, 'BrakID', '1');
        }
        self::addText($dom, $danePodmiotu2, 'Nazwa', $invoice['buyer_name']);

        if (!empty($invoice['buyer_address'])) {
            $buyerCountry = $invoice['buyer_country'] ?? 'PL';
            $adres2 = $dom->createElement('Adres');
            $podmiot2->appendChild($adres2);
            self::addText($dom, $adres2, 'KodKraju', $buyerCountry);
            self::addText($dom, $adres2, 'AdresL1', $invoice['buyer_address']);
        }

        // JST and GV are REQUIRED in FA(3) Podmiot2
        self::addText($dom, $podmiot2, 'JST', '2'); // 2 = not a local government unit
        self::addText($dom, $podmiot2, 'GV', '2');  // 2 = not a VAT group member

        // === Podmiot3 (Payer) — optional, if payer_data is present ===
        // Per FA(3) XSD: Podmiot3 sequence is: IDNabywcy?, NrEORI?, DaneIdentyfikacyjne,
        // Adres?, AdresKoresp?, DaneKontaktowe?, choice(Rola | RolaInna+OpisRoli), Udzial?, NrKlienta?
        // "Płatnik" is not a predefined Rola value — use RolaInna=1 + OpisRoli="Płatnik"
        $payerData = $invoice['payer_data'] ?? '';
        if (is_string($payerData) && $payerData !== '') {
            $payerData = json_decode($payerData, true) ?: [];
        }
        if (!empty($payerData['payer_nip']) && !empty($payerData['payer_name'])) {
            $podmiot3 = $dom->createElement('Podmiot3');
            $root->appendChild($podmiot3);

            // DaneIdentyfikacyjne (TPodmiot3 type: choice of NIP|IDWew|etc, then optional Nazwa)
            $danePodmiotu3 = $dom->createElement('DaneIdentyfikacyjne');
            $podmiot3->appendChild($danePodmiotu3);
            self::addText($dom, $danePodmiotu3, 'NIP', self::cleanNip($payerData['payer_nip']));
            self::addText($dom, $danePodmiotu3, 'Nazwa', $payerData['payer_name']);

            // Adres (optional)
            if (!empty($payerData['payer_address'])) {
                $adres3 = $dom->createElement('Adres');
                $podmiot3->appendChild($adres3);
                self::addText($dom, $adres3, 'KodKraju', 'PL');
                self::addText($dom, $adres3, 'AdresL1', $payerData['payer_address']);
            }

            // Rola: use RolaInna + OpisRoli since "Płatnik" is not a standard Rola enum value
            // Rola enum: 1=Faktor, 2=Odbiorca, 3=Podmiot pierwotny, 4=Dodatkowy nabywca,
            //            5=Wystawca, 6=Dostawca, 7=Nadawca, 8=JST, 9=Podmiot organizujacy transport, 10=Członek GV
            self::addText($dom, $podmiot3, 'RolaInna', '1');
            self::addText($dom, $podmiot3, 'OpisRoli', 'Płatnik');
        }

        // === Fa (Invoice data) — element order per XSD xs:sequence ===
        $fa = $dom->createElement('Fa');
        $root->appendChild($fa);

        $isCorrection = ($invoice['invoice_type'] ?? 'FV') === 'FV_KOR';

        // 1. KodWaluty
        self::addText($dom, $fa, 'KodWaluty', $invoice['currency'] ?? 'PLN');

        // 2. P_1 (issue date)
        self::addText($dom, $fa, 'P_1', $invoice['issue_date']);

        // 3. P_2 (invoice number)
        self::addText($dom, $fa, 'P_2', $invoice['invoice_number']);

        // 4. P_6 (sale/delivery date — mandatory in FA(3))
        $saleDate = $invoice['sale_date'] ?? $invoice['issue_date'];
        self::addText($dom, $fa, 'P_6', $saleDate);

        // 5. VAT rate totals — MUST come before P_15 and before Adnotacje
        // For corrections: P_13/P_14/P_15 are DIFFERENTIAL (new - original)
        $vatByRate = [];
        foreach ($vatDetails as $vd) {
            $rate = (int)($vd['rate'] ?? 23);
            $vatByRate[$rate] = $vd;
        }

        // For corrections with original data, calculate original VAT breakdown
        $origVatByRate = [];
        if ($isCorrection && !empty($invoice['original_line_items'])) {
            $origItems = $invoice['original_line_items'];
            if (is_string($origItems)) $origItems = json_decode($origItems, true) ?: [];
            foreach ($origItems as $oi) {
                $r = (int)($oi['vat_rate'] ?? 23);
                $oNet = round(($oi['quantity'] ?? 1) * ($oi['unit_price'] ?? 0), 2);
                $oVat = round($oNet * (is_numeric($oi['vat_rate'] ?? '') ? (int)$oi['vat_rate'] : 0) / 100, 2);
                $origVatByRate[$r] = [
                    'net' => ($origVatByRate[$r]['net'] ?? 0) + $oNet,
                    'vat' => ($origVatByRate[$r]['vat'] ?? 0) + $oVat,
                ];
            }
        }

        // Emit P_13/P_14 — differential for corrections, absolute for regular
        // For foreign currency invoices: also emit P_14_xW (VAT converted to PLN per art. 106e ust. 11)
        $allRates = array_unique(array_merge(array_keys($vatByRate), array_keys($origVatByRate)));
        $rateToFields = [23 => ['P_13_1', 'P_14_1'], 8 => ['P_13_2', 'P_14_2'], 5 => ['P_13_3', 'P_14_3'], 7 => ['P_13_4', 'P_14_4']];
        $currency = $invoice['currency'] ?? 'PLN';
        $exchangeRate = (float)($invoice['exchange_rate'] ?? 0);

        foreach ($rateToFields as $rate => $fields) {
            $newNet = (float)($vatByRate[$rate]['net'] ?? 0);
            $newVat = (float)($vatByRate[$rate]['vat'] ?? 0);
            if ($isCorrection && !empty($origVatByRate)) {
                $newNet -= (float)($origVatByRate[$rate]['net'] ?? 0);
                $newVat -= (float)($origVatByRate[$rate]['vat'] ?? 0);
            }
            if ($newNet != 0 || $newVat != 0 || isset($vatByRate[$rate]) || isset($origVatByRate[$rate])) {
                self::addText($dom, $fa, $fields[0], self::fmt($newNet));
                self::addText($dom, $fa, $fields[1], self::fmt($newVat));
                // P_14_xW — VAT in PLN for foreign currency invoices (required by FA(3) schema)
                if ($currency !== 'PLN' && $exchangeRate > 0) {
                    $vatPln = round($newVat * $exchangeRate, 2);
                    self::addText($dom, $fa, $fields[1] . 'W', self::fmt($vatPln));
                }
            }
        }
        // 0% rate
        $zeroNet = (float)($vatByRate[0]['net'] ?? 0);
        if ($isCorrection && !empty($origVatByRate)) {
            $zeroNet -= (float)($origVatByRate[0]['net'] ?? 0);
        }
        if ($zeroNet != 0 || isset($vatByRate[0]) || isset($origVatByRate[0])) {
            self::addText($dom, $fa, 'P_13_6_1', self::fmt($zeroNet));
        }

        // 6. P_15 (gross total) — differential for corrections
        $grossAmount = (float)$invoice['gross_amount'];
        if ($isCorrection && !empty($invoice['original_gross_amount'])) {
            $grossAmount -= (float)$invoice['original_gross_amount'];
        }
        self::addText($dom, $fa, 'P_15', self::fmt($grossAmount));

        // 6a. KursWalutyZ — exchange rate for foreign currency invoices (after P_15, before Adnotacje)
        if ($currency !== 'PLN' && $exchangeRate > 0) {
            self::addText($dom, $fa, 'KursWalutyZ', self::fmtDecimal((float)$invoice['exchange_rate']));
        }

        // 7. Adnotacje (Annotations) — mandatory in FA(3), AFTER P_15
        $adnotacje = $dom->createElement('Adnotacje');
        $fa->appendChild($adnotacje);
        self::addText($dom, $adnotacje, 'P_16', '2'); // No cash accounting
        self::addText($dom, $adnotacje, 'P_17', '2'); // No self-invoicing
        self::addText($dom, $adnotacje, 'P_18', '2'); // No reverse charge
        self::addText($dom, $adnotacje, 'P_18A', !empty($invoice['is_split_payment']) ? '1' : '2');

        $zwolnienie = $dom->createElement('Zwolnienie');
        $adnotacje->appendChild($zwolnienie);
        self::addText($dom, $zwolnienie, 'P_19N', '1'); // No VAT exemption

        $noweSrodki = $dom->createElement('NoweSrodkiTransportu');
        $adnotacje->appendChild($noweSrodki);
        self::addText($dom, $noweSrodki, 'P_22N', '1'); // No new transport means

        self::addText($dom, $adnotacje, 'P_23', '2'); // No simplified procedure

        $pMarzy = $dom->createElement('PMarzy');
        $adnotacje->appendChild($pMarzy);
        self::addText($dom, $pMarzy, 'P_PMarzyN', '1'); // No margin procedure

        // 8. RodzajFaktury — AFTER Adnotacje
        $invoiceType = $invoice['invoice_type'] ?? 'FV';
        if ($isCorrection) {
            self::addText($dom, $fa, 'RodzajFaktury', 'KOR');
            if (!empty($invoice['correction_reason'])) {
                self::addText($dom, $fa, 'PrzyczynaKorekty', $invoice['correction_reason']);
            }
            $typKorekty = (string)($invoice['correction_type'] ?? 1);
            self::addText($dom, $fa, 'TypKorekty', $typKorekty);

            // DaneFaKorygowanej — reference to original invoice
            if (!empty($invoice['corrected_invoice_id'])) {
                $originalInvoice = IssuedInvoice::findById((int)$invoice['corrected_invoice_id']);
                if ($originalInvoice) {
                    $daneFaKor = $dom->createElement('DaneFaKorygowanej');
                    $fa->appendChild($daneFaKor);
                    self::addText($dom, $daneFaKor, 'DataWystFaKorygowanej', $originalInvoice['issue_date']);
                    self::addText($dom, $daneFaKor, 'NrFaKorygowanej', $originalInvoice['invoice_number']);

                    $origKsefRef = $originalInvoice['ksef_reference_number'] ?? '';
                    $origKsefStatus = $originalInvoice['ksef_status'] ?? 'none';
                    // Validate that reference matches TNumerKSeF pattern (NIP-YYYYMMDD-XXXXXX-XXXXXX-XX)
                    $validKsefRef = !empty($origKsefRef)
                        && preg_match('/^([1-9]\d{9}|M\d{9}|[A-Z]{3}\d{7})-\d{8}-[0-9A-F]{6}-?[0-9A-F]{6}-[0-9A-F]{2}$/', $origKsefRef);
                    if ($validKsefRef && !in_array($origKsefStatus, ['none', '', 'error'], true)) {
                        // Original invoice was sent to KSeF with valid full reference
                        self::addText($dom, $daneFaKor, 'NrKSeF', '1');
                        self::addText($dom, $daneFaKor, 'NrKSeFFaKorygowanej', $origKsefRef);
                    } else {
                        // Original invoice not sent to KSeF or reference is incomplete
                        self::addText($dom, $daneFaKor, 'NrKSeFN', '1');
                    }
                }
            }
        } elseif ($invoiceType === 'FV_ZAL') {
            self::addText($dom, $fa, 'RodzajFaktury', 'ZAL');
        } elseif ($invoiceType === 'FV_KON') {
            self::addText($dom, $fa, 'RodzajFaktury', 'KON');
        } else {
            self::addText($dom, $fa, 'RodzajFaktury', 'VAT');
        }

        // 8a. FakturaZaliczkowa — for FV_KON (final invoice), reference advance invoices
        // Must appear BEFORE FaWiersz per XSD xs:sequence
        if ($invoiceType === 'FV_KON' && !empty($invoice['related_advance_ids'])) {
            $relatedIds = $invoice['related_advance_ids'];
            if (is_string($relatedIds)) $relatedIds = json_decode($relatedIds, true) ?: [];

            foreach ($relatedIds as $advId) {
                $advInvoice = IssuedInvoice::findById((int) $advId);
                if (!$advInvoice) continue;

                $fakturaZal = $dom->createElement('FakturaZaliczkowa');
                $fa->appendChild($fakturaZal);

                // Check if advance invoice was sent to KSeF
                $advKsefRef = $advInvoice['ksef_reference_number'] ?? '';
                $advKsefStatus = $advInvoice['ksef_status'] ?? 'none';
                $validAdvKsefRef = !empty($advKsefRef)
                    && preg_match('/^([1-9]\d{9}|M\d{9}|[A-Z]{3}\d{7})-\d{8}-[0-9A-F]{6}-?[0-9A-F]{6}-[0-9A-F]{2}$/', $advKsefRef);

                if ($validAdvKsefRef && !in_array($advKsefStatus, ['none', '', 'error'], true)) {
                    // Advance invoice was sent to KSeF — reference by KSeF number
                    self::addText($dom, $fakturaZal, 'NrKSeFFaZaliczkowej', $advKsefRef);
                } else {
                    // Advance invoice was NOT sent to KSeF — reference by invoice number
                    self::addText($dom, $fakturaZal, 'NrKSeFZN', '1');
                    self::addText($dom, $fakturaZal, 'NrFaZaliczkowej', $advInvoice['invoice_number']);
                }
            }
        }

        // 9. FaWiersz (line items) — AFTER FakturaZaliczkowa
        $wierszNr = 1;

        // For corrections: emit original lines with StanPrzed=1 first
        if ($isCorrection && !empty($invoice['original_line_items'])) {
            $origItems = $invoice['original_line_items'];
            if (is_string($origItems)) $origItems = json_decode($origItems, true) ?: [];

            foreach ($origItems as $item) {
                $wiersz = $dom->createElement('FaWiersz');
                $fa->appendChild($wiersz);

                self::addText($dom, $wiersz, 'NrWierszaFa', (string)$wierszNr++);
                self::addText($dom, $wiersz, 'P_7', $item['name'] ?? '');
                self::addText($dom, $wiersz, 'P_8A', $item['unit'] ?? 'szt.');
                self::addText($dom, $wiersz, 'P_8B', self::fmtDecimal((float)($item['quantity'] ?? 1)));
                self::addText($dom, $wiersz, 'P_9A', self::fmtDecimal((float)($item['unit_price'] ?? 0)));
                $net = round(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0), 2);
                self::addText($dom, $wiersz, 'P_11', self::fmtDecimal($net));

                $vatRate = $item['vat_rate'] ?? '23';
                self::addText($dom, $wiersz, 'P_12', (string)$vatRate);
                // KursWaluty per line item for foreign currency invoices
                if ($currency !== 'PLN' && !empty($invoice['exchange_rate'])) {
                    self::addText($dom, $wiersz, 'KursWaluty', self::fmtDecimal((float)$invoice['exchange_rate']));
                }
                // StanPrzed=1 marks this as "before correction" data
                self::addText($dom, $wiersz, 'StanPrzed', '1');
            }
        }

        // Corrected (new) lines — no StanPrzed = these are "after correction"
        foreach ($lineItems as $item) {
            $wiersz = $dom->createElement('FaWiersz');
            $fa->appendChild($wiersz);

            self::addText($dom, $wiersz, 'NrWierszaFa', (string)$wierszNr++);
            self::addText($dom, $wiersz, 'P_7', $item['name'] ?? '');
            self::addText($dom, $wiersz, 'P_8A', $item['unit'] ?? 'szt.');
            self::addText($dom, $wiersz, 'P_8B', self::fmtDecimal((float)($item['quantity'] ?? 1)));
            self::addText($dom, $wiersz, 'P_9A', self::fmtDecimal((float)($item['unit_price'] ?? 0)));
            self::addText($dom, $wiersz, 'P_11', self::fmtDecimal((float)($item['net'] ?? 0)));

            $vatRate = $item['vat_rate'] ?? '23';
            self::addText($dom, $wiersz, 'P_12', (string)$vatRate);
            // KursWaluty per line item for foreign currency invoices
            if ($currency !== 'PLN' && !empty($invoice['exchange_rate'])) {
                self::addText($dom, $wiersz, 'KursWaluty', self::fmtDecimal((float)$invoice['exchange_rate']));
            }
            // GTU code per line item
            if (!empty($item['gtu'])) {
                self::addText($dom, $wiersz, 'GTU', $item['gtu']);
            }
        }

        // 10. Platnosc (Payment) — AFTER FaWiersz, at the end
        $platnosc = $dom->createElement('Platnosc');
        $fa->appendChild($platnosc);

        // Zaplacono + DataZaplaty — both required together per XSD xs:sequence
        $isPaid = ($invoice['payment_status'] ?? '') === 'paid' || ($invoice['status'] ?? '') === 'paid';
        if ($isPaid && !empty($invoice['payment_date'])) {
            self::addText($dom, $platnosc, 'Zaplacono', '1');
            self::addText($dom, $platnosc, 'DataZaplaty', $invoice['payment_date']);
        }

        // TerminPlatnosci — payment deadline
        if (!empty($invoice['due_date'])) {
            $terminPl = $dom->createElement('TerminPlatnosci');
            $platnosc->appendChild($terminPl);
            self::addText($dom, $terminPl, 'Termin', $invoice['due_date']);
        }

        // FormaPlatnosci — payment method code
        $metodaPl = match($invoice['payment_method'] ?? 'przelew') {
            'gotowka' => '1',
            'karta' => '3',
            'kompensata' => '4',
            default => '6', // przelew (bank transfer)
        };
        self::addText($dom, $platnosc, 'FormaPlatnosci', $metodaPl);

        // RachunekBankowy — bank account
        if (!empty($invoice['bank_account_number'])) {
            $rachunekBankowy = $dom->createElement('RachunekBankowy');
            $platnosc->appendChild($rachunekBankowy);
            self::addText($dom, $rachunekBankowy, 'NrRB', self::cleanBankAccount($invoice['bank_account_number']));
        }

        return $dom->saveXML();
    }

    private static function addText(\DOMDocument $dom, \DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private static function fmt(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    /**
     * Format decimal number with minimal trailing zeros.
     * E.g. 1.00 → "1", 71.50 → "71.5", 16.45 → "16.45"
     */
    private static function fmtDecimal(float $value): string
    {
        $formatted = number_format(round($value, 6), 6, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Validate XML against FA(3) XSD schema.
     * Returns array of error messages, empty if valid.
     */
    public static function validateXml(string $xml): array
    {
        $xsdPath = __DIR__ . '/../../schema/FA3.xsd';
        if (!file_exists($xsdPath)) {
            error_log('[KSeF] XSD schema not found at ' . $xsdPath . ', skipping validation');
            return []; // Skip validation if XSD not available
        }

        $errors = [];
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        if (!$dom->schemaValidate($xsdPath)) {
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message) . ' (line ' . $error->line . ')';
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        return $errors;
    }

    /**
     * Strip non-digit characters from NIP (remove dashes, spaces, etc.)
     * KSeF requires exactly 10 digits for Polish NIP.
     */
    private static function cleanNip(string $nip): string
    {
        return preg_replace('/\D/', '', $nip);
    }

    /**
     * Strip non-alphanumeric characters from bank account number.
     * KSeF NrRB accepts up to 34 chars (IBAN format without spaces).
     */
    private static function cleanBankAccount(string $account): string
    {
        return preg_replace('/\s+/', '', $account);
    }
}
