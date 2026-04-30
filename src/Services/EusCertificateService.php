<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Certificate / secret vault for the e-Urząd Skarbowy integration.
 *
 * Mirrors the pattern in KsefCertificateService (AES-256-GCM at rest)
 * but uses a SEPARATE master key — `settings.eus_cert_encryption_key` —
 * so a compromise of the KSeF vault does not expose e-US credentials
 * (and vice versa). Both keys live in the same Settings row but a
 * future split (env vars / KMS) is easy to do per-namespace.
 *
 * Storage shape (base64): iv(12) || tag(16) || ciphertext.
 *
 * The XAdES-BES signing path lives in PR-2 (EusApiService) and reuses
 * KsefCertificateService::signXml() directly — no need to clone that
 * 300-line method here.
 */
class EusCertificateService
{
    private const CIPHER      = 'aes-256-gcm';
    private const KEY_SETTING = 'eus_cert_encryption_key';

    // ─── Master key management ───────────────────────────

    /**
     * Get or lazily generate the master encryption key. The result is
     * raw 32 bytes; the DB value is base64-encoded for portability.
     */
    public static function getEncryptionKey(): string
    {
        $stored = Setting::get(self::KEY_SETTING, '');
        if (!empty($stored)) {
            $key = base64_decode((string) $stored, true);
            if ($key !== false && strlen($key) === 32) {
                return $key;
            }
        }

        $key = random_bytes(32);
        Setting::set(self::KEY_SETTING, base64_encode($key));
        return $key;
    }

    /**
     * Idempotent — call from install.php to make the master key
     * present at install time (so backups capture it before any cert
     * is encrypted).
     */
    public static function ensureKey(): bool
    {
        $stored = Setting::get(self::KEY_SETTING, '');
        if (!empty($stored)) {
            return false; // already present
        }
        Setting::set(self::KEY_SETTING, base64_encode(random_bytes(32)));
        return true;
    }

    // ─── Encrypt / decrypt ───────────────────────────────

    public static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $iv  = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('e-US encryption failed: ' . (openssl_error_string() ?: 'unknown'));
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $key = self::getEncryptionKey();
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('Invalid encrypted blob');
        }

        $iv         = substr($raw, 0, 12);
        $tag        = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($plaintext === false) {
            throw new \RuntimeException('e-US decryption failed — wrong master key or corrupted data');
        }
        return $plaintext;
    }

    // ─── PFX parsing (used by PR-2 upload form) ──────────

    /**
     * Reads a PFX/P12 blob with the given passphrase, returning the
     * minimal metadata the office UI needs (subject, issuer, validity).
     * Throws on bad passphrase or malformed file — caller surfaces a
     * Polish-localized message.
     *
     * @return array{subject:string,issuer:string,fingerprint:string,valid_from:string,valid_to:string}
     */
    public static function parsePfx(string $pfxData, string $passphrase): array
    {
        $bag = [];
        if (!openssl_pkcs12_read($pfxData, $bag, $passphrase)) {
            throw new \RuntimeException(
                'Nie można odczytać certyfikatu (PFX/P12). Sprawdź hasło. '
                . (openssl_error_string() ?: '')
            );
        }
        if (empty($bag['cert']) || empty($bag['pkey'])) {
            throw new \RuntimeException('Certyfikat nie zawiera klucza prywatnego lub publicznego.');
        }

        $info = openssl_x509_parse($bag['cert']);
        if (!is_array($info)) {
            throw new \RuntimeException('Nie można sparsować certyfikatu X.509.');
        }

        $fp = openssl_x509_fingerprint($bag['cert'], 'sha256');

        return [
            'subject'     => self::dnToString($info['subject']  ?? []),
            'issuer'      => self::dnToString($info['issuer']   ?? []),
            'fingerprint' => is_string($fp) ? $fp : '',
            'valid_from'  => isset($info['validFrom_time_t']) ? date('Y-m-d H:i:s', (int) $info['validFrom_time_t']) : '',
            'valid_to'    => isset($info['validTo_time_t'])   ? date('Y-m-d H:i:s', (int) $info['validTo_time_t'])   : '',
        ];
    }

    private static function dnToString(array $dn): string
    {
        $parts = [];
        foreach ($dn as $k => $v) {
            $parts[] = "{$k}=" . (is_array($v) ? implode(',', $v) : (string) $v);
        }
        return implode(', ', $parts);
    }
}
