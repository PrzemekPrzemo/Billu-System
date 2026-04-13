-- Remove FV_D invoice type: convert existing FV_D invoices to FV
UPDATE issued_invoices SET invoice_type = 'FV' WHERE invoice_type = 'FV_D';

-- Remove FV_D from the ENUM
ALTER TABLE issued_invoices
    MODIFY COLUMN invoice_type ENUM('FV','FV_KOR','FP') DEFAULT 'FV';
