<?php

namespace App\Models;

use App\Core\Database;

class ClientInvoiceEmailTemplate
{
    public static function findByClientId(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_invoice_email_templates WHERE client_id = ?",
            [$clientId]
        );
    }

    public static function upsert(int $clientId, array $data): void
    {
        $existing = self::findByClientId($clientId);
        $db = Database::getInstance();

        if ($existing) {
            $sets = [];
            $params = [];
            foreach ($data as $key => $value) {
                $sets[] = "{$key} = ?";
                $params[] = $value;
            }
            $params[] = $clientId;
            $db->query(
                "UPDATE client_invoice_email_templates SET " . implode(', ', $sets) . " WHERE client_id = ?",
                $params
            );
        } else {
            $data['client_id'] = $clientId;
            $cols = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $db->query(
                "INSERT INTO client_invoice_email_templates ({$cols}) VALUES ({$placeholders})",
                array_values($data)
            );
        }
    }

    /**
     * Get subject and body for a given language.
     *
     * @return array{subject: string, body: string}
     */
    public static function getTemplate(int $clientId, string $lang = 'pl'): array
    {
        $tpl = self::findByClientId($clientId);
        $suffix = $lang === 'en' ? '_en' : '_pl';

        if ($tpl) {
            $subject = $tpl['subject_template' . $suffix] ?? '';
            $body = $tpl['body_template' . $suffix] ?? '';

            // Fall back to PL if EN is empty
            if ($lang === 'en' && empty($body)) {
                $subject = $tpl['subject_template_pl'] ?? '';
                $body = $tpl['body_template_pl'] ?? '';
            }
        }

        if (empty($subject)) {
            $subject = $lang === 'en' ? 'Invoice {{invoice_number}}' : 'Faktura {{invoice_number}}';
        }
        if (empty($body)) {
            $body = self::getDefaultBody($lang);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Render subject template with invoice data.
     */
    public static function renderSubject(string $template, array $vars): string
    {
        return self::replacePlaceholders($template, $vars);
    }

    /**
     * Render body template with invoice data.
     */
    public static function renderBody(string $template, array $vars): string
    {
        return self::replacePlaceholders($template, $vars);
    }

    /**
     * Get default body template for new clients.
     */
    public static function getDefaultBody(string $lang = 'pl'): string
    {
        if ($lang === 'en') {
            return '<p>Dear Sir or Madam,</p>'
                . '<p>Please find attached invoice no. <strong>{{invoice_number}}</strong> '
                . 'dated {{issue_date}} for the amount of <strong>{{gross_amount}} {{currency}}</strong>.</p>'
                . '<p>Payment due: <strong>{{due_date}}</strong></p>'
                . '<p>Kind regards,<br>{{seller_name}}</p>';
        }

        return '<p>Szanowni Państwo,</p>'
            . '<p>W załączeniu przesyłamy fakturę nr <strong>{{invoice_number}}</strong> '
            . 'z dnia {{issue_date}} na kwotę <strong>{{gross_amount}} {{currency}}</strong>.</p>'
            . '<p>Termin płatności: <strong>{{due_date}}</strong></p>'
            . '<p>Z poważaniem,<br>{{seller_name}}</p>';
    }

    /**
     * Update or clear logo path for a client.
     */
    public static function updateLogo(int $clientId, ?string $logoPath): void
    {
        self::upsert($clientId, ['logo_path' => $logoPath]);
    }

    private static function replacePlaceholders(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', htmlspecialchars((string) $value), $text);
        }
        return $text;
    }
}
