-- Import Templates (saved column mappings for CSV/Excel import)

CREATE TABLE IF NOT EXISTS import_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    column_mapping JSON NOT NULL,
    `separator` VARCHAR(5) DEFAULT ';',
    encoding VARCHAR(20) DEFAULT 'UTF-8',
    skip_rows INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_office (office_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
