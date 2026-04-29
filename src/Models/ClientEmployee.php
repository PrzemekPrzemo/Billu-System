<?php

namespace App\Models;

use App\Core\Database;

class ClientEmployee
{
    /** Default mass-assignment whitelist for HR data. login_email/password_hash/can_login require explicit $allowed override. */
    public const FILLABLE = [
        'first_name', 'last_name', 'pesel', 'date_of_birth',
        'email', 'phone',
        'address_street', 'address_city', 'address_postal_code',
        'tax_office', 'bank_account', 'nfz_branch',
        'is_active', 'hired_at', 'terminated_at', 'notes',
    ];

    /** Auth-related fields exposed to client/office CRUD. Password fields are NEVER mass-assigned. */
    public const AUTH_FILLABLE = [
        'login_email', 'can_login',
    ];

    /** Combined list for client/office "edit employee" forms (HR data + login flag). */
    public static function clientAllowedFields(): array
    {
        return array_merge(self::FILLABLE, self::AUTH_FILLABLE);
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_employees WHERE id = ?",
            [$id]
        );
    }

    /** Ownership-checked accessor for client-scoped routes. */
    public static function findByIdForClient(int $id, int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM client_employees WHERE id = ? AND client_id = ?",
            [$id, $clientId]
        );
    }

    /** Ownership-checked accessor for office-scoped routes. */
    public static function findByIdForOffice(int $id, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT ce.* FROM client_employees ce JOIN clients c ON ce.client_id = c.id WHERE ce.id = ? AND c.office_id = ?",
            [$id, $officeId]
        );
    }

    /** Lookup for /employee/login. Joins clients to expose office_id and client active flag. */
    public static function findByLoginEmail(string $email): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT ce.*, c.office_id, c.is_active AS client_is_active, c.company_name
             FROM client_employees ce
             JOIN clients c ON ce.client_id = c.id
             WHERE ce.login_email = ? LIMIT 1",
            [$email]
        );
    }

    /** Find by single-use activation token, only if not expired. */
    public static function findByActivationToken(string $token): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT ce.*, c.office_id, c.company_name
             FROM client_employees ce
             JOIN clients c ON ce.client_id = c.id
             WHERE ce.activation_token = ? AND ce.activation_expires_at IS NOT NULL AND ce.activation_expires_at > NOW()
             LIMIT 1",
            [$token]
        );
    }

    /** Find all employees for a client, optionally only active ones. */
    public static function findByClient(int $clientId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM client_employees WHERE client_id = ?";
        $params = [$clientId];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY last_name ASC, first_name ASC";
        return Database::getInstance()->fetchAll($sql, $params);
    }

    /** Create a new employee. $data MUST include client_id. */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('client_employees', $data);
    }

    /**
     * Update an employee. By default filters $data through self::FILLABLE.
     * Pass $allowed = self::clientAllowedFields() in client/office CRUD to permit login_email + can_login.
     * Password / activation_token / 2FA fields are never mass-assigned — use dedicated setters.
     */
    public static function update(int $id, array $data, ?array $allowed = null): int
    {
        $whitelist = $allowed ?? self::FILLABLE;
        $filtered = array_intersect_key($data, array_flip($whitelist));
        if (empty($filtered)) {
            return 0;
        }
        return Database::getInstance()->update('client_employees', $filtered, 'id = ?', [$id]);
    }

    /** Count active employees for a client. */
    public static function countByClient(int $clientId): int
    {
        $row = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS cnt FROM client_employees WHERE client_id = ? AND is_active = 1",
            [$clientId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /** Generate and store a one-time activation token (32 bytes hex). Returns plaintext token to send by email. */
    public static function issueActivationToken(int $id, int $ttlHours = 72): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime("+{$ttlHours} hours"));
        Database::getInstance()->update(
            'client_employees',
            ['activation_token' => $token, 'activation_expires_at' => $expires],
            'id = ?',
            [$id]
        );
        return $token;
    }

    /** Set password and activate the account. Used after the user clicks the activation link and submits the form. */
    public static function setPasswordAndActivate(int $id, string $passwordHash): void
    {
        Database::getInstance()->update('client_employees', [
            'password_hash'         => $passwordHash,
            'password_changed_at'   => date('Y-m-d H:i:s'),
            'force_password_change' => 0,
            'can_login'             => 1,
            'activation_token'      => null,
            'activation_expires_at' => null,
        ], 'id = ?', [$id]);
    }

    /** Plain password update (used by /employee/profile). */
    public static function updatePassword(int $id, string $passwordHash): void
    {
        Database::getInstance()->update('client_employees', [
            'password_hash'         => $passwordHash,
            'password_changed_at'   => date('Y-m-d H:i:s'),
            'force_password_change' => 0,
        ], 'id = ?', [$id]);
    }

    public static function updateLastLogin(int $id): void
    {
        Database::getInstance()->update('client_employees', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
    }

    public static function getFullName(array $employee): string
    {
        return trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
    }
}
