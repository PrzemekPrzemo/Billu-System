-- Migration v10.2: Add KSeF connection status tracking columns
-- Tracks API health check results per client for dashboard warnings and auto-retry

ALTER TABLE client_ksef_configs
    ADD COLUMN ksef_connection_status ENUM('ok', 'failed', 'unknown') DEFAULT 'unknown' AFTER last_error,
    ADD COLUMN ksef_connection_checked_at DATETIME DEFAULT NULL AFTER ksef_connection_status,
    ADD COLUMN ksef_connection_error TEXT DEFAULT NULL AFTER ksef_connection_checked_at;

ALTER TABLE client_ksef_configs ADD INDEX idx_connection_status (ksef_connection_status, ksef_connection_checked_at);
