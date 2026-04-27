-- =============================================
-- Migration v41.0: Trusted devices ("remember me 2FA bypass")
-- Token plaintext lives only in user's HttpOnly cookie; DB stores
-- only sha256 of the token, so a DB leak does not bypass 2FA.
-- Default TTL: 5 days (set by application code).
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

CREATE TABLE IF NOT EXISTS trusted_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','client','office','employee','client_employee') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE COMMENT 'sha256 of plaintext device token (cookie value)',
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    device_label VARCHAR(255) DEFAULT NULL COMMENT 'Optional human-readable device name',
    INDEX idx_user (user_type, user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v41.0_trusted_devices.sql');
