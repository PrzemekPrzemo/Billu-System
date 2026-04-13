<?php

namespace App\Services;

use App\Models\Setting;

/**
 * KSeF Certificate Service v4.0
 *
 * Handles:
 * - Secure storage/retrieval of certificates and keys (AES-256-GCM encrypted at rest)
 * - PFX/P12 certificate parsing and validation (qualified certs & electronic seals)
 * - EC P-256 / RSA-2048 key pair generation for KSeF certificate enrollment
 * - PKCS#10 CSR generation for KSeF enrollment
 * - XAdES-BES XML signature generation for KSeF authentication
 * - Encryption key management
 *
 * Certificate types supported:
 * 1. Podpis kwalifikowany (qualified personal signature) - PFX with PESEL
 * 2. Pieczęć elektroniczna (organizational electronic seal) - PFX with NIP
 * 3. Certyfikat KSeF (KSeF-issued certificate) - enrolled via CSR or imported
 *
 * Security:
 * - Certificates and private keys encrypted at rest with AES-256-GCM
 * - Per-installation master encryption key (auto-generated, stored in DB settings)
 * - PFX password used only during upload for validation, never stored
 * - XAdES-BES signatures with SHA-256 digests and exclusive C14N (xml-exc-c14n)
 *
 * @see https://github.com/CIRFMF/ksef-docs/blob/main/certyfikaty-KSeF.md
 * @see https://github.com/CIRFMF/ksef-docs/blob/main/uwierzytelnianie.md
 */
