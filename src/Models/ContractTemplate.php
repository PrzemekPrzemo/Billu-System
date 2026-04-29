<?php

namespace App\Models;

use App\Core\Database;

/**
 * PDF template (must be an AcroForm — system parses fields with pdftk and
 * uses them as the schema for the public form rendered to clients).
 */
class ContractTemplate
{
    /** Allowlist for office-side edit form. office_id, stored_path, fields_json,
     *  signers_json, created_by_* are set server-side and never accepted from POST. */
    public const FILLABLE = [
        'name', 'slug', 'description', 'is_active',
    ];

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM contract_templates WHERE id = ?",
            [$id]
        );
    }

    /** Tenant-checked accessor for /office routes. */
    public static function findByIdForOffice(int $id, int $officeId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM contract_templates WHERE id = ? AND office_id = ?",
            [$id, $officeId]
        );
    }

    public static function findActiveByOffice(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM contract_templates
             WHERE office_id = ? AND is_active = 1
             ORDER BY name",
            [$officeId]
        );
    }

    public static function findAllByOffice(int $officeId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM contract_templates WHERE office_id = ? ORDER BY is_active DESC, name",
            [$officeId]
        );
    }

    /**
     * Create a new template. Office-controlled fields go through FILLABLE;
     * the rest (office_id, stored_path, fields_json, signers_json, audit
     * columns) come from the calling service.
     *
     * @return int new template id
     */
    public static function create(int $officeId, array $data, string $storedPath, array $fields, array $signers, string $createdByType, int $createdById): int
    {
        $row = array_intersect_key($data, array_flip(self::FILLABLE));
        $row['office_id']         = $officeId;
        $row['stored_path']       = $storedPath;
        $row['fields_json']       = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $row['signers_json']      = json_encode($signers, JSON_UNESCAPED_UNICODE);
        $row['created_by_type']   = $createdByType;
        $row['created_by_id']     = $createdById;
        $row['original_filename'] = (string) ($data['original_filename'] ?? '');
        return Database::getInstance()->insert('contract_templates', $row);
    }

    public static function update(int $id, array $data, ?array $allowed = null): int
    {
        $whitelist = $allowed ?? self::FILLABLE;
        $filtered = array_intersect_key($data, array_flip($whitelist));
        if (empty($filtered)) {
            return 0;
        }
        return Database::getInstance()->update('contract_templates', $filtered, 'id = ?', [$id]);
    }

    /** Soft-delete: deactivates the template but preserves contract_forms / contract_signing_events history. */
    public static function deactivate(int $id): int
    {
        return Database::getInstance()->update('contract_templates', ['is_active' => 0], 'id = ?', [$id]);
    }

    /** Decode fields_json once for the caller; returns [] on missing/invalid JSON. */
    public static function decodeFields(array $template): array
    {
        $raw = $template['fields_json'] ?? null;
        if (!is_string($raw) || $raw === '') return [];
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : [];
    }

    public static function decodeSigners(array $template): array
    {
        $raw = $template['signers_json'] ?? null;
        if (!is_string($raw) || $raw === '') return [];
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : [];
    }
}
