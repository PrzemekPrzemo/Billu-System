<?php

namespace App\Services;

use App\Core\Database;
use App\Models\BankAccount;
use App\Models\Setting;
use App\Services\KsefApiService;
use App\Services\WhiteListService;

class ElixirExportService
{
    private const SPLIT_PAYMENT_THRESHOLD = 15000.00;

    /**
     * Generate bank export files for unpaid purchase invoices, grouped by currency.
     *
     * PLN invoices → Elixir-O (.pli)
     * EUR invoices → SEPA pain.001.001.03 (.xml)
     * Other currencies → listed as manual transfers (no file generated)
     *
     * @return array Result with packages per currency, manual transfers, and aggregated verified/failed
     */
    public static function generate(
        int $clientId,
        array $invoiceIds,
        int $bankAccountId,
        ?string $executionDate = null
    ): array {
        $executionDate = $executionDate ?: date('Y-m-d');
        $db = Database::getInstance();

        if (empty($invoiceIds)) {
            return ['packages' => [], 'manual' => [], 'verified' => [], 'failed' => []];
        }

        // Get client data
        $client = $db->fetchOne("SELECT nip, company_name FROM clients WHERE id = ?", [$clientId]);
        $nip = $client['nip'] ?? 'unknown';
        $companyName = $client['company_name'] ?? '';

        // Get all client bank accounts indexed by currency
        $allBankAccounts = BankAccount::findByClient($clientId);
        $accountsByCurrency = [];
        foreach ($allBankAccounts as $ba) {
            $cur = strtoupper($ba['currency'] ?? 'PLN');
            if (!isset($accountsByCurrency[$cur])) {
                $accountsByCurrency[$cur] = $ba;
            }
        }

        // The selected bank account is used for PLN (backward compat)
        $selectedAccount = BankAccount::findById($bankAccountId);
        if ($selectedAccount && (int) $selectedAccount['client_id'] === $clientId) {
            $accountsByCurrency['PLN'] = $selectedAccount;
        }

        // Fetch invoices
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $invoices = $db->fetchAll(
            "SELECT * FROM invoices WHERE id IN ($placeholders) AND client_id = ? AND status = 'accepted' AND is_paid IN (0, 2)",
            array_merge($invoiceIds, [$clientId])
        );

        // Group invoices by currency after basic validation
        $invoicesByCurrency = [];
        $allFailed = [];
        $whitelistEnabled = Setting::get('whitelist_check_enabled', '1') === '1';

        foreach ($invoices as $inv) {
            $currency = strtoupper($inv['currency'] ?? 'PLN');
            $invoicesByCurrency[$currency][] = $inv;
        }

        $packages = [];
        $manual = [];
        $allVerified = [];
        $exportDir = dirname(__DIR__, 2) . '/storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }
        $dateElixir = str_replace('-', '', $executionDate);

        // Process each currency group
        foreach ($invoicesByCurrency as $currency => $currencyInvoices) {
            if ($currency === 'PLN') {
                $result = self::generateElixirPackage(
                    $currencyInvoices, $accountsByCurrency['PLN'] ?? null,
                    $whitelistEnabled, $dateElixir, $nip, $exportDir
                );
                if (!empty($result['verified'])) {
                    $packages['PLN'] = [
                        'file_path' => $result['file_path'],
                        'file_name' => $result['file_name'],
                        'format' => 'elixir',
                        'verified' => $result['verified'],
                        'failed' => $result['failed'],
                    ];
                    $allVerified = array_merge($allVerified, $result['verified']);
                }
                $allFailed = array_merge($allFailed, $result['failed']);

            } elseif ($currency === 'EUR') {
                $result = self::generateSepaPackage(
                    $currencyInvoices, $accountsByCurrency['EUR'] ?? null,
                    $executionDate, $nip, $companyName, $exportDir
                );
                if (!empty($result['verified'])) {
                    $packages['EUR'] = [
                        'file_path' => $result['file_path'],
                        'file_name' => $result['file_name'],
                        'format' => 'sepa',
                        'verified' => $result['verified'],
                        'failed' => $result['failed'],
                    ];
                    $allVerified = array_merge($allVerified, $result['verified']);
                }
                $allFailed = array_merge($allFailed, $result['failed']);

            } else {
                // Other currencies — manual transfer list
                $manualInvoices = [];
                foreach ($currencyInvoices as $inv) {
                    $parsed = !empty($inv['ksef_xml']) ? KsefApiService::parseKsefFaXml($inv['ksef_xml']) : [];
                    $manualInvoices[] = [
                        'invoice_id' => $inv['id'],
                        'invoice_number' => $inv['invoice_number'],
                        'seller_name' => $inv['seller_name'],
                        'seller_nip' => $inv['seller_nip'],
                        'amount' => (float) $inv['gross_amount'],
                        'currency' => $currency,
                        'bank_account' => $parsed['payment']['bank_account'] ?? '',
                        'swift' => $parsed['payment']['swift'] ?? '',
                        'ksef_ref' => $inv['ksef_reference_number'] ?? '',
                    ];
                }
                $manual[$currency] = [
                    'invoices' => $manualInvoices,
                    'total' => array_sum(array_column($manualInvoices, 'amount')),
                ];
            }
        }

        // Mark verified invoices (PLN + EUR only) as "transfer in progress"
        if (!empty($allVerified)) {
            $verifiedIds = array_column($allVerified, 'invoice_id');
            $ph = implode(',', array_fill(0, count($verifiedIds), '?'));
            $db->query("UPDATE invoices SET is_paid = 2 WHERE id IN ($ph)", $verifiedIds);
        }

        return [
            'packages' => $packages,
            'manual' => $manual,
            'verified' => $allVerified,
            'failed' => $allFailed,
        ];
    }

