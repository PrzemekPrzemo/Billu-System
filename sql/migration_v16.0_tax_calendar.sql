-- Migration v16.0: Tax Calendar - per-client tax configuration and alert tracking

CREATE TABLE IF NOT EXISTS client_tax_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    vat_period ENUM('monthly','quarterly','none') NOT NULL DEFAULT 'monthly',
    taxation_type ENUM('PIT','CIT','none') NOT NULL DEFAULT 'PIT',
    tax_form ENUM('liniowy','skala','ryczalt','karta') DEFAULT 'skala',
    zus_payer_type ENUM('employer','self_employed','none') NOT NULL DEFAULT 'self_employed',
    jpk_vat_required TINYINT(1) NOT NULL DEFAULT 1,
    alert_days_before TINYINT UNSIGNED NOT NULL DEFAULT 5,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tax_calendar_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    obligation_type VARCHAR(30) NOT NULL,
    deadline_date DATE NOT NULL,
    alert_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_obligation_date (client_id, obligation_type, deadline_date),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v16.0_tax_calendar.sql');
