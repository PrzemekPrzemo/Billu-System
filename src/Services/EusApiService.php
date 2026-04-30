<?php

declare(strict_types=1);

namespace App\Services;

/**
 * e-Urząd Skarbowy HTTP client. Skeleton in PR-2 — only health-check
 * endpoints are wired so the office "Test connection" button works
 * end-to-end against the chosen environment (mock | test | prod).
 *
 * PR-3 fills in submitB() / getStatusB() / downloadUpoB() for JPK_V7M.
 * PR-4 fills in pollC() / fetchLetter() / submitReplyC() for KAS
 * correspondence.
 *
 * The XAdES-BES signing path delegates to KsefCertificateService::signXml()
 * directly — no need to clone 300 LOC of well-tested signing code.
 *
 * Environment routing:
 *   - 'mock' → DemoEusMockService is used instead of HTTP calls
 *   - 'test' → MF test sandbox URLs (config/eus.php)
 *   - 'prod' → MF production URLs
 */
class EusApiService
{
    private array $config;
    private EusLogger $logger;

    public function __construct(?array $config = null, ?EusLogger $logger = null)
    {
        $this->config = $config ?? self::loadConfig();
        $this->logger = $logger ?? new EusLogger();
    }

    /**
     * Resolve URL for the given bramka + environment.
     */
    public function urlFor(string $bramka, string $environment): string
    {
        $env = in_array($environment, ['mock', 'test', 'prod'], true) ? $environment : 'mock';
        $b   = strtoupper($bramka) === 'C' ? 'C' : 'B';
        $key = $env . '_' . $b;
        return (string) ($this->config['urls'][$key] ?? '');
    }

    /**
     * Cheap GET to verify the network path + cert is operational.
     * In 'mock' environment this is satisfied without a network call.
     *
     * @return array{ok:bool, http_status:int, message:string, duration_ms:int}
     */
    public function healthCheckB(string $environment = 'mock'): array
    {
        if ($environment === 'mock') {
            return DemoEusMockService::health('B');
        }
        return $this->doHealth($this->urlFor('B', $environment) . '/health');
    }

    public function healthCheckC(string $environment = 'mock'): array
    {
        if ($environment === 'mock') {
            return DemoEusMockService::health('C');
        }
        return $this->doHealth($this->urlFor('C', $environment) . '/health');
    }

    // ─── Bramka B — JPK submission ───────────────────────

    /**
     * Submit a JPK_V7M payload. Returns the e-US reference number on
     * success, throws RuntimeException on cert / network / 4xx errors.
     *
     * In 'mock' environment uses DemoEusMockService for predictable
     * test results. Real outbound (env=test|prod) is wired in a
     * follow-up commit when MF sandbox creds are provisioned —
     * stubbed for now to keep PR-3 reviewable.
     *
     * @return array{reference_no:string,received_at:string,message:string}
     */
    public function submitB(string $environment, string $period, string $signedXmlPath): array
    {
        if ($environment === 'mock') {
            $r = DemoEusMockService::submitJpk($period);
            return [
                'reference_no' => (string) $r['reference_no'],
                'received_at'  => (string) $r['received_at'],
                'message'      => (string) $r['message'],
            ];
        }
        // PR-3 follow-up: real cURL + multipart + Authorization Bearer.
        // Stub fails-closed so we never silently skip a submission.
        throw new \RuntimeException(
            "Real e-US Bramka B submission not yet implemented for env='{$environment}'. "
            . "Stay on 'mock' until follow-up commit lands."
        );
    }

    /**
     * Poll status for a previously submitted reference. The poller
     * supplies submittedAt so the mock can advance the timeline
     * deterministically.
     *
     * @return array{status:string,upo:?string,message:string}
     */
    public function getStatusB(string $environment, string $referenceNo, ?\DateTimeImmutable $submittedAt = null): array
    {
        if ($environment === 'mock') {
            $r = DemoEusMockService::statusForReference($referenceNo, $submittedAt);
            return [
                'status'  => (string) $r['status'],
                'upo'     => $r['upo'] !== null ? (string) $r['upo'] : null,
                'message' => (string) $r['message'],
            ];
        }
        throw new \RuntimeException(
            "Real e-US Bramka B status poll not yet implemented for env='{$environment}'."
        );
    }

