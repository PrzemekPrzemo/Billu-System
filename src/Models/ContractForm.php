<?php

namespace App\Models;

use App\Core\Database;

/**
 * Instance of a shared form link based on a ContractTemplate.
 *
 * Status flow:
 *   pending  → filled (client opened+filled)
 *            → submitted (PDF rendered + dispatched to SIGNIUS)
 *            → signed   (SIGNIUS webhook 'signed')
 *            → rejected / expired / cancelled (terminal)
 *
 * Sensitive columns (token, status, *_pdf_path, signius_package_id) are
 * NEVER mass-assignable from a controller — they go through the dedicated
 * setters below.
 */
class ContractForm
{
    public const FILLABLE = [
        'recipient_email', 'recipient_name', 'expires_at',
    ];

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM contract_forms WHERE id = ?", [$id]
        );
    }

    public static function findByIdForOffice(int $id, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM contract_forms WHERE id = ? AND office_id = ?",
            [$id, $officeId]
        );
    }

    /** Used by the public token URL — no auth context, lookup by raw token. */
    public static function findByToken(string $token): ?array
    {
        if (strlen($token) !== 64) {
            return null;
        }
        return Database::getInstance()->fetchOne(
            "SELECT * FROM contract_forms WHERE token = ? LIMIT 1", [$token]
        );
    }

    /** Forms visible to a given client (their /client/contracts panel). */
    public static function findByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM contract_forms
             WHERE client_id = ?
             ORDER BY created_at DESC", [$clientId]
        );
    }

    public static function findByOfficePaginated(int $officeId, int $offset, int $limit, ?string $statusFilter = null): array
    {
        $sql = "SELECT * FROM contract_forms WHERE office_id = ?";
        $params = [$officeId];
        if ($statusFilter !== null && $statusFilter !== '') {
            $sql .= " AND status = ?";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        return Database::getInstance()->fetchAll($sql, $params);
    }

    public static function countByOffice(int $officeId, ?string $statusFilter = null): int
    {
        $sql = "SELECT COUNT(*) AS c FROM contract_forms WHERE office_id = ?";
        $params = [$officeId];
        if ($statusFilter !== null && $statusFilter !== '') {
            $sql .= " AND status = ?";
            $params[] = $statusFilter;
        }
        $row = Database::getInstance()->fetchOne($sql, $params);
        return (int) ($row['c'] ?? 0);
    }

    /** Core insert. token + status are set explicitly — never from the caller's $data. */
    public static function create(int $templateId, int $officeId, ?int $clientId, string $token, string $expiresAt, array $extra, string $createdByType, int $createdById): int
    {
        $row = array_intersect_key($extra, array_flip(self::FILLABLE));
        $row['template_id']     = $templateId;
        $row['office_id']       = $officeId;
        $row['client_id']       = $clientId;
        $row['token']           = $token;
        $row['expires_at']      = $expiresAt;
        $row['status']          = 'pending';
        $row['created_by_type'] = $createdByType;
        $row['created_by_id']   = $createdById;
        return Database::getInstance()->insert('contract_forms', $row);
    }

    public static function markFilled(int $id, array $formData): void
    {
        Database::getInstance()->update('contract_forms', [
            'status'    => 'filled',
            'form_data' => json_encode($formData, JSON_UNESCAPED_UNICODE),
        ], 'id = ?', [$id]);
    }

    public static function markSubmitted(int $id, string $filledPdfPath, ?string $packageId): void
    {
        Database::getInstance()->update('contract_forms', [
            'status'             => 'submitted',
            'filled_pdf_path'    => $filledPdfPath,
            'signius_package_id' => $packageId,
            'submitted_at'       => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public static function attachSignedPdf(int $id, string $signedPath): void
    {
        Database::getInstance()->update('contract_forms', [
            'status'          => 'signed',
            'signed_pdf_path' => $signedPath,
            'signed_at'       => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        // Whitelist allowed terminal transitions; other transitions go through
        // dedicated methods above to keep the audit trail consistent.
        if (!in_array($status, ['rejected', 'expired', 'cancelled'], true)) {
            return;
        }
        Database::getInstance()->update('contract_forms', ['status' => $status], 'id = ?', [$id]);
    }

    public static function isExpired(array $form): bool
    {
        if (!isset($form['expires_at'])) return false;
        return strtotime((string) $form['expires_at']) < time();
    }

    public static function decodeFormData(array $form): array
    {
        $raw = $form['form_data'] ?? null;
        if (!is_string($raw) || $raw === '') return [];
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : [];
    }

    /** Cron helper — flips pending forms past expires_at to expired. Returns affected row count. */
    public static function expireOverdue(): int
    {
        $stmt = Database::getInstance()->query(
            "UPDATE contract_forms SET status = 'expired'
             WHERE status = 'pending' AND expires_at < NOW()"
        );
        return $stmt->rowCount();
    }
}
