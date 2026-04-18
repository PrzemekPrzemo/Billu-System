<?php

namespace App\Models;

use App\Core\HrDatabase;
use App\Services\HrEncryptionService;

class HrEmployee
{
    const SENSITIVE_FIELDS = [
        'pesel', 'nip', 'birth_date', 'address_street', 'address_city', 'address_zip',
        'email', 'phone', 'bank_account_iban', 'bank_name', 'tax_office_code', 'tax_office_name',
    ];

    public static function findById(int $id): ?array
    {
        $mainDb = HrDatabase::mainDbName();
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT e.*, c.company_name AS client_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS full_name
             FROM hr_employees e
             JOIN {$mainDb}.clients c ON e.client_id = c.id
             WHERE e.id = ?",
            [$id]
        );
        if ($row) {
            $row = HrEncryptionService::decryptFields($row, self::SENSITIVE_FIELDS);
        }
        return $row;
    }

    public static function findByClient(int $clientId, bool $activeOnly = false): array
    {
        $sql = "SELECT e.*,
                       CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                       c.contract_type, c.position, c.department, c.base_salary, c.work_time_fraction
                FROM hr_employees e
                LEFT JOIN hr_contracts c ON c.employee_id = e.id AND c.is_current = 1
                WHERE e.client_id = ?";
        if ($activeOnly) {
            $sql .= " AND e.is_active = 1";
        }
        $sql .= " ORDER BY e.last_name, e.first_name";
        $rows = HrDatabase::getInstance()->fetchAll($sql, [$clientId]);
        return HrEncryptionService::decryptRows($rows, self::SENSITIVE_FIELDS);
    }

    public static function findByPesel(int $clientId, string $pesel): ?array
    {
        $hash = HrEncryptionService::hashForSearch($pesel);
        $row = HrDatabase::getInstance()->fetchOne(
            "SELECT * FROM hr_employees WHERE client_id = ? AND pesel_hash = ?",
            [$clientId, $hash]
        );
        if ($row) {
            $row = HrEncryptionService::decryptFields($row, self::SENSITIVE_FIELDS);
        }
        return $row;
    }

    public static function countByClient(int $clientId, bool $activeOnly = true): int
    {
        $sql = "SELECT COUNT(*) AS cnt FROM hr_employees WHERE client_id = ?";
        if ($activeOnly) $sql .= " AND is_active = 1";
        $row = HrDatabase::getInstance()->fetchOne($sql, [$clientId]);
        return (int) ($row['cnt'] ?? 0);
    }

    public static function create(array $data): int
    {
        if (!empty($data['pesel'])) {
            $data['pesel_hash'] = HrEncryptionService::hashForSearch($data['pesel']);
        }
        $data = HrEncryptionService::encryptFields($data, self::SENSITIVE_FIELDS);
        return HrDatabase::getInstance()->insert('hr_employees', $data);
    }

    public static function update(int $id, array $data): int
    {
        if (isset($data['pesel'])) {
            $data['pesel_hash'] = HrEncryptionService::hashForSearch($data['pesel']);
        }
        $data = HrEncryptionService::encryptFields($data, self::SENSITIVE_FIELDS);
        return HrDatabase::getInstance()->update('hr_employees', $data, 'id = ?', [$id]);
    }

    public static function archive(int $id): void
    {
        HrDatabase::getInstance()->update('hr_employees', [
            'is_active'      => 0,
            'employment_end' => date('Y-m-d'),
        ], 'id = ?', [$id]);
    }

    public static function archiveWithReason(int $id, string $reason, string $endDate): void
    {
        $allowed = ['end_of_contract', 'resignation', 'dismissal', 'other'];
        $reason  = in_array($reason, $allowed, true) ? $reason : 'other';

        HrDatabase::getInstance()->update('hr_employees', [
            'is_active'      => 0,
            'employment_end' => $endDate,
            'archived_at'    => date('Y-m-d H:i:s'),
            'archive_reason' => $reason,
        ], 'id = ?', [$id]);
    }

    public static function setSwiadectwoPdfPath(int $id, string $path): void
    {
        HrDatabase::getInstance()->update('hr_employees', [
            'swiadectwo_pdf_path' => $path,
        ], 'id = ?', [$id]);
    }

    public static function findArchived(int $clientId): array
    {
        $rows = HrDatabase::getInstance()->fetchAll(
            "SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) AS full_name
             FROM hr_employees e
             WHERE e.client_id = ? AND e.archived_at IS NOT NULL
             ORDER BY e.archived_at DESC",
            [$clientId]
        );
        return HrEncryptionService::decryptRows($rows, self::SENSITIVE_FIELDS);
    }

    public static function validatePesel(string $pesel): bool
    {
        if (!preg_match('/^\d{11}$/', $pesel)) {
            return false;
        }
        $weights = [1, 3, 7, 9, 1, 3, 7, 9, 1, 3];
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$pesel[$i] * $weights[$i];
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit === (int)$pesel[10];
    }

    public static function maskPesel(?string $pesel): string
    {
        if (!$pesel) return '-';
        return '•••••••' . substr($pesel, -4);
    }
}
