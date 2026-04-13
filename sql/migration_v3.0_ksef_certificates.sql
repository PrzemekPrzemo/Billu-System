-- BiLLU v3.0 - KSeF Certificate Authentication & Full API Integration
-- Migration: Adds client certificate storage, KSeF sessions, and extended operations

-- ============================================================
-- Table: client_ksef_configs (KSeF configuration per client)
-- Replaces simple ksef_api_token + ksef_enabled fields
-- ============================================================
CREATE TABLE IF NOT EXISTS client_ksef_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,

    -- Authentication method
    auth_method ENUM('none', 'token', 'certificate') NOT NULL DEFAULT 'none',
    is_active TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'KSeF integration active',

    -- Token-based auth
    ksef_token_encrypted TEXT DEFAULT NULL COMMENT 'AES-256-GCM encrypted KSeF token',

    -- Certificate-based auth (XAdES)
    cert_pfx_encrypted MEDIUMBLOB DEFAULT NULL COMMENT 'AES-256-GCM encrypted PFX/P12 certificate',
    cert_fingerprint VARCHAR(64) DEFAULT NULL COMMENT 'SHA-256 fingerprint of the certificate',
    cert_subject_cn VARCHAR(255) DEFAULT NULL COMMENT 'CN from certificate subject',
    cert_subject_nip VARCHAR(10) DEFAULT NULL COMMENT 'NIP extracted from certificate (if org cert)',
    cert_issuer VARCHAR(255) DEFAULT NULL COMMENT 'Certificate issuer (CA)',
    cert_valid_from DATETIME DEFAULT NULL,
    cert_valid_to DATETIME DEFAULT NULL,
    cert_type ENUM('personal', 'seal', 'ksef') DEFAULT NULL COMMENT 'personal=podpis, seal=pieczęć, ksef=certyfikat KSeF',
    cert_serial_number VARCHAR(128) DEFAULT NULL COMMENT 'Certificate serial number',

    -- KSeF-specific settings
    ksef_environment ENUM('test', 'demo', 'production') NOT NULL DEFAULT 'test',
    ksef_context_nip VARCHAR(10) DEFAULT NULL COMMENT 'NIP used for KSeF context (defaults to client NIP)',
    ksef_permissions JSON DEFAULT NULL COMMENT 'Granted KSeF permissions: InvoiceRead, InvoiceWrite, etc.',

    -- Cached session data (ephemeral, auto-cleared)
    access_token TEXT DEFAULT NULL COMMENT 'Current JWT access token (temporary)',
    access_token_expires_at DATETIME DEFAULT NULL,
    refresh_token TEXT DEFAULT NULL COMMENT 'Current refresh token (temporary)',
    refresh_token_expires_at DATETIME DEFAULT NULL,

    -- Audit
    configured_by_type ENUM('admin', 'office', 'client') DEFAULT NULL,
    configured_by_id INT UNSIGNED DEFAULT NULL,
    last_import_at DATETIME DEFAULT NULL,
    last_import_status VARCHAR(50) DEFAULT NULL,
    last_error TEXT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_auth_method (auth_method),
    INDEX idx_active (is_active),
    INDEX idx_cert_fingerprint (cert_fingerprint),
    INDEX idx_cert_valid_to (cert_valid_to)
) ENGINE=InnoDB;

-- ============================================================
-- Table: ksef_operations_log (detailed operation audit trail)
-- ============================================================
CREATE TABLE IF NOT EXISTS ksef_operations_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    operation ENUM(
        'authenticate', 'token_refresh', 'session_open', 'session_close',
        'invoice_query', 'invoice_download', 'invoice_send',
        'permissions_query', 'permissions_grant', 'permissions_revoke',
        'certificate_upload', 'certificate_delete', 'token_generate',
        'import_batch', 'export_async', 'status_check'
    ) NOT NULL,
    status ENUM('started', 'success', 'failed', 'timeout') NOT NULL DEFAULT 'started',
    request_summary TEXT DEFAULT NULL COMMENT 'Sanitized request info (no secrets)',
    response_summary TEXT DEFAULT NULL COMMENT 'Sanitized response info',
    error_message TEXT DEFAULT NULL,
    ksef_reference_number VARCHAR(255) DEFAULT NULL,
    duration_ms INT UNSIGNED DEFAULT NULL,
    performed_by_type ENUM('admin', 'office', 'client', 'system') NOT NULL,
    performed_by_id INT UNSIGNED NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_op (client_id, operation),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_ksef_ref (ksef_reference_number)
) ENGINE=InnoDB;

-- ============================================================
-- Migrate existing client KSeF data to new table
-- ============================================================
INSERT INTO client_ksef_configs (client_id, auth_method, is_active, ksef_token_encrypted, ksef_environment, ksef_context_nip)
SELECT
    c.id,
    CASE WHEN c.ksef_api_token IS NOT NULL AND c.ksef_api_token != '' THEN 'token' ELSE 'none' END,
    c.ksef_enabled,
    c.ksef_api_token,  -- Will need re-encryption via migration script
    COALESCE((SELECT s.setting_value FROM settings s WHERE s.setting_key = 'ksef_api_env'), 'test'),
    c.nip
FROM clients c
WHERE c.ksef_enabled = 1 OR (c.ksef_api_token IS NOT NULL AND c.ksef_api_token != '')
ON DUPLICATE KEY UPDATE auth_method = VALUES(auth_method);

-- ============================================================
-- New settings for v3.0
-- ============================================================
INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('ksef_cert_encryption_key', '', 'Base64-encoded 256-bit key for encrypting stored certificates (auto-generated on first use)'),
    ('ksef_max_cert_size_kb', '50', 'Maximum certificate file size in KB'),
    ('ksef_allowed_cert_types', 'pfx,p12', 'Allowed certificate file extensions'),
    ('ksef_cert_upload_enabled', '1', 'Allow clients to upload certificates from their panel'),
    ('ksef_token_auth_enabled', '1', 'Allow token-based KSeF authentication'),
    ('ksef_default_environment', 'test', 'Default KSeF environment for new clients')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- ============================================================
-- Add storage directory marker for certificate files
-- (actual directory created by PHP on first use)
-- ============================================================
-- storage/ksef_certs/ - encrypted certificate cache
-- storage/logs/ksef/  - API debug logs (already exists)
