<?php

namespace App\Services;

use App\Models\ScheduledExport;
use App\Models\Client;
use App\Models\InvoiceBatch;
use App\Models\AuditLog;

class ScheduledExportService
{
    /**
     * Process all due scheduled exports. Called from cron.php.
     */
    public static function processDueExports(): array
    {
        $results = ['processed' => 0, 'sent' => 0, 'errors' => []];

        $dueExports = ScheduledExport::findDue();

        foreach ($dueExports as $export) {
            try {
                $filePath = self::generateExport($export);

                if ($filePath && file_exists($filePath)) {
                    $client = Client::findById($export['client_id']);
                    $periodLabel = date('m/Y');

                    $sent = MailService::sendReportMultiple(
                        $export['email'],
                        $client['company_name'] ?? $export['company_name'],
                        $client['nip'] ?? $export['nip'],
                        $periodLabel,
                        [$filePath]
                    );

                    if ($sent) {
                        $results['sent']++;
                    }
                }

                $nextRun = ScheduledExport::calculateNextRun($export['frequency'], $export['day_of_month']);
                ScheduledExport::markRun($export['id'], $nextRun);

                AuditLog::log(
                    $export['created_by_type'],
                    $export['created_by_id'],
                    'scheduled_export_run',
                    "Export #{$export['id']}, format: {$export['format']}, client: {$export['company_name']}"
                );

                $results['processed']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Export #{$export['id']}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Run a single export ad-hoc (triggered manually).
     */
    public static function runNow(int $exportId): bool
    {
        $export = ScheduledExport::findById($exportId);
        if (!$export) {
            return false;
        }

        $filePath = self::generateExport($export);

        if ($filePath && file_exists($filePath)) {
            $client = Client::findById($export['client_id']);
            $periodLabel = date('m/Y');

            MailService::sendReportMultiple(
                $export['email'],
                $client['company_name'] ?? $export['company_name'],
                $client['nip'] ?? $export['nip'],
                $periodLabel,
                [$filePath]
            );

            $nextRun = ScheduledExport::calculateNextRun($export['frequency'], $export['day_of_month']);
            ScheduledExport::markRun($export['id'], $nextRun);

            AuditLog::log(
                $export['created_by_type'],
                $export['created_by_id'],
                'scheduled_export_manual',
                "Manual run export #{$export['id']}, format: {$export['format']}"
            );

            return true;
        }

        return false;
    }

    /**
     * Generate the export file based on format.
     */
    private static function generateExport(array $export): ?string
    {
        // Find the latest finalized batch for this client
        $batch = self::findLatestBatch($export['client_id']);
        if (!$batch) {
            return null;
        }

        $batchId = (int) $batch['id'];

        switch ($export['format']) {
            case 'excel':
                return ExportService::generateAcceptedXls($batchId);

            case 'pdf':
                return PdfService::generateAcceptedPdf($batchId);

            case 'jpk_fa':
                return JpkV3Service::generateAcceptedJpk($batchId);

            case 'jpk_vat7':
                return JpkVat7Service::generate($batchId, !$export['include_rejected']);

            case 'comarch_optima':
                return ErpExportService::exportComarchOptima($batchId, !$export['include_rejected']);

            case 'sage':
                return ErpExportService::exportSage($batchId, !$export['include_rejected']);

            case 'enova':
                return ErpExportService::exportEnova($batchId, !$export['include_rejected']);

            default:
                return null;
        }
    }

    /**
     * Find the latest finalized batch for a client.
     */
    private static function findLatestBatch(int $clientId): ?array
    {
        return \App\Core\Database::getInstance()->fetchOne(
            "SELECT * FROM invoice_batches
             WHERE client_id = ? AND is_finalized = 1
             ORDER BY period_year DESC, period_month DESC
             LIMIT 1",
            [$clientId]
        );
    }
}
