-- Client internal notes (visible only to office staff)
CREATE TABLE IF NOT EXISTS client_internal_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    office_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_office (client_id, office_id),
    INDEX idx_pinned (office_id, is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('v18.0_client_notes')
ON DUPLICATE KEY UPDATE version = version;
