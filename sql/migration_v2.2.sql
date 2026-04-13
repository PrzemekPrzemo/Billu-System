-- Migration v2.2: Per-client KSeF token + JPK_v3 support - idempotent

-- Add per-client KSeF API token (skip if exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'ksef_api_token');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE clients ADD COLUMN ksef_api_token VARCHAR(500) DEFAULT NULL AFTER has_cost_centers',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'ksef_enabled');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE clients ADD COLUMN ksef_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER ksef_api_token',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Track which report format was generated (skip if exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'report_format');
SET @sql = IF(@col_exists = 0,
    "ALTER TABLE reports ADD COLUMN report_format ENUM('excel','jpk_xml') NOT NULL DEFAULT 'excel' AFTER cost_center_name",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'xml_path');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reports ADD COLUMN xml_path VARCHAR(500) DEFAULT NULL AFTER xls_path',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
