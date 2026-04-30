<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Client;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Office;

/**
 * RODO/GDPR Account Deletion Service
 * Implements Article 17 GDPR - Right to be Forgotten.
 * Permanently deletes all personal data related to a client.
 */
class RodoDeleteService
{
    /**
     * Delete all client data in the correct order respecting foreign keys.
     * Returns summary of what was deleted.
     *
     * @param int $clientId
     * @param string $deletedByType  'client' or 'office'
     * @param int $deletedById
     * @return array Summary of deletion
     */
    public static function deleteClientData(int $clientId, string $deletedByType, int $deletedById): array
    {
        $client = Client::findById($clientId);
        if (!$client) {
            return ['success' => false, 'error' => 'Client not found'];
        }

        // KAS retention guard (e-US Bramka C — 10y default per
        // settings.eus_kas_letters_retain_years). The retain_until
        // column on eus_documents is honored here AND in the optional
        // soft-purge cron — RODO delete refuses, never overrides.
        // Master admin must first export pism KAS to off-line archive,
        // then purge those rows manually before re-attempting.
        if (\App\Models\EusDocument::hasActiveRetention($clientId)) {
            return [
                'success' => false,
                'error'   => 'Klient ma aktywne pisma KAS objęte 10-letnią retencją. '
                          . 'Wymagany eksport offline + ręczne usunięcie pism z aktywnym retain_until '
                          . 'przed RODO delete. Skontaktuj się z master adminem.',
                'blocked_by' => 'eus_kas_retention',
            ];
        }

        $db = Database::getInstance();
        $summary = [
            'client_id' => $clientId,
            'client_nip' => $client['nip'] ?? '',
            'client_name' => $client['company_name'] ?? '',
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by_type' => $deletedByType,
            'deleted_by_id' => $deletedById,
            'counts' => [],
        ];

        try {
            // 1. Delete client_files records + files from disk
            $summary['counts']['client_files'] = self::deleteClientFiles($db, $clientId, $client);

            // 2. Delete messages + attachments from disk
            $summary['counts']['messages'] = self::deleteMessages($db, $clientId);

            // 3. Delete client_tasks + attachments from disk
            $summary['counts']['client_tasks'] = self::deleteClientTasks($db, $clientId);

            // 4. Delete issued_invoices (sales invoices) + PDF files
            $summary['counts']['issued_invoices'] = self::deleteIssuedInvoices($db, $clientId);

            // 5. Delete purchase invoices (via batches)
            $summary['counts']['purchase_invoices'] = self::deletePurchaseInvoices($db, $clientId);

            // 6. Delete contractors + logos
            $summary['counts']['contractors'] = self::deleteContractors($db, $clientId);

            // 7. Delete company_profile, bank_accounts, company_services
            $summary['counts']['company_profile'] = self::deleteCompanyProfile($db, $clientId);
            $summary['counts']['bank_accounts'] = self::deleteBankAccounts($db, $clientId);
            $summary['counts']['company_services'] = self::deleteCompanyServices($db, $clientId);

            // 8. Delete client_ksef_configs, ksef_certificates
            $summary['counts']['ksef_configs'] = self::deleteKsefData($db, $clientId);

            // 9. Delete notifications
            $summary['counts']['notifications'] = self::deleteNotifications($db, $clientId);

            // 10. Delete other related data
            $summary['counts']['cost_centers'] = self::deleteCostCenters($db, $clientId);
            $summary['counts']['tax_payments'] = self::deleteTaxPayments($db, $clientId);
            $summary['counts']['tax_configs'] = self::deleteTaxConfigs($db, $clientId);
            $summary['counts']['notes'] = self::deleteClientNotes($db, $clientId);
            $summary['counts']['monthly_statuses'] = self::deleteMonthlyStatuses($db, $clientId);
            $summary['counts']['message_prefs'] = self::deleteMessagePrefs($db, $clientId);
            $summary['counts']['login_history'] = self::deleteLoginHistory($db, $clientId);
            $summary['counts']['smtp_configs'] = self::deleteSmtpConfigs($db, $clientId);
            $summary['counts']['email_templates'] = self::deleteEmailTemplates($db, $clientId);
            $summary['counts']['invoice_batches'] = self::deleteInvoiceBatches($db, $clientId);

            // Log deletion in audit_log BEFORE deleting the client
            AuditLog::log(
                $deletedByType,
                $deletedById,
                'rodo_account_deletion',
                json_encode($summary, JSON_UNESCAPED_UNICODE),
                'client',
                $clientId
            );

            // 11. Finally: delete the client record itself
            Client::delete($clientId);

            // Notify the office about deletion
            self::notifyOffice($client, $summary);

            $summary['success'] = true;
            return $summary;

        } catch (\Throwable $e) {
            error_log("RODO deletion error for client #{$clientId}: " . $e->getMessage());

            // Log the error
            AuditLog::log(
                $deletedByType,
                $deletedById,
                'rodo_account_deletion_error',
                "Error deleting client #{$clientId}: " . $e->getMessage(),
                'client',
                $clientId
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'summary' => $summary,
            ];
        }
    }

