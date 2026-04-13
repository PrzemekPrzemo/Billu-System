-- Migration: Scheduled exports
-- Version: 6.3

CREATE TABLE IF NOT EXISTS scheduled_exports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    format ENUM('excel','pdf','jpk_fa','jpk_vat7','comarch_optima','sage','enova') NOT NULL,
    frequency ENUM('monthly','weekly') NOT NULL DEFAULT 'monthly',
    day_of_month INT DEFAULT 5,
    email VARCHAR(255) NOT NULL,
    include_rejected TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_by_type ENUM('admin','office') NOT NULL,
    created_by_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_next_run (next_run_at, is_active),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);
