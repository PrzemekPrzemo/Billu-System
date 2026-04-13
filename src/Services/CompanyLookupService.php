<?php

namespace App\Services;

/**
 * Unified company lookup service — GUS with CEIDG fallback.
 *
 * Tries GUS first. If GUS returns no data or incomplete data (missing company_name),
 * automatically falls back to CEIDG API. Returns normalized array with 'source' field
 * indicating data origin ('gus' or 'ceidg').
 */
class CompanyLookupService
{
    /**
     * Find company by NIP using GUS → CEIDG fallback chain.
     *
     * @return array|null Normalized company data with 'source' field, or null if not found in either registry.
     */
    public static function findByNip(string $nip): ?array
    {
        // 1. Try GUS first
        $gus = new GusApiService();
        if ($gus->isConfigured()) {
            try {
                $data = $gus->findByNip($nip);
                if ($data && !empty($data['company_name'])) {
                    $data['source'] = 'gus';
                    return $data;
                }
            } catch (\RuntimeException $e) {
                error_log("CompanyLookup GUS error for NIP {$nip}: " . $e->getMessage());
            }
        }

        // 2. Fallback to CEIDG
        $ceidg = new CeidgApiService();
        if ($ceidg->isConfigured()) {
            try {
                $data = $ceidg->findByNip($nip);
                if ($data && !empty($data['company_name'])) {
                    $data['source'] = 'ceidg';
                    return $data;
                }
            } catch (\RuntimeException $e) {
                error_log("CompanyLookup CEIDG error for NIP {$nip}: " . $e->getMessage());
            }
        }

        return null;
    }
}
