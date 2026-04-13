-- Migration v10.0: Add correction before/after data to issued_invoices
-- Stores original invoice data for proper KSeF FA(3) StanPrzed/StanPo generation
-- and before/after comparison in PDF, JPK, and UI

ALTER TABLE issued_invoices
    ADD COLUMN original_line_items JSON DEFAULT NULL AFTER correction_reason,
    ADD COLUMN original_net_amount DECIMAL(15,2) DEFAULT NULL AFTER original_line_items,
    ADD COLUMN original_vat_amount DECIMAL(15,2) DEFAULT NULL AFTER original_net_amount,
    ADD COLUMN original_gross_amount DECIMAL(15,2) DEFAULT NULL AFTER original_vat_amount,
    ADD COLUMN correction_type TINYINT(1) DEFAULT 1 AFTER original_gross_amount
    COMMENT 'TypKorekty: 1=wstecz (okres oryginału), 2=bieżąco (okres korekty), 3=mieszana';

-- Verify
SELECT
    COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'issued_invoices'
    AND COLUMN_NAME IN ('original_line_items','original_net_amount','original_vat_amount','original_gross_amount','correction_type')
ORDER BY ORDINAL_POSITION;
