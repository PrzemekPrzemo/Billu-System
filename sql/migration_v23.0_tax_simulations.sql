-- Migration v23.0: Tax simulation history for calculator
CREATE TABLE IF NOT EXISTS tax_simulations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    revenue DECIMAL(12,2) NOT NULL,
    is_gross TINYINT(1) DEFAULT 0,
    ryczalt_rate DECIMAL(5,3),
    costs DECIMAL(12,2) DEFAULT 0,
    zus_variant VARCHAR(30) DEFAULT 'full',
    results_json TEXT,
    best_option VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_office_client (office_id, client_id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v23.0_tax_simulations.sql');