class KsefCertificateService
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_SETTING = 'ksef_cert_encryption_key';

    // ─── Encryption Key Management ─────────────────────

    /**
     * Get or auto-generate the master encryption key for certificate/key storage.
     */
    public static function getEncryptionKey(): string
    {
        $stored = Setting::get(self::KEY_SETTING, '');
        if (!empty($stored)) {
            $key = base64_decode($stored, true);
            if ($key && strlen($key) === 32) {
                return $key;
            }
        }

        $key = random_bytes(32);
        Setting::set(self::KEY_SETTING, base64_encode($key));
        return $key;
    }

    // ─── Data Encryption/Decryption ────────────────────

    /**
     * Encrypt data with AES-256-GCM.
     * Returns base64(iv . tag . ciphertext)
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $iv = random_bytes(12);
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
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt AES-256-GCM encrypted data.
     */
    public static function decrypt(string $encoded): string
    {
        $key = self::getEncryptionKey();
        $raw = base64_decode($encoded, true);

        if (!$raw || strlen($raw) < 28) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
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
            throw new \RuntimeException('Decryption failed - wrong key or corrupted data');
        }

        return $plaintext;
    }

    // ─── Certificate Storage ──────────────────────────

    public static function encryptCertificate(string $pfxData): string
    {
        return self::encrypt($pfxData);
    }

    public static function decryptCertificate(string $encryptedData): string
    {
        return self::decrypt($encryptedData);
    }

    // ─── PFX/P12 Certificate Parsing ──────────────────

    /**
     * Parse and validate a PFX/P12 certificate file.
     *
     * @param string $pfxData Raw PFX file contents
     * @param string $password PFX password
     * @return array Certificate info
     */
    public static function parseCertificate(string $pfxData, string $password): array
    {
        $certs = [];
        if (!openssl_pkcs12_read($pfxData, $certs, $password)) {
            throw new \RuntimeException('Nie można odczytać certyfikatu PFX. Sprawdź hasło. OpenSSL: ' . openssl_error_string());
        }

        if (empty($certs['cert'])) {
            throw new \RuntimeException('Certyfikat PFX nie zawiera certyfikatu publicznego.');
        }

        if (empty($certs['pkey'])) {
            throw new \RuntimeException('Certyfikat PFX nie zawiera klucza prywatnego.');
        }

        return self::parseCertPem($certs['cert'], true);
    }

    /**
     * Parse an X.509 certificate in PEM format.
     */
    public static function parseCertPem(string $certPem, bool $hasPrivateKey = false): array
    {
        $certResource = openssl_x509_read($certPem);
        if (!$certResource) {
            throw new \RuntimeException('Nie można odczytać certyfikatu X.509.');
        }

        $certInfo = openssl_x509_parse($certResource);
        if (!$certInfo) {
            throw new \RuntimeException('Nie można sparsować certyfikatu X.509.');
        }

        $nip = self::extractNipFromCert($certInfo);
        $pesel = self::extractPeselFromCert($certInfo);
        $fingerprint = openssl_x509_fingerprint($certResource, 'sha256');

        // Determine certificate type
        $certType = 'personal';
        if ($nip && !$pesel) {
            $certType = 'seal';
        }

        $extensions = $certInfo['extensions'] ?? [];
        $keyUsage = $extensions['keyUsage'] ?? '';
        if (strpos($keyUsage, 'Digital Signature') !== false && strpos($keyUsage, 'Non Repudiation') === false) {
            $issuer = $certInfo['issuer']['O'] ?? '';
            if (stripos($issuer, 'KSeF') !== false || stripos($issuer, 'Ministerstwo Finans') !== false) {
                $certType = 'ksef';
            }
        }

        return [
            'subject_cn' => $certInfo['subject']['CN'] ?? $certInfo['subject']['O'] ?? 'Unknown',
            'subject_nip' => $nip,
            'subject_pesel' => $pesel,
            'issuer' => trim(($certInfo['issuer']['O'] ?? '') . ' ' . ($certInfo['issuer']['CN'] ?? '')),
            'serial_number' => $certInfo['serialNumber'] ?? $certInfo['serialNumberHex'] ?? null,
            'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
            'fingerprint' => $fingerprint,
            'cert_type' => $certType,
            'key_usage' => $keyUsage,
            'is_expired' => $certInfo['validTo_time_t'] < time(),
            'days_until_expiry' => max(0, (int)(($certInfo['validTo_time_t'] - time()) / 86400)),
            'has_private_key' => $hasPrivateKey,
        ];
    }

    /**
     * Extract NIP from X.509 certificate fields.
     */
    private static function extractNipFromCert(array $certInfo): ?string
    {
        $serialNumber = $certInfo['subject']['serialNumber'] ?? '';
        if (preg_match('/NIP[:\-]?\s*(\d{10})/', $serialNumber, $m)) {
            return $m[1];
        }

        $orgId = $certInfo['subject']['organizationIdentifier']
            ?? $certInfo['subject']['2.5.4.97'] ?? '';
        if (preg_match('/VATPL[:\-]?\s*(\d{10})/', $orgId, $m)) {
            return $m[1];
        }

        // Direct NIP in organizationIdentifier (without VATPL prefix)
        if (preg_match('/^\d{10}$/', $orgId) && self::validateNip($orgId)) {
            return $orgId;
        }

        $org = $certInfo['subject']['O'] ?? '';
        if (preg_match('/NIP[:\s\-]*(\d{10})/', $org, $m)) {
            return $m[1];
        }

        foreach ($certInfo['subject'] ?? [] as $value) {
            if (is_string($value) && preg_match('/\b(\d{10})\b/', $value, $m)) {
                if (self::validateNip($m[1])) {
                    return $m[1];
                }
            }
        }

        return null;
    }

    /**
     * Extract PESEL from X.509 certificate.
     */
    private static function extractPeselFromCert(array $certInfo): ?string
    {
        $serialNumber = $certInfo['subject']['serialNumber'] ?? '';
        if (preg_match('/PESEL[:\-]?\s*(\d{11})/', $serialNumber, $m)) {
            return $m[1];
        }
        if (preg_match('/^\d{11}$/', $serialNumber)) {
            return $serialNumber;
        }
        return null;
    }

    /**
     * Validate NIP checksum (Polish tax ID).
     */
    public static function validateNip(string $nip): bool
    {
        if (strlen($nip) !== 10 || !ctype_digit($nip)) return false;
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $weights[$i] * (int)$nip[$i];
        }
        return ($sum % 11) === (int)$nip[9];
    }

    // ─── Key Pair Generation ──────────────────────────

    /**
     * Generate EC P-256 key pair for KSeF certificate enrollment.
     *
     * EC P-256 (secp256r1) is recommended by KSeF docs.
     * Returns ['privateKeyPem' => '...', 'publicKeyPem' => '...']
     */
    public static function generateKeyPairEc(): array
    {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1', // = NIST P-256 = secp256r1
        ];

        $keyResource = openssl_pkey_new($config);
        if (!$keyResource) {
            throw new \RuntimeException('Nie można wygenerować klucza EC P-256: ' . openssl_error_string());
        }

        $privateKeyPem = '';
        if (!openssl_pkey_export($keyResource, $privateKeyPem)) {
            throw new \RuntimeException('Nie można wyeksportować klucza prywatnego: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($keyResource);
        $publicKeyPem = $details['key'] ?? '';

        return [
            'privateKeyPem' => $privateKeyPem,
            'publicKeyPem' => $publicKeyPem,
        ];
    }

    /**
     * Generate RSA-2048 key pair for KSeF certificate enrollment.
     */
    public static function generateKeyPairRsa(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $keyResource = openssl_pkey_new($config);
        if (!$keyResource) {
            throw new \RuntimeException('Nie można wygenerować klucza RSA-2048: ' . openssl_error_string());
        }

        $privateKeyPem = '';
        if (!openssl_pkey_export($keyResource, $privateKeyPem)) {
            throw new \RuntimeException('Nie można wyeksportować klucza prywatnego: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($keyResource);
        $publicKeyPem = $details['key'] ?? '';

        return [
            'privateKeyPem' => $privateKeyPem,
            'publicKeyPem' => $publicKeyPem,
        ];
    }

    // ─── CSR Generation ───────────────────────────────

    /**
     * Generate PKCS#10 CSR for KSeF certificate enrollment.
     *
     * The DN attributes MUST exactly match the enrollment data returned by
     * GET /certificates/enrollments/data - modified data will be rejected.
     *
     * @param array $enrollmentData DN attributes from KSeF API
     * @param string $privateKeyPem PEM-encoded private key
     * @param string $algorithm 'ec' or 'rsa'
     * @return string Base64-encoded DER CSR (ready for KSeF API submission)
     */
    public static function generateCsr(array $enrollmentData, string $privateKeyPem, string $algorithm = 'ec'): string
    {
        $dn = [];

        // Map KSeF enrollment data fields to X.509 DN
        if (!empty($enrollmentData['commonName'])) {
            $dn['commonName'] = $enrollmentData['commonName'];
        }
        if (!empty($enrollmentData['countryName'])) {
            $dn['countryName'] = $enrollmentData['countryName'];
        }
        if (!empty($enrollmentData['organizationName'])) {
            $dn['organizationName'] = $enrollmentData['organizationName'];
        }
        if (!empty($enrollmentData['serialNumber'])) {
            $dn['serialNumber'] = $enrollmentData['serialNumber'];
        }
        if (!empty($enrollmentData['surname'])) {
            $dn['surname'] = $enrollmentData['surname'];
        }
        if (!empty($enrollmentData['givenName'])) {
            $dn['givenName'] = $enrollmentData['givenName'];
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new \RuntimeException('Nie można odczytać klucza prywatnego: ' . openssl_error_string());
        }

        // Determine hash algorithm
        $digestAlg = 'sha256';

        $csr = openssl_csr_new($dn, $privateKey, [
            'digest_alg' => $digestAlg,
            'private_key_bits' => ($algorithm === 'rsa') ? 2048 : 256,
            'private_key_type' => ($algorithm === 'rsa') ? OPENSSL_KEYTYPE_RSA : OPENSSL_KEYTYPE_EC,
        ]);

        if (!$csr) {
            throw new \RuntimeException('Nie można wygenerować CSR: ' . openssl_error_string());
        }

        // Export CSR to PEM
        $csrPem = '';
        if (!openssl_csr_export($csr, $csrPem)) {
            throw new \RuntimeException('Nie można wyeksportować CSR: ' . openssl_error_string());
        }

        // Convert PEM to DER base64 (strip headers)
        return self::pemToBase64($csrPem);
    }

    // ─── XAdES-BES Signature ──────────────────────────

    /**
     * Build AuthTokenRequest XML document for KSeF authentication.
     *
     * Schema: http://ksef.mf.gov.pl/auth/token/2.0
     *
     * @param string $challenge Challenge value from /auth/challenge
     * @param string $contextNip NIP for the context identifier
     * @param string $identifierType 'certificateSubject' or 'certificateFingerprint'
     */
    public static function buildAuthTokenRequestXml(
        string $challenge,
        string $contextNip,
        string $identifierType = 'certificateSubject'
    ): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<AuthTokenRequest xmlns="http://ksef.mf.gov.pl/auth/token/2.0"';
        $xml .= ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
        $xml .= '<Challenge>' . htmlspecialchars($challenge, ENT_XML1) . '</Challenge>';
        $xml .= '<ContextIdentifier>';
        $xml .= '<Nip>' . htmlspecialchars($contextNip, ENT_XML1) . '</Nip>';
        $xml .= '</ContextIdentifier>';
        $xml .= '<SubjectIdentifierType>' . htmlspecialchars($identifierType, ENT_XML1) . '</SubjectIdentifierType>';
        $xml .= '</AuthTokenRequest>';

        return $xml;
    }

    /**
     * Sign XML document with XAdES-BES format.
     *
     * Supports two modes:
     * 1. PFX-based: for qualified certificates (podpis kwalifikowany, pieczęć)
     * 2. PEM-based: for KSeF-enrolled certificates (separate key + cert)
     *
     * @param string $xml XML document to sign
     * @param string $pfxOrKeyPem PFX data (mode 1) or PEM private key (mode 2)
     * @param string $passwordOrCertPem PFX password (mode 1) or PEM certificate (mode 2)
     * @param bool $isPem True for PEM mode, false for PFX mode
     * @return string Signed XML with enveloped XAdES-BES signature
     */
    public static function signXml(string $xml, string $pfxOrKeyPem, string $passwordOrCertPem, bool $isPem = false): string
    {
        if ($isPem) {
            $privateKey = openssl_pkey_get_private($pfxOrKeyPem);
            if (!$privateKey) {
                throw new \RuntimeException('Cannot read PEM private key: ' . openssl_error_string());
            }
            $certPem = $passwordOrCertPem;
        } else {
            $certs = [];
            if (!openssl_pkcs12_read($pfxOrKeyPem, $certs, $passwordOrCertPem)) {
                throw new \RuntimeException('Cannot read PFX for signing: ' . openssl_error_string());
            }
            $privateKey = openssl_pkey_get_private($certs['pkey']);
            if (!$privateKey) {
                throw new \RuntimeException('Cannot extract private key from PFX');
            }
            $certPem = $certs['cert'];
        }

        $certResource = openssl_x509_read($certPem);
        if (!$certResource) {
            throw new \RuntimeException('Cannot read certificate for signing');
        }

        // Export cert for base64 and parsing
        $certDer = '';
        openssl_x509_export($certResource, $certDer);
        $certBase64 = self::pemToBase64($certDer);
        $certInfo = openssl_x509_parse($certResource);

        // Determine signing algorithm based on key type
        $keyDetails = openssl_pkey_get_details($privateKey);
        $keyType = $keyDetails['type'] ?? OPENSSL_KEYTYPE_RSA;

        if ($keyType === OPENSSL_KEYTYPE_EC) {
            $signAlgo = OPENSSL_ALGO_SHA256; // ECDSA with SHA-256
            $signAlgoUri = 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256';
        } else {
            $signAlgo = OPENSSL_ALGO_SHA256; // RSA with SHA-256
            $signAlgoUri = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
        }

        $dsNs = 'http://www.w3.org/2000/09/xmldsig#';
        $xadesNs = 'http://uri.etsi.org/01903/v1.3.2#';
        // Use inclusive C14N — matches working KSeF implementations (DSS library, official Java client)
        $c14nAlgo = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

        // Step 1: Compute document digest
        // Apply enveloped-signature (noop on original) + inclusive C14N, then SHA-256
        $origDoc = new \DOMDocument();
        $origDoc->loadXML($xml);
        $docCanonical = $origDoc->C14N(false, false); // inclusive C14N (exclusive=false)
        $docDigest = base64_encode(hash('sha256', $docCanonical, true));

        // Step 2: Prepare XAdES values
        $certDigest = base64_encode(hash('sha256', base64_decode($certBase64), true));
        $issuerName = self::buildIssuerString($certInfo['issuer'] ?? []);
        $serialNumber = $certInfo['serialNumber'] ?? '';
        $signingTime = gmdate('Y-m-d\TH:i:s\Z');

        // Step 3: Build SignedProperties and compute its digest in full document context
        $signedPropsXml = '<xades:SignedProperties Id="SignedProperties">'
            . '<xades:SignedSignatureProperties>'
            . '<xades:SigningTime>' . $signingTime . '</xades:SigningTime>'
            . '<xades:SigningCertificate>'
            . '<xades:Cert>'
            . '<xades:CertDigest>'
            . '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'
            . '<ds:DigestValue>' . $certDigest . '</ds:DigestValue>'
            . '</xades:CertDigest>'
            . '<xades:IssuerSerial>'
            . '<ds:X509IssuerName>' . htmlspecialchars($issuerName, ENT_XML1) . '</ds:X509IssuerName>'
            . '<ds:X509SerialNumber>' . $serialNumber . '</ds:X509SerialNumber>'
            . '</xades:IssuerSerial>'
            . '</xades:Cert>'
            . '</xades:SigningCertificate>'
            . '</xades:SignedSignatureProperties>'
            . '</xades:SignedProperties>';

        // Build temp document with Signature structure to compute SP digest in correct context
        $closingTag = '</AuthTokenRequest>';
        $pos = strrpos($xml, $closingTag);
        if ($pos === false) {
            throw new \RuntimeException('Cannot find closing AuthTokenRequest tag');
        }

        $tempSignature = '<ds:Signature xmlns:ds="' . $dsNs . '" Id="Signature">'
            . '<ds:Object>'
            . '<xades:QualifyingProperties xmlns:xades="' . $xadesNs . '" Target="#Signature">'
            . $signedPropsXml
            . '</xades:QualifyingProperties>'
            . '</ds:Object>'
            . '</ds:Signature>';
        $tempDoc = new \DOMDocument();
        $tempDoc->loadXML(substr($xml, 0, $pos) . $tempSignature . $closingTag);

        $xpath = new \DOMXPath($tempDoc);
        $xpath->registerNamespace('xades', $xadesNs);
        $spNodes = $xpath->query('//xades:SignedProperties[@Id="SignedProperties"]');
        if ($spNodes->length === 0) {
            throw new \RuntimeException('SignedProperties not found in DOM');
        }
        // Inclusive C14N for SP digest (no transforms specified on this reference per working examples)
        $spCanonical = $spNodes->item(0)->C14N(false, false);
        $spDigest = base64_encode(hash('sha256', $spCanonical, true));

        // Step 4: Build SignedInfo
        // - Document reference: only enveloped-signature transform (no C14N transform per working examples)
        // - SignedProperties reference: no transforms (per working examples)
        $signedInfoXml = '<ds:SignedInfo xmlns:ds="' . $dsNs . '">'
            . '<ds:CanonicalizationMethod Algorithm="' . $c14nAlgo . '"/>'
            . '<ds:SignatureMethod Algorithm="' . $signAlgoUri . '"/>'
            . '<ds:Reference URI="">'
            . '<ds:Transforms>'
            . '<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>'
            . '</ds:Transforms>'
            . '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'
            . '<ds:DigestValue>' . $docDigest . '</ds:DigestValue>'
            . '</ds:Reference>'
            . '<ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#SignedProperties">'
            . '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>'
            . '<ds:DigestValue>' . $spDigest . '</ds:DigestValue>'
            . '</ds:Reference>'
            . '</ds:SignedInfo>';

        // Step 5: Canonicalize SignedInfo with inclusive C14N and sign
        // Use full document context for C14N to ensure correct namespace handling
        $fullSignatureXml = '<ds:Signature xmlns:ds="' . $dsNs . '" Id="Signature">'
            . $signedInfoXml
            . '<ds:SignatureValue>PLACEHOLDER</ds:SignatureValue>'
            . '<ds:KeyInfo><ds:X509Data>'
            . '<ds:X509Certificate>' . $certBase64 . '</ds:X509Certificate>'
            . '</ds:X509Data></ds:KeyInfo>'
            . '<ds:Object>'
            . '<xades:QualifyingProperties xmlns:xades="' . $xadesNs . '" Target="#Signature">'
            . $signedPropsXml
            . '</xades:QualifyingProperties>'
            . '</ds:Object>'
            . '</ds:Signature>';

        $fullDoc = new \DOMDocument();
        $fullDoc->loadXML(substr($xml, 0, $pos) . $fullSignatureXml . $closingTag);
        $xpathFull = new \DOMXPath($fullDoc);
        $xpathFull->registerNamespace('ds', $dsNs);

        $siNodes = $xpathFull->query('//ds:SignedInfo');
        if ($siNodes->length === 0) {
            throw new \RuntimeException('SignedInfo not found in full DOM');
        }
        $siCanonical = $siNodes->item(0)->C14N(false, false); // inclusive C14N

        $signatureRaw = '';
        if (!openssl_sign($siCanonical, $signatureRaw, $privateKey, $signAlgo)) {
            throw new \RuntimeException('XML signing failed: ' . openssl_error_string());
        }

        // For ECDSA: convert DER-encoded signature to IEEE P1363 (raw r||s) format
        // PHP's openssl_sign returns DER ASN.1, but XML DSig requires raw r||s
        if ($keyType === OPENSSL_KEYTYPE_EC) {
            $signatureRaw = self::ecDerToP1363($signatureRaw, $keyDetails);

        }

        $signatureValue = base64_encode($signatureRaw);

        // Step 6: Assemble final Signature XML via string concatenation
        $signatureXml = '<ds:Signature xmlns:ds="' . $dsNs . '" Id="Signature">'
            . $signedInfoXml
            . '<ds:SignatureValue>' . $signatureValue . '</ds:SignatureValue>'
            . '<ds:KeyInfo>'
            . '<ds:X509Data>'
            . '<ds:X509Certificate>' . $certBase64 . '</ds:X509Certificate>'
            . '</ds:X509Data>'
            . '</ds:KeyInfo>'
            . '<ds:Object>'
            . '<xades:QualifyingProperties xmlns:xades="' . $xadesNs . '" Target="#Signature">'
            . $signedPropsXml
            . '</xades:QualifyingProperties>'
            . '</ds:Object>'
            . '</ds:Signature>';

        // Step 7: Insert signature before closing tag
        return substr($xml, 0, $pos) . $signatureXml . $closingTag;
    }

    /**
     * Convert ECDSA DER-encoded signature to IEEE P1363 (raw r||s) format for XML DSig.
     * PHP's openssl_sign returns DER (ASN.1 SEQUENCE { INTEGER r, INTEGER s }).
     * XML Digital Signature requires raw concatenation of r and s, each padded to key size.
     */
    private static function ecDerToP1363(string $derSig, array $keyDetails): string
    {
        // Determine component size based on curve
        $curveBits = $keyDetails['bits'] ?? 256;
        $componentSize = (int)ceil($curveBits / 8); // 32 for P-256, 48 for P-384, 66 for P-521

        // Parse DER: SEQUENCE { INTEGER r, INTEGER s }
        $offset = 0;
        if (ord($derSig[$offset]) !== 0x30) {
            throw new \RuntimeException('Invalid ECDSA DER signature: expected SEQUENCE');
        }
        $offset++; // skip SEQUENCE tag
        // Skip length byte(s)
        $seqLen = ord($derSig[$offset]);
        $offset++;
        if ($seqLen & 0x80) {
            $offset += ($seqLen & 0x7F); // multi-byte length
        }

        // Read r INTEGER
        if (ord($derSig[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid ECDSA DER signature: expected INTEGER for r');
        }
        $offset++;
        $rLen = ord($derSig[$offset]);
        $offset++;
        $r = substr($derSig, $offset, $rLen);
        $offset += $rLen;

        // Read s INTEGER
        if (ord($derSig[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid ECDSA DER signature: expected INTEGER for s');
        }
        $offset++;
        $sLen = ord($derSig[$offset]);
        $offset++;
        $s = substr($derSig, $offset, $sLen);

        // Remove leading zero bytes (ASN.1 padding for positive integers)
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        // Pad to component size
        $r = str_pad($r, $componentSize, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, $componentSize, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * Convert IEEE P1363 (raw r||s) ECDSA signature back to DER format for openssl_verify.
     */
    private static function ecP1363ToDer(string $p1363Sig): string
    {
        $half = intdiv(strlen($p1363Sig), 2);
        $r = substr($p1363Sig, 0, $half);
        $s = substr($p1363Sig, $half);

        // Remove leading zeros but ensure positive (add 0x00 if high bit set)
        $r = ltrim($r, "\x00") ?: "\x00";
        $s = ltrim($s, "\x00") ?: "\x00";
        if (ord($r[0]) & 0x80) $r = "\x00" . $r;
        if (ord($s[0]) & 0x80) $s = "\x00" . $s;

        $rDer = "\x02" . chr(strlen($r)) . $r;
        $sDer = "\x02" . chr(strlen($s)) . $s;
        $seq = $rDer . $sDer;

        return "\x30" . chr(strlen($seq)) . $seq;
    }

    /**
     * Convert PEM-encoded data to raw base64 (strip headers/footers).
     */
    public static function pemToBase64(string $pem): string
    {
        // Remove any PEM header/footer lines
        $pem = preg_replace('/-----(BEGIN|END)\s+[A-Z\s]+-----/', '', $pem);
        return trim(str_replace(["\r", "\n", ' '], '', $pem));
    }

    /**
     * Build X.500 issuer DN string from parsed issuer array.
     */
    private static function buildIssuerString(array $issuer): string
    {
        $parts = [];
        $order = ['CN', 'O', 'OU', 'L', 'ST', 'C'];
        foreach ($order as $key) {
            if (isset($issuer[$key])) {
                $val = is_array($issuer[$key]) ? implode(', ', $issuer[$key]) : $issuer[$key];
                $parts[] = "{$key}={$val}";
            }
        }
        return implode(', ', $parts);
    }

    // ─── PEM Certificate Parsing (.crt + .key) ─────────

    /**
     * Parse and validate PEM certificate (.crt) + private key (.key) pair.
     *
     * Used for certificates generated directly in KSeF or electronic seals (pieczęć elektroniczna).
     *
     * @param string $certPem PEM-encoded certificate (.crt file contents)
     * @param string $keyPem PEM-encoded private key (.key file contents)
     * @param string|null $password Optional password for encrypted private key
     * @return array Certificate info (same structure as parseCertificate)
     */
    public static function parsePemCertificate(string $certPem, string $keyPem, ?string $password = null): array
    {
        $certResource = openssl_x509_read($certPem);
        if (!$certResource) {
            throw new \RuntimeException('Nie można odczytać certyfikatu .crt. Sprawdź format pliku. OpenSSL: ' . openssl_error_string());
        }

        $privateKey = openssl_pkey_get_private($keyPem, $password ?? '');
        if (!$privateKey) {
            throw new \RuntimeException('Nie można odczytać klucza prywatnego .key. Sprawdź hasło lub format pliku. OpenSSL: ' . openssl_error_string());
        }

        if (!openssl_x509_check_private_key($certResource, $privateKey)) {
            throw new \RuntimeException('Certyfikat i klucz prywatny nie tworzą pary. Upewnij się, że pliki .crt i .key pochodzą z tego samego zestawu.');
        }

        return self::parseCertPem($certPem, true);
    }

    /**
     * Validate uploaded PEM certificate files (.crt + .key).
     */
    public static function validatePemUpload(array $crtFile, array $keyFile): array
    {
        $errors = [];
        $maxSizeKb = (int) Setting::get('ksef_max_cert_size_kb', '50');

        if ($crtFile['size'] > $maxSizeKb * 1024) {
            $errors[] = "Plik certyfikatu .crt jest zbyt duży (max {$maxSizeKb} KB).";
        }
        if ($keyFile['size'] > $maxSizeKb * 1024) {
            $errors[] = "Plik klucza .key jest zbyt duży (max {$maxSizeKb} KB).";
        }

        $crtExt = strtolower(pathinfo($crtFile['name'], PATHINFO_EXTENSION));
        if (!in_array($crtExt, ['crt', 'pem', 'cer'])) {
            $errors[] = 'Dozwolone formaty certyfikatu: .crt, .pem, .cer';
        }

        $keyExt = strtolower(pathinfo($keyFile['name'], PATHINFO_EXTENSION));
        if (!in_array($keyExt, ['key', 'pem'])) {
            $errors[] = 'Dozwolone formaty klucza: .key, .pem';
        }

        return $errors;
    }

    // ─── Upload Validation ────────────────────────────

    /**
     * Validate uploaded certificate file (PFX/P12).
     */
    public static function validateUpload(array $file, string $password): array
    {
        $errors = [];

        $maxSizeKb = (int) Setting::get('ksef_max_cert_size_kb', '50');
        if ($file['size'] > $maxSizeKb * 1024) {
            $errors[] = "Plik certyfikatu jest zbyt duży (max {$maxSizeKb} KB).";
            return $errors;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = array_map('trim', explode(',', Setting::get('ksef_allowed_cert_types', 'pfx,p12')));
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Dozwolone formaty certyfikatu: ' . implode(', ', $allowed);
            return $errors;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['application/x-pkcs12', 'application/octet-stream', 'application/pkcs12'];
        if (!in_array($mime, $allowedMimes)) {
            $errors[] = 'Nieprawidłowy typ pliku certyfikatu.';
            return $errors;
        }

        $pfxData = file_get_contents($file['tmp_name']);
        if (!$pfxData) {
            $errors[] = 'Nie można odczytać pliku certyfikatu.';
            return $errors;
        }

        try {
            $certInfo = self::parseCertificate($pfxData, $password);

            if ($certInfo['is_expired']) {
                $errors[] = 'Certyfikat wygasł (' . $certInfo['valid_to'] . ').';
            }

            if ($certInfo['days_until_expiry'] < 7 && !$certInfo['is_expired']) {
                $errors[] = 'UWAGA: Certyfikat wygasa za ' . $certInfo['days_until_expiry'] . ' dni.';
            }
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Check if certificate will expire within given days.
     */
    public static function isExpiringSoon(string $validTo, int $daysThreshold = 30): bool
    {
        $expiry = strtotime($validTo);
        return $expiry && ($expiry - time()) < ($daysThreshold * 86400);
    }
}