    /**
     * Generate Elixir-O package for PLN invoices.
     */
    private static function generateElixirPackage(
        array $invoices,
        ?array $orderingAccount,
        bool $whitelistEnabled,
        string $dateElixir,
        string $nip,
        string $exportDir
    ): array {
        $verified = [];
        $failed = [];
        $lines = [];

        if (!$orderingAccount) {
            foreach ($invoices as $inv) {
                $failed[] = [
                    'invoice_id' => $inv['id'],
                    'invoice_number' => $inv['invoice_number'],
                    'seller_name' => $inv['seller_name'],
                    'seller_nip' => $inv['seller_nip'],
                    'amount' => (float) $inv['gross_amount'],
                    'currency' => 'PLN',
                    'reason' => 'Brak konta bankowego PLN zleceniodawcy',
                ];
            }
            return ['file_path' => null, 'file_name' => '', 'verified' => $verified, 'failed' => $failed];
        }

        $orderingAccountNumber = WhiteListService::normalizeAccount($orderingAccount['account_number']);
        if (strlen($orderingAccountNumber) !== 26) {
            foreach ($invoices as $inv) {
                $failed[] = [
                    'invoice_id' => $inv['id'],
                    'invoice_number' => $inv['invoice_number'],
                    'seller_name' => $inv['seller_name'],
                    'seller_nip' => $inv['seller_nip'],
                    'amount' => (float) $inv['gross_amount'],
                    'currency' => 'PLN',
                    'reason' => 'Konto zleceniodawcy musi mieć 26 cyfr (format PL IBAN)',
                ];
            }
            return ['file_path' => null, 'file_name' => '', 'verified' => $verified, 'failed' => $failed];
        }

        $bankSort = substr($orderingAccountNumber, 2, 8);

        foreach ($invoices as $inv) {
            $invoiceInfo = [
                'invoice_id' => $inv['id'],
                'invoice_number' => $inv['invoice_number'],
                'seller_name' => $inv['seller_name'],
                'seller_nip' => $inv['seller_nip'],
                'amount' => (float) $inv['gross_amount'],
                'currency' => 'PLN',
            ];

            if (empty($inv['ksef_reference_number'])) {
                $failed[] = array_merge($invoiceInfo, ['reason' => 'Brak numeru referencyjnego KSeF']);
                continue;
            }
            $invoiceInfo['ksef_ref'] = $inv['ksef_reference_number'];

            if (empty($inv['ksef_xml'])) {
                $failed[] = array_merge($invoiceInfo, ['reason' => 'Brak danych XML KSeF']);
                continue;
            }

            $parsed = KsefApiService::parseKsefFaXml($inv['ksef_xml']);
            $recipientAccount = $parsed['payment']['bank_account'] ?? null;

            if (empty($recipientAccount)) {
                $failed[] = array_merge($invoiceInfo, ['reason' => 'Brak konta bankowego w fakturze KSeF']);
                continue;
            }

            $recipientAccountNorm = WhiteListService::normalizeAccount($recipientAccount);
            if (strlen($recipientAccountNorm) !== 26) {
                $failed[] = array_merge($invoiceInfo, ['reason' => 'Nieprawidłowy format konta odbiorcy (' . $recipientAccount . ')']);
                continue;
            }
            $invoiceInfo['bank_account'] = $recipientAccountNorm;

            if ($whitelistEnabled) {
                $wlResult = WhiteListService::verifyNipBankAccount($inv['seller_nip'], $recipientAccountNorm);
                if (!$wlResult['verified']) {
                    $failed[] = array_merge($invoiceInfo, ['reason' => $wlResult['message']]);
                    continue;
                }
            }

            $grossAmount = (float) $inv['gross_amount'];
            $vatAmount = (float) ($inv['vat_amount'] ?? 0);
            $splitPaymentAnnotation = !empty($parsed['annotations']['split_payment']);
            $isSplitPayment = $splitPaymentAnnotation || $grossAmount >= self::SPLIT_PAYMENT_THRESHOLD;

            $invoiceInfo['split_payment'] = $isSplitPayment;
            $invoiceInfo['vat_amount'] = $vatAmount;

            $amountGrosze = (int) round($grossAmount * 100);

            if ($isSplitPayment) {
                $lines[] = self::buildSplitPaymentLine(
                    $dateElixir, $amountGrosze, $bankSort,
                    $orderingAccountNumber, $recipientAccountNorm,
                    $inv['seller_name'], $inv['seller_address'] ?? '',
                    $vatAmount, $inv['seller_nip'],
                    $inv['invoice_number'], $inv['ksef_reference_number']
                );
            } else {
                $lines[] = self::buildElixirLine(
                    $dateElixir, $amountGrosze, $bankSort,
                    $orderingAccountNumber, $recipientAccountNorm,
                    $inv['seller_name'], $inv['seller_address'] ?? '',
                    $inv['ksef_reference_number']
                );
            }

            $verified[] = $invoiceInfo;
        }

        $filePath = null;
        $fileName = '';

        if (!empty($lines)) {
            $content = implode("\r\n", $lines) . "\r\n";
            $content = self::toCP852($content);
            $fileName = 'elixir_' . $nip . '_' . $dateElixir . '_' . time() . '.pli';
            $filePath = $exportDir . '/' . $fileName;
            if (@file_put_contents($filePath, $content) === false) {
                error_log("ElixirExport: Failed to write $filePath");
                $filePath = null;
                $fileName = '';
            }
        }

        return ['file_path' => $filePath, 'file_name' => $fileName, 'verified' => $verified, 'failed' => $failed];
    }

