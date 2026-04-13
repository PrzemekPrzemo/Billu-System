<?php

namespace App\Models;

use App\Core\Database;

/**
 * KSeF operations audit log.
 */
class KsefOperationLog
{
    public static function log(
        int $clientId,
        string $operation,
        string $status,
        string $performedByType,
        int $performedById,
        ?string $requestSummary = null,
        ?string $responseSummary = null,
        ?string $errorMessage = null,
        ?string $ksefRef = null,
        ?int $durationMs = null
    ): int {
        return Database::getInstance()->insert('ksef_operations_log', [
            'client_id' => $clientId,
            'operation' => $operation,
            'status' => $status,
            'request_summary' => $requestSummary ? substr($requestSummary, 0, 65000) : null,
            'response_summary' => $responseSummary ? substr($responseSummary, 0, 65000) : null,
            'error_message' => $errorMessage,
            'ksef_reference_number' => $ksefRef,
            'duration_ms' => $durationMs,
            'performed_by_type' => $performedByType,
            'performed_by_id' => $performedById,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function findByClient(int $clientId, int $limit = 50): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM ksef_operations_log WHERE client_id = ? ORDER BY created_at DESC LIMIT ?",
            [$clientId, $limit]
        );
    }

    public static function findRecent(int $limit = 100): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT kol.*, c.company_name, c.nip
             FROM ksef_operations_log kol
             JOIN clients c ON c.id = kol.client_id
             ORDER BY kol.created_at DESC LIMIT ?",
            [$limit]
        );
    }

    public static function countByStatus(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT status, COUNT(*) as cnt FROM ksef_operations_log WHERE client_id = ? GROUP BY status",
            [$clientId]
        );
    }

    public static function getImportSendSummary(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT
                operation,
                MONTH(created_at) as month,
                YEAR(created_at) as year,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
             FROM ksef_operations_log
             WHERE client_id = ? AND operation IN ('import_batch', 'invoice_submit', 'invoice_download', 'invoice_download_raw')
             GROUP BY operation, YEAR(created_at), MONTH(created_at)
             ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC, operation",
            [$clientId]
        );
    }

    public static function getMonthlyHealth(int $months = 6): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    ROUND(AVG(duration_ms)) as avg_duration_ms
             FROM ksef_operations_log
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC",
            [$months]
        );
    }

    public static function getHealthByOffice(int $officeId, int $months = 6): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT DATE_FORMAT(k.created_at, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN k.status = 'success' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN k.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    ROUND(AVG(k.duration_ms)) as avg_duration_ms
             FROM ksef_operations_log k
             JOIN clients c ON c.id = k.client_id
             WHERE c.office_id = ? AND k.created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(k.created_at, '%Y-%m') ORDER BY month ASC",
            [$officeId, $months]
        );
    }
}
