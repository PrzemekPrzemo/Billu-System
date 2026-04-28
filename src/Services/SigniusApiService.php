<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Adapter for SIGNIUS Professional API.
 *
 * Endpoint paths are best-guesses based on typical e-signature REST APIs;
 * once the office team supplies their actual SIGNIUS docs, only the URI
 * fragments and the request shapes need adjustment — the public method
 * surface stays the same so the rest of the module doesn't churn.
 *
 * All methods throw \RuntimeException with the upstream HTTP status + body
 * snippet on failure; callers (ContractFormService, SigniusWebhookController)
 * decide how to surface that to the user / audit log.
 */
final class SigniusApiService
{
    private static ?Client $http = null;

    /**
     * Send a filled PDF to SIGNIUS for e-signature.
     *
     * @param array<int,array{role:string,label:string,email:string,order:int}> $signers
     * @param array<string,mixed> $metadata extra fields stored alongside the package (form_id, office_id …)
     * @return array{package_id:string,signing_urls:array<string,string>}
     */
    public static function createPackage(string $pdfPath, array $signers, array $metadata = []): array
    {
        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            throw new \RuntimeException('PDF missing or unreadable: ' . $pdfPath);
        }
        if (empty($signers)) {
            throw new \RuntimeException('SIGNIUS: at least one signer required');
        }

        $client = self::http();
        $multipart = [
            ['name' => 'document', 'contents' => fopen($pdfPath, 'rb'), 'filename' => basename($pdfPath)],
            ['name' => 'signers',  'contents' => json_encode(array_values($signers), JSON_UNESCAPED_UNICODE)],
            ['name' => 'metadata', 'contents' => json_encode($metadata,           JSON_UNESCAPED_UNICODE)],
            ['name' => 'flow',     'contents' => self::cfg()['default_signing_flow'] ?? 'sequential'],
        ];

        try {
            $response = $client->post('/packages', ['multipart' => $multipart]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('SIGNIUS createPackage failed: ' . $e->getMessage(), 0, $e);
        }
        $body = (string) $response->getBody();
        $parsed = json_decode($body, true);
        if (!is_array($parsed) || empty($parsed['package_id'])) {
            throw new \RuntimeException('SIGNIUS: invalid response body: ' . substr($body, 0, 200));
        }
        return [
            'package_id'   => (string) $parsed['package_id'],
            'signing_urls' => is_array($parsed['signing_urls'] ?? null) ? $parsed['signing_urls'] : [],
        ];
    }

    public static function getPackageStatus(string $packageId): string
    {
        try {
            $response = self::http()->get('/packages/' . rawurlencode($packageId));
        } catch (GuzzleException $e) {
            throw new \RuntimeException('SIGNIUS getPackageStatus failed: ' . $e->getMessage(), 0, $e);
        }
        $parsed = json_decode((string) $response->getBody(), true);
        return is_array($parsed) ? (string) ($parsed['status'] ?? 'unknown') : 'unknown';
    }

    public static function downloadSignedPdf(string $packageId, string $outputPath): bool
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        try {
            $response = self::http()->get('/packages/' . rawurlencode($packageId) . '/signed.pdf', [
                'sink' => $outputPath,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('SIGNIUS downloadSignedPdf failed: ' . $e->getMessage(), 0, $e);
        }
        @chmod($outputPath, 0640);
        return $response->getStatusCode() === 200 && filesize($outputPath) > 0;
    }

    /** HMAC-SHA256 verification of webhook calls. Tolerant to 'sha256=' prefix. */
    public static function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        $secret = (string) (self::cfg()['webhook_secret'] ?? '');
        if ($secret === '') {
            return false; // configuration error — never accept unsigned in prod
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        $given = trim($signatureHeader);
        if (str_starts_with($given, 'sha256=')) {
            $given = substr($given, 7);
        }
        return hash_equals($expected, $given);
    }

    // ─────────────────────────────────────────────

    private static function http(): Client
    {
        if (self::$http !== null) return self::$http;
        $cfg = self::cfg();
        self::$http = new Client([
            'base_uri'        => rtrim((string) ($cfg['base_url'] ?? ''), '/') . '/',
            'timeout'         => (int) ($cfg['http_timeout'] ?? 15),
            'connect_timeout' => (int) ($cfg['http_connect_timeout'] ?? 5),
            'headers'         => [
                'Authorization' => 'Bearer ' . (string) ($cfg['api_key'] ?? ''),
                'Accept'        => 'application/json',
                'User-Agent'    => 'BiLLU/Contracts',
            ],
            'http_errors' => true,
        ]);
        return self::$http;
    }

    private static function cfg(): array
    {
        $contracts = require dirname(__DIR__, 2) . '/config/contracts.php';
        return is_array($contracts['signius'] ?? null) ? $contracts['signius'] : [];
    }
}