    /**
     * Generate SEPA pain.001.001.03 package for EUR invoices.
     */
    private static function generateSepaPackage(
        array $invoices,
        ?array $orderingAccount,
        string $executionDate,
        string $nip,
        string $companyName,
        string $exportDir
    ): array {
        $verified = [];
        $failed = [];

        if (!$orderingAccount) {
            foreach ($invoices as $inv) {
                $failed[] = [
                    'invoice_id' => $inv['id'],
                    'invoice_number' => $inv['invoice_number'],
                    'seller_name' => $inv['seller_name'],
                    'seller_nip' => $inv['seller_nip'],
                    'amount' => (float) $inv['gross_amount'],
                    'currency' => 'EUR',
                    'reason' => 'Brak konta bankowego EUR — dodaj konto walutowe w profilu firmy',
                ];
            }
            return ['file_path' => null, 'file_name' => '', 'verified' => $verified, 'failed' => $failed];
        }

        $orderingIban = self::normalizeIban($orderingAccount['account_number']);
        $orderingSwift = $orderingAccount['swift'] ?? '';

        // Validate and collect SEPA-eligible invoices
        $sepaItems = [];

        foreach ($invoices as $inv) {
            $invoiceInfo = [
                'invoice_id' => $inv['id'],
                'invoice_number' => $inv['invoice_number'],
                'seller_name' => $inv['seller_name'],
                'seller_nip' => $inv['seller_nip'],
                'amount' => (float) $inv['gross_amount'],
                'currency' => 'EUR',
            ];

            if (empty($inv['ksef_reference_number'])) {
                $failed[] = array_merge($invoiceInfo, ['reason' => 'Brak numeru referencyjnego KSeF']);
                continue;
            }
            $invoiceInfo['ksef_ref'] = $inv['ksef_reference_number'];

            if (empty($inv['ksef_xml'])) {
                $failed[] = array_merge($invoiceInfo, ['reason' => 'Brak danych XML KSeF']);
                continue;
            }

            $parsed = KsefApiService::parseKsefFaXml($inv['ksef_xml']);
            $recipientAccount = $parsed['payment']['bank_account'] ?? null;

            if (empty($recipientAccount)) {
                $failed[] = array_merge($invoiceInfo, ['reason' => 'Brak konta bankowego w fakturze KSeF']);
                continue;
            }

            $recipientIban = self::normalizeIban($recipientAccount);
            if (strlen($recipientIban) < 15 || strlen($recipientIban) > 34) {
                $failed[] = array_merge($invoiceInfo, ['reason' => 'Nieprawidłowy format IBAN odbiorcy (' . $recipientAccount . ')']);
                continue;
            }
            $invoiceInfo['bank_account'] = $recipientIban;
            $invoiceInfo['swift'] = $parsed['payment']['swift'] ?? '';
            $invoiceInfo['vat_amount'] = (float) ($inv['vat_amount'] ?? 0);

            $sepaItems[] = [
                'invoice' => $inv,
                'info' => $invoiceInfo,
                'recipientIban' => $recipientIban,
                'recipientSwift' => $parsed['payment']['swift'] ?? '',
                'recipientName' => $inv['seller_name'],
            ];

            $verified[] = $invoiceInfo;
        }

        $filePath = null;
        $fileName = '';

        if (!empty($sepaItems)) {
            $xml = self::buildSepaXml($sepaItems, $orderingIban, $orderingSwift, $companyName, $nip, $executionDate);
            $dateCompact = str_replace('-', '', $executionDate);
            $fileName = 'sepa_EUR_' . $nip . '_' . $dateCompact . '_' . time() . '.xml';
            $filePath = $exportDir . '/' . $fileName;
            if (@file_put_contents($filePath, $xml) === false) {
                error_log("SepaExport: Failed to write $filePath");
                $filePath = null;
                $fileName = '';
            }
        }

        return ['file_path' => $filePath, 'file_name' => $fileName, 'verified' => $verified, 'failed' => $failed];
    }

