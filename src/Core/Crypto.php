<?php

declare(strict_types=1);

namespace App\Core;

/**
 * AES-256-GCM symmetric encryption for at-rest secrets (SFTP credentials,
 * 2FA backup material, etc.). Master key is derived from APP_SECRET_KEY
 * via HKDF with a per-call $context label, so leaking one ciphertext
 * doesn't help an attacker decrypt others encrypted under a different
 * context.
 *
 * Format on disk (base64-encoded, single string):
 *   v1.<base64(nonce || ciphertext || tag)>
 *
 * Use:
 *   $cipher = Crypto::encrypt('s3cret-pwd', 'sftp.password');
 *   $plain  = Crypto::decrypt($cipher, 'sftp.password');
 */
final class Crypto
{
    private const VERSION_PREFIX = 'v1.';
    private const NONCE_LEN = 12;     // GCM standard
    private const TAG_LEN   = 16;

    public static function encrypt(string $plaintext, string $context = ''): string
    {
        $key = self::deriveKey($context);
        $nonce = random_bytes(self::NONCE_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Crypto::encrypt failed');
        }
        return self::VERSION_PREFIX . self::base64UrlEncode($nonce . $ciphertext . $tag);
    }

    /**
     * Returns the plaintext, or null if the ciphertext is malformed / forged.
     * Never throws on bad input — caller decides how to handle a null return.
     */
    public static function decrypt(string $blob, string $context = ''): ?string
    {
        if (!str_starts_with($blob, self::VERSION_PREFIX)) {
            return null;
        }
        $raw = self::base64UrlDecode(substr($blob, strlen(self::VERSION_PREFIX)));
        if ($raw === null || strlen($raw) < self::NONCE_LEN + self::TAG_LEN + 1) {
            return null;
        }
        $nonce      = substr($raw, 0, self::NONCE_LEN);
        $tag        = substr($raw, -self::TAG_LEN);
        $ciphertext = substr($raw, self::NONCE_LEN, -self::TAG_LEN);

        $key = self::deriveKey($context);
        $plain = openssl_decrypt(
            $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag
        );
        return $plain === false ? null : $plain;
    }

    /** Derive a 32-byte key from APP_SECRET_KEY + context using HKDF-SHA256. */
    private static function deriveKey(string $context): string
    {
        $secret = self::masterKey();
        $info = 'BiLLU.crypto.v1' . ($context !== '' ? '/' . $context : '');
        $derived = hash_hkdf('sha256', $secret, 32, $info, '');
        if (!is_string($derived) || strlen($derived) !== 32) {
            throw new \RuntimeException('Crypto: HKDF derivation failed');
        }
        return $derived;
    }

    private static function masterKey(): string
    {
        static $key = null;
        if ($key !== null) return $key;

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $secretHex = (string) ($appConfig['secret_key'] ?? '');
        if ($secretHex === '') {
            throw new \RuntimeException('Crypto: APP_SECRET_KEY missing in config/app.php');
        }
        // secret_key is conventionally 64 hex chars (= 32 bytes). Either form works
        // because HKDF accepts arbitrary-length input keying material.
        $key = ctype_xdigit($secretHex) && strlen($secretHex) % 2 === 0
            ? hex2bin($secretHex)
            : $secretHex;
        return $key;
    }

    private static function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $b64): ?string
    {
        $padded = strtr($b64, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $raw = base64_decode($padded, true);
        return $raw === false ? null : $raw;
    }
}
