-- Migration v10.1: Add invoice_type and correction tracking to cost invoices (invoices table)
-- Enables proper handling of correction invoices (FV-KOR) imported from KSeF

ALTER TABLE invoices
    ADD COLUMN invoice_type VARCHAR(10) DEFAULT 'VAT' AFTER ksef_reference_number,
    ADD COLUMN corrected_invoice_number VARCHAR(100) DEFAULT NULL AFTER invoice_type,
    ADD COLUMN corrected_invoice_date DATE DEFAULT NULL AFTER corrected_invoice_number,
    ADD COLUMN corrected_ksef_number VARCHAR(100) DEFAULT NULL AFTER corrected_invoice_date,
    ADD COLUMN correction_reason TEXT DEFAULT NULL AFTER corrected_ksef_number;

ALTER TABLE invoices ADD INDEX idx_invoice_type (invoice_type);
