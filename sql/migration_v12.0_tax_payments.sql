-- Migration v12.0: Tax payments (VAT/PIT/CIT) per client per month
-- Biuro/opiekun wprowadza kwoty podatkow dla klienta co miesiac

CREATE TABLE IF NOT EXISTS tax_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    tax_type ENUM('VAT','PIT','CIT') NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('do_zaplaty','do_przeniesienia') NOT NULL DEFAULT 'do_zaplaty',
    created_by_type ENUM('office','employee') NOT NULL,
    created_by_id INT UNSIGNED NOT NULL,
    updated_by_type ENUM('office','employee') DEFAULT NULL,
    updated_by_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_period_tax (client_id, year, month, tax_type),
    INDEX idx_client_year (client_id, year),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v12.0_tax_payments.sql');
