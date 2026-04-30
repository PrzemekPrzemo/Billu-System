<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Per-session debug logger for the e-Urząd Skarbowy integration.
 *
 * Twin of KsefLogger but writes to storage/logs/eus/{sessionId}.log
 * and masks the e-US-specific token field names. Sensitive headers
 * (Authorization, X-EUS-Token) and body fields (accessToken,
 * refreshToken, authenticationToken, samlAssertion) are truncated
 * to first 20 chars before persistence.
 */
class EusLogger
{
    private string $logDir;
    private string $sessionFile;
    private string $sessionId;
    private float  $sessionStart;

    /** @var array<string,bool> case-insensitive header names to mask */
    private const SENSITIVE_HEADERS = [
        'authorization' => true,
        'x-eus-token'   => true,
        'cookie'        => true,
        'set-cookie'    => true,
    ];

    /** @var array<string,bool> JSON body fields whose value is masked */
    private const SENSITIVE_BODY_FIELDS = [
        'accessToken'         => true,
        'refreshToken'        => true,
        'authenticationToken' => true,
        'samlAssertion'       => true,
        'samlArtifact'        => true,
        'token'               => true,
        'apiKey'              => true,
    ];

    public function __construct(?string $sessionId = null)
    {
        $this->logDir = __DIR__ . '/../../storage/logs/eus';
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0775, true);
        }

        $this->sessionId    = $sessionId ?? (date('Ymd_His') . '_' . substr(uniqid('', true), -6));
        $this->sessionFile  = $this->logDir . '/' . $this->sessionId . '.log';
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

    public function info(string $message, array $context = []): void  { $this->log('INFO',  $message, $context); }
    public function error(string $message, array $context = []): void { $this->log('ERROR', $message, $context); }
    public function debug(string $message, array $context = []): void { $this->log('DEBUG', $message, $context); }

    public function log(string $level, string $message, array $context = []): void
    {
        $elapsed   = round(microtime(true) - $this->sessionStart, 3);
        $timestamp = date('Y-m-d H:i:s');
        $line      = "[{$timestamp}] [{$elapsed}s] [{$level}] {$message}";

        if (!empty($context)) {
            $line .= "\n" . json_encode(
                $context,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
        $line .= "\n" . str_repeat('-', 80) . "\n";

        file_put_contents($this->sessionFile, $line, FILE_APPEND | LOCK_EX);
    }

    public function logRequest(string $method, string $url, ?array $body = null, array $headers = []): void
    {
        $this->log('REQUEST', "{$method} {$url}", [
            'headers' => $this->maskHeaders($headers),
            'body'    => $body !== null ? $this->maskBody($body) : null,
        ]);
    }

    public function logResponse(string $method, string $url, int $httpCode, string $responseBody, float $duration): void
    {
        $decoded = json_decode($responseBody, true);
        $body    = is_array($decoded) ? $this->maskBody($decoded) : $responseBody;

        $this->log('RESPONSE', "{$method} {$url} → HTTP {$httpCode} ({$duration}s)", [
            'http_code'   => $httpCode,
            'body'        => $body,
            'body_length' => strlen($responseBody),
        ]);
    }

    /**
     * @param array<int|string,string> $headers either ['Header: value', ...] OR ['Header' => 'value', ...]
     * @return array<int|string,string>
     */
    private function maskHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $key => $val) {
            if (is_int($key) && is_string($val)) {
                // 'Header: value' form
                if (strpos($val, ':') === false) {
                    $masked[] = $val;
                    continue;
                }
                [$name, $rest] = explode(':', $val, 2);
                $rest = ltrim($rest);
                if (isset(self::SENSITIVE_HEADERS[strtolower(trim($name))])) {
                    $masked[] = $name . ': ' . self::truncate($rest);
                } else {
                    $masked[] = $val;
                }
            } else {
                $masked[$key] = isset(self::SENSITIVE_HEADERS[strtolower((string) $key)])
                    ? self::truncate((string) $val)
                    : (string) $val;
            }
        }
        return $masked;
    }

    /** Recursively masks known sensitive fields in any nested array. */
    private function maskBody(array $body): array
    {
        foreach ($body as $k => $v) {
            if (is_string($k) && isset(self::SENSITIVE_BODY_FIELDS[$k]) && is_string($v)) {
                $body[$k] = self::truncate($v);
            } elseif (is_array($v)) {
                $body[$k] = $this->maskBody($v);
            }
        }
        return $body;
    }

    private static function truncate(string $value): string
    {
        if (strlen($value) <= 20) {
            return '[MASKED]';
        }
        return substr($value, 0, 20) . '...[MASKED]';
    }

    /**
     * Lists recent session files for the master dashboard. Newest first.
     * @return array<int,array{session_id:string,size:int,modified:int}>
     */
    public static function listSessions(int $limit = 50): array
    {
        $dir = __DIR__ . '/../../storage/logs/eus';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.log') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, $limit);

        $out = [];
        foreach ($files as $f) {
            $out[] = [
                'session_id' => basename($f, '.log'),
                'size'       => (int) filesize($f),
                'modified'   => (int) filemtime($f),
            ];
        }
        return $out;
    }

    /**
     * Removes session log files older than $daysToKeep.
     * Called from cron.php cleanup step.
     */
    public static function cleanup(int $daysToKeep = 90): int
    {
        $dir = __DIR__ . '/../../storage/logs/eus';
        if (!is_dir($dir)) {
            return 0;
        }
        $cutoff = time() - ($daysToKeep * 86400);
        $removed = 0;
        foreach (glob($dir . '/*.log') ?: [] as $f) {
            if (filemtime($f) < $cutoff) {
                @unlink($f);
                $removed++;
            }
        }
        return $removed;
    }
}
