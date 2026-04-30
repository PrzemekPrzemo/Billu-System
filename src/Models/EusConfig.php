<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Per-client e-US configuration.
 *
 * Mass-assignment policy: cert / passphrase / auth-provider blobs are
 * NEVER set via update($_POST) — they go through the dedicated setters
 * in EusCertificateService (PR-1) and EusProfilZaufanyService (PR-2).
 * The FILLABLE list below covers ONLY the operational toggles that an
 * office admin can change from the configuration form.
 */
class EusConfig
{
    public const FILLABLE = [
        'environment',
        'auth_method',
        'upl1_status',
        'upl1_valid_from',
        'upl1_valid_to',
        'upl1_scope',
        'upl1_pdf_path',
        'upl1_uploaded_at',
        'bramka_b_enabled',
        'bramka_c_enabled',
        'auto_submit_eus',
        'poll_incoming_enabled',
        'poll_interval_minutes',
    ];

    public const ENVIRONMENTS  = ['mock', 'test', 'prod'];
    public const AUTH_METHODS  = ['cert_qual', 'profil_zaufany', 'mdowod'];
    public const UPL1_STATUSES = ['none', 'pending', 'active', 'revoked', 'expired'];

    public static function findByClient(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_eus_configs WHERE client_id = ?",
            [$clientId]
        );
    }

    /**
     * Office-scoped lookup. Returns null if the config belongs to a
     * different office — the canonical tenant gate for e-US.
     */
    public static function findByClientForOffice(int $clientId, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_eus_configs WHERE client_id = ? AND office_id = ?",
            [$clientId, $officeId]
        );
    }

    public static function findByIdForOffice(int $id, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_eus_configs WHERE id = ? AND office_id = ?",
            [$id, $officeId]
        );
    }

    /**
     * All clients of an office that have e-US configured. Used by the
     * cron poller and the office overview list.
     */
    public static function findAllForOffice(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT cfg.*, c.company_name, c.nip
               FROM client_eus_configs cfg
               JOIN clients c ON c.id = cfg.client_id
              WHERE cfg.office_id = ?
              ORDER BY c.company_name",
            [$officeId]
        );
    }

    /**
     * Configs whose UPL-1 expires within $days. Used by the cron warning.
     * @return array<int,array{client_id:int,office_id:int,upl1_valid_to:string,company_name:string}>
     */
    public static function findExpiringUpl1(int $days = 30): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT cfg.client_id, cfg.office_id, cfg.upl1_valid_to, c.company_name
               FROM client_eus_configs cfg
               JOIN clients c ON c.id = cfg.client_id
              WHERE cfg.upl1_status = 'active'
                AND cfg.upl1_valid_to IS NOT NULL
                AND cfg.upl1_valid_to <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
              ORDER BY cfg.upl1_valid_to ASC",
            [$days]
        );
    }

    /**
     * Clients due for Bramka C polling. Filtered by:
     *   - poll_incoming_enabled = 1
     *   - bramka_c_enabled = 1
     *   - upl1_status = 'active'
     *   - last_poll_at NULL OR older than poll_interval_minutes
     */
    public static function findDueForPolling(): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT *
               FROM client_eus_configs
              WHERE poll_incoming_enabled = 1
                AND bramka_c_enabled = 1
                AND upl1_status = 'active'
                AND (last_poll_at IS NULL
                     OR last_poll_at <= DATE_SUB(NOW(), INTERVAL poll_interval_minutes MINUTE))"
        );
    }

    /**
     * Office-driven create or update. Filters $data through FILLABLE.
     * Privilege fields (cert_*, auth_provider_*, office_id, client_id)
     * are deliberately NOT in FILLABLE — those go through dedicated
     * setters that take an explicit office context.
     */
    public static function upsertForOffice(int $clientId, int $officeId, array $data): int
    {
        $existing = self::findByClient($clientId);

        $allowed = array_intersect_key($data, array_flip(self::FILLABLE));

        if ($existing === null) {
            $allowed['client_id'] = $clientId;
            $allowed['office_id'] = $officeId;
            return Database::getInstance()->insert('client_eus_configs', $allowed);
        }

        // Tenant gate: if the existing row belongs to another office, do nothing.
        if ((int) $existing['office_id'] !== $officeId) {
            return 0;
        }

        Database::getInstance()->update(
            'client_eus_configs',
            $allowed,
            'id = ?',
            [$existing['id']]
        );
        return (int) $existing['id'];
    }

    /**
     * Touch last_poll_at after a Bramka C poll completes (success or failure).
     */
    public static function markPolled(int $configId): void
    {
        Database::getInstance()->update(
            'client_eus_configs',
            ['last_poll_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$configId]
        );
    }
}
