-- Migration: Split is_default into is_default_receiving and is_default_outgoing
-- is_default_receiving: auto-selected on sales invoices (konto do odbioru płatności)
-- is_default_outgoing: auto-selected in bank export (konto do przelewów wychodzących)

ALTER TABLE company_bank_accounts
    ADD COLUMN is_default_receiving TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default,
    ADD COLUMN is_default_outgoing  TINYINT(1) NOT NULL DEFAULT 0 AFTER is_default_receiving;

-- Copy existing defaults to both new columns
UPDATE company_bank_accounts
   SET is_default_receiving = is_default,
       is_default_outgoing  = is_default;

-- Drop old column
ALTER TABLE company_bank_accounts DROP COLUMN is_default;
