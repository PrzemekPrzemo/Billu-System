<?php

namespace App\Services;

class HrEncryptionService
{
    private static ?string $encKey  = null;
    private static ?string $hmacKey = null;

    private static function boot(): void
    {
        if (self::$encKey !== null) {
            return;
        }

        $config = require __DIR__ . '/../../config/app.php';
        $secret = $config['secret_key'] ?? '';

        if (empty($secret) || $secret === 'CHANGE_THIS_TO_RANDOM_64_CHAR_STRING') {
            throw new \RuntimeException(
                'Encryption key not configured. Set a secure secret_key in config/app.php before using the HR module.'
            );
        }

        self::$encKey  = hash('sha256', $secret . ':hr_enc', true);
        self::$hmacKey = hash('sha256', $secret . ':hr_hmac');
    }

    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }

        self::boot();

        $nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, self::$encKey);
        sodium_memzero($plaintext);

        return base64_encode($nonce . $ciphertext);
    }

    public static function decrypt(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return $ciphertext;
        }

        self::boot();

        $decoded = base64_decode($ciphertext, strict: true);
        if ($decoded === false) {
            return $ciphertext;
        }

        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

        if (strlen($decoded) <= $nonceLen) {
            return null;
        }

        $nonce     = substr($decoded, 0, $nonceLen);
        $encrypted = substr($decoded, $nonceLen);

        $plaintext = sodium_crypto_secretbox_open($encrypted, $nonce, self::$encKey);

        if ($plaintext === false) {
            error_log('[HrEncryption] Decryption failed — bad key or corrupted data');
            return null;
        }

        return $plaintext;
    }

    public static function hashForSearch(string $plaintext): string
    {
        self::boot();
        return hash_hmac('sha256', strtolower(trim($plaintext)), self::$hmacKey);
    }

    public static function encryptFields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = self::encrypt((string)($data[$field] ?? ''));
                if ($data[$field] === '') $data[$field] = null;
            }
        }
        return $data;
    }

    public static function decryptFields(array $row, array $fields): array
    {
        if (empty($row)) {
            return $row;
        }
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = self::decrypt($row[$field]);
            }
        }
        return $row;
    }

    public static function decryptRows(array $rows, array $fields): array
    {
        foreach ($rows as &$row) {
            $row = self::decryptFields($row, $fields);
        }
        return $rows;
    }
}