    /**
     * In real envs this returns the UPO XML body. In mock the UPO is
     * already inlined in getStatusB() — caller may skip this step.
     */
    public function downloadUpoB(string $environment, string $referenceNo): string
    {
        if ($environment === 'mock') {
            // Mock UPO is delivered through getStatusB; this is just a
            // convenience that re-fetches.
            $r = DemoEusMockService::statusForReference($referenceNo);
            return (string) ($r['upo'] ?? '');
        }
        throw new \RuntimeException(
            "Real e-US Bramka B UPO download not yet implemented for env='{$environment}'."
        );
    }

    // ─── Bramka C — KAS correspondence ───────────────────

    /**
     * Poll for new incoming KAS letters for a given NIP. Mock is
     * deterministic by NIP last digit (see DemoEusMockService).
     *
     * @return array<int,array<string,mixed>> letter envelopes:
     *   [{reference_no, doc_kind, subject, urzad_name, received_at,
     *     requires_reply, reply_deadline, body}]
     */
    public function pollC(string $environment, string $nip): array
    {
        if ($environment === 'mock') {
            return DemoEusMockService::pollKasLetters($nip);
        }
        throw new \RuntimeException(
            "Real e-US Bramka C poll not yet implemented for env='{$environment}'."
        );
    }

    /**
     * Submit a reply to a KAS letter. Caller has already produced
     * the signed XML payload + reference number of the original
     * letter being replied to.
     *
     * @return array{reply_reference_no:string,status:string,message:string}
     */
    public function submitReplyC(string $environment, string $kasReferenceNo, string $signedXmlPath): array
    {
        if ($environment === 'mock') {
            $r = DemoEusMockService::submitReply($kasReferenceNo);
            return [
                'reply_reference_no' => (string) $r['reply_reference_no'],
                'status'             => (string) $r['status'],
                'message'            => (string) $r['message'],
            ];
        }
        throw new \RuntimeException(
            "Real e-US Bramka C reply not yet implemented for env='{$environment}'."
        );
    }

    /**
     * Internal: actual cURL hit. Logged through EusLogger so the full
     * roundtrip is captured for support purposes.
     *
     * @return array{ok:bool, http_status:int, message:string, duration_ms:int}
     */
    private function doHealth(string $url): array
    {
        if ($url === '' || strpos($url, 'http') !== 0) {
            return ['ok' => false, 'http_status' => 0, 'message' => 'Brak poprawnego URL dla wybranego środowiska.', 'duration_ms' => 0];
        }

        $start = microtime(true);
        $this->logger->logRequest('GET', $url);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: BiLLU/1.0 (eus health)'],
        ]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);

        $duration = (int) round((microtime(true) - $start) * 1000);
        $this->logger->logResponse('GET', $url, $code, $body, $duration / 1000);

        if ($err !== 0) {
            return [
                'ok'          => false,
                'http_status' => 0,
                'message'     => 'Błąd połączenia: ' . $errMsg,
                'duration_ms' => $duration,
            ];
        }
        $ok = $code >= 200 && $code < 300;
        return [
            'ok'          => $ok,
            'http_status' => $code,
            'message'     => $ok ? 'Połączenie OK' : "Serwer e-US zwrócił HTTP {$code}",
            'duration_ms' => $duration,
        ];
    }

    /** Loads URL list + timeouts from config/eus.php with sane defaults. */
    private static function loadConfig(): array
    {
        $path = __DIR__ . '/../../config/eus.php';
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                return $loaded;
            }
        }
        return [
            'urls' => [
                'mock_B' => 'mock://eus/B',
                'mock_C' => 'mock://eus/C',
                'test_B' => 'https://test-eus.mf.gov.pl/api/b',
                'test_C' => 'https://test-eus.mf.gov.pl/api/c',
                'prod_B' => 'https://eus.mf.gov.pl/api/b',
                'prod_C' => 'https://eus.mf.gov.pl/api/c',
            ],
            'timeout_sec'    => 30,
            'retry_attempts' => 2,
        ];
    }
}
