-- BiLLU v4.0 - KSeF Certificate Enrollment & Enhanced Authentication
-- Migration: Adds KSeF certificate enrollment support, fixes API paths, extends operations
-- Run AFTER v3.0 migration
-- Safe to re-run (uses IF NOT EXISTS / column existence checks)

-- ============================================================
-- Extend client_ksef_configs for KSeF certificate enrollment
-- ============================================================

-- Add KSeF certificate fields (separate from qualified cert PFX)
-- Using procedure to make ADD COLUMN idempotent
DELIMITER //
DROP PROCEDURE IF EXISTS _migrate_v40//
CREATE PROCEDURE _migrate_v40()
BEGIN
    -- cert_ksef_private_key_encrypted
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_private_key_encrypted') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_private_key_encrypted TEXT DEFAULT NULL
            COMMENT 'AES-256-GCM encrypted private key (PEM) for KSeF-enrolled certificate'
            AFTER cert_serial_number;
    END IF;

    -- cert_ksef_pem
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_pem') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_pem TEXT DEFAULT NULL
            COMMENT 'KSeF-issued certificate (PEM/DER base64) - public, no encryption needed'
            AFTER cert_ksef_private_key_encrypted;
    END IF;

    -- cert_ksef_serial_number
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_serial_number') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_serial_number VARCHAR(128) DEFAULT NULL
            COMMENT 'Serial number of KSeF-enrolled certificate'
            AFTER cert_ksef_pem;
    END IF;

    -- cert_ksef_name
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_name') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_name VARCHAR(255) DEFAULT NULL
            COMMENT 'Display name of KSeF certificate'
            AFTER cert_ksef_serial_number;
    END IF;

    -- cert_ksef_valid_from
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_valid_from') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_valid_from DATETIME DEFAULT NULL
            AFTER cert_ksef_name;
    END IF;

    -- cert_ksef_valid_to
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_valid_to') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_valid_to DATETIME DEFAULT NULL
            AFTER cert_ksef_valid_from;
    END IF;

    -- cert_ksef_status
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_status') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_status ENUM('none', 'enrolling', 'active', 'revoked', 'expired') NOT NULL DEFAULT 'none'
            COMMENT 'Status of KSeF-enrolled certificate'
            AFTER cert_ksef_valid_to;
    END IF;

    -- cert_ksef_enrollment_ref
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_enrollment_ref') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_enrollment_ref VARCHAR(255) DEFAULT NULL
            COMMENT 'Reference number during enrollment polling'
            AFTER cert_ksef_status;
    END IF;

    -- cert_ksef_type
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND COLUMN_NAME = 'cert_ksef_type') THEN
        ALTER TABLE client_ksef_configs
            ADD COLUMN cert_ksef_type ENUM('Authentication', 'Offline') DEFAULT 'Authentication'
            COMMENT 'KSeF certificate type'
            AFTER cert_ksef_enrollment_ref;
    END IF;

    -- Add index for enrollment reference lookups (safe: check if exists)
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_ksef_configs'
        AND INDEX_NAME = 'idx_ksef_enrollment_ref') THEN
        ALTER TABLE client_ksef_configs ADD INDEX idx_ksef_enrollment_ref (cert_ksef_enrollment_ref);
    END IF;
END//
DELIMITER ;

CALL _migrate_v40();
DROP PROCEDURE IF EXISTS _migrate_v40;

-- Update auth_method enum to include 'ksef_cert' (safe to re-run - MODIFY is idempotent)
ALTER TABLE client_ksef_configs
    MODIFY COLUMN auth_method ENUM('none', 'token', 'certificate', 'ksef_cert') NOT NULL DEFAULT 'none';

-- Update cert_type enum to be more specific
ALTER TABLE client_ksef_configs
    MODIFY COLUMN cert_type ENUM('personal', 'seal', 'ksef', 'ksef_enrolled') DEFAULT NULL
    COMMENT 'personal=podpis kwalifikowany, seal=pieczęć elektroniczna, ksef=certyfikat KSeF (uploaded), ksef_enrolled=certyfikat KSeF (wygenerowany)';

-- Extend operations log with new operation types (safe to re-run)
ALTER TABLE ksef_operations_log
    MODIFY COLUMN operation ENUM(
        'authenticate', 'token_refresh', 'session_open', 'session_close',
        'invoice_query', 'invoice_download', 'invoice_send',
        'permissions_query', 'permissions_grant', 'permissions_revoke',
        'certificate_upload', 'certificate_delete', 'token_generate',
        'import_batch', 'export_async', 'status_check',
        'cert_enroll_start', 'cert_enroll_poll', 'cert_enroll_complete',
        'cert_retrieve', 'cert_revoke', 'cert_limits_check'
    ) NOT NULL;

-- ============================================================
-- New settings for v4.0
-- ============================================================
INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('ksef_cert_enrollment_enabled', '1', 'Allow clients to enroll KSeF certificates from their panel'),
    ('ksef_api_path_prefix', '/v2', 'API path prefix for KSeF endpoints (/v2 for new hosts, /api/v2 for deprecated)')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- Fix API path prefix: new hosts use /v2, not /api/v2
UPDATE settings SET setting_value = '/v2' WHERE setting_key = 'ksef_api_path_prefix';
