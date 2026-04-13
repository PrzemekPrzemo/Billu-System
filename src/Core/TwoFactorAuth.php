<?php

namespace App\Core;

/**
 * TOTP (RFC 6238) Two-Factor Authentication
 * Pure PHP implementation - compatible with Google Authenticator,
 * Microsoft Authenticator, Authy, FreeOTP, and all standard TOTP apps.
 */
class TwoFactorAuth
{
    private const SECRET_LENGTH = 20; // 160 bits
    private const CODE_DIGITS = 6;
    private const TIME_STEP = 30; // seconds
    private const HASH_ALGO = 'sha1';
    private const WINDOW = 1; // allow ±1 time step for clock drift

    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random Base32-encoded secret.
     */
    public static function generateSecret(): string
    {
        $bytes = random_bytes(self::SECRET_LENGTH);
        return self::base32Encode($bytes);
    }

    /**
     * Generate a TOTP code for the given secret and time.
     */
    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeCounter = intdiv($timestamp, self::TIME_STEP);

        $secretBytes = self::base32Decode($secret);
        $timeBytes = pack('N*', 0, $timeCounter); // 8-byte big-endian

        $hash = hash_hmac(self::HASH_ALGO, $timeBytes, $secretBytes, true);

        // Dynamic truncation (RFC 4226 §5.4)
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::CODE_DIGITS);

        return str_pad((string)$code, self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a user-provided code against the secret.
     * Allows a window of ±1 time step for clock drift.
     */
    public static function verifyCode(string $secret, string $code, ?int $timestamp = null): bool
    {
        $timestamp = $timestamp ?? time();
        $code = trim($code);

        if (strlen($code) !== self::CODE_DIGITS || !ctype_digit($code)) {
            return false;
        }

        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $checkTime = $timestamp + ($i * self::TIME_STEP);
            if (hash_equals(self::getCode($secret, $checkTime), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate an otpauth:// URI for QR code scanning.
     */
    public static function getOtpAuthUri(string $secret, string $label, string $issuer = 'BiLLU'): string
    {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::HASH_ALGO),
            'digits' => self::CODE_DIGITS,
            'period' => self::TIME_STEP,
        ]);

        $label = rawurlencode($issuer) . ':' . rawurlencode($label);
        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Generate recovery codes (one-time use backup codes).
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8-char hex codes
        }
        return $codes;
    }

    /**
     * Hash recovery codes for storage.
     */
    public static function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn($code) => hash('sha256', strtoupper(trim($code))), $codes);
    }

    /**
     * Verify a recovery code against hashed codes.
     * Returns the index of the matched code, or -1 if not found.
     */
    public static function verifyRecoveryCode(string $code, array $hashedCodes): int
    {
        $hash = hash('sha256', strtoupper(trim($code)));
        foreach ($hashedCodes as $i => $hashed) {
            if (hash_equals($hashed, $hash)) {
                return $i;
            }
        }
        return -1;
    }

    /**
     * Generate QR code as SVG string using chillerlan/php-qrcode.
     */
    public static function generateQrSvg(string $data): string
    {
        $options = new \chillerlan\QRCode\QROptions([
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
            'addQuietzone' => true,
            'scale' => 6,
        ]);

        return (new \chillerlan\QRCode\QRCode($options))->render($data);
    }

    // ── Base32 encoding/decoding ──────────────────────

    private static function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= self::BASE32_CHARS[bindec($chunk)];
        }

        return $result;
    }

    private static function base32Decode(string $data): string
    {
        $data = strtoupper(rtrim($data, '='));
        $binary = '';

        foreach (str_split($data) as $char) {
            $pos = strpos(self::BASE32_CHARS, $char);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) < 8) break;
            $result .= chr(bindec($byte));
        }

        return $result;
    }
}
