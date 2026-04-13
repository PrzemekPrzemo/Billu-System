<?php

namespace App\Models;

use App\Core\Database;

/**
 * KSeF configuration per client.
 * Stores auth method, encrypted certificates/tokens, session data.
 */
class KsefConfig
{
    public static function findByClientId(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_ksef_configs WHERE client_id = ?",
            [$clientId]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_ksef_configs WHERE id = ?",
            [$id]
        );
    }

    public static function findAllActive(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ck.*, c.nip, c.company_name, c.office_id
             FROM client_ksef_configs ck
             JOIN clients c ON c.id = ck.client_id
             WHERE ck.is_active = 1 AND ck.auth_method != 'none'
             ORDER BY c.company_name"
        );
    }

    public static function findByFingerprint(string $fingerprint): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_ksef_configs WHERE cert_fingerprint = ?",
            [$fingerprint]
        );
    }

    public static function create(array $data): int
    {
        return Database::getInstance()->insert('client_ksef_configs', $data);
    }

    public static function update(int $clientId, array $data): int
    {
        return Database::getInstance()->update(
            'client_ksef_configs',
            $data,
            'client_id = ?',
            [$clientId]
        );
    }

    public static function upsert(int $clientId, array $data): void
    {
        $existing = self::findByClientId($clientId);
        if ($existing) {
            self::update($clientId, $data);
        } else {
            $data['client_id'] = $clientId;
            self::create($data);
        }
    }

    public static function delete(int $clientId): void
    {
        Database::getInstance()->query(
            "DELETE FROM client_ksef_configs WHERE client_id = ?",
            [$clientId]
        );
    }

    /**
     * Store cached access/refresh tokens.
     */
    public static function updateTokens(int $clientId, ?string $accessToken, ?string $accessExpires, ?string $refreshToken, ?string $refreshExpires): void
    {
        self::update($clientId, [
            'access_token' => $accessToken,
            'access_token_expires_at' => $accessExpires,
            'refresh_token' => $refreshToken,
            'refresh_token_expires_at' => $refreshExpires,
        ]);
    }

    /**
     * Clear cached tokens.
     */
    public static function clearTokens(int $clientId): void
    {
        self::updateTokens($clientId, null, null, null, null);
    }

    /**
     * Update last import status.
     */
    public static function updateImportStatus(int $clientId, string $status, ?string $error = null): void
    {
        self::update($clientId, [
            'last_import_at' => date('Y-m-d H:i:s'),
            'last_import_status' => $status,
            'last_error' => $error,
        ]);
    }

    /**
     * Get clients with expiring certificates (qualified or KSeF).
     */
    public static function findExpiringCertificates(int $daysThreshold = 30): array
    {
        $thresholdDate = date('Y-m-d H:i:s', strtotime("+{$daysThreshold} days"));
        return Database::getInstance()->fetchAll(
            "SELECT ck.*, c.nip, c.company_name, c.email
             FROM client_ksef_configs ck
             JOIN clients c ON c.id = ck.client_id
             WHERE ck.is_active = 1
               AND (
                   (ck.auth_method = 'certificate' AND ck.cert_valid_to IS NOT NULL AND ck.cert_valid_to <= ?)
                   OR (ck.auth_method = 'ksef_cert' AND ck.cert_ksef_valid_to IS NOT NULL AND ck.cert_ksef_valid_to <= ?)
               )
             ORDER BY COALESCE(ck.cert_valid_to, ck.cert_ksef_valid_to)",
            [$thresholdDate, $thresholdDate]
        );
    }

    /**
     * Update KSeF certificate enrollment data.
     */
    public static function updateKsefCert(int $clientId, array $data): void
    {
        self::update($clientId, $data);
    }

    /**
     * Update KSeF connection status after health check.
     */
    public static function updateConnectionStatus(int $clientId, string $status, ?string $error = null): void
    {
        self::update($clientId, [
            'ksef_connection_status' => $status,
            'ksef_connection_checked_at' => date('Y-m-d H:i:s'),
            'ksef_connection_error' => $error,
        ]);
    }

    /**
     * Get connection status for a client.
     */
    public static function getConnectionStatus(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT ksef_connection_status, ksef_connection_checked_at, ksef_connection_error
             FROM client_ksef_configs WHERE client_id = ?",
            [$clientId]
        );
    }

    /**
     * Find clients with failed KSeF connections that need retry.
     * Only returns clients with ksef_cert auth (auto-auth possible, no password needed).
     */
    public static function findFailedConnections(int $withinHours = 24): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$withinHours} hours"));
        return Database::getInstance()->fetchAll(
            "SELECT ck.*, c.nip, c.company_name, c.email, c.office_id, c.ksef_enabled
             FROM client_ksef_configs ck
             JOIN clients c ON c.id = ck.client_id
             WHERE ck.is_active = 1
               AND ck.auth_method = 'ksef_cert'
               AND c.is_active = 1
               AND c.ksef_enabled = 1
               AND ck.ksef_connection_status = 'failed'
               AND ck.ksef_connection_checked_at >= ?
             ORDER BY ck.ksef_connection_checked_at ASC",
            [$since]
        );
    }

    /**
     * Find configs with pending enrollment.
     */
    public static function findPendingEnrollments(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT ck.*, c.nip, c.company_name
             FROM client_ksef_configs ck
             JOIN clients c ON c.id = ck.client_id
             WHERE ck.cert_ksef_status = 'enrolling'
               AND ck.cert_ksef_enrollment_ref IS NOT NULL"
        );
    }
}
