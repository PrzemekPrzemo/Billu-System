-- API JWT tokens table for mobile app authentication
-- Run this migration to add REST API support

CREATE TABLE IF NOT EXISTS api_tokens (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id           INT UNSIGNED NOT NULL,
    refresh_token_hash  VARCHAR(64) NOT NULL UNIQUE,
    device_name         VARCHAR(255) DEFAULT NULL,
    ip_address          VARCHAR(45)  DEFAULT NULL,
    expires_at          TIMESTAMP    NOT NULL,
    revoked_at          TIMESTAMP    NULL DEFAULT NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client    (client_id),
    INDEX idx_expires   (expires_at),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FCM push notification tokens
CREATE TABLE IF NOT EXISTS client_fcm_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    fcm_token   TEXT NOT NULL,
    device_name VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
