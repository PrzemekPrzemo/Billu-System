<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\IssuedInvoice;

/**
 * Mock KSeF operations for demo accounts.
 * Generates realistic-looking data without calling the real KSeF API.
 */
class DemoKsefMockService
{
    /**
     * Generate random cost invoices as if fetched from KSeF.
     */
    public static function mockImport(
        int $clientId,
        int $month,
        int $year,
        int $importedById,
        string $importedByType,
        int $officeId
    ): array {
        $result = ['success' => 0, 'errors' => [], 'total' => 0, 'skipped' => 0];

        // Find or create batch
        $batch = InvoiceBatch::findByClientAndPeriod($clientId, $month, $year);
        $deadline = ImportService::calculateDeadlineDate($month, $year);

        if (!$batch) {
            $batchId = InvoiceBatch::create([
                'client_id'             => $clientId,
                'office_id'             => $officeId,
                'period_month'          => $month,
                'period_year'           => $year,
                'verification_deadline' => $deadline,
                'is_finalized'          => 0,
                'source'                => 'ksef',
            ]);
        } else {
            $batchId = (int) $batch['id'];
            if ($batch['is_finalized']) {
                InvoiceBatch::reopen($batchId);
            }
        }

        $sellers = self::getSellers();
        $invoiceCount = rand(5, 10);
        $result['total'] = $invoiceCount;

        $client = Client::findById($clientId);
        $clientNip = $client['nip'] ?? '0000000000';
        $clientName = $client['company_name'] ?? 'Demo Klient';

        // Check existing invoices to avoid duplicate numbers
        $existingNumbers = \App\Core\Database::getInstance()->fetchAll(
            "SELECT invoice_number FROM invoices WHERE batch_id = ?",
            [$batchId]
        );
        $existingSet = array_column($existingNumbers, 'invoice_number');
        $startNum = count($existingSet) + 1;

        for ($i = 0; $i < $invoiceCount; $i++) {
            $seller = $sellers[array_rand($sellers)];
            $net = round(rand(10000, 3500000) / 100, 2);
            $vatRate = $seller['vat_rate'];
            $vat = round($net * $vatRate / 100, 2);
            $gross = $net + $vat;
            $day = rand(1, min(28, (int) date('t', mktime(0, 0, 0, $month, 1, $year))));
            $issueDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $ksefRef = 'KSEF-DEMO-' . strtoupper(bin2hex(random_bytes(8)));
            $invNumber = sprintf('FV/%s/%04d/%02d/%03d', $seller['prefix'], $year, $month, $startNum + $i);

            if (in_array($invNumber, $existingSet)) {
                $result['skipped']++;
                continue;
            }

            try {
                Invoice::create([
                    'batch_id'              => $batchId,
                    'client_id'             => $clientId,
                    'invoice_number'        => $invNumber,
                    'seller_nip'            => $seller['nip'],
                    'seller_name'           => $seller['name'],
                    'seller_address'        => $seller['address'],
                    'buyer_nip'             => $clientNip,
                    'buyer_name'            => $clientName,
                    'issue_date'            => $issueDate,
                    'sale_date'             => $issueDate,
                    'currency'              => 'PLN',
                    'net_amount'            => $net,
                    'vat_amount'            => $vat,
                    'gross_amount'          => $gross,
                    'status'                => 'pending',
                    'ksef_reference_number' => $ksefRef,
                    'source'                => 'ksef',
                ]);
                $result['success']++;
            } catch (\Throwable $e) {
                $result['errors'][] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Simulate sending invoices to KSeF — mark them as sent with fake reference numbers.
     */
    public static function mockBatchSend(array $invoiceIds, int $clientId): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($invoiceIds as $invoiceId) {
            $invoiceId = (int) $invoiceId;
            $invoice = IssuedInvoice::findById($invoiceId);
            if (!$invoice) {
                $results[$invoiceId] = ['status' => 'error', 'message' => 'Faktura nie znaleziona'];
                $errorCount++;
                continue;
            }

            $fakeRef = 'KSEF-DEMO-' . strtoupper(bin2hex(random_bytes(10)));

            IssuedInvoice::updateKsefStatus($invoiceId, 'sent', $fakeRef);
            IssuedInvoice::updateStatus($invoiceId, 'sent_ksef');

            $results[$invoiceId] = [
                'status'         => 'completed',
                'message'        => $fakeRef,
                'invoice_number' => $invoice['invoice_number'],
            ];
            $successCount++;

            // Small delay to simulate real API
            usleep(200000);
        }

        return [
            'results'       => $results,
            'success_count' => $successCount,
            'error_count'   => $errorCount,
        ];
    }

    private static function getSellers(): array
    {
        return [
            ['nip' => '5272643650', 'name' => 'Orange Polska S.A.', 'address' => 'Al. Jerozolimskie 160, Warszawa', 'vat_rate' => 23, 'prefix' => 'OR'],
            ['nip' => '7740001454', 'name' => 'PKN Orlen S.A.', 'address' => 'ul. Chemików 7, Płock', 'vat_rate' => 23, 'prefix' => 'ORL'],
            ['nip' => '5260250995', 'name' => 'PGE Polska Grupa Energetyczna S.A.', 'address' => 'ul. Mysia 2, Warszawa', 'vat_rate' => 23, 'prefix' => 'PGE'],
            ['nip' => '1132853869', 'name' => 'Lyreco Polska S.A.', 'address' => 'ul. Sokołowska 33, Warszawa', 'vat_rate' => 23, 'prefix' => 'LYR'],
            ['nip' => '9512012600', 'name' => 'OVHcloud Sp. z o.o.', 'address' => 'ul. Szkocka 5/7, Wrocław', 'vat_rate' => 23, 'prefix' => 'OVH'],
            ['nip' => '5213003520', 'name' => 'Allegro.pl Sp. z o.o.', 'address' => 'ul. Grunwaldzka 182, Poznań', 'vat_rate' => 23, 'prefix' => 'ALG'],
            ['nip' => '7791011327', 'name' => 'Media Expert', 'address' => 'ul. 17 Stycznia 56, Łódź', 'vat_rate' => 23, 'prefix' => 'ME'],
            ['nip' => '5862014478', 'name' => 'Leroy Merlin Polska', 'address' => 'ul. Targowa 72, Warszawa', 'vat_rate' => 8, 'prefix' => 'LM'],
            ['nip' => '5261032120', 'name' => 'Makro Cash and Carry', 'address' => 'Al. Krakowska 61, Warszawa', 'vat_rate' => 23, 'prefix' => 'MK'],
            ['nip' => '5220100534', 'name' => 'Polkomtel Sp. z o.o.', 'address' => 'ul. Konstruktorska 4, Warszawa', 'vat_rate' => 23, 'prefix' => 'PLUS'],
        ];
    }
}
