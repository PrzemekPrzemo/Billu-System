<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\IssuedInvoice;
use App\Models\KsefConfig;
use App\Models\KsefOperationLog;
use App\Services\ImportService;
use App\Services\KsefCertificateService;
use App\Services\WhiteListService;

/**
 * KSeF API v2 Service - Krajowy System e-Faktur v4.0
 *
 * API docs: https://api.ksef.mf.gov.pl/docs/v2/index.html
 * Auth docs: https://github.com/CIRFMF/ksef-docs/blob/main/uwierzytelnianie.md
 * Cert docs: https://github.com/CIRFMF/ksef-docs/blob/main/certyfikaty-KSeF.md
 *
 * Environments (v2):
 * - test:       https://api-test.ksef.mf.gov.pl/v2
 * - demo:       https://api-demo.ksef.mf.gov.pl/v2
 * - production: https://api.ksef.mf.gov.pl/v2
 *
 * Authentication flows:
 *
 * A) Token-based:
 *    1. POST /auth/challenge → {challenge, timestampMs}
 *    2. Encrypt "{token}|{timestampMs}" with RSA-OAEP SHA-256
 *    3. POST /auth/ksef-token → {authenticationToken, referenceNumber}
 *    4. Poll GET /auth/{ref} until ready
 *    5. POST /auth/token/redeem → {accessToken, refreshToken}
 *
 * B) Certificate-based (XAdES) - qualified cert or KSeF cert:
 *    1. POST /auth/challenge → {challenge, timestampMs}
 *    2. Build AuthTokenRequest XML with challenge
 *    3. Sign XML with XAdES-BES using client certificate
 *    4. POST /auth/xades-signature → {authenticationToken, referenceNumber}
 *    5. Poll GET /auth/{ref} until ready (OCSP/CRL on production)
 *    6. POST /auth/token/redeem → {accessToken, refreshToken}
 *
 * C) KSeF Certificate Enrollment:
 *    1. Authenticate with qualified cert (flow B)
 *    2. GET /certificates/limits → check if enrollment allowed
 *    3. GET /certificates/enrollments/data → DN attributes
 *    4. Generate key pair + CSR locally
 *    5. POST /certificates/enrollments → submit CSR
 *    6. Poll GET /certificates/enrollments/{ref} → wait for issuance
 *    7. POST /certificates/retrieve → download certificate
 *
 * Operations:
 * - POST /invoices/query/metadata - query invoices (paginated)
 * - GET  /invoices/ksef/{ksefNumber} - download single invoice
 * - POST /permissions/query/personal/grants - check permissions
 * - GET  /auth/sessions - list active sessions
 * - DELETE /auth/sessions/current - revoke session
 * - POST /certificates/query - list certificates
 * - POST /certificates/{serial}/revoke - revoke certificate
 */
class KsefApiService
{
    /**
     * Base URLs per environment (include /v2 path prefix per OpenAPI spec).
     * @see https://api-test.ksef.mf.gov.pl/docs/v2/index.html
     */
    private const ENV_URLS = [
        'test'       => 'https://api-test.ksef.mf.gov.pl',
        'demo'       => 'https://api-demo.ksef.mf.gov.pl',
        'production' => 'https://api.ksef.mf.gov.pl',
    ];

    private string $baseUrl;
    private string $env;
    private string $nip;

    /** API path prefix: /v2 for new hosts, /api/v2 for deprecated hosts */
    private string $pathPrefix = '/v2';

    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?string $authenticationToken = null;

    /** Authentication method: 'certificate' or 'ksef_cert' */
    private string $authMethod = 'certificate';

    /** For qualified cert auth: encrypted PFX data from DB */
    private ?string $certPfxEncrypted = null;

    /** For qualified cert auth: PFX password (only in memory, never stored) */
    private ?string $certPfxPassword = null;

    /** For KSeF cert auth: encrypted private key PEM from DB */
    private ?string $ksefCertKeyEncrypted = null;

    /** For KSeF cert auth: certificate PEM (public, not encrypted) */
    private ?string $ksefCertPem = null;

    /** Client ID for operation logging */
    private ?int $clientId = null;

    /** Who is performing operations */
    private string $performerType = 'system';
    private int $performerId = 0;

    /** Cached public key for token encryption */
    private ?string $publicKeyPem = null;

    /** Last session error for better diagnostics */
    private string $lastSessionError = '';

    /** Debug logger */
    private ?KsefLogger $logger = null;

    /** Demo mode — bypasses real API checks */
    private bool $isDemo = false;

    /**
     * @param string|null $clientNip  NIP of the client whose invoices we fetch
     */
    public function __construct(?string $clientNip = null)
    {
        $this->env = Setting::get('ksef_api_env', 'test');

        // Resolve base URL from environment
        $storedUrl = Setting::get('ksef_api_url', '');
        if (!empty($storedUrl) && strpos($storedUrl, 'ksef') !== false) {
            // Strip path suffixes to get base URL
            $this->baseUrl = preg_replace('#/(api/)?v2/?$#', '', rtrim($storedUrl, '/'));

            // Detect path prefix from stored URL
            if (preg_match('#/api/v2/?$#', $storedUrl)) {
                $this->pathPrefix = '/api/v2';
            }
        } else {
            $this->baseUrl = self::ENV_URLS[$this->env] ?? self::ENV_URLS['test'];
        }

        // Allow override of path prefix from settings
        $configuredPrefix = Setting::get('ksef_api_path_prefix', '');
        if (!empty($configuredPrefix)) {
            $this->pathPrefix = $configuredPrefix;
        }

        $this->nip = $clientNip ?? Setting::get('ksef_nip', '');
    }

    public function isConfigured(): bool
    {
        if ($this->isDemo) {
            return true;
        }
        if ($this->authMethod === 'certificate') {
            return !empty($this->certPfxEncrypted) && !empty($this->nip);
        }
        if ($this->authMethod === 'ksef_cert') {
            return !empty($this->ksefCertKeyEncrypted) && !empty($this->ksefCertPem) && !empty($this->nip);
        }
        return false;
    }

    /**
     * Create instance for a specific client using new KsefConfig.
     */
    public static function forClient(array $client): self
    {
        try {
            $config = KsefConfig::findByClientId((int)$client['id']);
        } catch (\Exception $e) {
            // client_ksef_configs table may not exist if v3.0 migration not applied
            $config = null;
        }

        if ($config && $config['is_active'] && $config['auth_method'] !== 'none') {
            $instance = new self(null, $client['nip']);
            $instance->clientId = (int)$client['id'];

            // Use per-client environment if set
            if (!empty($config['ksef_environment'])) {
                $instance->env = $config['ksef_environment'];
                $instance->baseUrl = self::ENV_URLS[$instance->env] ?? self::ENV_URLS['test'];
            }

            // Use context NIP if different from client NIP
            if (!empty($config['ksef_context_nip'])) {
                $instance->nip = $config['ksef_context_nip'];
            }

            if ($config['auth_method'] === 'certificate') {
                $instance->authMethod = 'certificate';
                $instance->certPfxEncrypted = $config['cert_pfx_encrypted'];
            } elseif ($config['auth_method'] === 'ksef_cert') {
                $instance->authMethod = 'ksef_cert';
                $instance->ksefCertKeyEncrypted = $config['cert_ksef_private_key_encrypted'] ?? null;
                $instance->ksefCertPem = $config['cert_ksef_pem'] ?? null;
            }

            // Restore cached session tokens if still valid
            if (!empty($config['access_token']) && !empty($config['access_token_expires_at'])) {
                if (strtotime($config['access_token_expires_at']) > time()) {
                    $instance->accessToken = $config['access_token'];
                }
            }
            if (!empty($config['refresh_token']) && !empty($config['refresh_token_expires_at'])) {
                if (strtotime($config['refresh_token_expires_at']) > time()) {
                    $instance->refreshToken = $config['refresh_token'];
                }
            }

            return $instance;
        }

        // No config found — return unconfigured instance (or demo instance)
        $instance = new self($client['nip'] ?? null);
        if (!empty($client['is_demo'])) {
            $instance->isDemo = true;
            $instance->clientId = (int)$client['id'];
        }
        return $instance;
    }

    /**
     * Set certificate PFX password for certificate-based auth.
     * Password is only kept in memory, never stored.
     */
    public function setCertificatePassword(string $password): self
    {
        $this->certPfxPassword = $password;
        return $this;
    }

