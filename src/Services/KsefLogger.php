<?php

namespace App\Services;

/**
 * KSeF API Debug Logger.
 *
 * Logs all KSeF API requests and responses to storage/logs/ksef/.
 * Each import session gets its own log file for easy debugging.
 */
class KsefLogger
{
    private string $logDir;
    private string $sessionFile;
    private string $sessionId;
    private float $sessionStart;

    public function __construct(?string $sessionId = null)
    {
        $this->logDir = __DIR__ . '/../../storage/logs/ksef';
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }

        $this->sessionId = $sessionId ?? date('Ymd_His') . '_' . substr(uniqid(), -6);
        $this->sessionFile = $this->logDir . '/' . $this->sessionId . '.log';
        $this->sessionStart = microtime(true);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getSessionFile(): string
    {
        return $this->sessionFile;
    }

    /**
     * Log a message with context.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $elapsed = round(microtime(true) - $this->sessionStart, 3);
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$elapsed}s] [{$level}] {$message}";

        if (!empty($context)) {
            $line .= "\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= "\n" . str_repeat('-', 80) . "\n";

        file_put_contents($this->sessionFile, $line, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Log an API request.
     */
    public function logRequest(string $method, string $url, ?array $data = null, array $headers = []): void
    {
        $safeHeaders = array_map(function ($h) {
            // Mask tokens in Authorization header (show first 20 chars)
            if (stripos($h, 'Authorization:') === 0) {
                $val = trim(substr($h, 14));
                if (strlen($val) > 30) {
                    return 'Authorization: ' . substr($val, 0, 30) . '...[MASKED]';
                }
            }
            return $h;
        }, $headers);

        $this->log('REQUEST', "{$method} {$url}", [
            'headers' => $safeHeaders,
            'body' => $data,
        ]);
    }

    /**
     * Log an API response.
     */
    public function logResponse(string $method, string $url, int $httpCode, string $responseBody, float $duration): void
    {
        $decoded = json_decode($responseBody, true);

        // Mask sensitive data in response
        $safeResponse = $decoded;
        if (is_array($safeResponse)) {
            foreach (['accessToken', 'refreshToken', 'authenticationToken'] as $key) {
                if (isset($safeResponse[$key]) && is_string($safeResponse[$key]) && strlen($safeResponse[$key]) > 20) {
                    $safeResponse[$key] = substr($safeResponse[$key], 0, 20) . '...[MASKED]';
                }
            }
        }

        $this->log('RESPONSE', "{$method} {$url} → HTTP {$httpCode} ({$duration}s)", [
            'http_code' => $httpCode,
            'body' => $safeResponse ?? $responseBody,
            'body_length' => strlen($responseBody),
        ]);
    }

    /**
     * Log the start of an import session.
     */
    public function logImportStart(string $nip, int $month, int $year, array $config = []): void
    {
        $this->info("=== KSeF Import Session Start ===", [
            'nip' => $nip,
            'period' => sprintf('%02d/%04d', $month, $year),
            'environment' => $config['env'] ?? '?',
            'base_url' => $config['base_url'] ?? '?',
            'has_client_token' => !empty($config['has_client_token']),
            'has_global_token' => !empty($config['has_global_token']),
            'php_version' => PHP_VERSION,
            'openssl_version' => OPENSSL_VERSION_TEXT,
            'curl_version' => curl_version()['version'] ?? '?',
        ]);
    }

    /**
     * Log import results.
     */
    public function logImportResult(array $result): void
    {
        $level = ($result['success'] ?? 0) > 0 ? 'INFO' : 'ERROR';
        $this->log($level, "=== Import Result ===", $result);
    }

    /**
     * List all log sessions (newest first).
     */
    public static function listSessions(int $limit = 50): array
    {
        $logDir = __DIR__ . '/../../storage/logs/ksef';
        if (!is_dir($logDir)) {
            return [];
        }

        $files = glob($logDir . '/*.log');
        if (!$files) {
            return [];
        }

        // Sort by modification time descending
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $sessions = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $name = basename($file, '.log');
            $size = filesize($file);
            $modified = date('Y-m-d H:i:s', filemtime($file));

            // Read first line to get summary
            $firstLine = '';
            $fh = fopen($file, 'r');
            if ($fh) {
                $firstLine = fgets($fh) ?: '';
                fclose($fh);
            }

            // Count errors
            $content = file_get_contents($file);
            $errorCount = substr_count($content, '[ERROR]');
            $requestCount = substr_count($content, '[REQUEST]');

            $sessions[] = [
                'id' => $name,
                'file' => $file,
                'size' => $size,
                'modified' => $modified,
                'first_line' => trim($firstLine),
                'error_count' => $errorCount,
                'request_count' => $requestCount,
            ];
        }

        return $sessions;
    }

    /**
     * Read a session log file.
     */
    public static function readSession(string $sessionId): ?string
    {
        $logDir = __DIR__ . '/../../storage/logs/ksef';
        // Sanitize sessionId to prevent path traversal
        $sessionId = basename($sessionId);
        $file = $logDir . '/' . $sessionId . '.log';

        if (!file_exists($file)) {
            return null;
        }

        return file_get_contents($file);
    }

    /**
     * Delete old log files (keep last N days).
     */
    public static function cleanup(int $daysToKeep = 30): int
    {
        $logDir = __DIR__ . '/../../storage/logs/ksef';
        if (!is_dir($logDir)) return 0;

        $cutoff = time() - ($daysToKeep * 86400);
        $deleted = 0;

        foreach (glob($logDir . '/*.log') as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
