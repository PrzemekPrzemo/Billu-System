-- Separate counters for correction and duplicate invoices
ALTER TABLE company_profiles
    ADD COLUMN next_correction_number INT UNSIGNED DEFAULT 1 AFTER next_invoice_number,
    ADD COLUMN next_duplicate_number INT UNSIGNED DEFAULT 1 AFTER next_correction_number;

-- Add FV_D (duplicate) to invoice_type enum
ALTER TABLE issued_invoices
    MODIFY COLUMN invoice_type ENUM('FV','FV_KOR','FV_D','FP') DEFAULT 'FV';
