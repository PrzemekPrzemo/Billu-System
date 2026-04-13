<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiResponse;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\IssuedInvoice;
use App\Models\ClientTask;
use App\Models\Message;

class ReportApiController
{
    // GET /api/v1/reports/dashboard-summary
    public function dashboardSummary(array $params, ?int $clientId): void
    {
        // Current / latest active batch (first = newest unfinalized)
        $activeBatches = InvoiceBatch::findActiveByClient($clientId);
        $activeBatch   = $activeBatches[0] ?? null;

        $pendingCount  = 0;
        $acceptedCount = 0;
        $rejectedCount = 0;
        $batchInfo     = null;

        if ($activeBatch) {
            $stats = Invoice::countByBatchAndStatus((int) $activeBatch['id']);
            foreach ($stats as $s) {
                match ($s['status'] ?? '') {
                    'pending'  => $pendingCount  = (int) $s['count'],
                    'accepted' => $acceptedCount = (int) $s['count'],
                    'rejected' => $rejectedCount = (int) $s['count'],
                    default    => null,
                };
            }
            $batchInfo = [
                'id'                    => (int) $activeBatch['id'],
                'period_month'          => (int) $activeBatch['period_month'],
                'period_year'           => (int) $activeBatch['period_year'],
                'verification_deadline' => $activeBatch['verification_deadline'],
                'is_finalized'          => (bool) $activeBatch['is_finalized'],
            ];
        }

        // Current month issued invoices
        $currentMonth  = (int) date('m');
        $currentYear   = (int) date('Y');
        $salesStats    = IssuedInvoice::countByClient($clientId);
        $monthlyIssued = 0;
        $monthlyTotal  = 0.0;

        foreach ($salesStats as $s) {
            if ((int) ($s['month'] ?? 0) === $currentMonth && (int) ($s['year'] ?? 0) === $currentYear) {
                $monthlyIssued = (int) ($s['count'] ?? 0);
                $monthlyTotal  = (float) ($s['gross_amount'] ?? 0);
            }
        }

        // Open tasks count
        $openTasks = ClientTask::countOpenByClient($clientId);

        // Unread messages
        $unreadMessages = Message::countUnreadByClient($clientId);

        ApiResponse::success([
            'active_batch'        => $batchInfo,
            'pending_invoices'    => $pendingCount,
            'accepted_invoices'   => $acceptedCount,
            'rejected_invoices'   => $rejectedCount,
            'current_month_sales' => [
                'count'  => $monthlyIssued,
                'total'  => $monthlyTotal,
                'month'  => $currentMonth,
                'year'   => $currentYear,
            ],
            'open_tasks'          => $openTasks,
            'unread_messages'     => $unreadMessages,
        ]);
    }

    // GET /api/v1/reports/monthly-stats?months=12
    public function monthlyStats(array $params, ?int $clientId): void
    {
        $months = min(24, max(1, (int) ($_GET['months'] ?? 12)));

        $purchaseStats = Invoice::getMonthlyComparison($clientId, $months);
        $salesStats    = IssuedInvoice::getMonthlySales($clientId, $months);

        // Index sales by year-month
        $salesByPeriod = [];
        foreach ($salesStats as $s) {
            $key                = sprintf('%04d-%02d', $s['year'], $s['month']);
            $salesByPeriod[$key] = $s;
        }

        // Merge
        $result = array_map(function ($p) use ($salesByPeriod) {
            $key   = sprintf('%04d-%02d', $p['year'], $p['month']);
            $sales = $salesByPeriod[$key] ?? [];
            return [
                'year'             => (int) $p['year'],
                'month'            => (int) $p['month'],
                'purchase_count'   => (int) ($p['count'] ?? 0),
                'purchase_gross'   => (float) ($p['gross_amount'] ?? 0),
                'sales_count'      => (int) ($sales['count'] ?? 0),
                'sales_gross'      => (float) ($sales['gross_amount'] ?? 0),
            ];
        }, $purchaseStats);

        ApiResponse::success($result);
    }

    // GET /api/v1/reports/supplier-analysis?date_from=&date_to=&limit=20
    public function supplierAnalysis(array $params, ?int $clientId): void
    {
        $dateFrom = $_GET['date_from'] ?? date('Y-01-01');
        $dateTo   = $_GET['date_to']   ?? date('Y-m-d');
        $limit    = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

        $suppliers = Invoice::getSupplierAnalysis($clientId, $dateFrom, $dateTo, $limit);

        ApiResponse::success([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'suppliers' => $suppliers,
        ]);
    }
}
