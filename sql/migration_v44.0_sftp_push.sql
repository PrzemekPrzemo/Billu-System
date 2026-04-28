-- =============================================
-- Migration v44.0: SFTP push to office's remote server
-- Office admin configures one SFTP target per office. Each client of
-- that office can independently opt in to having specific kinds of
-- files pushed (HR / messages / invoices / exports / payslips).
-- Credentials are stored encrypted (AES-256-GCM) using App\Core\Crypto;
-- never written here in plaintext.
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

-- ── Office-level SFTP configuration ────────────
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_host VARCHAR(255) DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_port SMALLINT UNSIGNED DEFAULT 22;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_user VARCHAR(255) DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_password_enc TEXT DEFAULT NULL COMMENT 'AES-256-GCM ciphertext';
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_private_key_enc TEXT DEFAULT NULL COMMENT 'AES-256-GCM ciphertext (PEM)';
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_key_passphrase_enc TEXT DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_base_path VARCHAR(500) NOT NULL DEFAULT '/';
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_host_fingerprint VARCHAR(150) DEFAULT NULL COMMENT 'sha256:base64 of host pubkey (TOFU)';
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_last_test_at DATETIME DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS sftp_last_test_result VARCHAR(40) DEFAULT NULL;

-- ── Client-level push opt-ins ──────────────────
ALTER TABLE clients ADD COLUMN IF NOT EXISTS sftp_push_files     TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS sftp_push_messages  TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS sftp_push_invoices  TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS sftp_push_exports   TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS sftp_push_payslips  TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS sftp_subdir VARCHAR(255) DEFAULT NULL COMMENT 'optional override; default = NIP/';

-- ── Queue (worker drains it via cron) ──────────
CREATE TABLE IF NOT EXISTS sftp_queue (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id       INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    source_type     ENUM('files','messages','invoices','exports','payslips') NOT NULL,
    source_ref      VARCHAR(255) DEFAULT NULL COMMENT 'optional: invoice_id / file_id / message_id for traceability',
    local_path      VARCHAR(1024) NOT NULL,
    remote_filename VARCHAR(255) NOT NULL,
    status          ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error      TEXT DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sent_at         DATETIME DEFAULT NULL,
    INDEX idx_status_created (status, created_at),
    INDEX idx_office (office_id),
    INDEX idx_client (client_id),
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v44.0_sftp_push.sql');
