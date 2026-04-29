-- =============================================
-- Migration v43.0: VAT whitelist override approvals
-- Allows office (or office-employee assigned to the client) to accept
-- a cost invoice that the client cannot accept on their own because
-- whitelist_failed=1. The override REQUIRES a written justification
-- and an audit trail (who / when).
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

ALTER TABLE invoices ADD COLUMN IF NOT EXISTS whitelist_override_reason TEXT DEFAULT NULL
    COMMENT 'Mandatory justification when an office accepts an invoice on behalf of a client despite whitelist_failed=1';
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS whitelist_override_by_type ENUM('office','employee') DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS whitelist_override_by_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS whitelist_override_at DATETIME DEFAULT NULL;

ALTER TABLE invoices ADD INDEX IF NOT EXISTS idx_whitelist_override (whitelist_override_at);

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v43.0_whitelist_override.sql');
