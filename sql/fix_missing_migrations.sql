-- =============================================
-- BiLLU: NAPRAWA BRAKUJĄCYCH MIGRACJI
-- Wklej w phpMyAdmin → zakładka SQL → Wykonaj
-- Bezpieczne do wielokrotnego uruchomienia (IF NOT EXISTS / IF EXISTS)
-- =============================================

-- ══════════════════════════════════════════════
-- v17.0: Exchange rate fields for issued_invoices
-- ══════════════════════════════════════════════
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(10,6) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS exchange_rate_date DATE DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS exchange_rate_table VARCHAR(20) DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v17.0_exchange_rate.sql');

-- ══════════════════════════════════════════════
-- v18.0: Client internal notes
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS client_internal_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    office_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_office (client_id, office_id),
    INDEX idx_pinned (office_id, is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v18.0_client_notes.sql');

-- ══════════════════════════════════════════════
-- v18.1: Client monthly workflow status
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS client_monthly_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    office_id INT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    status ENUM('import', 'weryfikacja', 'jpk', 'zamkniety') DEFAULT 'import',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_period (client_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v18.1_client_workflow.sql');

-- ══════════════════════════════════════════════
-- v19.1: KSeF batch element ref
-- ══════════════════════════════════════════════
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS ksef_element_ref VARCHAR(100) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS vat_amount_pln DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS net_amount_pln DECIMAL(15,2) DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v19.1_ksef_batch.sql');

-- ══════════════════════════════════════════════
-- v20.0: Client email branding
-- ══════════════════════════════════════════════
ALTER TABLE client_invoice_email_templates ADD COLUMN IF NOT EXISTS header_color VARCHAR(7) DEFAULT '#0B7285';
ALTER TABLE client_invoice_email_templates ADD COLUMN IF NOT EXISTS logo_in_emails TINYINT(1) DEFAULT 0;
ALTER TABLE client_invoice_email_templates ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE client_invoice_email_templates ADD COLUMN IF NOT EXISTS footer_text TEXT DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v20.0_client_email_branding.sql');

-- ══════════════════════════════════════════════
-- v20.1: Bilingual email templates (PL/EN)
-- ══════════════════════════════════════════════
-- Rename single-language columns to _pl (skip if already renamed)
ALTER TABLE client_invoice_email_templates
    CHANGE COLUMN IF EXISTS subject_template subject_template_pl VARCHAR(500) NOT NULL DEFAULT 'Faktura {{invoice_number}}';

ALTER TABLE client_invoice_email_templates
    CHANGE COLUMN IF EXISTS body_template body_template_pl TEXT NOT NULL;

-- Add EN variants
ALTER TABLE client_invoice_email_templates
    ADD COLUMN IF NOT EXISTS subject_template_en VARCHAR(500) DEFAULT 'Invoice {{invoice_number}}';

ALTER TABLE client_invoice_email_templates
    ADD COLUMN IF NOT EXISTS body_template_en TEXT DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v20.1_bilingual_email_templates.sql');

-- ══════════════════════════════════════════════
-- v22.2: Tax events for employees
-- ══════════════════════════════════════════════
ALTER TABLE tax_custom_events
    MODIFY client_id INT UNSIGNED DEFAULT NULL;

ALTER TABLE tax_custom_events
    ADD COLUMN IF NOT EXISTS employee_id INT UNSIGNED DEFAULT NULL;

-- Index (safe to create if not exists - will error silently if duplicate)
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tax_custom_events'
      AND INDEX_NAME = 'idx_employee_date'
);
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE tax_custom_events ADD INDEX idx_employee_date (employee_id, event_date)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v22.2_event_employee.sql');

-- ══════════════════════════════════════════════
-- GOTOWE! Uruchom verify_database.sql aby potwierdzić
-- ══════════════════════════════════════════════
SELECT 'Wszystkie brakujące migracje zostały zastosowane!' AS `Status`;
