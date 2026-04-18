-- Migration v38.0: HR multi-client status cache
-- Supports batch operations dashboard for accounting offices

CREATE TABLE IF NOT EXISTS hr_client_monthly_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    payroll_status VARCHAR(20) DEFAULT NULL,
    zus_status VARCHAR(20) DEFAULT NULL,
    pit_status VARCHAR(20) DEFAULT NULL,
    employee_count INT UNSIGNED NOT NULL DEFAULT 0,
    pending_leaves INT UNSIGNED NOT NULL DEFAULT 0,
    total_employer_cost DECIMAL(12,2) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_period (client_id, period_month, period_year),
    INDEX idx_period (period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
