-- Add whitelist_failed flag to invoices table
ALTER TABLE invoices ADD COLUMN whitelist_failed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_paid;