    /**
     * Build SEPA Credit Transfer Initiation XML (pain.001.001.03).
     */
    private static function buildSepaXml(
        array $items,
        string $debtorIban,
        string $debtorBic,
        string $debtorName,
        string $debtorNip,
        string $executionDate
    ): string {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $document = $dom->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.001.001.03', 'Document');
        $dom->appendChild($document);

        $cstmr = $dom->createElement('CstmrCdtTrfInitn');
        $document->appendChild($cstmr);

        // Group Header
        $grpHdr = $dom->createElement('GrpHdr');
        $cstmr->appendChild($grpHdr);

        $msgId = 'SEPA-' . $debtorNip . '-' . str_replace('-', '', $executionDate) . '-' . time();
        self::sepaText($dom, $grpHdr, 'MsgId', substr($msgId, 0, 35));
        self::sepaText($dom, $grpHdr, 'CreDtTm', date('c'));
        self::sepaText($dom, $grpHdr, 'NbOfTxs', (string) count($items));

        $ctrlSum = array_sum(array_map(fn($i) => (float) $i['invoice']['gross_amount'], $items));
        self::sepaText($dom, $grpHdr, 'CtrlSum', number_format($ctrlSum, 2, '.', ''));

        $initgPty = $dom->createElement('InitgPty');
        $grpHdr->appendChild($initgPty);
        self::sepaText($dom, $initgPty, 'Nm', mb_substr($debtorName, 0, 70));

        // Payment Information
        $pmtInf = $dom->createElement('PmtInf');
        $cstmr->appendChild($pmtInf);

        self::sepaText($dom, $pmtInf, 'PmtInfId', substr('PMT-' . $msgId, 0, 35));
        self::sepaText($dom, $pmtInf, 'PmtMtd', 'TRF');
        self::sepaText($dom, $pmtInf, 'NbOfTxs', (string) count($items));
        self::sepaText($dom, $pmtInf, 'CtrlSum', number_format($ctrlSum, 2, '.', ''));

        // Payment Type Information
        $pmtTpInf = $dom->createElement('PmtTpInf');
        $pmtInf->appendChild($pmtTpInf);
        $svcLvl = $dom->createElement('SvcLvl');
        $pmtTpInf->appendChild($svcLvl);
        self::sepaText($dom, $svcLvl, 'Cd', 'SEPA');

        self::sepaText($dom, $pmtInf, 'ReqdExctnDt', $executionDate);

        // Debtor
        $dbtr = $dom->createElement('Dbtr');
        $pmtInf->appendChild($dbtr);
        self::sepaText($dom, $dbtr, 'Nm', mb_substr($debtorName, 0, 70));

        // Debtor Account
        $dbtrAcct = $dom->createElement('DbtrAcct');
        $pmtInf->appendChild($dbtrAcct);
        $dbtrAcctId = $dom->createElement('Id');
        $dbtrAcct->appendChild($dbtrAcctId);
        self::sepaText($dom, $dbtrAcctId, 'IBAN', $debtorIban);
        self::sepaText($dom, $dbtrAcct, 'Ccy', 'EUR');

        // Debtor Agent
        $dbtrAgt = $dom->createElement('DbtrAgt');
        $pmtInf->appendChild($dbtrAgt);
        $finInstnId = $dom->createElement('FinInstnId');
        $dbtrAgt->appendChild($finInstnId);
        if (!empty($debtorBic)) {
            self::sepaText($dom, $finInstnId, 'BIC', $debtorBic);
        } else {
            self::sepaText($dom, $finInstnId, 'Othr', 'NOTPROVIDED');
        }

        self::sepaText($dom, $pmtInf, 'ChrgBr', 'SLEV'); // Shared charges

        // Credit Transfer Transaction Information — one per invoice
        foreach ($items as $idx => $item) {
            $cdtTrfTxInf = $dom->createElement('CdtTrfTxInf');
            $pmtInf->appendChild($cdtTrfTxInf);

            // Payment ID
            $pmtId = $dom->createElement('PmtId');
            $cdtTrfTxInf->appendChild($pmtId);
            $endToEndId = substr('E2E-' . ($idx + 1) . '-' . preg_replace('/[^A-Za-z0-9]/', '', $item['invoice']['invoice_number']), 0, 35);
            self::sepaText($dom, $pmtId, 'EndToEndId', $endToEndId);

            // Amount
            $amt = $dom->createElement('Amt');
            $cdtTrfTxInf->appendChild($amt);
            $instdAmt = $dom->createElement('InstdAmt', number_format((float) $item['invoice']['gross_amount'], 2, '.', ''));
            $instdAmt->setAttribute('Ccy', 'EUR');
            $amt->appendChild($instdAmt);

            // Creditor Agent (BIC/SWIFT)
            if (!empty($item['recipientSwift'])) {
                $cdtrAgt = $dom->createElement('CdtrAgt');
                $cdtTrfTxInf->appendChild($cdtrAgt);
                $cdtrFinInstn = $dom->createElement('FinInstnId');
                $cdtrAgt->appendChild($cdtrFinInstn);
                self::sepaText($dom, $cdtrFinInstn, 'BIC', $item['recipientSwift']);
            }

            // Creditor
            $cdtr = $dom->createElement('Cdtr');
            $cdtTrfTxInf->appendChild($cdtr);
            self::sepaText($dom, $cdtr, 'Nm', mb_substr($item['recipientName'], 0, 70));

            // Creditor Account
            $cdtrAcct = $dom->createElement('CdtrAcct');
            $cdtTrfTxInf->appendChild($cdtrAcct);
            $cdtrAcctId = $dom->createElement('Id');
            $cdtrAcct->appendChild($cdtrAcctId);
            self::sepaText($dom, $cdtrAcctId, 'IBAN', $item['recipientIban']);

            // Remittance Information (payment reference)
            $rmtInf = $dom->createElement('RmtInf');
            $cdtTrfTxInf->appendChild($rmtInf);
            $ref = $item['invoice']['ksef_reference_number'] ?? $item['invoice']['invoice_number'];
            self::sepaText($dom, $rmtInf, 'Ustrd', mb_substr($ref, 0, 140));
        }

        return $dom->saveXML();
    }

