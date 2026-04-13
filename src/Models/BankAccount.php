<?php

namespace App\Models;

use App\Core\Database;

class BankAccount
{
    public static function findByClient(int $clientId): array
    {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM company_bank_accounts WHERE client_id = ? ORDER BY sort_order, id",
            [$clientId]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM company_bank_accounts WHERE id = ?",
            [$id]
        );
    }

    public static function create(int $clientId, array $data): int
    {
        $data['client_id'] = $clientId;

        if (!empty($data['is_default_receiving'])) {
            self::clearDefaultReceiving($clientId);
        }
        if (!empty($data['is_default_outgoing'])) {
            self::clearDefaultOutgoing($clientId);
        }

        return Database::getInstance()->insert('company_bank_accounts', $data);
    }

    public static function update(int $id, array $data): void
    {
        $account = self::findById($id);
        if ($account) {
            if (!empty($data['is_default_receiving'])) {
                self::clearDefaultReceiving($account['client_id']);
            }
            if (!empty($data['is_default_outgoing'])) {
                self::clearDefaultOutgoing($account['client_id']);
            }
        }

        Database::getInstance()->update('company_bank_accounts', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query("DELETE FROM company_bank_accounts WHERE id = ?", [$id]);
    }

    public static function getDefaultReceiving(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM company_bank_accounts WHERE client_id = ? AND is_default_receiving = 1",
            [$clientId]
        );
    }

    public static function getDefaultOutgoing(int $clientId): ?array
    {
        return Database::getInstance()->fetchOne(
            "SELECT * FROM company_bank_accounts WHERE client_id = ? AND is_default_outgoing = 1",
            [$clientId]
        );
    }

    public static function clearDefaultReceiving(int $clientId): void
    {
        Database::getInstance()->query(
            "UPDATE company_bank_accounts SET is_default_receiving = 0 WHERE client_id = ?",
            [$clientId]
        );
    }

    public static function clearDefaultOutgoing(int $clientId): void
    {
        Database::getInstance()->query(
            "UPDATE company_bank_accounts SET is_default_outgoing = 0 WHERE client_id = ?",
            [$clientId]
        );
    }
}
