<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceBatch;

/**
 * RODO/GDPR Data Export Service
 * Exports all personal data related to a client as a ZIP archive.
 * Implements Article 20 GDPR - Right to Data Portability.
 */
class RodoExportService
{
    /**
     * Generate a ZIP archive with all client data.
     * Returns path to the generated ZIP file.
     */
    public static function exportClientData(int $clientId): ?string
    {
        $client = Client::findById($clientId);
        if (!$client) {
            return null;
        }

        $db = Database::getInstance();
        $exportDir = __DIR__ . '/../../storage/exports';
        $zipPath = $exportDir . '/rodo_export_' . $clientId . '_' . date('Ymd_His') . '.zip';

        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            return null;
        }

        // 1. Client profile data
        $profileData = self::sanitizeRecord($client, [
            'id', 'nip', 'company_name', 'representative_name', 'email', 'report_email',
            'phone', 'address', 'language',
            'is_active', 'privacy_accepted', 'privacy_accepted_at',
            'created_at', 'updated_at'
        ]);
        $zip->addFromString('profil_klienta.json', json_encode($profileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 2. Cost centers
        $costCenters = $db->fetchAll(
            "SELECT id, name, is_active, created_at FROM client_cost_centers WHERE client_id = ?",
            [$clientId]
        );
        if (!empty($costCenters)) {
            $zip->addFromString('centra_kosztow.json', json_encode($costCenters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // 3. Invoice batches
        $batches = $db->fetchAll(
            "SELECT id, period_month, period_year, is_finalized, verification_deadline, finalized_at, created_at FROM invoice_batches WHERE client_id = ?",
            [$clientId]
        );
        $zip->addFromString('paczki_faktur.json', json_encode($batches, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 4. All invoices
        $invoices = $db->fetchAll(
            "SELECT i.id, i.invoice_number, i.issue_date, i.sale_date, i.seller_nip, i.seller_name,
                    i.seller_address, i.buyer_nip, i.buyer_name, i.buyer_address,
                    i.net_amount, i.vat_amount, i.gross_amount, i.currency,
                    i.status, i.comment, i.cost_center,
                    i.verified_at, i.ksef_reference_number, i.created_at
             FROM invoices i
             JOIN invoice_batches ib ON i.batch_id = ib.id
             WHERE ib.client_id = ?
             ORDER BY i.created_at DESC",
            [$clientId]
        );
        $zip->addFromString('faktury.json', json_encode($invoices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 4b. Invoices as CSV for easier import
        if (!empty($invoices)) {
            $csv = self::arrayToCsv($invoices);
            $zip->addFromString('faktury.csv', "\xEF\xBB\xBF" . $csv); // UTF-8 BOM for Excel
        }

        // 5. Audit log entries related to this client
        $auditLogs = $db->fetchAll(
            "SELECT action, details, ip_address, created_at FROM audit_log
             WHERE (user_type = 'client' AND user_id = ?) OR (entity_type = 'client' AND entity_id = ?)
             ORDER BY created_at DESC LIMIT 1000",
            [$clientId, $clientId]
        );
        if (!empty($auditLogs)) {
            $zip->addFromString('dziennik_audytu.json', json_encode($auditLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // 6. Login history
        $loginHistory = $db->fetchAll(
            "SELECT ip_address, user_agent, success, created_at FROM login_history
             WHERE user_type = 'client' AND user_id = ?
             ORDER BY created_at DESC LIMIT 500",
            [$clientId]
        );
        if (!empty($loginHistory)) {
            $zip->addFromString('historia_logowan.json', json_encode($loginHistory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // 7. Export metadata
        $metadata = [
            'export_date' => date('Y-m-d H:i:s'),
            'client_id' => $clientId,
            'client_nip' => $client['nip'],
            'client_name' => $client['company_name'],
            'format' => 'RODO/GDPR Article 20 - Data Portability Export',
            'files' => [
                'profil_klienta.json' => 'Dane profilu klienta',
                'centra_kosztow.json' => 'Centra kosztow (MPK)',
                'paczki_faktur.json' => 'Paczki faktur z metadanymi',
                'faktury.json' => 'Wszystkie faktury (JSON)',
                'faktury.csv' => 'Wszystkie faktury (CSV - do importu)',
                'dziennik_audytu.json' => 'Dziennik audytu (ostatnie 1000 wpisow)',
                'historia_logowan.json' => 'Historia logowan (ostatnie 500 wpisow)',
            ],
        ];
        $zip->addFromString('README.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();

        return $zipPath;
    }

    /**
     * Filter record to only include specified keys.
     */
    private static function sanitizeRecord(array $record, array $allowedKeys): array
    {
        return array_intersect_key($record, array_flip($allowedKeys));
    }

    /**
     * Convert array of associative arrays to CSV string.
     */
    private static function arrayToCsv(array $data): string
    {
        if (empty($data)) return '';

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]), ';');
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