    private static function sepaText(\DOMDocument $dom, \DOMElement $parent, string $tag, string $value): void
    {
        $el = $dom->createElement($tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    /**
     * Normalize IBAN — remove spaces, dashes, uppercase.
     */
    private static function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', $iban));
    }

    // ── Elixir-O builders (unchanged) ──────────────────────────

    private static function buildElixirLine(
        string $executionDate,
        int $amountGrosze,
        string $bankSort,
        string $orderingAccount,
        string $recipientAccount,
        string $recipientName,
        string $recipientAddress,
        string $title
    ): string {
        $recipientData = self::splitToLines($recipientName . ', ' . $recipientAddress);
        $paymentTitle = self::splitToLines($title);

        return sprintf(
            '"%s","%s","%s","%s","%s","%s","%s","%s","%s",%s,,"%s","%s","%s","%s","%s"',
            '110', $executionDate, (string) $amountGrosze, $bankSort, '0',
            $orderingAccount, $recipientAccount, '', $recipientData, '0',
            $paymentTitle, '', '', '51', ''
        );
    }

    private static function buildSplitPaymentLine(
        string $executionDate,
        int $amountGrosze,
        string $bankSort,
        string $orderingAccount,
        string $recipientAccount,
        string $recipientName,
        string $recipientAddress,
        float $vatAmount,
        string $sellerNip,
        string $invoiceNumber,
        string $ksefReference
    ): string {
        $recipientData = self::splitToLines($recipientName . ', ' . $recipientAddress);
        $vatFormatted = number_format($vatAmount, 2, ',', '');
        $splitTitle = '/VAT/' . $vatFormatted
            . '/IDC/' . $sellerNip
            . '/INV/' . $invoiceNumber
            . '/TXT/' . $ksefReference;
        $paymentTitle = self::splitToLines($splitTitle);

        return sprintf(
            '"%s","%s","%s","%s","%s","%s","%s","%s","%s",%s,,"%s","%s","%s","%s","%s"',
            '120', $executionDate, (string) $amountGrosze, $bankSort, '0',
            $orderingAccount, $recipientAccount, '', $recipientData, '0',
            $paymentTitle, '', '', '53', ''
        );
    }

    private static function splitToLines(string $text, int $maxLen = 35, int $maxLines = 4): string
    {
        $text = trim($text);
        if (empty($text)) {
            return '|';
        }

        $text = preg_replace('/[\r\n]+/', ' ', $text);
        $lines = [];
        $remaining = $text;

        for ($i = 0; $i < $maxLines && !empty($remaining); $i++) {
            if (mb_strlen($remaining) <= $maxLen) {
                $lines[] = $remaining;
                $remaining = '';
            } else {
                $chunk = mb_substr($remaining, 0, $maxLen);
                $lastSpace = mb_strrpos($chunk, ' ');
                if ($lastSpace !== false && $lastSpace > 10) {
                    $lines[] = mb_substr($remaining, 0, $lastSpace);
                    $remaining = ltrim(mb_substr($remaining, $lastSpace));
                } else {
                    $lines[] = $chunk;
                    $remaining = mb_substr($remaining, $maxLen);
                }
            }
        }

        return implode('|', $lines) . '|';
    }

    private static function toCP852(string $text): string
    {
        $converted = @iconv('UTF-8', 'CP852//TRANSLIT', $text);
        return $converted !== false ? $converted : $text;
    }
}
