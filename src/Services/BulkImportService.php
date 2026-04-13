<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\Client;
use App\Models\AuditLog;

/**
 * Bulk client import from TXT file.
 *
 * Expected format (tab or semicolon separated):
 * NIP;Nazwa firmy;Przedstawiciel;Email;Email do raportów
 *
 * First row = header (skipped).
 * Each imported client gets a random 18-char password and force_password_change=1.
 */
class BulkImportService
{
    public static function importFromText(string $filePath, ?int $officeId = null): array
    {
        $result = [
            'success'   => 0,
            'errors'    => [],
            'total'     => 0,
            'passwords' => [], // NIP => generated password (for admin to distribute)
        ];

        $content = file_get_contents($filePath);
        if ($content === false) {
            $result['errors'][] = 'file_read_error';
            return $result;
        }

        // Handle BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = array_filter(explode("\n", $content), fn($l) => trim($l) !== '');

        // Detect delimiter
        $firstLine = $lines[array_key_first($lines)] ?? '';
        $delimiter = str_contains($firstLine, "\t") ? "\t" : ';';

        // Skip header
        array_shift($lines);
        $result['total'] = count($lines);

        if ($result['total'] === 0) {
            $result['errors'][] = 'empty_file';
            return $result;
        }

        $rowNum = 1;
        foreach ($lines as $line) {
            $rowNum++;
            $cols = str_getcsv(trim($line), $delimiter);

            if (count($cols) < 4) {
                $result['errors'][] = "Wiersz {$rowNum}: za mało kolumn (" . count($cols) . "), wymagane min. 4";
                continue;
            }

            $nip = preg_replace('/[^0-9]/', '', $cols[0] ?? '');
            $companyName = trim($cols[1] ?? '');
            $representative = trim($cols[2] ?? '');
            $email = trim($cols[3] ?? '');
            $reportEmail = trim($cols[4] ?? $email); // fallback to main email

            // Validate NIP
            if (strlen($nip) !== 10) {
                $result['errors'][] = "Wiersz {$rowNum}: nieprawidłowy NIP '{$cols[0]}'";
                continue;
            }

            // Check if exists
            if (Client::findByNip($nip)) {
                $result['errors'][] = "Wiersz {$rowNum}: klient z NIP {$nip} już istnieje";
                continue;
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['errors'][] = "Wiersz {$rowNum}: nieprawidłowy email '{$email}'";
                continue;
            }

            if (empty($companyName)) {
                $result['errors'][] = "Wiersz {$rowNum}: brak nazwy firmy";
                continue;
            }

            // Generate random password
            $password = Auth::generateRandomPassword(18);

            try {
                Client::create([
                    'nip'                   => $nip,
                    'company_name'          => $companyName,
                    'representative_name'   => $representative ?: $companyName,
                    'email'                 => $email,
                    'report_email'          => $reportEmail,
                    'password_hash'         => Auth::hashPassword($password),
                    'force_password_change' => 1,
                    'office_id'             => $officeId,
                ]);

                $result['passwords'][$nip] = [
                    'company_name' => $companyName,
                    'email'        => $email,
                    'password'     => $password,
                ];

                $result['success']++;

                AuditLog::log(
                    'admin',
                    Auth::currentUserId() ?? 0,
                    'client_bulk_imported',
                    "NIP: {$nip}, Company: {$companyName}",
                    'client',
                    null
                );
            } catch (\Exception $e) {
                $result['errors'][] = "Wiersz {$rowNum}: " . $e->getMessage();
            }
        }

        return $result;
    }
}
