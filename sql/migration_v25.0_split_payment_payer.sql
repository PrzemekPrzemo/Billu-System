-- v25.0: Add split payment flag and payer data to issued invoices
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS is_split_payment TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS payer_data JSON DEFAULT NULL;
