-- Migration v19.1: KSeF batch session support
-- Adds ksef_element_ref column for mapping invoice to KSeF element reference during batch send

ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS ksef_element_ref VARCHAR(100) DEFAULT NULL;

-- Ensure v19.0 columns exist (vat/net in PLN for foreign currency invoices)
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS vat_amount_pln DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS net_amount_pln DECIMAL(15,2) DEFAULT NULL;
