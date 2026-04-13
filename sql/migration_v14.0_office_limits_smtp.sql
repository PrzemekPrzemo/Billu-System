-- Office limits
ALTER TABLE offices ADD COLUMN max_employees INT UNSIGNED DEFAULT NULL;
ALTER TABLE offices ADD COLUMN max_clients INT UNSIGNED DEFAULT NULL;

-- Per-office SMTP configuration
CREATE TABLE IF NOT EXISTS office_smtp_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED NOT NULL UNIQUE,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host VARCHAR(255) NOT NULL DEFAULT '',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_encryption ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
    smtp_user VARCHAR(255) NOT NULL DEFAULT '',
    smtp_pass_encrypted TEXT DEFAULT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v14.0_office_limits_smtp.sql');
