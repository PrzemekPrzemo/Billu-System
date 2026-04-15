<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Office;
use App\Models\Client;
use App\Models\OfficeEmployee;
use App\Models\InvoiceBatch;
use App\Models\Invoice;
use App\Models\IssuedInvoice;
use App\Models\Contractor;
use App\Models\Message;
use App\Models\ClientTask;
use App\Models\TaxPayment;
use App\Models\CompanyProfile;
use App\Models\BankAccount;
use App\Models\AuditLog;

class DemoSeederService
{
    private const DEFAULT_PASSWORD = 'Demo2026!@#';

    /**
     * Seed all demo data. Returns login credentials.
     */
    public static function seedAll(): array
    {
        $db = Database::getInstance();
        $passwordHash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_BCRYPT);
        $credentials = [];
        $now = date('Y-m-d H:i:s');

        // ── 1. Demo Office ──
        $officeId = Office::create([
            'nip'                 => '0000000001',
            'name'                => 'Demo Biuro Księgowe Sp. z o.o.',
            'address'             => 'ul. Przykładowa 10, 00-001 Warszawa',
            'email'               => 'demo-biuro@billu.pl',
            'phone'               => '+48 22 000 0001',
            'representative_name' => 'Jan Nowak',
            'password_hash'       => $passwordHash,
            'language'            => 'pl',
            'is_active'           => 1,
            'is_demo'             => 1,
        ]);
        $credentials[] = [
            'type' => 'Biuro księgowe',
            'name' => 'Demo Biuro Księgowe Sp. z o.o.',
            'login' => 'demo-biuro@billu.pl',
            'password' => self::DEFAULT_PASSWORD,
        ];

        // ── 2. Demo Employee ──
        $employeeId = OfficeEmployee::create([
            'office_id'             => $officeId,
            'name'                  => 'Anna Kowalska',
            'email'                 => 'anna.kowalska@demo-biuro.pl',
            'password_hash'         => $passwordHash,
            'force_password_change' => 0,
            'is_active'             => 1,
        ]);
        $credentials[] = [
            'type' => 'Pracownik biura',
            'name' => 'Anna Kowalska',
            'login' => 'anna.kowalska@demo-biuro.pl',
            'password' => self::DEFAULT_PASSWORD,
        ];

        // ── 3. Demo Clients ──
        $clientDefs = [
            [
                'nip'          => '0000000011',
                'company_name' => 'TechSoft Sp. z o.o.',
                'rep'          => 'Piotr Wiśniewski',
                'email'        => 'demo-techsoft@billu.pl',
                'address'      => 'ul. Programistów 5, 02-100 Warszawa',
                'branch'       => 'IT',
            ],
            [
                'nip'          => '0000000012',
                'company_name' => 'Handel Plus S.A.',
                'rep'          => 'Maria Zielińska',
                'email'        => 'demo-handel@billu.pl',
                'address'      => 'ul. Handlowa 22, 60-300 Poznań',
                'branch'       => 'trade',
            ],
            [
                'nip'          => '0000000013',
                'company_name' => 'BudExpert Sp. z o.o.',
                'rep'          => 'Tomasz Kamiński',
                'email'        => 'demo-budexpert@billu.pl',
                'address'      => 'ul. Budowlana 8, 30-400 Kraków',
                'branch'       => 'construction',
            ],
        ];

        foreach ($clientDefs as $def) {
            $clientId = Client::create([
                'nip'                   => $def['nip'],
                'company_name'          => $def['company_name'],
                'representative_name'   => $def['rep'],
                'email'                 => $def['email'],
                'report_email'          => $def['email'],
                'address'               => $def['address'],
                'phone'                 => '+48 ' . rand(500, 799) . ' ' . rand(100, 999) . ' ' . rand(100, 999),
                'office_id'             => $officeId,
                'password_hash'         => $passwordHash,
                'force_password_change' => 0,
                'language'              => 'pl',
                'is_active'             => 1,
                'ksef_enabled'          => 1,
                'is_demo'               => 1,
            ]);

            $credentials[] = [
                'type' => 'Klient',
                'name' => $def['company_name'],
                'login' => $def['nip'],
                'password' => self::DEFAULT_PASSWORD,
            ];

            // Assign employee to client
            $db->query("INSERT IGNORE INTO office_employee_clients (employee_id, client_id) VALUES (?, ?)", [$employeeId, $clientId]);

            // Seed all data for this client
            self::seedClientData($clientId, $officeId, $def['branch'], $now);
        }

