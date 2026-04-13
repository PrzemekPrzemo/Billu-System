<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Invoice;
use App\Models\IssuedInvoice;
use App\Models\DuplicateCandidate;
use App\Models\Client;
use App\Models\Notification;

class DuplicateDetectionService
{
    private const TOLERANCE_PERCENT = 1.0;
    private const TOLERANCE_ABSOLUTE = 1.00;

    /**
     * Normalize invoice number: trim, uppercase, collapse whitespace.
     */
    public static function normalizeInvoiceNumber(string $number): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($number)));
    }

    /**
     * Normalize NIP: remove dashes and spaces.
     */
    public static function normalizeNip(string $nip): string
    {
        return preg_replace('/[\s\-]/', '', $nip);
    }

    /**
     * Check if two amounts match within tolerance.
     */
    public static function amountsMatch(float $a, float $b): bool
    {
        if ($a == $b) return true;
        $diff = abs($a - $b);
        $maxVal = max(abs($a), abs($b));
        $toleranceAbs = max(self::TOLERANCE_ABSOLUTE, $maxVal * self::TOLERANCE_PERCENT / 100);
        return $diff <= $toleranceAbs;
    }

    /**
     * Check for purchase invoice duplicates.
     * Returns ['is_duplicate' => bool, 'candidates' => [...], 'match_type' => 'exact'|'fuzzy'|null]
     */
    public static function checkPurchaseInvoice(int $clientId, string $invoiceNumber, string $sellerNip, float $grossAmount, ?int $excludeId = null): array
    {
        $normalizedNumber = self::normalizeInvoiceNumber($invoiceNumber);
        $normalizedNip = self::normalizeNip($sellerNip);

        if ($normalizedNumber === '' || $normalizedNip === '') {
            return ['is_duplicate' => false, 'candidates' => [], 'match_type' => null];
        }

        // Search by invoice number + seller NIP within this client
        $sql = "SELECT i.id, i.invoice_number, i.seller_nip, i.gross_amount, i.issue_date, i.status
                FROM invoices i
                JOIN invoice_batches ib ON i.batch_id = ib.id
                WHERE ib.client_id = ?
                  AND UPPER(TRIM(i.invoice_number)) = ?
                  AND REPLACE(REPLACE(i.seller_nip, '-', ''), ' ', '') = ?";
        $params = [$clientId, $normalizedNumber, $normalizedNip];

        if ($excludeId !== null) {
            $sql .= " AND i.id != ?";
            $params[] = $excludeId;
        }

        $candidates = Database::getInstance()->fetchAll($sql, $params);

        if (empty($candidates)) {
            return ['is_duplicate' => false, 'candidates' => [], 'match_type' => null];
        }

        // Check amounts
        $exact = [];
        $fuzzy = [];
        foreach ($candidates as $c) {
            if ((float) $c['gross_amount'] == $grossAmount) {
                $c['match_type'] = 'exact';
                $exact[] = $c;
            } elseif (self::amountsMatch((float) $c['gross_amount'], $grossAmount)) {
                $c['match_type'] = 'fuzzy';
                $fuzzy[] = $c;
            }
        }

        $allMatches = array_merge($exact, $fuzzy);
        if (empty($allMatches)) {
            return ['is_duplicate' => false, 'candidates' => $candidates, 'match_type' => null];
        }

        return [
            'is_duplicate' => true,
            'candidates' => $allMatches,
            'match_type' => !empty($exact) ? 'exact' : 'fuzzy',
        ];
    }

    /**
     * Check for sales invoice duplicates.
     */
    public static function checkSalesInvoice(int $clientId, string $invoiceNumber, ?string $buyerNip, float $grossAmount, ?int $excludeId = null): array
    {
        $normalizedNumber = self::normalizeInvoiceNumber($invoiceNumber);
        if ($normalizedNumber === '' || strpos($normalizedNumber, 'DRAFT-') === 0) {
            return ['is_duplicate' => false, 'candidates' => [], 'match_type' => null];
        }

        $sql = "SELECT id, invoice_number, buyer_nip, gross_amount, issue_date, status
                FROM issued_invoices
                WHERE client_id = ?
                  AND UPPER(TRIM(invoice_number)) = ?
                  AND status != 'cancelled'";
        $params = [$clientId, $normalizedNumber];

        if ($buyerNip !== null && $buyerNip !== '') {
            $normalizedNip = self::normalizeNip($buyerNip);
            $sql .= " AND REPLACE(REPLACE(buyer_nip, '-', ''), ' ', '') = ?";
            $params[] = $normalizedNip;
        }

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $candidates = Database::getInstance()->fetchAll($sql, $params);

        if (empty($candidates)) {
            return ['is_duplicate' => false, 'candidates' => [], 'match_type' => null];
        }

        $exact = [];
        $fuzzy = [];
        foreach ($candidates as $c) {
            if ((float) $c['gross_amount'] == $grossAmount) {
                $c['match_type'] = 'exact';
                $exact[] = $c;
            } elseif (self::amountsMatch((float) $c['gross_amount'], $grossAmount)) {
                $c['match_type'] = 'fuzzy';
                $fuzzy[] = $c;
            }
        }

        $allMatches = array_merge($exact, $fuzzy);
        if (empty($allMatches)) {
            return ['is_duplicate' => false, 'candidates' => $candidates, 'match_type' => null];
        }

        return [
            'is_duplicate' => true,
            'candidates' => $allMatches,
            'match_type' => !empty($exact) ? 'exact' : 'fuzzy',
        ];
    }

    /**
     * Batch scan for duplicates in purchase invoices for a client.
     * Returns ['new_duplicates' => int, 'total_checked' => int]
     */
    public static function batchScanForClient(int $clientId): array
    {
        $result = ['new_duplicates' => 0, 'total_checked' => 0];

        // Purchase invoices: find groups with same invoice_number + seller_nip
        $groups = Database::getInstance()->fetchAll(
            "SELECT UPPER(TRIM(i.invoice_number)) AS norm_number,
                    REPLACE(REPLACE(i.seller_nip, '-', ''), ' ', '') AS norm_nip,
                    GROUP_CONCAT(i.id ORDER BY i.id) AS ids,
                    GROUP_CONCAT(i.gross_amount ORDER BY i.id) AS amounts,
                    COUNT(*) AS cnt
             FROM invoices i
             JOIN invoice_batches ib ON i.batch_id = ib.id
             WHERE ib.client_id = ? AND i.invoice_number != ''
             GROUP BY norm_number, norm_nip
             HAVING cnt > 1",
            [$clientId]
        );

        foreach ($groups as $group) {
            $ids = explode(',', $group['ids']);
            $amounts = explode(',', $group['amounts']);
            $result['total_checked'] += count($ids);

            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    if (self::amountsMatch((float) $amounts[$i], (float) $amounts[$j])) {
                        $id1 = (int) $ids[$i];
                        $id2 = (int) $ids[$j];

                        if (!DuplicateCandidate::existsPair('purchase', $id1, $id2)) {
                            $score = ((float) $amounts[$i] == (float) $amounts[$j]) ? 100 : 90;
                            DuplicateCandidate::create([
                                'invoice_type' => 'purchase',
                                'invoice_id' => $id1,
                                'duplicate_of_id' => $id2,
                                'match_score' => $score,
                                'match_details' => 'number+nip+amount',
                            ]);
                            $result['new_duplicates']++;
                        }
                    }
                }
            }
        }

        // Sales invoices: find groups with same invoice_number + buyer_nip
        $groups = Database::getInstance()->fetchAll(
            "SELECT UPPER(TRIM(invoice_number)) AS norm_number,
                    REPLACE(REPLACE(buyer_nip, '-', ''), ' ', '') AS norm_nip,
                    GROUP_CONCAT(id ORDER BY id) AS ids,
                    GROUP_CONCAT(gross_amount ORDER BY id) AS amounts,
                    COUNT(*) AS cnt
             FROM issued_invoices
             WHERE client_id = ? AND invoice_number != '' AND invoice_number NOT LIKE 'DRAFT-%' AND status != 'cancelled'
             GROUP BY norm_number, norm_nip
             HAVING cnt > 1",
            [$clientId]
        );

        foreach ($groups as $group) {
            $ids = explode(',', $group['ids']);
            $amounts = explode(',', $group['amounts']);
            $result['total_checked'] += count($ids);

            for ($i = 0; $i < count($ids); $i++) {
                for ($j = $i + 1; $j < count($ids); $j++) {
                    if (self::amountsMatch((float) $amounts[$i], (float) $amounts[$j])) {
                        $id1 = (int) $ids[$i];
                        $id2 = (int) $ids[$j];

                        if (!DuplicateCandidate::existsPair('sales', $id1, $id2)) {
                            $score = ((float) $amounts[$i] == (float) $amounts[$j]) ? 100 : 90;
                            DuplicateCandidate::create([
                                'invoice_type' => 'sales',
                                'invoice_id' => $id1,
                                'duplicate_of_id' => $id2,
                                'match_score' => $score,
                                'match_details' => 'number+nip+amount',
                            ]);
                            $result['new_duplicates']++;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Batch scan for all clients of an office.
     */
    public static function batchScanForOffice(int $officeId): array
    {
        $clients = Client::findByOffice($officeId, true);
        $total = ['new_duplicates' => 0, 'total_checked' => 0, 'clients_scanned' => 0];

        foreach ($clients as $client) {
            $r = self::batchScanForClient((int) $client['id']);
            $total['new_duplicates'] += $r['new_duplicates'];
            $total['total_checked'] += $r['total_checked'];
            $total['clients_scanned']++;
        }

        if ($total['new_duplicates'] > 0) {
            Notification::create(
                'office',
                $officeId,
                "Wykryto {$total['new_duplicates']} potencjalnych duplikatów faktur",
                null,
                'warning',
                '/office/duplicates'
            );
        }

        return $total;
    }

    /**
     * Batch scan for all active clients (admin use / cron).
     */
    public static function batchScanAll(): array
    {
        $clients = Client::findAll(true);
        $total = ['new_duplicates' => 0, 'total_checked' => 0, 'clients_scanned' => 0];

        foreach ($clients as $client) {
            $r = self::batchScanForClient((int) $client['id']);
            $total['new_duplicates'] += $r['new_duplicates'];
            $total['total_checked'] += $r['total_checked'];
            $total['clients_scanned']++;
        }

        return $total;
    }
}