    private static function deleteClientFiles(Database $db, int $clientId, array $client): int
    {
        $files = $db->fetchAll("SELECT * FROM client_files WHERE client_id = ?", [$clientId]);
        foreach ($files as $file) {
            $fullPath = \App\Models\ClientFile::getFullPath($file, $client['file_storage_path'] ?? null, $client['nip'] ?? '');
            if ($fullPath && file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
        $db->query("DELETE FROM client_files WHERE client_id = ?", [$clientId]);
        return count($files);
    }

    private static function deleteMessages(Database $db, int $clientId): int
    {
        $messages = $db->fetchAll("SELECT * FROM messages WHERE client_id = ?", [$clientId]);
        foreach ($messages as $msg) {
            if (!empty($msg['attachment_path'])) {
                $fullPath = \App\Models\Message::getAttachmentFullPath($msg);
                if ($fullPath && file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }
        $db->query("DELETE FROM messages WHERE client_id = ?", [$clientId]);
        return count($messages);
    }

    private static function deleteClientTasks(Database $db, int $clientId): int
    {
        $tasks = $db->fetchAll("SELECT * FROM client_tasks WHERE client_id = ?", [$clientId]);
        foreach ($tasks as $task) {
            if (!empty($task['attachment_path'])) {
                $fullPath = realpath(__DIR__ . '/../../' . $task['attachment_path']);
                if ($fullPath && file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }
        $db->query("DELETE FROM client_tasks WHERE client_id = ?", [$clientId]);
        return count($tasks);
    }

    private static function deleteIssuedInvoices(Database $db, int $clientId): int
    {
        $invoices = $db->fetchAll("SELECT * FROM issued_invoices WHERE client_id = ?", [$clientId]);
        foreach ($invoices as $inv) {
            // Delete PDF files if they exist
            if (!empty($inv['pdf_path'])) {
                $pdfFullPath = realpath(__DIR__ . '/../../' . $inv['pdf_path']);
                if ($pdfFullPath && file_exists($pdfFullPath)) {
                    @unlink($pdfFullPath);
                }
            }
            if (!empty($inv['upo_path'])) {
                $upoFullPath = realpath(__DIR__ . '/../../' . $inv['upo_path']);
                if ($upoFullPath && file_exists($upoFullPath)) {
                    @unlink($upoFullPath);
                }
            }
        }
        $db->query("DELETE FROM issued_invoices WHERE client_id = ?", [$clientId]);
        return count($invoices);
    }

    private static function deletePurchaseInvoices(Database $db, int $clientId): int
    {
        // Delete invoice_comments linked to invoices in this client's batches
        $db->query(
            "DELETE ic FROM invoice_comments ic
             INNER JOIN invoices i ON ic.invoice_id = i.id
             INNER JOIN invoice_batches ib ON i.batch_id = ib.id
             WHERE ib.client_id = ?",
            [$clientId]
        );

        // Delete invoices linked to this client's batches
        $result = $db->fetchOne(
            "SELECT COUNT(*) as cnt FROM invoices i
             INNER JOIN invoice_batches ib ON i.batch_id = ib.id
             WHERE ib.client_id = ?",
            [$clientId]
        );
        $count = (int) ($result['cnt'] ?? 0);

        // Delete the invoices and their source files
        $invoices = $db->fetchAll(
            "SELECT i.* FROM invoices i
             INNER JOIN invoice_batches ib ON i.batch_id = ib.id
             WHERE ib.client_id = ?",
            [$clientId]
        );
        foreach ($invoices as $inv) {
            if (!empty($inv['source_file'])) {
                $srcPath = realpath(__DIR__ . '/../../' . $inv['source_file']);
                if ($srcPath && file_exists($srcPath)) {
                    @unlink($srcPath);
                }
            }
        }

        $db->query(
            "DELETE i FROM invoices i
             INNER JOIN invoice_batches ib ON i.batch_id = ib.id
             WHERE ib.client_id = ?",
            [$clientId]
        );

        return $count;
    }

    private static function deleteContractors(Database $db, int $clientId): int
    {
        $contractors = $db->fetchAll("SELECT * FROM contractors WHERE client_id = ?", [$clientId]);
        foreach ($contractors as $c) {
            if (!empty($c['logo_path'])) {
                $logoPath = realpath(__DIR__ . '/../../' . $c['logo_path']);
                if ($logoPath && file_exists($logoPath)) {
                    @unlink($logoPath);
                }
            }
        }
        $db->query("DELETE FROM contractors WHERE client_id = ?", [$clientId]);
        return count($contractors);
    }

    private static function deleteCompanyProfile(Database $db, int $clientId): int
    {
        $profile = $db->fetchOne("SELECT * FROM company_profiles WHERE client_id = ?", [$clientId]);
        if ($profile) {
            if (!empty($profile['logo_path'])) {
                $logoPath = realpath(__DIR__ . '/../../public/' . ltrim($profile['logo_path'], '/'));
                if ($logoPath && file_exists($logoPath)) {
                    @unlink($logoPath);
                }
            }
            $db->query("DELETE FROM company_profiles WHERE client_id = ?", [$clientId]);
            return 1;
        }
        return 0;
    }

    private static function deleteBankAccounts(Database $db, int $clientId): int
    {
        $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM bank_accounts WHERE client_id = ?", [$clientId]);
        $db->query("DELETE FROM bank_accounts WHERE client_id = ?", [$clientId]);
        return (int) ($result['cnt'] ?? 0);
    }

    private static function deleteCompanyServices(Database $db, int $clientId): int
    {
        $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM company_services WHERE client_id = ?", [$clientId]);
        $db->query("DELETE FROM company_services WHERE client_id = ?", [$clientId]);
        return (int) ($result['cnt'] ?? 0);
    }

    private static function deleteKsefData(Database $db, int $clientId): int
    {
        $count = 0;

        // Delete KSeF certificates
        $certs = $db->fetchAll("SELECT * FROM ksef_certificates WHERE client_id = ?", [$clientId]);
        foreach ($certs as $cert) {
            if (!empty($cert['cert_path'])) {
                $certPath = realpath(__DIR__ . '/../../' . $cert['cert_path']);
                if ($certPath && file_exists($certPath)) {
                    @unlink($certPath);
                }
            }
        }
        $db->query("DELETE FROM ksef_certificates WHERE client_id = ?", [$clientId]);
        $count += count($certs);

        // Delete KSeF configs
        $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM client_ksef_configs WHERE client_id = ?", [$clientId]);
        $db->query("DELETE FROM client_ksef_configs WHERE client_id = ?", [$clientId]);
        $count += (int) ($result['cnt'] ?? 0);

        // Delete KSeF operation logs
        try {
            $db->query("DELETE FROM ksef_operation_log WHERE client_id = ?", [$clientId]);
        } catch (\Throwable $e) {
            // Table may not exist
        }

        return $count;
    }

    private static function deleteNotifications(Database $db, int $clientId): int
    {
        $result = $db->fetchOne(
            "SELECT COUNT(*) as cnt FROM notifications WHERE user_type = 'client' AND user_id = ?",
            [$clientId]
        );
        $db->query("DELETE FROM notifications WHERE user_type = 'client' AND user_id = ?", [$clientId]);
        return (int) ($result['cnt'] ?? 0);
    }

    private static function deleteCostCenters(Database $db, int $clientId): int
    {
        $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM client_cost_centers WHERE client_id = ?", [$clientId]);
        $db->query("DELETE FROM client_cost_centers WHERE client_id = ?", [$clientId]);
        return (int) ($result['cnt'] ?? 0);
    }

    private static function deleteTaxPayments(Database $db, int $clientId): int
    {
        $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM tax_payments WHERE client_id = ?", [$clientId]);
        $db->query("DELETE FROM tax_payments WHERE client_id = ?", [$clientId]);
        return (int) ($result['cnt'] ?? 0);
    }

    private static function deleteTaxConfigs(Database $db, int $clientId): int
    {
        try {
            $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM client_tax_configs WHERE client_id = ?", [$clientId]);
            $db->query("DELETE FROM client_tax_configs WHERE client_id = ?", [$clientId]);
            return (int) ($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function deleteClientNotes(Database $db, int $clientId): int
    {
        try {
            $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM client_notes WHERE client_id = ?", [$clientId]);
            $db->query("DELETE FROM client_notes WHERE client_id = ?", [$clientId]);
            return (int) ($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function deleteMonthlyStatuses(Database $db, int $clientId): int
    {
        try {
            $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM client_monthly_statuses WHERE client_id = ?", [$clientId]);
            $db->query("DELETE FROM client_monthly_statuses WHERE client_id = ?", [$clientId]);
            return (int) ($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function deleteMessagePrefs(Database $db, int $clientId): int
    {
        try {
            $result = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM message_notification_prefs WHERE user_type = 'client' AND user_id = ?",
                [$clientId]
            );
            $db->query("DELETE FROM message_notification_prefs WHERE user_type = 'client' AND user_id = ?", [$clientId]);
            return (int) ($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function deleteLoginHistory(Database $db, int $clientId): int
    {
        try {
            $result = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM login_history WHERE user_type = 'client' AND user_id = ?",
                [$clientId]
            );
            $db->query("DELETE FROM login_history WHERE user_type = 'client' AND user_id = ?", [$clientId]);
            return (int) ($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function deleteSmtpConfigs(Database $db, int $clientId): int
    {
        try {
            $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM client_smtp_configs WHERE client_id = ?", [$clientId]);
            $db->query("DELETE FROM client_smtp_configs WHERE client_id = ?", [$clientId]);
            return (int) ($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function deleteEmailTemplates(Database $db, int $clientId): int
    {
        try {
            $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM client_invoice_email_templates WHERE client_id = ?", [$clientId]);
            $db->query("DELETE FROM client_invoice_email_templates WHERE client_id = ?", [$clientId]);
            return (int) ($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function deleteInvoiceBatches(Database $db, int $clientId): int
    {
        // Delete reports associated with batches
        try {
            $db->query(
                "DELETE r FROM reports r
                 INNER JOIN invoice_batches ib ON r.batch_id = ib.id
                 WHERE ib.client_id = ?",
                [$clientId]
            );
        } catch (\Throwable $e) {
            // Table may not have batch_id column
        }

        $result = $db->fetchOne("SELECT COUNT(*) as cnt FROM invoice_batches WHERE client_id = ?", [$clientId]);
        $db->query("DELETE FROM invoice_batches WHERE client_id = ?", [$clientId]);
        return (int) ($result['cnt'] ?? 0);
    }

    /**
     * Notify the office about client data deletion.
     */
    private static function notifyOffice(array $client, array $summary): void
    {
        $officeId = $client['office_id'] ?? null;
        if (!$officeId) {
            return;
        }

        $office = Office::findById((int) $officeId);
        if (!$office) {
            return;
        }

        // Create in-app notification for the office
        Notification::notify(
            'office',
            (int) $officeId,
            'Klient usunął swoje konto (RODO Art. 17)',
            sprintf(
                'Klient %s (NIP: %s) skorzystał z prawa do usunięcia danych. Wszystkie dane zostały trwale usunięte.',
                $client['company_name'] ?? '',
                $client['nip'] ?? ''
            ),
            'warning',
            '/office/clients'
        );

        // Send email notification to the office
        try {
            $officeEmail = $office['email'] ?? null;
            if ($officeEmail) {
                $mail = MailService::createMailerForClient(0);
                $mail->addAddress($officeEmail);
                $mail->isHTML(true);
                $mail->Subject = 'RODO - Klient usunął swoje konto: ' . ($client['company_name'] ?? '');

                $countsHtml = '';
                foreach ($summary['counts'] as $key => $count) {
                    if ($count > 0) {
                        $countsHtml .= "<li>{$key}: {$count}</li>";
                    }
                }

                $mail->Body = self::buildEmailBody(
                    'Usunięcie konta klienta (RODO Art. 17)',
                    "<p>Klient <strong>" . htmlspecialchars($client['company_name'] ?? '') . "</strong>
                     (NIP: " . htmlspecialchars($client['nip'] ?? '') . ") skorzystał z prawa do usunięcia danych
                     zgodnie z Art. 17 RODO (prawo do bycia zapomnianym).</p>
                     <p><strong>Data usunięcia:</strong> " . $summary['deleted_at'] . "</p>
                     <p><strong>Usunięte dane:</strong></p>
                     <ul>{$countsHtml}</ul>
                     <p>Wszystkie dane osobowe klienta oraz powiązane dokumenty zostały trwale usunięte z systemu.</p>"
                );

                $mail->send();
            }
        } catch (\Throwable $e) {
            error_log("RODO deletion email notification error: " . $e->getMessage());
        }
    }

    private static function buildEmailBody(string $title, string $body): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
            <div style="background: #dc2626; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
                <h2 style="margin: 0;">{$title}</h2>
            </div>
            <div style="padding: 20px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
                {$body}
            </div>
            <div style="padding: 10px; text-align: center; color: #9ca3af; font-size: 12px;">
                BiLLU Financial Solutions - System weryfikacji faktur
            </div>
        </body>
        </html>
        HTML;
    }
}
