<?php

namespace App\Services;

class HrDocumentStorageService
{
    private static string $baseDir = __DIR__ . '/../../storage/hr/documents';
    private static ?string $key = null;

    private static function getKey(): string
    {
        if (self::$key === null) {
            $config = require __DIR__ . '/../../config/app.php';
            $secret = $config['secret_key'] ?? '';
            self::$key = hash('sha256', $secret . ':hr_file_enc', true);
        }
        return self::$key;
    }

    public static function storeEncrypted(string $tmpPath, int $clientId, int $employeeId): string
    {
        $dir = self::$baseDir . '/' . $clientId . '/' . $employeeId;
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $uuid     = self::generateUuid();
        $filename = $uuid . '.enc';
        $fullPath = $dir . '/' . $filename;

        $plaintext = file_get_contents($tmpPath);
        if ($plaintext === false) {
            throw new \RuntimeException('Nie można odczytać pliku tymczasowego');
        }

        $iv         = random_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', self::getKey(), OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Szyfrowanie pliku nie powiodło się');
        }

        file_put_contents($fullPath, $iv . $ciphertext);

        return 'storage/hr/documents/' . $clientId . '/' . $employeeId . '/' . $filename;
    }

    public static function readDecrypted(string $relativePath): string
    {
        $fullPath = __DIR__ . '/../../' . $relativePath;
        if (!file_exists($fullPath)) {
            throw new \RuntimeException('Plik nie istnieje: ' . $relativePath);
        }

        $raw = file_get_contents($fullPath);
        if ($raw === false || strlen($raw) < 17) {
            throw new \RuntimeException('Plik zaszyfrowany jest uszkodzony');
        }

        $iv         = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);

        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', self::getKey(), OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Odszyfrowanie pliku nie powiodło się — plik może być uszkodzony lub klucz nieprawidłowy');
        }

        return $plaintext;
    }

    public static function delete(string $relativePath): void
    {
        $fullPath = __DIR__ . '/../../' . $relativePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
