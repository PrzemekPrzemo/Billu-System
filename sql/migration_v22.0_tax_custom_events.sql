-- Migration v22.0: Custom tax calendar events for offices
-- Allows accounting offices to add custom deadlines/events per client

CREATE TABLE IF NOT EXISTS tax_custom_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#6366f1',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_office_date (office_id, event_date),
    INDEX idx_client_date (client_id, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v22.0_tax_custom_events.sql');
