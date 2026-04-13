-- Migration v2.1: Per-client cost centers (MPK) - idempotent

-- Add has_cost_centers flag to clients (skip if exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'has_cost_centers');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE clients ADD COLUMN has_cost_centers TINYINT(1) NOT NULL DEFAULT 0 AFTER regon',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Cost centers per client (max 10)
CREATE TABLE IF NOT EXISTS client_cost_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_active (client_id, is_active)
) ENGINE=InnoDB;

-- Add cost_center_id to invoices (skip if exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'cost_center_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE invoices ADD COLUMN cost_center_id INT UNSIGNED DEFAULT NULL AFTER cost_center',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add FK constraint (skip if exists)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND CONSTRAINT_NAME = 'fk_invoice_cost_center');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE invoices ADD CONSTRAINT fk_invoice_cost_center FOREIGN KEY (cost_center_id) REFERENCES client_cost_centers(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add cost_center_name to reports (skip if exists)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'cost_center_name');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reports ADD COLUMN cost_center_name VARCHAR(255) DEFAULT NULL AFTER report_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
