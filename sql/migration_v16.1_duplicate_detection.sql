-- Migration v16.1: Invoice duplicate detection - candidates tracking and acknowledged flag

CREATE TABLE IF NOT EXISTS duplicate_candidates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_type ENUM('purchase','sales') NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    duplicate_of_id INT UNSIGNED NOT NULL,
    match_score TINYINT UNSIGNED NOT NULL DEFAULT 100,
    match_details VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','dismissed','confirmed') NOT NULL DEFAULT 'pending',
    reviewed_by_type VARCHAR(20) DEFAULT NULL,
    reviewed_by_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_type, invoice_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS duplicate_acknowledged TINYINT(1) NOT NULL DEFAULT 0 AFTER internal_notes;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v16.1_duplicate_detection.sql');
