-- Migration v41.0: PFRON declarations
-- Companies with 25+ employees must submit monthly PFRON declarations
-- If disability ratio < 6%, the company pays a levy to PFRON

CREATE TABLE IF NOT EXISTS hr_pfron_declarations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    total_employees INT UNSIGNED NOT NULL DEFAULT 0,
    disabled_employees INT UNSIGNED NOT NULL DEFAULT 0,
    disability_ratio DECIMAL(5,4) NOT NULL DEFAULT 0,
    pfron_liable TINYINT(1) NOT NULL DEFAULT 0,
    levy_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    avg_salary DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Przeciętne wynagrodzenie (GUS) used for calculation',
    status ENUM('draft','calculated','submitted') NOT NULL DEFAULT 'draft',
    notes TEXT DEFAULT NULL,
    calculated_by_type VARCHAR(16) DEFAULT NULL,
    calculated_by_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_period (client_id, period_month, period_year),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: hr_employees.disability_level already exists (ENUM: none/mild/moderate/severe)
-- Used by PFRON calculations: mild/moderate/severe count as disabled employees.