    /**
     * Set who is performing the operation (for audit logging).
     */
    public function setPerformer(string $type, int $id): self
    {
        $this->performerType = $type;
        $this->performerId = $id;
        return $this;
    }

    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }

    // ─── Authentication Flow (v2) ───────────────────────

    /**
     * Authenticate with KSeF and obtain accessToken.
     *
     * Full flow:
     * 1. POST /auth/challenge
     * 2. Encrypt token with RSA-OAEP
     * 3. POST /auth/ksef-token
     * 4. Poll GET /auth/{ref} until ready
     * 5. POST /auth/token/redeem → accessToken + refreshToken
     */
    /**
     * Start a debug logging session. Call before authenticate/import.
     */
    public function enableLogging(?string $sessionId = null): self
    {
        $this->logger = new KsefLogger($sessionId);
        return $this;
    }

    public function getLogger(): ?KsefLogger
    {
        return $this->logger;
    }

    public function authenticate(): bool
    {
        if ($this->isDemo) {
            return true;
        }
        if (!$this->isConfigured()) {
            $this->logMsg('error', 'KSeF not configured', [
                'auth_method' => $this->authMethod,
                'has_qualified_cert' => !empty($this->certPfxEncrypted),
                'has_ksef_cert' => !empty($this->ksefCertKeyEncrypted),
                'has_nip' => !empty($this->nip),
            ]);
            return false;
        }

        $startTime = microtime(true);

        try {
            if ($this->authMethod === 'certificate') {
                $ok = $this->authenticateWithCertificate();
            } elseif ($this->authMethod === 'ksef_cert') {
                $ok = $this->authenticateWithKsefCert();
            } else {
                $this->logMsg('error', 'No valid auth method configured. Only certificate auth is supported.');
                $ok = false;
            }

            // Log operation
            $this->logOperation('authenticate', $ok ? 'success' : 'failed', $startTime);

            // Cache tokens in DB for this client
            if ($ok && $this->clientId) {
                $this->cacheTokensInDb();
            }

            return $ok;
        } catch (\Exception $e) {
            $this->logMsg('error', 'Authentication exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->logOperation('authenticate', 'failed', $startTime, $e->getMessage());
            return false;
        }
    }

    /**
     * Certificate-based authentication (XAdES flow).
     *
     * Flow:
     * 1. POST /auth/challenge
     * 2. Build AuthTokenRequest XML
     * 3. Sign XML with XAdES using client PFX
     * 4. POST /auth/xades-signature
     * 5. Poll GET /auth/{ref}
     * 6. POST /auth/token/redeem
     */
    private function authenticateWithCertificate(): bool
    {
        if (!$this->certPfxEncrypted) {
            $this->logMsg('error', 'No certificate configured');
            return false;
        }

        // Decrypt the PFX certificate
        $this->logMsg('info', '[Cert Auth] Decrypting stored certificate...');
        try {
            $pfxData = KsefCertificateService::decryptCertificate($this->certPfxEncrypted);
        } catch (\Exception $e) {
            $this->logMsg('error', 'Cannot decrypt certificate: ' . $e->getMessage());
            return false;
        }

        // We need the PFX password to use the private key
        if (!$this->certPfxPassword) {
            $this->logMsg('error', 'Certificate password not provided');
            return false;
        }

        // Step 1: Get challenge
        $this->logMsg('info', 'Step 1/6: Getting challenge...');
        $challengeData = $this->getChallenge();
        if (!$challengeData) {
            $this->logMsg('error', 'Step 1 FAILED: No challenge returned');
            return false;
        }
        $challenge = $challengeData['challenge'];
        $this->logMsg('info', 'Step 1 OK: Challenge received');

        // Step 2: Build AuthTokenRequest XML
        $this->logMsg('info', 'Step 2/6: Building AuthTokenRequest XML...');
        $xml = KsefCertificateService::buildAuthTokenRequestXml(
            $challenge,
            $this->nip,
            'certificateSubject'
        );
        $this->logMsg('info', 'Step 2 OK: XML built');

        // Step 3: Sign XML with XAdES
        $this->logMsg('info', 'Step 3/6: Signing XML with XAdES...');
        try {
            $signedXml = KsefCertificateService::signXml($xml, $pfxData, $this->certPfxPassword);
        } catch (\Exception $e) {
            $this->logMsg('error', 'Step 3 FAILED: XAdES signing error: ' . $e->getMessage());
            return false;
        }
        $this->logMsg('info', 'Step 3 OK: XML signed', ['signed_length' => strlen($signedXml)]);

        // Step 4: Submit signed XML
        $this->logMsg('info', 'Step 4/6: Submitting XAdES signature...');
        $authResult = $this->submitXadesSignature($signedXml);
        if (!$authResult) {
            $this->logMsg('error', 'Step 4 FAILED: XAdES submission rejected');
            return false;
        }
        $this->authenticationToken = $authResult['authenticationToken'];
        $refNumber = $authResult['referenceNumber'];
        $this->logMsg('info', 'Step 4 OK: Auth token received', ['referenceNumber' => $refNumber]);

        // Step 5: Poll for auth status (certificate validation via OCSP may take longer)
        $this->logMsg('info', 'Step 5/6: Polling auth status (OCSP/CRL verification)...');
        if (!$this->waitForAuthReady($refNumber, 30)) { // More attempts for cert auth
            $this->logMsg('error', 'Step 5 FAILED: Auth polling timed out (OCSP/CRL may be slow)');
            return false;
        }
        $this->logMsg('info', 'Step 5 OK: Auth ready');

        // Step 6: Redeem tokens
        $this->logMsg('info', 'Step 6/6: Redeeming tokens...');
        $ok = $this->redeemTokens();
        if ($ok) {
            $this->logMsg('info', 'Step 6 OK: accessToken + refreshToken obtained');
        } else {
            $this->logMsg('error', 'Step 6 FAILED: Token redeem failed');
        }

        // Clear PFX data from memory
        $pfxData = str_repeat("\0", strlen($pfxData));
        unset($pfxData);

        return $ok;
    }

    /**
     * KSeF certificate-based authentication (PEM key + PEM cert).
     *
     * Uses the same XAdES flow as qualified certs but with PEM-based signing.
     * The private key is stored encrypted in DB, cert is public.
     */
    private function authenticateWithKsefCert(): bool
    {
        if (!$this->ksefCertKeyEncrypted || !$this->ksefCertPem) {
            $this->logMsg('error', 'No KSeF certificate configured');
            return false;
        }

        // Decrypt the private key
        $this->logMsg('info', '[KSeF Cert Auth] Decrypting stored private key...');
        try {
            $privateKeyPem = KsefCertificateService::decrypt($this->ksefCertKeyEncrypted);
        } catch (\Exception $e) {
            $this->logMsg('error', 'Cannot decrypt KSeF private key: ' . $e->getMessage());
            return false;
        }

        // Step 1: Get challenge
        $this->logMsg('info', 'Step 1/6: Getting challenge...');
        $challengeData = $this->getChallenge();
        if (!$challengeData) {
            $this->logMsg('error', 'Step 1 FAILED: No challenge returned');
            return false;
        }
        $challenge = $challengeData['challenge'];
        $this->logMsg('info', 'Step 1 OK: Challenge received');

        // Step 2: Build AuthTokenRequest XML
        $this->logMsg('info', 'Step 2/6: Building AuthTokenRequest XML...');
        $xml = KsefCertificateService::buildAuthTokenRequestXml(
            $challenge,
            $this->nip,
            'certificateSubject'
        );
        $this->logMsg('info', 'Step 2 OK: XML built');

        // Step 3: Sign XML with XAdES using PEM key + cert
        $this->logMsg('info', 'Step 3/6: Signing XML with XAdES (KSeF cert)...');
        try {
            $signedXml = KsefCertificateService::signXml($xml, $privateKeyPem, $this->ksefCertPem, true);
        } catch (\Exception $e) {
            $this->logMsg('error', 'Step 3 FAILED: XAdES signing error: ' . $e->getMessage());
            return false;
        }
        $this->logMsg('info', 'Step 3 OK: XML signed', ['signed_length' => strlen($signedXml)]);
        // Step 4: Submit signed XML
        $this->logMsg('info', 'Step 4/6: Submitting XAdES signature...');
        $authResult = $this->submitXadesSignature($signedXml);
        if (!$authResult) {
            $this->logMsg('error', 'Step 4 FAILED: XAdES submission rejected');
            return false;
        }
        $this->authenticationToken = $authResult['authenticationToken'];
        $refNumber = $authResult['referenceNumber'];
        $this->logMsg('info', 'Step 4 OK: Auth token received', ['referenceNumber' => $refNumber]);

        // Step 5: Poll for auth status
        $this->logMsg('info', 'Step 5/6: Polling auth status...');
        if (!$this->waitForAuthReady($refNumber, 30)) {
            $this->logMsg('error', 'Step 5 FAILED: Auth polling timed out');
            return false;
        }
        $this->logMsg('info', 'Step 5 OK: Auth ready');

        // Step 6: Redeem tokens
        $this->logMsg('info', 'Step 6/6: Redeeming tokens...');
        $ok = $this->redeemTokens();
        if ($ok) {
            $this->logMsg('info', 'Step 6 OK: accessToken + refreshToken obtained');
        } else {
            $this->logMsg('error', 'Step 6 FAILED: Token redeem failed');
        }

        // Clear private key from memory
        $privateKeyPem = str_repeat("\0", strlen($privateKeyPem));
        unset($privateKeyPem);

        return $ok;
    }

    // ─── KSeF Certificate Enrollment ──────────────────

    /**
     * Check certificate limits for the authenticated subject.
     * GET /certificates/limits
     */
    public function getCertificateLimits(): ?array
    {
        if (!$this->ensureAuthenticated()) return null;

        $startTime = microtime(true);
        try {
            $response = $this->apiRequestAuth('GET', $this->ep('certificates/limits'));
            $this->logOperation('cert_limits_check', 'success', $startTime);
            return $response;
        } catch (\Exception $e) {
            $this->logMsg('error', "getCertificateLimits error: " . $e->getMessage());
            $this->logOperation('cert_limits_check', 'failed', $startTime, $e->getMessage());
            return null;
        }
    }

    /**
     * Get enrollment data for CSR generation.
     * GET /certificates/enrollments/data
     *
     * Returns DN attributes that MUST be used in the CSR exactly as returned.
     * Requires prior XAdES authentication (not token auth).
     */
    public function getCertificateEnrollmentData(): ?array
    {
        if (!$this->ensureAuthenticated()) return null;

        $startTime = microtime(true);
        try {
            $response = $this->apiRequestAuth('GET', $this->ep('certificates/enrollments/data'));
            $this->logOperation('cert_enroll_start', 'success', $startTime);
            return $response;
        } catch (\Exception $e) {
            $this->logMsg('error', "getCertificateEnrollmentData error: " . $e->getMessage());
            $this->logOperation('cert_enroll_start', 'failed', $startTime, $e->getMessage());
            return null;
        }
    }

    /**
     * Submit certificate enrollment request (CSR).
     * POST /certificates/enrollments
     *
     * @param string $csrBase64 Base64-encoded DER CSR
     * @param string $certificateName Display name for the certificate
     * @param string $certificateType 'Authentication' or 'Offline'
     * @param string|null $validFrom Optional validity start date (ISO 8601)
     * @return array|null Response with referenceNumber for status tracking
     */
    public function submitCertificateEnrollment(
        string $csrBase64,
        string $certificateName,
        string $certificateType = 'Authentication',
        ?string $validFrom = null
    ): ?array {
        if (!$this->ensureAuthenticated()) return null;

        $startTime = microtime(true);
        try {
            $data = [
                'certificateName' => $certificateName,
                'certificateType' => $certificateType,
                'csr' => $csrBase64,
            ];
            if ($validFrom) {
                $data['validFrom'] = $validFrom;
            }

            $response = $this->apiRequestAuth('POST', $this->ep('certificates/enrollments'), $data);
            $this->logOperation('cert_enroll_start', 'success', $startTime);
            return $response;
        } catch (\Exception $e) {
            $this->logMsg('error', "submitCertificateEnrollment error: " . $e->getMessage());
            $this->logOperation('cert_enroll_start', 'failed', $startTime, $e->getMessage());
            return null;
        }
    }

    /**
     * Check certificate enrollment status.
     * GET /certificates/enrollments/{referenceNumber}
     */
    public function getCertificateEnrollmentStatus(string $referenceNumber): ?array
    {
        if (!$this->ensureAuthenticated()) return null;

        $startTime = microtime(true);
        try {
            $response = $this->apiRequestAuth('GET', $this->ep('certificates/enrollments/' . urlencode($referenceNumber)));
            $this->logOperation('cert_enroll_poll', 'success', $startTime);
            return $response;
        } catch (\Exception $e) {
            $this->logMsg('error', "getCertificateEnrollmentStatus error: " . $e->getMessage());
            $this->logOperation('cert_enroll_poll', 'failed', $startTime, $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve certificate content by serial numbers.
     * POST /certificates/retrieve
     */
    public function retrieveCertificates(array $serialNumbers): ?array
    {
        if (!$this->ensureAuthenticated()) return null;

        $startTime = microtime(true);
        try {
            $response = $this->apiRequestAuth('POST', $this->ep('certificates/retrieve'), [
                'certificateSerialNumbers' => $serialNumbers,
            ]);
            $this->logOperation('cert_retrieve', 'success', $startTime);
            return $response;
        } catch (\Exception $e) {
            $this->logMsg('error', "retrieveCertificates error: " . $e->getMessage());
            $this->logOperation('cert_retrieve', 'failed', $startTime, $e->getMessage());
            return null;
        }
    }

    /**
     * Query certificate metadata.
     * POST /certificates/query
     */
    public function queryCertificates(array $filters = []): ?array
    {
        if (!$this->ensureAuthenticated()) return null;

        try {
            return $this->apiRequestAuth('POST', $this->ep('certificates/query'), $filters);
        } catch (\Exception $e) {
            $this->logMsg('error', "queryCertificates error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Revoke a certificate.
     * POST /certificates/{certificateSerialNumber}/revoke
     */
    public function revokeCertificate(string $serialNumber, string $reason = 'Unspecified'): bool
    {
        if (!$this->ensureAuthenticated()) return false;

        $startTime = microtime(true);
        try {
            $this->apiRequestAuth('POST', $this->ep('certificates/' . urlencode($serialNumber) . '/revoke'), [
                'revocationReason' => $reason,
            ]);
            $this->logOperation('cert_revoke', 'success', $startTime);
            return true;
        } catch (\Exception $e) {
            $this->logMsg('error', "revokeCertificate error: " . $e->getMessage());
            $this->logOperation('cert_revoke', 'failed', $startTime, $e->getMessage());
            return false;
        }
    }

    /**
     * Full KSeF certificate enrollment flow.
     *
     * 1. Check limits
     * 2. Get enrollment data
     * 3. Generate key pair + CSR
     * 4. Submit enrollment
     * 5. Return reference number + encrypted private key for storage
     *
     * Caller must poll enrollment status and retrieve certificate separately.
     */
    public function enrollKsefCertificate(string $certName = 'BiLLU-Auth', string $keyType = 'ec'): ?array
    {
        // Step 1: Check limits
        $this->logMsg('info', '[Cert Enrollment] Step 1: Checking certificate limits...');
        $limits = $this->getCertificateLimits();
        if (!$limits) {
            $this->logMsg('error', 'Cannot check certificate limits');
            return null;
        }
        $this->logMsg('info', 'Certificate limits', $limits);

        // Step 2: Get enrollment data
        $this->logMsg('info', 'Step 2: Getting enrollment data...');
        $enrollmentData = $this->getCertificateEnrollmentData();
        if (!$enrollmentData) {
            $this->logMsg('error', 'Cannot get enrollment data (requires XAdES auth, not token)');
            return null;
        }
        $this->logMsg('info', 'Enrollment data received', [
            'commonName' => $enrollmentData['commonName'] ?? 'N/A',
            'organizationName' => $enrollmentData['organizationName'] ?? 'N/A',
        ]);

        // Step 3: Generate key pair
        $this->logMsg('info', 'Step 3: Generating ' . strtoupper($keyType) . ' key pair...');
        try {
            $keyPair = ($keyType === 'rsa')
                ? KsefCertificateService::generateKeyPairRsa()
                : KsefCertificateService::generateKeyPairEc();
        } catch (\Exception $e) {
            $this->logMsg('error', 'Key generation failed: ' . $e->getMessage());
            return null;
        }

        // Step 4: Generate CSR
        $this->logMsg('info', 'Step 4: Generating CSR...');
        try {
            $csrBase64 = KsefCertificateService::generateCsr($enrollmentData, $keyPair['privateKeyPem'], $keyType);
        } catch (\Exception $e) {
            $this->logMsg('error', 'CSR generation failed: ' . $e->getMessage());
            return null;
        }

        // Step 5: Submit enrollment
        $this->logMsg('info', 'Step 5: Submitting enrollment request...');
        $enrollResult = $this->submitCertificateEnrollment($csrBase64, $certName, 'Authentication');
        if (!$enrollResult) {
            $this->logMsg('error', 'Enrollment submission failed');
            return null;
        }

        $referenceNumber = self::extractString($enrollResult, 'referenceNumber') ?? '';
        $this->logMsg('info', 'Enrollment submitted', ['referenceNumber' => $referenceNumber]);

        // Encrypt private key for storage
        $encryptedKey = KsefCertificateService::encrypt($keyPair['privateKeyPem']);

        // Clear private key from memory
        $keyPair['privateKeyPem'] = str_repeat("\0", strlen($keyPair['privateKeyPem']));
        unset($keyPair);

        return [
            'referenceNumber' => $referenceNumber,
            'encryptedPrivateKey' => $encryptedKey,
            'enrollmentData' => $enrollmentData,
        ];
    }

    /**
     * Submit XAdES signed AuthTokenRequest.
     * POST /api/v2/auth/xades-signature
     */
    private function submitXadesSignature(string $signedXml): ?array
    {
        try {
            // This endpoint expects XML content, not JSON
            $url = $this->baseUrl . $this->ep('auth/xades-signature');
            $headers = [
                'Content-Type: application/xml',
                'Accept: application/json',
            ];

            if ($this->logger) {
                $this->logger->logRequest('POST', $url, ['xml_length' => strlen($signedXml)], $headers);
            }

            $startTime = microtime(true);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $signedXml,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'BiLLU/4.0',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $duration = round(microtime(true) - $startTime, 3);

            if ($this->logger) {
                $this->logger->logResponse('POST', $url, $httpCode, $response ?: '', $duration);
            }

            if ($error) {
                throw new \RuntimeException("cURL error: {$error}");
            }

            if ($httpCode >= 400) {
                $decoded = json_decode($response ?: '{}', true);
                $errorMsg = $decoded['exception']['exceptionDetailList'][0]['exceptionDescription']
                    ?? $decoded['message'] ?? $response;
                throw new \RuntimeException("HTTP {$httpCode}: {$errorMsg}");
            }

            $decoded = json_decode($response ?: '{}', true) ?: [];

            $authToken = self::extractString($decoded, 'authenticationToken');
            $refNumber = self::extractString($decoded, 'referenceNumber');

            if (!$authToken || !$refNumber) {
                $this->logMsg('error', 'xades-signature: missing fields', ['response' => $decoded]);
                return null;
            }

            return [
                'authenticationToken' => $authToken,
                'referenceNumber' => $refNumber,
            ];
        } catch (\Exception $e) {
            $this->logMsg('error', 'submitXadesSignature error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache obtained tokens in database for the client.
     */
    private function cacheTokensInDb(): void
    {
        if (!$this->clientId) return;

        try {
            // JWT typically expires in ~15 min, refresh in ~7 days
            $accessExpires = date('Y-m-d H:i:s', strtotime('+14 minutes'));
            $refreshExpires = date('Y-m-d H:i:s', strtotime('+7 days'));

            KsefConfig::updateTokens(
                $this->clientId,
                $this->accessToken,
                $accessExpires,
                $this->refreshToken,
                $refreshExpires
            );
        } catch (\Exception $e) {
            // Non-critical, just log
            $this->logMsg('debug', 'Failed to cache tokens: ' . $e->getMessage());
        }
    }

    /**
     * Step 1: Get authorization challenge.
     */
    private function getChallenge(): ?array
    {
        try {
            $response = $this->apiRequest('POST', $this->ep('auth/challenge'), [
                'contextIdentifier' => [
                    'type' => 'nip',
                    'value' => $this->nip,
                ],
            ]);

            $this->logMsg('debug', 'Challenge raw response', ['response' => $response]);

            $challenge = self::extractString($response, 'challenge');

            // Use timestampMs (Unix milliseconds) - required for token encryption
            // API returns both 'timestamp' (ISO date) and 'timestampMs' (milliseconds)
            $timestampMs = $response['timestampMs'] ?? null;
            if ($timestampMs === null) {
                // Fallback: convert ISO timestamp to milliseconds
                $tsIso = self::extractString($response, 'timestamp');
                if ($tsIso) {
                    $dt = new \DateTimeImmutable($tsIso);
                    $timestampMs = (int) ($dt->format('U') * 1000 + (int) $dt->format('v'));
                    $this->logMsg('info', 'Converted ISO timestamp to ms', ['iso' => $tsIso, 'ms' => $timestampMs]);
                }
            }

            if (is_array($timestampMs)) {
                $timestampMs = $timestampMs['value'] ?? $timestampMs[0] ?? null;
            }

            if (!$challenge || !$timestampMs) {
                $this->logMsg('error', 'Challenge: missing fields', ['response' => $response]);
                return null;
            }

            $timestampMs = (string) $timestampMs;

            return ['challenge' => $challenge, 'timestampMs' => $timestampMs];
        } catch (\Exception $e) {
            $this->logMsg('error', 'Challenge error: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Lightweight KSeF API health check.
     * Calls /auth/challenge with a short timeout to verify the API is reachable.
     *
     * @return array ['ok' => bool, 'error' => ?string, 'response_time_ms' => int]
     */
    public function checkConnection(): array
    {
        if ($this->isDemo) {
            return ['ok' => true, 'error' => null, 'response_time_ms' => rand(50, 150), 'demo' => true];
        }
        if (!$this->nip) {
            return ['ok' => false, 'error' => 'Brak NIP — KSeF nie skonfigurowany', 'response_time_ms' => 0];
        }

        $url = $this->baseUrl . $this->ep('auth/challenge');
        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'contextIdentifier' => ['type' => 'nip', 'value' => $this->nip],
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'BiLLU/4.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        if ($error) {
            return ['ok' => false, 'error' => 'Błąd połączenia: ' . $error, 'response_time_ms' => $durationMs];
        }

        if ($httpCode >= 500) {
            return ['ok' => false, 'error' => 'Serwer KSeF niedostępny (HTTP ' . $httpCode . ')', 'response_time_ms' => $durationMs];
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($response ?: '', true);
            $errMsg = $decoded['exception']['exceptionDetailList'][0]['exceptionDescription']
                ?? $decoded['message']
                ?? 'HTTP ' . $httpCode;
            return ['ok' => false, 'error' => 'Błąd KSeF: ' . $errMsg, 'response_time_ms' => $durationMs];
        }

        // 2xx response with challenge = API is working
        $decoded = json_decode($response ?: '', true);
        if (empty($decoded['challenge'])) {
            return ['ok' => false, 'error' => 'Nieprawidłowa odpowiedź KSeF (brak challenge)', 'response_time_ms' => $durationMs];
        }

        return ['ok' => true, 'error' => null, 'response_time_ms' => $durationMs];
    }

    /**
     * Fetch KSeF public key certificate by usage type.
     *
     * @param string $usage 'KsefTokenEncryption' for auth, 'SymmetricKeyEncryption' for session encryption
     */
    private function getPublicKeyByUsage(string $usage = 'KsefTokenEncryption'): mixed
    {
        // Cache only for the default (token encryption) usage
        if ($usage === 'KsefTokenEncryption' && $this->publicKeyPem) {
            return openssl_pkey_get_public($this->publicKeyPem);
        }

        try {
            $response = $this->apiRequest('GET', $this->ep('security/public-key-certificates'));
            $certificates = $response['certificates'] ?? $response ?? [];

            $this->logMsg('debug', 'Available certificates', [
                'count' => count($certificates),
                'usages' => array_map(fn($c) => $c['usage'] ?? [], $certificates),
            ]);

            // Find certificate by usage
            $certData = null;
            foreach ($certificates as $cert) {
                $certUsage = $cert['usage'] ?? [];
                if (in_array($usage, $certUsage)) {
                    $certData = $cert['certificate'] ?? null;
                    break;
                }
            }

            // Fallback: try any certificate if specific usage not found
            if (!$certData && !empty($certificates)) {
                $this->logMsg('info', "No certificate with usage '{$usage}', trying first available");
                $first = is_array($certificates[0] ?? null) ? $certificates[0] : null;
                $certData = $first['certificate'] ?? null;
            }

            if (!$certData) {
                error_log("KSeF: no {$usage} certificate found");
                return null;
            }

            // Convert DER Base64 to PEM
            $pem = "-----BEGIN CERTIFICATE-----\n"
                . chunk_split($certData, 64, "\n")
                . "-----END CERTIFICATE-----\n";

            if ($usage === 'KsefTokenEncryption') {
                $this->publicKeyPem = $pem;
            }

            $certResource = openssl_x509_read($pem);
            if (!$certResource) {
                error_log("KSeF: invalid certificate: " . openssl_error_string());
                return null;
            }

            $keyResource = openssl_pkey_get_public($certResource);
            if (!$keyResource) {
                error_log("KSeF: could not extract public key: " . openssl_error_string());
                return null;
            }

            return $keyResource;
        } catch (\Exception $e) {
            error_log("KSeF getPublicKeyByUsage({$usage}) error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Encrypt data with RSA-OAEP using SHA-256 (required by KSeF for SymmetricKeyEncryption).
     * PHP's openssl_public_encrypt() only supports SHA-1 for OAEP, so we use openssl CLI.
     */
    private function rsaOaepSha256Encrypt(string $data, $publicKeyResource): string|false
    {
        $keyDetails = openssl_pkey_get_details($publicKeyResource);
        if (!$keyDetails || empty($keyDetails['key'])) {
            $this->logMsg('error', 'Cannot extract public key PEM from resource');
            return false;
        }
        $pubKeyPem = $keyDetails['key'];

        $pubKeyFile = tempnam(sys_get_temp_dir(), 'ksef_pub_');
        $dataFile = tempnam(sys_get_temp_dir(), 'ksef_dat_');
        $encFile = tempnam(sys_get_temp_dir(), 'ksef_enc_');

        try {
            file_put_contents($pubKeyFile, $pubKeyPem);
            file_put_contents($dataFile, $data);

            $cmd = sprintf(
                'openssl pkeyutl -encrypt -pubin -inkey %s -in %s -out %s -pkeyopt rsa_padding_mode:oaep -pkeyopt rsa_oaep_md:sha256 -pkeyopt rsa_mgf1_md:sha256 2>&1',
                escapeshellarg($pubKeyFile),
                escapeshellarg($dataFile),
                escapeshellarg($encFile)
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                $this->logMsg('error', 'openssl pkeyutl RSA-OAEP SHA-256 failed', [
                    'exit_code' => $exitCode,
                    'output' => implode("\n", $output),
                ]);
                return false;
            }

            $encrypted = file_get_contents($encFile);
            if ($encrypted === false || $encrypted === '') {
                $this->logMsg('error', 'openssl pkeyutl produced empty output');
                return false;
            }

            return $encrypted;
        } finally {
            @unlink($pubKeyFile);
            @unlink($dataFile);
            @unlink($encFile);
        }
    }


    /**
     * Step 4: Poll auth status until ready (max 30 seconds).
     */
    private function waitForAuthReady(string $referenceNumber, int $maxAttempts = 15): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $response = $this->apiRequest('GET', $this->ep("auth/{$referenceNumber}"), null, [
                    'Authorization: Bearer ' . $this->authenticationToken,
                ]);

                $this->logMsg('debug', "Auth poll attempt {$i}", ['response' => $response]);

                // Status can be a nested object {"code": 200, "description": "..."} or a scalar
                $statusObj = $response['status'] ?? null;
                $statusCode = null;
                if (is_array($statusObj)) {
                    $statusCode = $statusObj['code'] ?? null;
                } elseif (is_int($statusObj) || is_string($statusObj)) {
                    $statusCode = (int)$statusObj;
                }
                if ($statusCode === null) {
                    $statusCode = (int)(self::extractString($response, 'processingCode') ?? 0);
                }

                $desc = self::extractString($response, 'processingDescription') ?? '';

                // 200 = auth completed successfully
                if ($statusCode === 200 || $desc === 'Completed') {
                    return true;
                }

                // Still processing
                usleep(500000); // 0.5s
            } catch (\Exception $e) {
                $this->logMsg('debug', "Auth poll attempt {$i} exception: " . $e->getMessage());
                usleep(500000);
            }
        }
        return false;
    }

    /**
     * Step 5: Redeem authenticationToken for accessToken + refreshToken.
     */
    private function redeemTokens(): bool
    {
        try {
            $response = $this->apiRequest('POST', $this->ep('auth/token/redeem'), null, [
                'Authorization: Bearer ' . $this->authenticationToken,
            ]);

            $this->logMsg('debug', 'Token redeem raw response', ['response' => $response]);

            $this->accessToken = self::extractString($response, 'accessToken');
            $this->refreshToken = self::extractString($response, 'refreshToken');

            if (!$this->accessToken) {
                $this->logMsg('error', 'Redeem: no accessToken', ['response' => $response]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logMsg('error', 'redeemTokens error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refresh accessToken using refreshToken.
     */
    private function refreshAccessToken(): bool
    {
        if (!$this->refreshToken) return false;

        try {
            $response = $this->apiRequest('POST', $this->ep('auth/token/refresh'), null, [
                'Authorization: Bearer ' . $this->refreshToken,
            ]);

            $this->accessToken = self::extractString($response, 'accessToken');
            return !empty($this->accessToken);
        } catch (\Exception $e) {
            $this->logMsg('error', 'refreshAccessToken error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure we have a valid accessToken (authenticate or refresh).
     */
    private function ensureAuthenticated(): bool
    {
        if ($this->accessToken) {
            return true;
        }
        if ($this->refreshToken && $this->refreshAccessToken()) {
            return true;
        }
        return $this->authenticate();
    }

    // ─── Invoice Fetching ───────────────────────────────

    /**
     * Fetch invoices for a given NIP and date range (v2 API).
     *
     * Endpoint: POST /api/v2/invoices/query/metadata
     * Docs: https://github.com/CIRFMF/ksef-docs/blob/main/pobieranie-faktur/pobieranie-faktur.md
     *
     * subjectType: Subject1 = seller, Subject2 = buyer, Subject3 = third party
     * dateRange.dateType: Issue, Invoicing, PermanentStorage
     *
     * Pagination: pageSize (10-250), pageOffset (0-based), sortOrder (Asc/Desc) as query params.
     * Max 10000 records per query - when isTruncated=true, narrow dateRange.
     * Max date range: 3 months.
     */
    public function fetchInvoices(string $buyerNip, string $dateFrom, string $dateTo, string $subjectType = 'Subject2'): array
    {
        // Use Europe/Warsaw timezone - KSeF is a Polish system
        $tz = new \DateTimeZone('Europe/Warsaw');
        $fromDt = new \DateTimeImmutable($dateFrom . ' 00:00:00', $tz);
        $toDt = new \DateTimeImmutable($dateTo . ' 23:59:59', $tz);
        $fromIso = $fromDt->format('Y-m-d\TH:i:sP');
        $toIso = $toDt->format('Y-m-d\TH:i:sP');

        $this->logMsg('info', 'Fetching invoices...', [
            'nip' => $buyerNip,
            'date_from' => $fromIso,
            'date_to' => $toIso,
            'subject_type' => $subjectType,
            'env' => $this->env,
            'base_url' => $this->baseUrl,
        ]);

        if (!$this->ensureAuthenticated()) {
            $this->logMsg('error', 'Authentication failed - cannot fetch invoices');
            return [];
        }

        try {
            $allInvoices = [];
            $pageOffset = 0;
            $pageSize = 100;
            $hasMore = true;
            $currentFrom = $fromIso;

            while ($hasMore) {
                $queryData = [
                    'subjectType' => $subjectType,
                    'dateRange' => [
                        'dateType' => 'PermanentStorage',
                        'from' => $currentFrom,
                        'to' => $toIso,
                    ],
                ];

                $this->logMsg('debug', "Query page {$pageOffset}", ['query' => $queryData]);

                $endpoint = $this->ep('invoices/query/metadata') . '?' . http_build_query([
                    'pageSize' => $pageSize,
                    'pageOffset' => $pageOffset,
                    'sortOrder' => 'Asc',
                ]);
                $response = $this->apiRequestAuth('POST', $endpoint, $queryData);

                $this->logMsg('debug', "Query page {$pageOffset} response", [
                    'response_keys' => array_keys($response),
                    'hasMore' => $response['hasMore'] ?? 'N/A',
                    'isTruncated' => $response['isTruncated'] ?? 'N/A',
                    'invoices_count' => isset($response['invoices']) ? count($response['invoices']) : 'missing',
                    'full_response_sample' => json_encode(array_slice($response, 0, 3)),
                ]);

                $invoices = $response['invoices'] ?? [];

                if (empty($invoices)) {
                    $this->logMsg('info', "Page {$pageOffset}: no invoices returned", [
                        'full_response' => $response,
                    ]);
                    $hasMore = false;
                    break;
                }

                $this->logMsg('info', "Page {$pageOffset}: " . count($invoices) . " invoices");

                foreach ($invoices as $inv) {
                    $allInvoices[] = $this->mapInvoiceHeader($inv, $buyerNip);
                }

                $responseHasMore = $response['hasMore'] ?? false;
                $isTruncated = $response['isTruncated'] ?? false;

                if (!$responseHasMore) {
                    $hasMore = false;
                } elseif ($isTruncated) {
                    // Max 10000 records per filter set reached - narrow date range
                    $lastInvoice = end($invoices);
                    $lastDate = $lastInvoice['permanentStorageDate'] ?? $lastInvoice['invoicingDate'] ?? null;
                    if ($lastDate) {
                        $currentFrom = $lastDate;
                        $pageOffset = 0;
                        $this->logMsg('info', "Truncated - narrowing from date to {$lastDate}");
                    } else {
                        $hasMore = false;
                    }
                } else {
                    $pageOffset++;
                }

                if ($pageOffset >= 100) {
                    $this->logMsg('info', 'Reached max page limit (100)');
                    $hasMore = false;
                }
            }

            $this->logMsg('info', "Fetch complete: " . count($allInvoices) . " invoices total (subjectType={$subjectType})");
            return $allInvoices;
        } catch (\Exception $e) {
            $this->logMsg('error', 'fetchInvoices exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            // If 401, try refresh and retry once
            if (strpos($e->getMessage(), '401') !== false && $this->refreshAccessToken()) {
                $this->logMsg('info', 'Retrying after 401 + token refresh...');
                return $this->fetchInvoices($buyerNip, $dateFrom, $dateTo, $subjectType);
            }
            error_log("KSeF fetchInvoices error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Map KSeF v2 invoice metadata to internal format.
     *
     * v2 response fields:
     * - ksefNumber, invoiceNumber, issueDate, invoicingDate
     * - seller: {nip, name}
     * - buyer: {identifier: {type, value}, name}
     * - netAmount, grossAmount, vatAmount, currency
     */
    private function mapInvoiceHeader(array $inv, string $fallbackBuyerNip): array
    {
        $sellerNip = $inv['seller']['nip'] ?? '';
        $sellerName = $inv['seller']['name'] ?? '';
        $buyerNip = $inv['buyer']['identifier']['value'] ?? $fallbackBuyerNip;
        $buyerName = $inv['buyer']['name'] ?? '';

        // Map KSeF invoiceType: "Vat" => "VAT", "Kor" => "KOR"
        $ksefType = $inv['invoiceType'] ?? 'Vat';
        $invoiceType = match (strtolower($ksefType)) {
            'kor' => 'KOR',
            'zal' => 'ZAL',
            'roz' => 'ROZ',
            'upd' => 'UPD',
            default => 'VAT',
        };

        return [
            'ksef_reference_number' => $inv['ksefNumber'] ?? '',
            'invoice_number'        => $inv['invoiceNumber'] ?? '',
            'issue_date'            => substr($inv['issueDate'] ?? $inv['invoicingDate'] ?? '', 0, 10),
            'seller_nip'            => $sellerNip,
            'seller_name'           => $sellerName,
            'buyer_nip'             => $buyerNip,
            'buyer_name'            => $buyerName,
            'net_amount'            => (float) ($inv['netAmount'] ?? 0),
            'vat_amount'            => (float) ($inv['vatAmount'] ?? 0),
            'gross_amount'          => (float) ($inv['grossAmount'] ?? 0),
            'currency'              => $inv['currency'] ?? 'PLN',
            'invoice_type'          => $invoiceType,
        ];
    }

    // ─── Batch Import ───────────────────────────────────

    /**
     * Import invoices from KSeF into a batch.
     */
    public function importInvoicesToBatch(
        int $clientId,
        string $buyerNip,
        int $month,
        int $year,
        int $importedById,
        string $importedByType = 'admin',
        ?int $officeId = null
    ): array {
        // Always enable logging for imports
        if (!$this->logger) {
            $this->enableLogging();
        }

        $this->logger->logImportStart($buyerNip, $month, $year, [
            'env' => $this->env,
            'base_url' => $this->baseUrl,
            'has_certificate' => !empty($this->certPfxEncrypted) || !empty($this->ksefCertKeyEncrypted),
            'client_id' => $clientId,
            'imported_by' => "{$importedByType}:{$importedById}",
        ]);

        $result = ['success' => 0, 'errors' => [], 'total' => 0, 'skipped' => 0, 'log_session' => $this->logger->getSessionId()];

        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $dateTo = sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));

        // Fetch only as buyer (Subject2) — cost invoices for verification
        // Subject1 (seller) invoices are sales invoices and should NOT go to verification
        $invoices = $this->fetchInvoices($buyerNip, $dateFrom, $dateTo, 'Subject2');

        // Filter out invoices that were issued by this client (exist in issued_invoices)
        $beforeFilter = count($invoices);
        $issuedRefs = IssuedInvoice::getKsefReferences($clientId);
        $invoices = array_values(array_filter($invoices, function ($inv) use ($issuedRefs) {
            $ref = $inv['ksef_reference_number'] ?? '';
            return empty($ref) || !in_array($ref, $issuedRefs);
        }));

        $this->logMsg('info', 'Fetched cost invoices (Subject2)', [
            'fetched' => $beforeFilter,
            'after_filter' => count($invoices),
            'own_invoices_excluded' => $beforeFilter - count($invoices),
        ]);

        $result['total'] = count($invoices);

        if (empty($invoices)) {
            $this->logMsg('info', 'No cost invoices (Subject2) returned from KSeF API for this period');
            $this->logger->logImportResult($result);
            return $result;
        }

        $deadlineDate = ImportService::calculateDeadlineDate($month, $year);

        $batch = InvoiceBatch::findByClientAndPeriod($clientId, $month, $year);
        if ($batch && $batch['is_finalized']) {
            // Reopen finalized batch — new invoices may appear in KSeF after finalization
            InvoiceBatch::reopen($batch['id']);
            $this->logMsg('info', 'Reopened finalized batch for new invoices', ['batch_id' => $batch['id']]);
        }

        if (!$batch) {
            // Ensure imported_by_type is valid for the ENUM column
            $safeImportedByType = in_array($importedByType, ['admin', 'office', 'client']) ? $importedByType : 'admin';
            $this->logMsg('info', 'Creating new batch', [
                'client_id' => $clientId,
                'period' => "{$month}/{$year}",
                'imported_by' => "{$safeImportedByType}:{$importedById}",
            ]);
            $batchId = InvoiceBatch::create([
                'client_id'             => $clientId,
                'office_id'             => $officeId,
                'period_month'          => $month,
                'period_year'           => $year,
                'imported_by_type'      => $safeImportedByType,
                'imported_by_id'        => $importedById,
                'verification_deadline' => $deadlineDate,
                'source'                => 'ksef_api',
            ]);
            $this->logMsg('info', 'Batch created', ['batch_id' => $batchId]);
        } else {
            $batchId = $batch['id'];
            $this->logMsg('info', 'Using existing batch', ['batch_id' => $batchId]);
        }

        foreach ($invoices as $inv) {
            try {
                if (!empty($inv['ksef_reference_number'])) {
                    $existing = Invoice::findByKsefReference($inv['ksef_reference_number']);
                    if ($existing) {
                        $result['skipped']++;
                        continue;
                    }
                }

                $invoiceData = [
                    'batch_id'              => $batchId,
                    'client_id'             => $clientId,
                    'seller_nip'            => $inv['seller_nip'],
                    'seller_name'           => $inv['seller_name'],
                    'buyer_nip'             => $inv['buyer_nip'],
                    'buyer_name'            => $inv['buyer_name'],
                    'invoice_number'        => $inv['invoice_number'],
                    'issue_date'            => $inv['issue_date'],
                    'currency'              => $inv['currency'],
                    'net_amount'            => $inv['net_amount'],
                    'vat_amount'            => $inv['vat_amount'],
                    'gross_amount'          => $inv['gross_amount'],
                    'ksef_reference_number' => $inv['ksef_reference_number'],
                    'invoice_type'          => $inv['invoice_type'] ?? 'VAT',
                ];

                // Try to download full invoice XML for enriched data
                if (!empty($inv['ksef_reference_number'])) {
                    try {
                        $xml = $this->downloadInvoiceRaw($inv['ksef_reference_number']);
                        if ($xml) {
                            $invoiceData['ksef_xml'] = $xml;
                            $parsed = self::parseKsefFaXml($xml);

                            if (!empty($parsed['line_items'])) {
                                $invoiceData['line_items'] = json_encode($parsed['line_items']);
                            }
                            if (!empty($parsed['vat_rates'])) {
                                $invoiceData['vat_details'] = json_encode($parsed['vat_rates']);
                            }
                            if (!empty($parsed['invoice']['sale_date_from'])) {
                                $invoiceData['sale_date'] = $parsed['invoice']['sale_date_from'];
                            }
                            if (!empty($parsed['payment']['due_date'])) {
                                $invoiceData['payment_due_date'] = $parsed['payment']['due_date'];
                            }
                            if (!empty($parsed['payment']['form_name'])) {
                                $invoiceData['payment_method_detected'] = $parsed['payment']['form_name'];
                            }
                            if (!empty($parsed['seller']['address_l1'])) {
                                $addr = $parsed['seller']['address_l1'];
                                if (!empty($parsed['seller']['address_l2'])) $addr .= ', ' . $parsed['seller']['address_l2'];
                                $invoiceData['seller_address'] = $addr;
                            }
                            if (!empty($parsed['buyer']['address_l1'])) {
                                $addr = $parsed['buyer']['address_l1'];
                                if (!empty($parsed['buyer']['address_l2'])) $addr .= ', ' . $parsed['buyer']['address_l2'];
                                $invoiceData['buyer_address'] = $addr;
                            }
                            if (!empty($parsed['buyer']['name'])) {
                                $invoiceData['buyer_name'] = $parsed['buyer']['name'];
                            }

                            // Exchange rate from XML (KursWalutyZ)
                            if (!empty($parsed['invoice']['exchange_rate'])) {
                                $invoiceData['exchange_rate'] = (float) $parsed['invoice']['exchange_rate'];
                            } elseif (($invoiceData['currency'] ?? 'PLN') !== 'PLN') {
                                // Auto-fetch from NBP if not in XML
                                // Per art. 31a VAT: use the last business day before the issue date
                                $rateRefDate = $invoiceData['issue_date'] ?? $invoiceData['sale_date'] ?? '';
                                if ($rateRefDate) {
                                    $nbpRate = NbpExchangeRateService::getRate($invoiceData['currency'], $rateRefDate);
                                    if ($nbpRate) {
                                        $invoiceData['exchange_rate'] = round($nbpRate['rate'], 6);
                                        $invoiceData['exchange_rate_date'] = $nbpRate['date'];
                                        $invoiceData['exchange_rate_table'] = $nbpRate['table'];
                                    }
                                }
                            }

                            // Correction data from XML
                            if (!empty($parsed['correction'])) {
                                $corr = $parsed['correction'];
                                if (!empty($corr['corrected_invoice_number'])) {
                                    $invoiceData['corrected_invoice_number'] = $corr['corrected_invoice_number'];
                                }
                                if (!empty($corr['corrected_invoice_date'])) {
                                    $invoiceData['corrected_invoice_date'] = $corr['corrected_invoice_date'];
                                }
                                if (!empty($corr['corrected_ksef_number'])) {
                                    $invoiceData['corrected_ksef_number'] = $corr['corrected_ksef_number'];
                                }
                                if (!empty($corr['correction_reason'])) {
                                    $invoiceData['correction_reason'] = $corr['correction_reason'];
                                }
                            }

                            // Invoice type from XML (more reliable than metadata)
                            if (!empty($parsed['invoice']['invoice_type'])) {
                                $xmlType = strtoupper($parsed['invoice']['invoice_type']);
                                if ($xmlType === 'KOR') {
                                    $invoiceData['invoice_type'] = 'KOR';
                                }
                            }
                        }
                    } catch (\Exception $xmlErr) {
                        $this->logMsg('warning', "Could not download XML for {$inv['invoice_number']}: " . $xmlErr->getMessage());
                    }
                }

                // VAT whitelist verification at import time
                if (!empty($parsed) && !empty($parsed['payment']['bank_account']) && !empty($invoiceData['seller_nip'])) {
                    $formCode = $parsed['payment']['form_code'] ?? '';
                    if ($formCode === '' || $formCode === '6') {
                        try {
                            $wlResult = WhiteListService::verifyNipBankAccount($invoiceData['seller_nip'], $parsed['payment']['bank_account']);
                            if (!$wlResult['verified']) {
                                $invoiceData['whitelist_failed'] = 1;
                            }
                        } catch (\Exception $wlErr) {
                            $this->logMsg('warning', "Whitelist check failed for {$inv['invoice_number']}: " . $wlErr->getMessage());
                        }
                    }
                }

                Invoice::create($invoiceData);
                $result['success']++;
            } catch (\Exception $e) {
                $errMsg = "Invoice {$inv['invoice_number']}: " . $e->getMessage();
                $result['errors'][] = $errMsg;
                $this->logMsg('error', 'Failed to save invoice', [
                    'invoice_number' => $inv['invoice_number'],
                    'ksef_ref' => $inv['ksef_reference_number'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->logImportResult($result);
        return $result;
    }

    // ─── Invoice Operations ────────────────────────────

    /**
     * Download a single invoice by KSeF number.
     * GET /api/v2/invoices/ksef/{ksefNumber}
     */
    public function downloadInvoice(string $ksefNumber): ?string
    {
        if (!$this->ensureAuthenticated()) return null;

        $startTime = microtime(true);
        try {
            $response = $this->apiRequestAuth('GET', $this->ep('invoices/ksef/' . urlencode($ksefNumber)));
            $this->logOperation('invoice_download', 'success', $startTime);
            // Response is the invoice XML content
            return $response['content'] ?? json_encode($response);
        } catch (\Exception $e) {
            $this->logMsg('error', "downloadInvoice error: " . $e->getMessage());
            $this->logOperation('invoice_download', 'failed', $startTime, $e->getMessage());
            return null;
        }
    }

    /**
     * Download raw invoice XML from KSeF.
     * Uses Accept: application/octet-stream to get raw XML content.
     */
    public function downloadInvoiceRaw(string $ksefNumber): ?string
    {
        if (!$this->ensureAuthenticated()) return null;

        $startTime = microtime(true);
        try {
            $url = $this->baseUrl . $this->ep('invoices/ksef/' . urlencode($ksefNumber));
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/octet-stream',
                    'Authorization: Bearer ' . $this->accessToken,
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'BiLLU/4.0',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \RuntimeException("cURL error: {$error}");
            }
            if ($httpCode >= 400) {
                throw new \RuntimeException("HTTP {$httpCode}");
            }

            $this->logOperation('invoice_download_raw', 'success', $startTime);

            // Response may be raw XML or JSON-wrapped
            if ($response && str_starts_with(trim($response), '<?xml')) {
                return $response;
            }
            // Try JSON decode — KSeF might return {content: "..."} or {invoiceBody: "..."}
            $decoded = json_decode($response, true);
            if ($decoded) {
                $xml = $decoded['content'] ?? $decoded['invoiceBody'] ?? $decoded['body'] ?? null;
                if ($xml && str_starts_with(trim($xml), '<?xml')) {
                    return $xml;
                }
                // Content might be base64 encoded
                if ($xml) {
                    $tryDecode = base64_decode($xml, true);
                    if ($tryDecode && str_starts_with(trim($tryDecode), '<?xml')) {
                        return $tryDecode;
                    }
                }
            }

            // Return whatever we got
            return $response ?: null;
        } catch (\Exception $e) {
            $this->logMsg('error', "downloadInvoiceRaw error: " . $e->getMessage());
            $this->logOperation('invoice_download_raw', 'failed', $startTime, $e->getMessage());
            return null;
        }
    }

    /**
     * Parse KSeF FA(3) XML into structured data.
     */
    public static function parseKsefFaXml(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (!$doc->loadXML($xml)) {
            return ['error' => 'Invalid XML'];
        }

        // Detect namespace
        $root = $doc->documentElement;
        $ns = $root->namespaceURI ?: '';

        $xpath = new \DOMXPath($doc);
        if ($ns) {
            $xpath->registerNamespace('fa', $ns);
            $p = 'fa:';
        } else {
            $p = '';
        }

        $val = function (string $path) use ($xpath, $root) {
            $nodes = $xpath->query($path, $root);
            return $nodes && $nodes->length > 0 ? trim($nodes->item(0)->textContent) : '';
        };

        // Header
        $result = [
            'form_code' => $val(".//{$p}KodFormularza"),
            'form_variant' => $val(".//{$p}WariantFormularza"),
            'creation_date' => $val(".//{$p}DataWytworzeniaFa"),
            'system_info' => $val(".//{$p}SystemInfo"),
        ];

        // Seller (Podmiot1)
        $result['seller'] = [
            'nip' => $val(".//{$p}Podmiot1/{$p}DaneIdentyfikacyjne/{$p}NIP")
                ?: $val(".//{$p}Podmiot1/{$p}PrefiksPodat662/{$p}NIP"),
            'name' => $val(".//{$p}Podmiot1/{$p}DaneIdentyfikacyjne/{$p}Nazwa")
                ?: $val(".//{$p}Podmiot1/{$p}DaneIdentyfikacyjne/{$p}PelnaNazwa"),
            'trade_name' => $val(".//{$p}Podmiot1/{$p}DaneIdentyfikacyjne/{$p}NazwaHandlowa"),
            'country' => $val(".//{$p}Podmiot1/{$p}Adres/{$p}KodKraju"),
            'address_l1' => $val(".//{$p}Podmiot1/{$p}Adres/{$p}AdresL1"),
            'address_l2' => $val(".//{$p}Podmiot1/{$p}Adres/{$p}AdresL2"),
            'email' => $val(".//{$p}Podmiot1/{$p}DaneKontaktowe/{$p}Email"),
            'phone' => $val(".//{$p}Podmiot1/{$p}DaneKontaktowe/{$p}Telefon"),
        ];

        // Buyer (Podmiot2)
        $result['buyer'] = [
            'nip' => $val(".//{$p}Podmiot2/{$p}DaneIdentyfikacyjne/{$p}NIP"),
            'name' => $val(".//{$p}Podmiot2/{$p}DaneIdentyfikacyjne/{$p}Nazwa")
                ?: $val(".//{$p}Podmiot2/{$p}DaneIdentyfikacyjne/{$p}PelnaNazwa"),
            'country' => $val(".//{$p}Podmiot2/{$p}Adres/{$p}KodKraju"),
            'address_l1' => $val(".//{$p}Podmiot2/{$p}Adres/{$p}AdresL1"),
            'address_l2' => $val(".//{$p}Podmiot2/{$p}Adres/{$p}AdresL2"),
        ];

        // Invoice data (Fa)
        $fa = $xpath->query(".//{$p}Fa", $root);
        $faNode = $fa && $fa->length > 0 ? $fa->item(0) : $root;
        $fv = function (string $path) use ($xpath, $faNode) {
            $nodes = $xpath->query($path, $faNode);
            return $nodes && $nodes->length > 0 ? trim($nodes->item(0)->textContent) : '';
        };

        $result['invoice'] = [
            'currency' => $fv(".//{$p}KodWaluty") ?: 'PLN',
            'exchange_rate' => $fv(".//{$p}KursWalutyZ") ?: null,
            'issue_date' => $fv(".//{$p}P_1"),
            'issue_place' => $fv(".//{$p}P_1M"),
            'invoice_number' => $fv(".//{$p}P_2"),
            'sale_date_from' => $fv(".//{$p}P_6"),
            'sale_date_to' => $fv(".//{$p}P_6_Do"),
            'invoice_type' => $fv(".//{$p}RodzajFaktury"),
            // Amounts
            'net_amount' => $fv(".//{$p}P_13_1") ?: $fv(".//{$p}P_13_2") ?: $fv(".//{$p}P_13_3"),
            'vat_amount' => $fv(".//{$p}P_14_1") ?: $fv(".//{$p}P_14_2") ?: $fv(".//{$p}P_14_3"),
            'gross_amount' => $fv(".//{$p}P_15"),
            'amount_to_pay' => $fv(".//{$p}P_15ZT") ?: $fv(".//{$p}KwotaDoZaplaty"),
        ];

        // VAT rates breakdown
        $result['vat_rates'] = [];
        // P_13_1/P_14_1 = 23%, P_13_2/P_14_2 = 8%, P_13_3/P_14_3 = 5%, etc.
        $rates = [
            ['rate' => '23%', 'net' => $fv(".//{$p}P_13_1"), 'vat' => $fv(".//{$p}P_14_1")],
            ['rate' => '8%', 'net' => $fv(".//{$p}P_13_2"), 'vat' => $fv(".//{$p}P_14_2")],
            ['rate' => '5%', 'net' => $fv(".//{$p}P_13_3"), 'vat' => $fv(".//{$p}P_14_3")],
            ['rate' => '0%', 'net' => $fv(".//{$p}P_13_6_1")],
            ['rate' => 'zw.', 'net' => $fv(".//{$p}P_13_7")],
            ['rate' => 'np.', 'net' => $fv(".//{$p}P_13_11")],
        ];
        foreach ($rates as $r) {
            if (!empty($r['net'])) {
                $result['vat_rates'][] = $r;
            }
        }

        // Line items (FaWiersz)
        $result['line_items'] = [];
        $rows = $xpath->query(".//{$p}FaWiersz", $faNode);
        if ($rows) {
            foreach ($rows as $row) {
                $rv = function (string $tag) use ($xpath, $row, $p) {
                    $n = $xpath->query(".//{$p}{$tag}", $row);
                    return $n && $n->length > 0 ? trim($n->item(0)->textContent) : '';
                };
                $result['line_items'][] = [
                    'lp' => $rv('NrWierszaFa'),
                    'name' => $rv('P_7'),
                    'unit' => $rv('P_8A'),
                    'quantity' => $rv('P_8B'),
                    'unit_price_net' => $rv('P_9A'),
                    'unit_price_gross' => $rv('P_9B'),
                    'discount' => $rv('P_10'),
                    'net_value' => $rv('P_11') ?: $rv('P_11A'),
                    'vat_rate' => $rv('P_12'),
                    'gross_value' => $rv('P_11Vat'),
                    'gtu' => $rv('GTU'),
                    'pkwiu' => $rv('PKWiU'),
                    'cn' => $rv('CN'),
                ];
            }
        }

        // Payment (Platnosc)
        $paymentForms = ['1' => 'gotówka', '2' => 'karta', '3' => 'bon', '4' => 'czek',
            '5' => 'kredyt', '6' => 'przelew', '7' => 'mobilna'];
        $formaPl = $fv(".//{$p}Platnosc/{$p}FormaPlatnosci")
            ?: $fv(".//{$p}FormaPlatnosci");
        $result['payment'] = [
            'due_date' => $fv(".//{$p}Platnosc/{$p}TerminPlatnosci/{$p}Termin")
                ?: $fv(".//{$p}TerminPlatnosci/{$p}Termin"),
            'form_code' => $formaPl,
            'form_name' => $paymentForms[$formaPl] ?? $formaPl,
            'bank_account' => $fv(".//{$p}Platnosc/{$p}RachunekBankowy/{$p}NrRB")
                ?: $fv(".//{$p}RachunekBankowy/{$p}NrRB"),
            'bank_name' => $fv(".//{$p}Platnosc/{$p}RachunekBankowy/{$p}NazwaBanku")
                ?: $fv(".//{$p}RachunekBankowy/{$p}NazwaBanku"),
            'swift' => $fv(".//{$p}Platnosc/{$p}RachunekBankowy/{$p}SWIFT")
                ?: $fv(".//{$p}RachunekBankowy/{$p}SWIFT"),
            'description' => $fv(".//{$p}Platnosc/{$p}OpisPlatnosci")
                ?: $fv(".//{$p}OpisPlatnosci"),
        ];

        // Annotations (Adnotacje)
        $result['annotations'] = [
            'self_invoicing' => $fv(".//{$p}Adnotacje/{$p}P_16") === '1',
            'reverse_charge' => $fv(".//{$p}Adnotacje/{$p}P_17") === '1',
            'split_payment' => $fv(".//{$p}Adnotacje/{$p}P_18A") === '1',
            'margin' => $fv(".//{$p}Adnotacje/{$p}P_19A") === '1' || $fv(".//{$p}Adnotacje/{$p}P_19B") === '1',
        ];

        // Correction data (DaneFaKorygowanej)
        $result['correction'] = [
            'corrected_invoice_number' => $fv(".//{$p}DaneFaKorygowanej/{$p}NrFaKorygowanej"),
            'corrected_invoice_date' => $fv(".//{$p}DaneFaKorygowanej/{$p}DataWystFaKorygowanej"),
            'corrected_ksef_number' => $fv(".//{$p}DaneFaKorygowanej/{$p}NrKSeFFaKorygowanej")
                ?: $fv(".//{$p}DaneFaKorygowanej/{$p}NrKSeF"),
            'correction_type' => $fv(".//{$p}TypKorekty"),
            'correction_reason' => $fv(".//{$p}PrzyczynaKorekty")
                ?: $fv(".//{$p}DaneFaKorygowanej/{$p}PrzyczynaKorekty"),
        ];

        // Mark line items with StanPrzed flag (original items before correction)
        foreach ($result['line_items'] as &$item) {
            $item['stan_przed'] = false;
        }
        unset($item);
        $rows = $xpath->query(".//{$p}FaWiersz", $faNode);
        if ($rows) {
            $idx = 0;
            foreach ($rows as $row) {
                $stanPrzed = $xpath->query(".//{$p}StanPrzed", $row);
                if ($stanPrzed && $stanPrzed->length > 0 && trim($stanPrzed->item(0)->textContent) === '1') {
                    if (isset($result['line_items'][$idx])) {
                        $result['line_items'][$idx]['stan_przed'] = true;
                    }
                }
                $idx++;
            }
        }

        // Notes (Uwagi)
        $result['notes'] = $fv(".//{$p}Fa/{$p}Uwagi") ?: $fv(".//{$p}Uwagi");

        // Additional info
        $result['additional_info'] = $fv(".//{$p}DodatkowyOpis/{$p}Wartosc") ?: $fv(".//{$p}DodatkowyOpis");

        return $result;
    }

    // ─── Permissions ────────────────────────────────────

    /**
     * Query personal permissions.
     * POST /api/v2/permissions/query/personal/grants
     */
    public function queryPermissions(): ?array
    {
        if (!$this->ensureAuthenticated()) return null;

        $startTime = microtime(true);
        try {
            $response = $this->apiRequestAuth('POST', $this->ep('permissions/query/personal/grants'), [
                'pageSize' => 50,
                'pageOffset' => 0,
            ]);
            $this->logOperation('permissions_query', 'success', $startTime);
            return $response;
        } catch (\Exception $e) {
            $this->logMsg('error', "queryPermissions error: " . $e->getMessage());
            $this->logOperation('permissions_query', 'failed', $startTime, $e->getMessage());
            return null;
        }
    }

    // ─── Session Management ─────────────────────────────

    /**
     * List active KSeF authentication sessions.
     * GET /api/v2/auth/sessions
     */
    public function listSessions(): ?array
    {
        if (!$this->ensureAuthenticated()) return null;

        try {
            return $this->apiRequestAuth('GET', $this->ep('auth/sessions') . '?pageSize=50');
        } catch (\Exception $e) {
            $this->logMsg('error', "listSessions error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Revoke current session.
     * DELETE /api/v2/auth/sessions/current
     */
    public function revokeCurrentSession(): bool
    {
        if (!$this->accessToken) return false;

        try {
            $this->apiRequestAuth('DELETE', $this->ep('auth/sessions/current'));
            $this->accessToken = null;
            $this->refreshToken = null;
            if ($this->clientId) {
                KsefConfig::clearTokens($this->clientId);
            }
            return true;
        } catch (\Exception $e) {
            $this->logMsg('error', "revokeSession error: " . $e->getMessage());
            return false;
        }
    }

    // ─── Operation Logging ──────────────────────────────

    private function logOperation(string $operation, string $status, float $startTime, ?string $error = null): void
    {
        if (!$this->clientId) return;

        try {
            $durationMs = (int)((microtime(true) - $startTime) * 1000);
            KsefOperationLog::log(
                $this->clientId,
                $operation,
                $status,
                $this->performerType,
                $this->performerId,
                null, null, $error, null,
                $durationMs
            );
        } catch (\Exception $e) {
            // Non-critical
            error_log("KSeF operation log error: " . $e->getMessage());
        }
    }

    // ─── Utility ────────────────────────────────────────

    /**
     * Test connection to KSeF API.
     */
    public function testConnection(): array
    {
        if ($this->isDemo) {
            return ['success' => true, 'environment' => 'demo', 'response_time_ms' => rand(80, 200)];
        }
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'not_configured'];
        }

        try {
            $challengeData = $this->getChallenge();
            if ($challengeData) {
                return [
                    'success' => true,
                    'environment' => $this->env,
                    'api_url' => $this->baseUrl,
                    'nip' => $this->nip,
                    'challenge' => $challengeData['challenge'],
                ];
            }
            return ['success' => false, 'error' => 'challenge_failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getEnvironment(): string
    {
        return $this->env;
    }

    public function getApiUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Build full API endpoint path with prefix.
     */
    private function ep(string $path): string
    {
        return $this->pathPrefix . '/' . ltrim($path, '/');
    }

    // ─── HTTP Layer ─────────────────────────────────────

    /**
     * Make authenticated API request (with accessToken).
     */
    private function apiRequestAuth(string $method, string $endpoint, ?array $data = null): array
    {
        return $this->apiRequest($method, $endpoint, $data, [
            'Authorization: Bearer ' . $this->accessToken,
        ]);
    }

    /**
     * Make API request to KSeF.
     *
     * @param string      $method   HTTP method
     * @param string      $endpoint API path (e.g. /api/v2/auth/challenge)
     * @param array|null  $data     POST body (JSON)
     * @param array       $extraHeaders Additional headers
     */
    private function apiRequest(string $method, string $endpoint, ?array $data = null, array $extraHeaders = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extraHeaders);

        // Log the request
        if ($this->logger) {
            $this->logger->logRequest($method, $url, $data, $headers);
        }

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'BiLLU/4.0',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        $duration = round(microtime(true) - $startTime, 3);

        // Log the response
        if ($this->logger) {
            if ($error) {
                $this->logger->logResponse($method, $url, 0, "cURL error: {$error}", $duration);
                $this->logger->error("cURL details", [
                    'curl_errno' => $curlInfo['http_code'] ?? 0,
                    'ssl_verify_result' => $curlInfo['ssl_verify_result'] ?? null,
                    'connect_time' => $curlInfo['connect_time'] ?? null,
                    'total_time' => $curlInfo['total_time'] ?? null,
                    'primary_ip' => $curlInfo['primary_ip'] ?? null,
                ]);
            } else {
                $this->logger->logResponse($method, $url, $httpCode, $response ?: '', $duration);
            }
        }

        if ($error) {
            throw new \RuntimeException("KSeF API cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            $body = $response ?: 'No response';
            $decoded = json_decode($body, true);
            $errorMsg = $decoded['exception']['exceptionDetailList'][0]['exceptionDescription']
                ?? $decoded['Exception']['ExceptionDetailList'][0]['ExceptionDescription']
                ?? $decoded['message']
                ?? $body;

            if ($this->logger) {
                $this->logger->error("API error HTTP {$httpCode}", [
                    'parsed_error' => $errorMsg,
                    'full_body' => $decoded ?? $body,
                ]);
            }

            throw new \RuntimeException("KSeF API HTTP {$httpCode}: {$errorMsg}");
        }

        return json_decode($response ?: '{}', true) ?: [];
    }

    /**
     * Submit an invoice XML to KSeF.
     *
     * @param string $xml FA(2) XML document
     * @return array ['referenceNumber' => ?string, 'error' => ?string]
     */
    /**
     * Submit an invoice to KSeF using the encrypted online session flow.
     *
     * KSeF API v2 requires:
     * 1. Generate AES-256 key + IV
     * 2. Encrypt AES key with KSeF public key (RSA-OAEP)
     * 3. POST /v2/sessions/online → open encrypted session
     * 4. Encrypt invoice XML with AES-256-CBC
     * 5. POST /v2/sessions/online/{ref}/invoices → send encrypted invoice
     * 6. POST /v2/sessions/online/{ref}/close → close session
     */
    public function submitInvoice(string $xml): array
    {
        $this->logMsg('info', 'Submitting invoice to KSeF (encrypted session flow)');
        $startTime = microtime(true);

        // Always save generated XML for debugging
        $xmlDebugDir = dirname(__DIR__, 2) . '/storage/ksef_send/xml';
        if (!is_dir($xmlDebugDir)) {
            @mkdir($xmlDebugDir, 0775, true);
        }
        $xmlDebugFile = $xmlDebugDir . '/last_invoice_' . date('Ymd_His') . '.xml';
        @file_put_contents($xmlDebugFile, $xml);
        $this->logMsg('info', 'Invoice XML saved to: ' . $xmlDebugFile . ' (' . strlen($xml) . ' bytes)');

        if (!$this->accessToken) {
            return ['referenceNumber' => null, 'error' => 'No access token — authenticate first'];
        }

        try {
            // Step 1: Generate AES-256 key and IV
            $aesKey = random_bytes(32); // AES-256
            $iv = random_bytes(16);     // CBC IV
            $this->logMsg('info', 'Step 1: Generated AES key + IV');

            // Step 2: Encrypt AES key with KSeF public key (RSA-OAEP)
            // Use SymmetricKeyEncryption certificate (not KsefTokenEncryption which is for auth)
            $publicKey = $this->getPublicKeyByUsage('SymmetricKeyEncryption');
            if (!$publicKey) {
                $this->logOperation('invoice_submit', 'failed', $startTime, 'Could not fetch KSeF SymmetricKeyEncryption certificate');
                return ['referenceNumber' => null, 'error' => 'Could not fetch KSeF encryption certificate'];
            }

            $encryptedKey = $this->rsaOaepSha256Encrypt($aesKey, $publicKey);
            if ($encryptedKey === false) {
                $this->logOperation('invoice_submit', 'failed', $startTime, 'RSA-OAEP SHA-256 encrypt failed');
                return ['referenceNumber' => null, 'error' => 'RSA-OAEP SHA-256 encrypt failed (openssl pkeyutl)'];
            }
            $this->logMsg('info', 'Step 2: AES key encrypted with RSA-OAEP SHA-256');

            // Step 3: Open online session with encryption
            $this->logMsg('info', 'Step 3: Opening encrypted online session...');
            $sessionRef = $this->openOnlineSession(base64_encode($encryptedKey), base64_encode($iv));
            if (!$sessionRef) {
                $detail = $this->lastSessionError ?: 'Unknown error';
                $this->logOperation('invoice_submit', 'failed', $startTime, 'Failed to open online session: ' . $detail);
                return ['referenceNumber' => null, 'error' => 'Failed to open online session: ' . $detail];
            }
            $this->logMsg('info', 'Session opened', [
                'referenceNumber' => $sessionRef,
                'apiPrefix' => $this->sessionApiPrefix,
            ]);

            // Wait for session to initialize (optimistic — proceeds even if status check fails)
            if (!$this->waitForSessionReady($sessionRef)) {
                $this->closeOnlineSession($sessionRef);
                $this->logOperation('invoice_submit', 'failed', $startTime, 'Session reached terminal state');
                return ['referenceNumber' => null, 'error' => 'KSeF session failed to initialize (terminal state). Try again.'];
            }
            $this->logMsg('info', 'Session ready check passed, proceeding with invoice send');

            // Step 4: Encrypt invoice XML with AES-256-CBC
            $encryptedXml = openssl_encrypt($xml, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
            if ($encryptedXml === false) {
                $this->closeOnlineSession($sessionRef);
                $this->logOperation('invoice_submit', 'failed', $startTime, 'AES encrypt failed');
                return ['referenceNumber' => null, 'error' => 'AES encrypt of invoice failed'];
            }
            $this->logMsg('info', 'Step 4: Invoice encrypted', [
                'plainSize' => strlen($xml),
                'encryptedSize' => strlen($encryptedXml),
            ]);

            // Step 5: Send encrypted invoice
            $this->logMsg('info', 'Step 5: Sending encrypted invoice in session ' . $sessionRef);

            // Log generated XML for debugging (first 500 chars)
            $this->logMsg('debug', 'Generated invoice XML preview', [
                'xml_preview' => substr($xml, 0, 500),
                'xml_length' => strlen($xml),
            ]);

            // KSeF v2 API expects flat payload format (not nested hashSHA objects)
            $invoicePayload = [
                'invoiceHash' => base64_encode(hash('sha256', $xml, true)),
                'invoiceSize' => strlen($xml),
                'encryptedInvoiceHash' => base64_encode(hash('sha256', $encryptedXml, true)),
                'encryptedInvoiceSize' => strlen($encryptedXml),
                'encryptedInvoiceContent' => base64_encode($encryptedXml),
                'offlineMode' => false,
            ];

            // Send with retry — if session not ready yet, wait and retry (max 2 retries)
            $sendResult = $this->sendInvoiceInSession($sessionRef, $invoicePayload);

            for ($retry = 1; $retry <= 2 && !empty($sendResult['error']); $retry++) {
                $errLower = mb_strtolower($sendResult['error']);
                if (strpos($errLower, 'status sesji') === false && strpos($errLower, 'session') === false) {
                    break; // Not a session-status error — don't retry
                }
                $this->logMsg('warning', "Invoice send failed (session not ready), retry #{$retry} in 3s...");
                sleep(3);
                $sendResult = $this->sendInvoiceInSession($sessionRef, $invoicePayload);
            }

            // Step 6: Close session (best-effort)
            $this->logMsg('info', 'Step 6: Closing session ' . $sessionRef);
            $this->closeOnlineSession($sessionRef);

            if (!empty($sendResult['error'])) {
                // Add diagnostic info to error
                $diag = ' | Session response: ' . json_encode($this->lastSessionResponse)
                    . ' | Prefix: ' . $this->sessionApiPrefix
                    . ' | Auth: ' . $this->authMethod;
                $sendResult['error'] .= $diag;
                $this->logOperation('invoice_submit', 'failed', $startTime, $sendResult['error']);
                return $sendResult;
            }

            // Step 7: Poll for full KSeF reference number (NIP-DATE-XXXXXX-XXXXXX-XX)
            // The elementReferenceNumber from send is partial; full number available after session closes
            $elementRef = $sendResult['referenceNumber'] ?? '';
            $fullKsefRef = $this->pollForKsefNumber($sessionRef, $elementRef);
            if ($fullKsefRef) {
                $sendResult['referenceNumber'] = $fullKsefRef;
                $this->logMsg('info', 'Full KSeF reference resolved: ' . $fullKsefRef);
            } elseif (!empty($elementRef)) {
                // Fallback: polling failed but invoice was accepted — use elementRef
                $sendResult['referenceNumber'] = $elementRef;
                $sendResult['partial'] = true;
                $this->logMsg('info', 'Using elementRef as fallback reference: ' . $elementRef);
            }

            $this->logOperation('invoice_submit', 'success', $startTime);
            return array_merge($sendResult, ['sessionRef' => $sessionRef, 'elementRef' => $elementRef]);

        } catch (\Throwable $e) {
            $this->logMsg('error', 'Submit invoice failed', ['exception' => $e->getMessage()]);
            $this->logOperation('invoice_submit', 'failed', $startTime, $e->getMessage());
            return ['referenceNumber' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Submit multiple invoices in a SINGLE KSeF session (batch mode).
     *
     * Instead of opening/closing a session per invoice (≈35s each), this method
     * opens one session, sends all invoices, closes once, then polls for all
     * KSeF reference numbers.
     *
     * @param array $xmlList Indexed array of invoice XML strings
     * @param callable|null $onProgress fn(int $index, int $total, string $status) — optional progress callback
     * @return array Indexed array matching $xmlList keys: ['referenceNumber' => string|null, 'elementRef' => string|null, 'error' => string|null]
     */
    public function submitBatch(array $xmlList, ?callable $onProgress = null): array
    {
        $this->logMsg('info', 'Starting batch submit for ' . count($xmlList) . ' invoices');
        $startTime = microtime(true);
        $results = [];

        if (!$this->accessToken) {
            foreach ($xmlList as $i => $_) {
                $results[$i] = ['referenceNumber' => null, 'elementRef' => null, 'error' => 'No access token — authenticate first'];
            }
            return $results;
        }

        // Save debug XMLs
        $xmlDebugDir = dirname(__DIR__, 2) . '/storage/ksef_send/xml';
        if (!is_dir($xmlDebugDir)) {
            @mkdir($xmlDebugDir, 0775, true);
        }
        foreach ($xmlList as $i => $xml) {
            @file_put_contents($xmlDebugDir . '/batch_' . date('Ymd_His') . '_' . $i . '.xml', $xml);
        }

        try {
            // Step 1: Generate ONE AES-256 key + IV for the entire session
            $aesKey = random_bytes(32);
            $iv = random_bytes(16);
            $this->logMsg('info', 'Batch step 1: Generated single AES key + IV');

            // Step 2: Encrypt AES key with KSeF public key (RSA-OAEP)
            $publicKey = $this->getPublicKeyByUsage('SymmetricKeyEncryption');
            if (!$publicKey) {
                $this->logOperation('batch_submit', 'failed', $startTime, 'Could not fetch KSeF encryption certificate');
                foreach ($xmlList as $i => $_) {
                    $results[$i] = ['referenceNumber' => null, 'elementRef' => null, 'error' => 'Could not fetch KSeF encryption certificate'];
                }
                return $results;
            }

            $encryptedKey = $this->rsaOaepSha256Encrypt($aesKey, $publicKey);
            if ($encryptedKey === false) {
                $this->logOperation('batch_submit', 'failed', $startTime, 'RSA-OAEP SHA-256 encrypt failed');
                foreach ($xmlList as $i => $_) {
                    $results[$i] = ['referenceNumber' => null, 'elementRef' => null, 'error' => 'RSA-OAEP SHA-256 encrypt failed'];
                }
                return $results;
            }
            $this->logMsg('info', 'Batch step 2: AES key encrypted with RSA-OAEP SHA-256');

            // Step 3: Open ONE online session
            $this->logMsg('info', 'Batch step 3: Opening single encrypted online session...');
            $sessionRef = $this->openOnlineSession(base64_encode($encryptedKey), base64_encode($iv));
            if (!$sessionRef) {
                $detail = $this->lastSessionError ?: 'Unknown error';
                $this->logOperation('batch_submit', 'failed', $startTime, 'Failed to open session: ' . $detail);
                foreach ($xmlList as $i => $_) {
                    $results[$i] = ['referenceNumber' => null, 'elementRef' => null, 'error' => 'Failed to open session: ' . $detail];
                }
                return $results;
            }
            $this->logMsg('info', 'Batch session opened: ' . $sessionRef);

            // Step 4: Wait for session ready
            if (!$this->waitForSessionReady($sessionRef)) {
                $this->closeOnlineSession($sessionRef);
                $this->logOperation('batch_submit', 'failed', $startTime, 'Session failed to initialize');
                foreach ($xmlList as $i => $_) {
                    $results[$i] = ['referenceNumber' => null, 'elementRef' => null, 'error' => 'KSeF session failed to initialize'];
                }
                return $results;
            }

            // Step 5: Send each invoice in the SAME session
            $elementRefs = []; // index => elementRef
            $total = count($xmlList);

            foreach ($xmlList as $i => $xml) {
                if ($onProgress) {
                    $onProgress($i, $total, 'sending');
                }

                try {
                    // Encrypt invoice with the SAME AES key
                    $encryptedXml = openssl_encrypt($xml, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
                    if ($encryptedXml === false) {
                        $results[$i] = ['referenceNumber' => null, 'elementRef' => null, 'error' => 'AES encrypt failed'];
                        $this->logMsg('error', "Batch invoice #{$i}: AES encrypt failed");
                        continue;
                    }

                    $invoicePayload = [
                        'invoiceHash' => base64_encode(hash('sha256', $xml, true)),
                        'invoiceSize' => strlen($xml),
                        'encryptedInvoiceHash' => base64_encode(hash('sha256', $encryptedXml, true)),
                        'encryptedInvoiceSize' => strlen($encryptedXml),
                        'encryptedInvoiceContent' => base64_encode($encryptedXml),
                        'offlineMode' => false,
                    ];

                    $sendResult = $this->sendInvoiceInSession($sessionRef, $invoicePayload);

                    // Retry once if session-status error
                    if (!empty($sendResult['error'])) {
                        $errLower = mb_strtolower($sendResult['error']);
                        if (strpos($errLower, 'session') !== false || strpos($errLower, 'status sesji') !== false) {
                            $this->logMsg('warning', "Batch invoice #{$i}: retrying in 3s...");
                            sleep(3);
                            $sendResult = $this->sendInvoiceInSession($sessionRef, $invoicePayload);
                        }
                    }

                    $elemRef = $sendResult['referenceNumber'] ?? null;
                    $results[$i] = [
                        'referenceNumber' => null, // Will be resolved after session close
                        'elementRef' => $elemRef,
                        'error' => $sendResult['error'] ?? null,
                        'sessionRef' => $sessionRef,
                    ];
                    if ($elemRef) {
                        $elementRefs[$i] = $elemRef;
                    }

                    $this->logMsg('info', "Batch invoice #{$i} sent: elementRef=" . ($elemRef ?? 'null'));
                } catch (\Throwable $e) {
                    $results[$i] = ['referenceNumber' => null, 'elementRef' => null, 'error' => $e->getMessage()];
                    $this->logMsg('error', "Batch invoice #{$i} exception: " . $e->getMessage());
                }
            }

            // Step 6: Close session (once for all)
            $this->logMsg('info', 'Batch step 6: Closing session ' . $sessionRef);
            $this->closeOnlineSession($sessionRef);

            // Step 7: Poll for ALL KSeF numbers
            if ($onProgress) {
                $onProgress(0, $total, 'polling');
            }
            $this->logMsg('info', 'Batch step 7: Polling for KSeF numbers...');

            $ksefNumbers = $this->pollForAllKsefNumbers($sessionRef, $elementRefs);

            // Map resolved KSeF numbers back to results
            foreach ($ksefNumbers as $i => $ksefNum) {
                if (isset($results[$i]) && $ksefNum) {
                    $results[$i]['referenceNumber'] = $ksefNum;
                }
            }

            // Fallback: if polling failed but invoice was accepted (has elementRef, no error),
            // use elementRef as temporary reference and mark as partial
            foreach ($results as $i => &$r) {
                if (empty($r['referenceNumber']) && !empty($r['elementRef']) && empty($r['error'])) {
                    $r['referenceNumber'] = $r['elementRef'];
                    $r['partial'] = true;
                    $this->logMsg('info', "Invoice #{$i}: using elementRef as fallback reference: " . $r['elementRef']);
                }
            }
            unset($r);

            $successCount = count(array_filter($results, fn($r) => !empty($r['referenceNumber'])));
            $this->logMsg('info', "Batch complete: {$successCount}/" . count($xmlList) . " resolved");
            $this->logOperation('batch_submit', 'success', $startTime, "Sent {$successCount}/" . count($xmlList));

            return $results;

        } catch (\Throwable $e) {
            $this->logMsg('error', 'Batch submit failed: ' . $e->getMessage());
            $this->logOperation('batch_submit', 'failed', $startTime, $e->getMessage());
            // Fill any missing results with the error
            foreach ($xmlList as $i => $_) {
                if (!isset($results[$i])) {
                    $results[$i] = ['referenceNumber' => null, 'elementRef' => null, 'error' => $e->getMessage()];
                }
            }
            return $results;
        }
    }

    /**
     * Poll session status after close to retrieve ALL KSeF invoice reference numbers.
     * Used by submitBatch() to resolve multiple element refs to full KSeF numbers.
     *
     * @param string $sessionRef
     * @param array $elementRefs index => elementRefString
     * @return array index => ksefNumber|null
     */
    private function pollForAllKsefNumbers(string $sessionRef, array $elementRefs): array
    {
        $ksefPattern = '/^([1-9]\d{9}|M\d{9}|[A-Z]{3}\d{7})-\d{8}-[0-9A-F]{6}-?[0-9A-F]{6}-[0-9A-F]{2}$/';
        $resolved = [];
        $consecutiveAllFailed = 0;

        for ($i = 0; $i < 15; $i++) { // Up to 45s (15 × 3s)
            sleep(3);
            try {
                $status = $this->tryGetSessionStatus($sessionRef);

                if ($status === null) {
                    $consecutiveAllFailed++;
                    $this->logMsg('warning', "Batch poll #{$i}: all prefixes returned 404 ({$consecutiveAllFailed} consecutive)");
                    if ($consecutiveAllFailed >= 3) {
                        $this->logMsg('warning', 'Session status not available on any prefix — stopping batch poll early');
                        return $resolved;
                    }
                    continue;
                }

                $consecutiveAllFailed = 0;
                $code = $status['processingCode'] ?? null;
                $this->logMsg('info', "Batch poll #{$i}: processingCode={$code}");

                if ($code === 350 || $code === '350') {
                    // Session closed — extract ALL KSeF numbers
                    $allNumbers = $this->findAllKsefNumbersInResponse($status, $ksefPattern);
                    $this->logMsg('info', 'Found ' . count($allNumbers) . ' KSeF numbers in session response');

                    if (!empty($allNumbers)) {
                        $prefix = $this->sessionApiPrefix ?: $this->pathPrefix;
                        // Try to match by element reference first
                        foreach ($elementRefs as $idx => $elemRef) {
                            try {
                                $invStatus = $this->apiRequestAuth('GET',
                                    $prefix . '/sessions/online/' . rawurlencode($sessionRef)
                                    . '/invoice-status/' . rawurlencode($elemRef));
                                $found = $this->findKsefNumberInResponse($invStatus, $ksefPattern);
                                if ($found) {
                                    $resolved[$idx] = $found;
                                    $this->logMsg('info', "Resolved invoice #{$idx}: {$found}");
                                    continue;
                                }
                            } catch (\Exception $e) {
                                $this->logMsg('warning', "Invoice status for #{$idx} failed: " . $e->getMessage());
                            }
                        }

                        // For any unresolved, assign remaining numbers in order
                        $usedNumbers = array_values($resolved);
                        $remainingNumbers = array_values(array_diff($allNumbers, $usedNumbers));
                        $remainingIdx = 0;
                        foreach ($elementRefs as $idx => $_) {
                            if (!isset($resolved[$idx]) && isset($remainingNumbers[$remainingIdx])) {
                                $resolved[$idx] = $remainingNumbers[$remainingIdx];
                                $this->logMsg('info', "Assigned remaining KSeF number to #{$idx}: " . $remainingNumbers[$remainingIdx]);
                                $remainingIdx++;
                            }
                        }
                    }

                    return $resolved;
                }

                if (in_array($code, [415, '415', 440, '440'])) {
                    $this->logMsg('error', "Session error state {$code} during batch poll");
                    return $resolved;
                }
            } catch (\Exception $e) {
                $this->logMsg('warning', "Batch poll #{$i} failed: " . $e->getMessage());
            }
        }

        $this->logMsg('warning', 'Timeout polling for batch KSeF numbers');
        return $resolved;
    }

    /**
     * Find ALL KSeF-formatted reference numbers in a response (for batch resolution).
     */
    private function findAllKsefNumbersInResponse($data, string $pattern): array
    {
        $found = [];
        if (is_string($data) && preg_match($pattern, $data)) {
            $found[] = $data;
        }
        if (is_array($data)) {
            foreach ($data as $value) {
                $found = array_merge($found, $this->findAllKsefNumbersInResponse($value, $pattern));
            }
        }
        return array_unique($found);
    }

    /**
     * Download UPO (Urzędowe Poswiadczenie Odbioru) for a session.
     *
     * Polls GET /sessions/online/{ref} until session is closed (processingCode=350),
     * then fetches UPO XML.
     *
     * @return array|null ['content' => string] or null on failure
     */
    /**
     * Download UPO for a KSeF invoice.
     *
     * Strategy:
     *   0. Try /common/Upo/{ksefRef} — works without active session (for invoices with known KSeF number)
     *   1. Try direct /sessions/online/{ref}/upo — for recently closed sessions still in KSeF cache
     *   2. Open new session, poll, fetch UPO — last resort for edge cases
     *
     * @param string $sessionRef  Primary ref (ksef_session_ref SO-...)
     * @param string|null $ksefRef  KSeF invoice reference number (NIP-DATE-...)
     */
    public function downloadUpo(string $sessionRef, ?string $ksefRef = null): ?array
    {
        $this->logMsg('info', "Attempting UPO download — sessionRef: {$sessionRef}, ksefRef: {$ksefRef}");

        if (empty($sessionRef) && empty($ksefRef)) {
            return ['content' => null, 'error' => 'Brak identyfikatora sesji i numeru KSeF'];
        }

        // Authenticate
        if (!$this->ensureAuthenticated()) {
            return ['content' => null, 'error' => 'Autentykacja KSeF nie powiodła się'];
        }
        $this->logMsg('info', 'Authentication OK');

        $prefixes = $this->getSessionStatusPrefixes();

        // === STRATEGY 0: /common/Upo/{ksefReferenceNumber} — no session required ===
        if (!empty($ksefRef)) {
            $this->logMsg('info', 'Strategy 0: GET /common/Upo/{ksefRef}');
            foreach ($prefixes as $prefix) {
                try {
                    $endpoint = $prefix . '/common/Upo/' . rawurlencode($ksefRef);
                    $this->logMsg('info', "Trying: {$endpoint}");
                    $response = $this->apiRequestAuth('GET', $endpoint);
                    $content = $this->parseUpoResponse($response);
                    if ($content) {
                        $this->logMsg('info', 'Strategy 0 succeeded: ' . strlen($content) . ' bytes');
                        return ['content' => $content];
                    }
                } catch (\Exception $e) {
                    $this->logMsg('info', "Strategy 0 failed on {$prefix}: " . $e->getMessage());
                }
            }

            // Also try /common/Status/{ksefRef} which may return UPO inline
            foreach ($prefixes as $prefix) {
                try {
                    $endpoint = $prefix . '/common/Status/' . rawurlencode($ksefRef);
                    $this->logMsg('info', "Trying status: {$endpoint}");
                    $response = $this->apiRequestAuth('GET', $endpoint);
                    if (!empty($response['upo'])) {
                        $content = $this->parseUpoResponse($response);
                        if ($content) {
                            $this->logMsg('info', 'Strategy 0 (Status) succeeded: ' . strlen($content) . ' bytes');
                            return ['content' => $content];
                        }
                    }
                    $this->logMsg('info', 'Status response keys: ' . implode(',', array_keys($response ?? [])));
                } catch (\Exception $e) {
                    $this->logMsg('info', "Status endpoint failed on {$prefix}: " . $e->getMessage());
                }
            }
        }

        // === STRATEGY 1: Direct /sessions/online/{ref}/upo — for fresh sessions ===
        $refsToTry = [];
        if (!empty($sessionRef)) $refsToTry[] = $sessionRef;
        if (!empty($ksefRef) && $ksefRef !== $sessionRef) $refsToTry[] = $ksefRef;

        $this->logMsg('info', 'Strategy 1: Direct session UPO fetch');
        foreach ($refsToTry as $ref) {
            foreach ($prefixes as $prefix) {
                try {
                    $endpoint = $prefix . '/sessions/online/' . rawurlencode($ref) . '/upo';
                    $this->logMsg('info', "Trying: {$endpoint}");
                    $response = $this->apiRequestAuth('GET', $endpoint);
                    $content = $this->parseUpoResponse($response);
                    if ($content) {
                        $this->logMsg('info', 'Strategy 1 succeeded: ' . strlen($content) . ' bytes');
                        return ['content' => $content];
                    }
                } catch (\Exception $e) {
                    $this->logMsg('info', "Failed on {$prefix} ref {$ref}: " . $e->getMessage());
                }
            }
        }

        // === STRATEGY 2: Open new session, poll, fetch UPO ===
        $this->logMsg('info', 'Strategy 2: Open new session for UPO retrieval');
        try {
            // Generate encryption keys for new session
            $aesKey = openssl_random_pseudo_bytes(32);
            $iv = openssl_random_pseudo_bytes(16);

            // Get KSeF public key and encrypt AES key
            $publicKey = $this->getPublicKeyByUsage('SymmetricKeyEncryption');
            if (!$publicKey) {
                $this->logMsg('error', 'Cannot get KSeF encryption key for new session');
                return ['content' => null, 'error' => 'Nie można pobrać klucza szyfrowania KSeF'];
            }

            $encryptedKey = $this->rsaOaepSha256Encrypt($aesKey, $publicKey);
            if ($encryptedKey === false) {
                $this->logMsg('error', 'Failed to encrypt AES key');
                return ['content' => null, 'error' => 'Błąd szyfrowania klucza sesji'];
            }

            $newSessionRef = $this->openOnlineSession(base64_encode($encryptedKey), base64_encode($iv));

            if (!$newSessionRef) {
                $this->logMsg('error', 'Failed to open new session');
                return ['content' => null, 'error' => 'Nie udało się otworzyć nowej sesji KSeF'];
            }
            $this->logMsg('info', "New session opened: {$newSessionRef}");

            // Poll for session readiness (processingCode=315 = active)
            $sessionActive = false;
            for ($i = 0; $i < 5; $i++) {
                sleep(2);
                $status = $this->tryGetSessionStatus($newSessionRef);
                $code = $status['processingCode'] ?? null;
                $this->logMsg('info', "New session poll #{$i}: code={$code}");
                if ($code === 315 || $code === '315' || $code === 350 || $code === '350') {
                    $sessionActive = true;
                    break;
                }
            }

            // Now close the new session and wait for processingCode=350
            try {
                $this->closeOnlineSession($newSessionRef);
                $this->logMsg('info', 'New session closed, waiting for UPO...');
            } catch (\Exception $e) {
                $this->logMsg('warning', 'Close session failed: ' . $e->getMessage());
            }

            // Poll for UPO availability
            for ($i = 0; $i < 10; $i++) {
                sleep(2);
                $status = $this->tryGetSessionStatus($newSessionRef);
                if (!$status) continue;
                $code = $status['processingCode'] ?? null;
                $this->logMsg('info', "UPO poll #{$i}: code={$code}");

                if ($code === 350 || $code === '350') {
                    // Session closed — try UPO endpoint
                    $workingPrefix = $this->sessionApiPrefix ?: $this->pathPrefix;
                    $upoEndpoint = $workingPrefix . '/sessions/online/' . rawurlencode($newSessionRef) . '/upo';
                    $this->logMsg('info', "Fetching UPO from new session: {$upoEndpoint}");
                    $response = $this->apiRequestAuth('GET', $upoEndpoint);
                    $content = $this->parseUpoResponse($response);
                    if ($content) {
                        $this->logMsg('info', 'Strategy 2 succeeded: ' . strlen($content) . ' bytes');
                        return ['content' => $content];
                    }
                    break;
                }
            }

            $this->logMsg('error', 'Strategy 2 failed — new session did not produce UPO');
        } catch (\Exception $e) {
            $this->logMsg('error', 'Strategy 2 exception: ' . $e->getMessage());
        }

        return ['content' => null, 'error' => 'Nie udało się pobrać UPO żadną ze strategii (common/Upo, direct session, new session)'];
    }

    /**
     * Parse UPO from API response — handles base64, raw XML, and nested formats.
     */
    private function parseUpoResponse($response): ?string
    {
        if (!empty($response['upo'])) {
            $content = base64_decode($response['upo']);
            if ($content !== false && strlen($content) > 0) {
                return $content;
            }
            if (is_string($response['upo']) && str_contains($response['upo'], '<?xml')) {
                return $response['upo'];
            }
        }

        // Response might contain UPO under different key
        if (!empty($response['content'])) {
            $content = base64_decode($response['content']);
            if ($content !== false && strlen($content) > 0 && str_contains($content, '<?xml')) {
                return $content;
            }
        }

        if (is_string($response) && str_contains($response, '<?xml')) {
            return $response;
        }

        return null;
    }

    /**
     * Recover KSeF reference number for a single invoice using its session reference.
     * Queries the closed session status endpoint to find the full NIP-prefixed number.
     *
     * @return string|null Full KSeF reference number or null if not found
     */
    public function recoverKsefNumber(string $sessionRef): ?string
    {
        if (empty($sessionRef)) {
            return null;
        }

        if (!$this->ensureAuthenticated()) {
            $this->logMsg('error', 'Authentication failed - cannot recover KSeF number');
            return null;
        }

        $prefix = $this->sessionApiPrefix ?: $this->pathPrefix;
        $ksefPattern = '/^([1-9]\d{9}|M\d{9}|[A-Z]{3}\d{7})-\d{8}-[0-9A-F]{6}-?[0-9A-F]{6}-[0-9A-F]{2}$/';

        try {
            $status = $this->apiRequestAuth('GET', $prefix . '/sessions/online/' . rawurlencode($sessionRef));
            $code = $status['processingCode'] ?? null;

            if ($code === 350 || $code === '350') {
                $found = $this->findKsefNumberInResponse($status, $ksefPattern);
                if ($found) {
                    return $found;
                }
            }
        } catch (\Exception $e) {
            $this->logMsg('warning', 'recoverKsefNumber failed for session ' . $sessionRef . ': ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Backfill missing KSeF reference numbers for issued invoices.
     *
     * Strategy:
     * 1. For invoices with ksef_session_ref — try to recover from session status
     * 2. For remaining — query KSeF API as Subject1 (seller) and match by invoice number
     *
     * @return array{recovered: int, failed: int, errors: string[]}
     */
    public function backfillKsefNumbers(int $clientId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $result = ['recovered' => 0, 'failed' => 0, 'errors' => []];

        $db = \App\Core\Database::getInstance();

        // Find invoices with ksef_status=sent/accepted but no ksef_reference_number
        $sql = "SELECT id, invoice_number, issue_date, ksef_session_ref, ksef_status, buyer_nip
                FROM issued_invoices
                WHERE client_id = ?
                  AND ksef_status IN ('sent', 'accepted')
                  AND (ksef_reference_number IS NULL OR ksef_reference_number = '')";
        $params = [$clientId];

        if ($dateFrom) {
            $sql .= " AND issue_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND issue_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY issue_date ASC";
        $invoices = $db->fetchAll($sql, $params);

        if (empty($invoices)) {
            return $result;
        }

        if (!$this->ensureAuthenticated()) {
            $result['errors'][] = 'Autentykacja KSeF nie powiodła się';
            return $result;
        }

        // Phase 1: Try session-based recovery
        $remaining = [];
        foreach ($invoices as $inv) {
            if (!empty($inv['ksef_session_ref'])) {
                $ksefNum = $this->recoverKsefNumber($inv['ksef_session_ref']);
                if ($ksefNum) {
                    \App\Models\IssuedInvoice::updateKsefStatus((int)$inv['id'], $inv['ksef_status'], $ksefNum);
                    $result['recovered']++;
                    $this->logMsg('info', "Recovered KSeF number for invoice #{$inv['id']}: {$ksefNum}");
                    continue;
                }
            }
            $remaining[] = $inv;
        }

        if (empty($remaining)) {
            return $result;
        }

        // Phase 2: Query KSeF API as Subject1 (seller) and match by invoice number
        $dates = array_column($remaining, 'issue_date');
        $minDate = $dateFrom ?: min($dates);
        $maxDate = $dateTo ?: max($dates);

        try {
            $ksefInvoices = $this->fetchInvoices($this->nip, $minDate, $maxDate, 'Subject1');
        } catch (\Exception $e) {
            $result['errors'][] = 'Błąd zapytania KSeF: ' . $e->getMessage();
            $result['failed'] += count($remaining);
            return $result;
        }

        // Build lookup by invoice number
        $ksefByNumber = [];
        foreach ($ksefInvoices as $ki) {
            $num = $ki['invoice_number'] ?? '';
            if ($num !== '' && !empty($ki['ksef_reference_number'])) {
                $ksefByNumber[$num] = $ki['ksef_reference_number'];
            }
        }

        foreach ($remaining as $inv) {
            $invNumber = $inv['invoice_number'] ?? '';
            if (isset($ksefByNumber[$invNumber])) {
                $ksefNum = $ksefByNumber[$invNumber];
                \App\Models\IssuedInvoice::updateKsefStatus((int)$inv['id'], $inv['ksef_status'], $ksefNum);
                $result['recovered']++;
                $this->logMsg('info', "Backfilled KSeF number for invoice #{$inv['id']} ({$invNumber}): {$ksefNum}");
            } else {
                $result['failed']++;
                $this->logMsg('warning', "No KSeF match for invoice #{$inv['id']} ({$invNumber})");
            }
        }

        return $result;
    }

    /**
     * Backfill missing KSeF reference numbers for purchase (received) invoices.
     *
     * Queries KSeF API as Subject2 (buyer) and matches by invoice_number + seller_nip.
     *
     * @return array{recovered: int, failed: int, total: int, errors: string[]}
     */
    public function backfillPurchaseKsefNumbers(int $clientId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $result = ['recovered' => 0, 'failed' => 0, 'total' => 0, 'errors' => []];

        $db = \App\Core\Database::getInstance();

        // Find purchase invoices without ksef_reference_number
        $sql = "SELECT id, invoice_number, issue_date, seller_nip, seller_name
                FROM invoices
                WHERE client_id = ?
                  AND (ksef_reference_number IS NULL OR ksef_reference_number = '')";
        $params = [$clientId];

        if ($dateFrom) {
            $sql .= " AND issue_date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND issue_date <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY issue_date ASC";
        $invoices = $db->fetchAll($sql, $params);

        $result['total'] = count($invoices);

        if (empty($invoices)) {
            return $result;
        }

        if (!$this->ensureAuthenticated()) {
            $result['errors'][] = 'Autentykacja KSeF nie powiodła się';
            return $result;
        }

        // Query KSeF API as Subject2 (buyer) for the date range
        $dates = array_column($invoices, 'issue_date');
        $minDate = $dateFrom ?: min($dates);
        $maxDate = $dateTo ?: max($dates);

        try {
            $ksefInvoices = $this->fetchInvoices($this->nip, $minDate, $maxDate, 'Subject2');
        } catch (\Exception $e) {
            $result['errors'][] = 'Błąd zapytania KSeF: ' . $e->getMessage();
            $result['failed'] = count($invoices);
            return $result;
        }

        // Build lookup by invoice_number + seller_nip for precise matching
        $ksefLookup = [];
        foreach ($ksefInvoices as $ki) {
            $num = $ki['invoice_number'] ?? '';
            $nip = $ki['seller_nip'] ?? '';
            if ($num !== '' && !empty($ki['ksef_reference_number'])) {
                $key = $nip . '|' . $num;
                $ksefLookup[$key] = $ki['ksef_reference_number'];
            }
        }

        foreach ($invoices as $inv) {
            $key = ($inv['seller_nip'] ?? '') . '|' . ($inv['invoice_number'] ?? '');
            if (isset($ksefLookup[$key])) {
                $ksefNum = $ksefLookup[$key];
                $db->update('invoices', ['ksef_reference_number' => $ksefNum], 'id = ?', [$inv['id']]);
                $result['recovered']++;
                $this->logMsg('info', "Backfilled purchase invoice #{$inv['id']} ({$inv['invoice_number']}): {$ksefNum}");
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Open an encrypted online (interactive) session for invoice submission.
     * POST /v2/sessions/online
     *
     * @return string|null Session referenceNumber or null on failure
     */
    /** The session API prefix that worked (set by openOnlineSession) */
    private string $sessionApiPrefix = '/api/v2';

    /** Full response from session opening for diagnostics */
    private array $lastSessionResponse = [];

    private function openOnlineSession(string $encryptedKeyB64, string $ivB64): ?string
    {
        try {
            $body = [
                'formCode' => [
                    'systemCode' => 'FA (3)',
                    'schemaVersion' => '1-0E',
                    'value' => 'FA',
                ],
                'encryption' => [
                    'encryptedSymmetricKey' => $encryptedKeyB64,
                    'initializationVector' => $ivB64,
                ],
            ];

            $this->logMsg('debug', 'openOnlineSession request body', ['body' => $body]);

            // Try multiple path prefixes
            $response = null;
            $lastError = '';
            $prefixes = ['/api/v2', $this->pathPrefix];
            foreach ($prefixes as $prefix) {
                $endpoint = $prefix . '/sessions/online';
                try {
                    $this->logMsg('info', 'Trying session endpoint: ' . $endpoint);
                    $response = $this->apiRequestAuth('POST', $endpoint, $body);
                    $this->sessionApiPrefix = $prefix;
                    $this->logMsg('info', 'Session endpoint works: ' . $prefix);
                    break;
                } catch (\RuntimeException $e) {
                    $lastError = $e->getMessage();
                    $this->logMsg('info', 'Endpoint failed: ' . $endpoint . ' → ' . $lastError);
                    if (strpos($lastError, 'HTTP 404') === false) {
                        throw $e;
                    }
                }
            }

            if ($response === null) {
                $this->lastSessionError = $lastError;
                return null;
            }

            $this->lastSessionResponse = $response;
            $this->logMsg('debug', 'openOnlineSession FULL response', ['response' => $response]);

            $ref = self::extractString($response, 'referenceNumber');
            if (!$ref) {
                $this->lastSessionError = 'No referenceNumber in response: ' . json_encode($response);
                $this->logMsg('error', 'openOnlineSession: no referenceNumber', ['response' => $response]);
                return null;
            }

            return $ref;
        } catch (\Exception $e) {
            $this->lastSessionError = $e->getMessage();
            $this->logMsg('error', 'openOnlineSession error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send an encrypted invoice within an open online session.
     * POST /v2/sessions/online/{referenceNumber}/invoices
     */
    private function sendInvoiceInSession(string $sessionRef, array $payload): array
    {
        try {
            $endpoint = $this->sessionApiPrefix . '/sessions/online/' . rawurlencode($sessionRef) . '/invoices';

            $this->logMsg('info', 'sendInvoiceInSession endpoint: ' . $endpoint, [
                'payloadKeys' => array_keys($payload),
                'invoiceSize' => $payload['invoiceSize'] ?? 0,
                'encryptedInvoiceSize' => $payload['encryptedInvoiceSize'] ?? 0,
            ]);

            $response = $this->apiRequestAuth('POST', $endpoint, $payload);

            $this->logMsg('info', 'sendInvoiceInSession response', ['response' => $response]);

            $ref = self::extractString($response, 'referenceNumber')
                ?? self::extractString($response, 'elementReferenceNumber')
                ?? self::extractString($response, 'invoiceReferenceNumber')
                ?? null;

            return ['referenceNumber' => $ref, 'error' => null];
        } catch (\Exception $e) {
            $this->logMsg('error', 'sendInvoiceInSession error: ' . $e->getMessage());
            return ['referenceNumber' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get list of API path prefixes to try for session status GET requests.
     * Tries the session prefix first, then pathPrefix, then both standard variants.
     */
    private function getSessionStatusPrefixes(): array
    {
        $prefixes = [$this->sessionApiPrefix ?: '/api/v2'];
        if ($this->pathPrefix && $this->pathPrefix !== $prefixes[0]) {
            $prefixes[] = $this->pathPrefix;
        }
        foreach (['/api/v2', '/v2'] as $p) {
            if (!in_array($p, $prefixes, true)) {
                $prefixes[] = $p;
            }
        }
        return $prefixes;
    }

    /**
     * Try GET session status across multiple path prefixes.
     * Returns the response array or null if all prefixes return 404.
     * Updates $this->sessionApiPrefix if a working prefix is found.
     */
    private function tryGetSessionStatus(string $sessionRef): ?array
    {
        $prefixes = $this->getSessionStatusPrefixes();
        foreach ($prefixes as $tryPrefix) {
            try {
                $response = $this->apiRequestAuth('GET', $tryPrefix . '/sessions/online/' . rawurlencode($sessionRef));
                // Found working prefix — remember it for future calls
                $this->sessionApiPrefix = $tryPrefix;
                return $response;
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'HTTP 404') !== false) {
                    $this->logMsg('info', "Session status 404 on prefix {$tryPrefix}, trying next...");
                    continue;
                }
                throw $e; // Non-404 error — propagate
            }
        }
        return null; // All prefixes returned 404
    }

    /**
     * Wait for session to become active. Optimistic approach:
     * sleep(3) + one GET check across multiple prefixes.
     * Returns true even if GET fails, so the caller can attempt the invoice send.
     *
     * Processing codes: 310=initializing, 315=active, 350=closed, 415=error
     */
    private function waitForSessionReady(string $sessionRef): bool
    {
        sleep(3); // Give KSeF time to initialize the session

        try {
            $response = $this->tryGetSessionStatus($sessionRef);

            if ($response === null) {
                $this->logMsg('warning', 'Session status check: all prefixes returned 404 — proceeding optimistically');
                return true;
            }

            $processingCode = $response['processingCode'] ?? null;
            $this->logMsg('info', 'Session status check', [
                'processingCode' => $processingCode,
                'response' => $response,
            ]);

            if (in_array($processingCode, [350, '350', 415, '415', 440, '440'])) {
                $this->logMsg('error', "Session in terminal state: {$processingCode}");
                return false;
            }

            // 315 = ready, 310 = still initializing (try anyway)
            return true;
        } catch (\Exception $e) {
            // Non-404 error — proceed optimistically
            $this->logMsg('warning', 'Session status check failed: ' . $e->getMessage() . ' — proceeding optimistically');
            return true;
        }
    }

    /**
     * Poll session status after close to retrieve full KSeF invoice reference number.
     * The sendInvoiceInSession returns elementReferenceNumber (partial),
     * but the full NIP-prefixed number is only in the closed session status.
     */
    private function pollForKsefNumber(string $sessionRef, string $elementRef): ?string
    {
        $ksefPattern = '/^([1-9]\d{9}|M\d{9}|[A-Z]{3}\d{7})-\d{8}-[0-9A-F]{6}-?[0-9A-F]{6}-[0-9A-F]{2}$/';
        $consecutiveAllFailed = 0;

        for ($i = 0; $i < 10; $i++) {
            sleep(3);
            try {
                $status = $this->tryGetSessionStatus($sessionRef);

                if ($status === null) {
                    $consecutiveAllFailed++;
                    $this->logMsg('warning', "KSeF number poll #{$i}: all prefixes returned 404 ({$consecutiveAllFailed} consecutive)");
                    // If 3 consecutive polls all return 404 across all prefixes, stop early
                    if ($consecutiveAllFailed >= 3) {
                        $this->logMsg('warning', 'Session status not available on any prefix — stopping poll early');
                        return null;
                    }
                    continue;
                }

                $consecutiveAllFailed = 0;
                $code = $status['processingCode'] ?? null;
                $this->logMsg('info', "KSeF number poll #{$i}: processingCode={$code}");

                if ($code === 350 || $code === '350') {
                    $this->logMsg('info', 'Session status response (closed)', [
                        'full_response' => json_encode($status),
                    ]);

                    $found = $this->findKsefNumberInResponse($status, $ksefPattern);
                    if ($found) {
                        return $found;
                    }

                    // Try invoiceStatus endpoint for specific element
                    if (!empty($elementRef)) {
                        $prefix = $this->sessionApiPrefix ?: $this->pathPrefix;
                        try {
                            $invStatus = $this->apiRequestAuth('GET',
                                $prefix . '/sessions/online/' . rawurlencode($sessionRef)
                                . '/invoice-status/' . rawurlencode($elementRef));
                            $this->logMsg('info', 'Invoice status response', [
                                'full_response' => json_encode($invStatus),
                            ]);
                            $found = $this->findKsefNumberInResponse($invStatus, $ksefPattern);
                            if ($found) {
                                return $found;
                            }
                        } catch (\Exception $e) {
                            $this->logMsg('warning', 'Invoice status endpoint failed: ' . $e->getMessage());
                        }
                    }

                    $this->logMsg('info', 'Session closed but no full KSeF number found');
                    return null;
                }

                if (in_array($code, [415, '415', 440, '440'])) {
                    $this->logMsg('error', "Session error state {$code}, cannot get KSeF number");
                    return null;
                }
            } catch (\Exception $e) {
                $this->logMsg('warning', "KSeF number poll #{$i} failed: " . $e->getMessage());
            }
        }

        $this->logMsg('warning', 'Timeout polling for full KSeF number');
        return null;
    }

    /**
     * Recursively search response array for a value matching KSeF number pattern.
     */
    private function findKsefNumberInResponse($data, string $pattern): ?string
    {
        if (is_string($data) && preg_match($pattern, $data)) {
            return $data;
        }
        if (is_array($data)) {
            // Check known field names first
            foreach (['ksefReferenceNumber', 'ksefNumber', 'invoiceReferenceNumber', 'referenceNumber'] as $key) {
                if (isset($data[$key]) && is_string($data[$key]) && preg_match($pattern, $data[$key])) {
                    return $data[$key];
                }
            }
            // Recurse into nested arrays
            foreach ($data as $value) {
                $found = $this->findKsefNumberInResponse($value, $pattern);
                if ($found) return $found;
            }
        }
        return null;
    }

    /**
     * Close an online session.
     * POST /v2/sessions/online/{referenceNumber}/close
     */
    private function closeOnlineSession(string $sessionRef): void
    {
        try {
            $this->apiRequestAuth('POST', $this->sessionApiPrefix . '/sessions/online/' . rawurlencode($sessionRef) . '/close');
            $this->logMsg('info', 'Online session closed: ' . $sessionRef);
        } catch (\Exception $e) {
            $this->logMsg('error', 'closeOnlineSession error: ' . $e->getMessage());
        }
    }

    /**
     * Internal log helper.
     */
    private function logMsg(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log(strtoupper($level), $message, $context);
        }
        if ($level === 'error') {
            error_log("KSeF: {$message}");
        }
    }

    /**
     * Safely extract a string value from API response.
     * KSeF API sometimes returns values as nested objects instead of plain strings.
     */
    /**
     * Safely extract a string value from API response.
     * KSeF API returns values in different formats:
     * - Plain string: "abc123"
     * - Nested object: {"token": "abc123"} or {"value": "abc123"}
     * - Number: 1234567890
     */
    private static function extractString(array $response, string $key): ?string
    {
        $value = $response[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            // KSeF wraps tokens as {token: "..."}, other values as {value: "..."}
            return (string) ($value['token'] ?? $value['value'] ?? $value[$key] ?? $value[0] ?? json_encode($value));
        }

        return (string) $value;
    }
}