        return $credentials;
    }

    /**
     * Seed sample data for a single client.
     */
    private static function seedClientData(int $clientId, int $officeId, string $branch, string $now): void
    {
        $curMonth = (int) date('n');
        $curYear = (int) date('Y');

        // ── Company profile + bank account ──
        CompanyProfile::upsert($clientId, [
            'default_payment_method' => 'przelew',
            'default_payment_days'   => 14,
            'invoice_number_pattern' => 'FV/{YYYY}/{MM}/{NR}',
            'next_invoice_number'    => 1,
            'invoice_notes'          => 'Dziękujemy za terminową płatność.',
        ]);

        BankAccount::create($clientId, [
            'account_name'   => 'Konto główne',
            'bank_name'      => 'PKO BP',
            'account_number' => 'PL' . str_pad((string) rand(10000000000000000, 99999999999999999), 26, '0', STR_PAD_LEFT),
            'currency'       => 'PLN',
            'is_default_receiving' => 1,
            'is_default_outgoing'  => 1,
        ]);

        // ── Cost centers ──
        $db = Database::getInstance();
        $costCenterNames = match ($branch) {
            'IT' => ['Rozwój oprogramowania', 'Administracja', 'Marketing'],
            'trade' => ['Magazyn', 'Logistyka', 'Biuro'],
            default => ['Budowy', 'Sprzęt', 'Administracja'],
        };
        $costCenterIds = [];
        foreach ($costCenterNames as $i => $ccName) {
            $db->query(
                "INSERT INTO client_cost_centers (client_id, name, is_active, sort_order) VALUES (?, ?, 1, ?)",
                [$clientId, $ccName, $i + 1]
            );
            $costCenterIds[] = (int) $db->lastInsertId();
        }
        $db->query("UPDATE clients SET has_cost_centers = 1 WHERE id = ?", [$clientId]);

        // ── Contractors (buyers for issued invoices) ──
        $contractorDefs = self::getContractorDefs($branch);
        $contractorIds = [];
        foreach ($contractorDefs as $cd) {
            $contractorIds[] = Contractor::create($clientId, $cd);
        }

        // ── Cost invoice batches (last 3 months) ──
        $sellers = self::getSellerDefs();
        for ($offset = 1; $offset <= 3; $offset++) {
            $batchMonth = $curMonth - $offset;
            $batchYear = $curYear;
            if ($batchMonth < 1) {
                $batchMonth += 12;
                $batchYear--;
            }

            $deadline = (new \DateTime("$batchYear-$batchMonth-01"))
                ->modify('+1 month')->modify('+14 days')->format('Y-m-d');

            $batchId = InvoiceBatch::create([
                'client_id'             => $clientId,
                'office_id'             => $officeId,
                'period_month'          => $batchMonth,
                'period_year'           => $batchYear,
                'verification_deadline' => $deadline,
                'is_finalized'          => ($offset >= 2) ? 1 : 0,
                'finalized_at'          => ($offset >= 2) ? $now : null,
                'source'                => 'file',
            ]);

            // 8-12 invoices per batch
            $invoiceCount = rand(8, 12);
            for ($j = 1; $j <= $invoiceCount; $j++) {
                $seller = $sellers[array_rand($sellers)];
                $net = round(rand(50000, 5000000) / 100, 2);
                $vatRate = $seller['vat_rate'];
                $vat = round($net * $vatRate / 100, 2);
                $gross = $net + $vat;
                $issueDay = rand(1, 28);
                $issueDate = sprintf('%04d-%02d-%02d', $batchYear, $batchMonth, $issueDay);

                $statuses = ($offset >= 2)
                    ? ['accepted', 'accepted', 'accepted', 'rejected']
                    : ['pending', 'pending', 'accepted', 'rejected', 'pending'];
                $status = $statuses[array_rand($statuses)];

                Invoice::create([
                    'batch_id'       => $batchId,
                    'client_id'      => $clientId,
                    'invoice_number' => sprintf('FV/%04d/%02d/%03d', $batchYear, $batchMonth, $j),
                    'seller_nip'     => $seller['nip'],
                    'seller_name'    => $seller['name'],
                    'seller_address' => $seller['address'],
                    'buyer_nip'      => str_pad('', 10, '0'),
                    'buyer_name'     => 'Demo Klient',
                    'issue_date'     => $issueDate,
                    'sale_date'      => $issueDate,
                    'currency'       => 'PLN',
                    'net_amount'     => $net,
                    'vat_amount'     => $vat,
                    'gross_amount'   => $gross,
                    'status'         => $status,
                    'comment'        => $status === 'rejected' ? 'Niezgodność danych na fakturze' : null,
                    'cost_center_id' => $costCenterIds[array_rand($costCenterIds)],
                    'verified_at'    => $status !== 'pending' ? $now : null,
                ]);
            }
        }

        // ── Issued invoices (sales) ──
        $issuedCount = rand(5, 8);
        for ($k = 1; $k <= $issuedCount; $k++) {
            $contractor = $contractorDefs[array_rand($contractorDefs)];
            $net = round(rand(200000, 10000000) / 100, 2);
            $vat = round($net * 0.23, 2);
            $gross = $net + $vat;
            $issueMonth = $curMonth - rand(0, 2);
            $issueYear = $curYear;
            if ($issueMonth < 1) {
                $issueMonth += 12;
                $issueYear--;
            }
            $issueDay = rand(1, 28);
            $issueDate = sprintf('%04d-%02d-%02d', $issueYear, $issueMonth, $issueDay);
            $dueDate = (new \DateTime($issueDate))->modify('+14 days')->format('Y-m-d');

            $statuses = ['draft', 'issued', 'issued', 'sent_ksef'];
            $status = $statuses[array_rand($statuses)];

            $lineItems = [[
                'name' => self::getServiceName($branch),
                'unit' => 'szt.',
                'quantity' => rand(1, 10),
                'unit_price' => $net,
                'vat_rate' => '23',
                'net' => $net,
                'vat' => $vat,
                'gross' => $gross,
            ]];

            $vatDetails = [[
                'rate' => '23',
                'net' => $net,
                'vat' => $vat,
                'gross' => $gross,
            ]];

            IssuedInvoice::create([
                'client_id'      => $clientId,
                'contractor_id'  => $contractorIds[array_rand($contractorIds)],
                'invoice_type'   => 'FV',
                'invoice_number' => sprintf('FS/%04d/%02d/%03d', $issueYear, $issueMonth, $k),
                'issue_date'     => $issueDate,
                'sale_date'      => $issueDate,
                'due_date'       => $dueDate,
                'seller_nip'     => str_pad('', 10, '0'),
                'seller_name'    => 'Demo Klient',
                'seller_address' => 'ul. Demo 1',
                'buyer_nip'      => $contractor['nip'],
                'buyer_name'     => $contractor['company_name'],
                'buyer_address'  => $contractor['address_street'] ?? 'Warszawa',
                'currency'       => 'PLN',
                'net_amount'     => $net,
                'vat_amount'     => $vat,
                'gross_amount'   => $gross,
                'line_items'     => $lineItems,
                'vat_details'    => $vatDetails,
                'payment_method' => 'przelew',
                'status'         => $status,
                'ksef_reference_number' => $status === 'sent_ksef' ? 'KSEF-DEMO-' . rand(100000, 999999) : null,
                'ksef_sent_at'          => $status === 'sent_ksef' ? $now : null,
                'ksef_status'           => $status === 'sent_ksef' ? 'accepted' : null,
            ]);
        }

        // ── Messages (2 threads) ──
        $threadSubjects = [
            'Pytanie o fakturę kosztową',
            'Dokumenty do rozliczenia VAT',
        ];
        foreach ($threadSubjects as $subject) {
            $threadId = Message::create($clientId, 'office', $officeId, 'Dzień dobry, proszę o weryfikację załączonych dokumentów.', $subject);
            Message::create($clientId, 'client', $clientId, 'Dziękuję, sprawdzę i odpowiem wkrótce.', null, null, null, $threadId);
            Message::create($clientId, 'office', $officeId, 'Przypominam o terminowej weryfikacji faktur.', null, null, null, $threadId);
        }

        // ── Tasks ──
        $taskDefs = [
            ['Zweryfikować faktury za ' . self::monthName($curMonth - 1), 'Proszę o weryfikację faktur kosztowych', 'high', 'open', -3],
            ['Przygotować dokumenty VAT', 'Dokumenty potrzebne do rozliczenia VAT', 'normal', 'in_progress', 7],
            ['Aktualizacja danych firmy', 'Zaktualizować adres i dane kontaktowe', 'low', 'open', 14],
            ['Rozliczenie poprzedniego kwartału', null, 'normal', 'done', -10],
        ];
        foreach ($taskDefs as $td) {
            $dueDate = (new \DateTime())->modify("{$td[4]} days")->format('Y-m-d');
            $taskId = ClientTask::create($clientId, 'office', $officeId, $td[0], $td[1], $td[2], $dueDate);
            if ($td[3] !== 'open') {
                ClientTask::markStatus($taskId, $td[3], $td[3] === 'done' ? 'office' : null, $td[3] === 'done' ? $officeId : null);
            }
        }

        // ── Tax Payments (last 6 months) ──
        for ($m = 1; $m <= 6; $m++) {
            $taxMonth = $curMonth - $m;
            $taxYear = $curYear;
            if ($taxMonth < 1) {
                $taxMonth += 12;
                $taxYear--;
            }

            $data = [];
            $data[$taxMonth] = [];
            foreach (['VAT', 'PIT', 'CIT'] as $type) {
                $amount = match ($type) {
                    'VAT' => round(rand(100000, 2500000) / 100, 2),
                    'PIT' => round(rand(50000, 800000) / 100, 2),
                    'CIT' => round(rand(80000, 1500000) / 100, 2),
                };
                $status = (rand(0, 3) === 0) ? 'do_przeniesienia' : 'do_zaplaty';
                $data[$taxMonth][$type] = [
                    'amount' => $amount,
                    'status' => $status,
                ];
            }

            TaxPayment::bulkUpsert($clientId, $taxYear, $data, 'office', $officeId);
        }
    }

    /**
     * Delete all demo data and re-seed.
     */
    public static function resetDemo(): array
    {
        $db = Database::getInstance();

        // Find demo clients and offices
        $demoClients = $db->fetchAll("SELECT id FROM clients WHERE is_demo = 1");
        $demoOffices = $db->fetchAll("SELECT id FROM offices WHERE is_demo = 1");

        $clientIds = array_column($demoClients, 'id');
        $officeIds = array_column($demoOffices, 'id');

        if (!empty($clientIds)) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));

            // Delete data that may not CASCADE
            $tables = [
                'tax_payments'          => 'client_id',
                'client_tasks'          => 'client_id',
                'messages'              => 'client_id',
                'issued_invoices'       => 'client_id',
                'invoices'              => 'client_id',
                'invoice_batches'       => 'client_id',
                'contractors'           => 'client_id',
                'company_services'      => 'client_id',
                'company_bank_accounts' => 'client_id',
                'company_profiles'      => 'client_id',
                'client_cost_centers'   => 'client_id',
                'reports'               => 'client_id',
                'ksef_config'           => 'client_id',
                'notifications'         => 'user_type = "client" AND user_id',
            ];
            foreach ($tables as $table => $col) {
                try {
                    if (str_contains($col, '=')) {
                        $db->query("DELETE FROM {$table} WHERE {$col} IN ({$placeholders})", $clientIds);
                    } else {
                        $db->query("DELETE FROM {$table} WHERE {$col} IN ({$placeholders})", $clientIds);
                    }
                } catch (\Throwable $e) {
                    // Table may not exist — skip
                }
            }

            // Delete employee-client assignments
            $db->query("DELETE FROM office_employee_clients WHERE client_id IN ({$placeholders})", $clientIds);

            // Delete clients
            $db->query("DELETE FROM clients WHERE id IN ({$placeholders})", $clientIds);
        }

        if (!empty($officeIds)) {
            $placeholders = implode(',', array_fill(0, count($officeIds), '?'));

            // Delete employees of demo offices
            $db->query("DELETE FROM office_employees WHERE office_id IN ({$placeholders})", $officeIds);

            // Delete offices
            $db->query("DELETE FROM offices WHERE id IN ({$placeholders})", $officeIds);
        }

        // Re-seed
        $credentials = self::seedAll();

        // Audit log
        AuditLog::log('admin', 1, 'demo_reset', 'Zresetowano dane demo');

        return $credentials;
    }

    /**
     * Change passwords for all demo accounts.
     */
    public static function resetDemoPasswords(string $newPassword): void
    {
        $db = Database::getInstance();
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);

        $db->query("UPDATE offices SET password_hash = ? WHERE is_demo = 1", [$hash]);
        $db->query("UPDATE clients SET password_hash = ?, force_password_change = 0 WHERE is_demo = 1", [$hash]);

        // Employees of demo offices
        $db->query(
            "UPDATE office_employees SET password_hash = ?, force_password_change = 0
             WHERE office_id IN (SELECT id FROM offices WHERE is_demo = 1)",
            [$hash]
        );

        AuditLog::log('admin', 1, 'demo_password_reset', 'Zmieniono hasła kont demo');
    }

    // ── Helper data ──

    private static function getSellerDefs(): array
    {
        return [
            ['nip' => '5272643650', 'name' => 'Orange Polska S.A.', 'address' => 'Al. Jerozolimskie 160, Warszawa', 'vat_rate' => 23],
            ['nip' => '7740001454', 'name' => 'PKN Orlen S.A.', 'address' => 'ul. Chemików 7, Płock', 'vat_rate' => 23],
            ['nip' => '5260250995', 'name' => 'PGE Polska Grupa Energetyczna S.A.', 'address' => 'ul. Mysia 2, Warszawa', 'vat_rate' => 23],
            ['nip' => '1132853869', 'name' => 'Lyreco Polska S.A.', 'address' => 'ul. Sokołowska 33, Warszawa', 'vat_rate' => 23],
            ['nip' => '9512012600', 'name' => 'OVHcloud Sp. z o.o.', 'address' => 'ul. Szkocka 5/7, Wrocław', 'vat_rate' => 23],
            ['nip' => '5213003520', 'name' => 'Allegro.pl Sp. z o.o.', 'address' => 'ul. Grunwaldzka 182, Poznań', 'vat_rate' => 23],
            ['nip' => '7791011327', 'name' => 'Media Expert', 'address' => 'ul. 17 Stycznia 56, Łódź', 'vat_rate' => 23],
            ['nip' => '5862014478', 'name' => 'Leroy Merlin Polska', 'address' => 'ul. Targowa 72, Warszawa', 'vat_rate' => 8],
        ];
    }

    private static function getContractorDefs(string $branch): array
    {
        return match ($branch) {
            'IT' => [
                ['company_name' => 'DataFlow Sp. z o.o.', 'nip' => '1111111111', 'email' => 'kontakt@dataflow.pl', 'address_street' => 'ul. Cyfrowa 10, Warszawa'],
                ['company_name' => 'CloudNet S.A.', 'nip' => '1111111112', 'email' => 'biuro@cloudnet.pl', 'address_street' => 'ul. Serwerowa 5, Kraków'],
                ['company_name' => 'SecureSoft Sp. z o.o.', 'nip' => '1111111113', 'email' => 'info@securesoft.pl', 'address_street' => 'ul. Bezpieczeństwa 3, Gdańsk'],
            ],
            'trade' => [
                ['company_name' => 'HurtMax Sp. z o.o.', 'nip' => '2222222221', 'email' => 'zamowienia@hurtmax.pl', 'address_street' => 'ul. Magazynowa 15, Poznań'],
                ['company_name' => 'SuperMarket S.A.', 'nip' => '2222222222', 'email' => 'biuro@supermarket.pl', 'address_street' => 'ul. Handlowa 8, Łódź'],
                ['company_name' => 'ExpoTrade Sp. z o.o.', 'nip' => '2222222223', 'email' => 'info@expotrade.pl', 'address_street' => 'ul. Targowa 20, Wrocław'],
            ],
            default => [
                ['company_name' => 'MiejskiDom Sp. z o.o.', 'nip' => '3333333331', 'email' => 'biuro@miejskidom.pl', 'address_street' => 'ul. Osiedlowa 7, Katowice'],
                ['company_name' => 'InfraBud S.A.', 'nip' => '3333333332', 'email' => 'kontakt@infrabud.pl', 'address_street' => 'ul. Drogowa 12, Rzeszów'],
                ['company_name' => 'EkoStroy Sp. z o.o.', 'nip' => '3333333333', 'email' => 'info@ekostroy.pl', 'address_street' => 'ul. Zielona 4, Lublin'],
            ],
        };
    }

    private static function getServiceName(string $branch): string
    {
        $services = match ($branch) {
            'IT' => ['Usługi programistyczne', 'Hosting i utrzymanie serwerów', 'Konsultacje IT', 'Wdrożenie systemu ERP', 'Licencja oprogramowania'],
            'trade' => ['Dostawa towarów', 'Usługi logistyczne', 'Magazynowanie', 'Transport krajowy', 'Opakowania zbiorcze'],
            default => ['Roboty budowlane', 'Projekt architektoniczny', 'Nadzór budowlany', 'Materiały budowlane', 'Usługi remontowe'],
        };
        return $services[array_rand($services)];
    }

    private static function monthName(int $month): string
    {
        if ($month < 1) $month += 12;
        $names = ['', 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
                   'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];
        return $names[$month] ?? '';
    }
}
