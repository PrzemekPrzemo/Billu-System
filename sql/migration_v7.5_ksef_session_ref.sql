-- Migration v7.5: KSeF session ref for UPO download + contractor short name
-- Run: mysql -u <user> -p <database> < sql/migration_v7.5_ksef_session_ref.sql

-- Store KSeF session reference for UPO download (session ref != invoice ref)
ALTER TABLE issued_invoices ADD COLUMN ksef_session_ref VARCHAR(100) NULL AFTER ksef_reference_number;

-- Contractor short name for system display (full name from GUS used on invoices)
ALTER TABLE contractors ADD COLUMN short_name VARCHAR(100) NULL AFTER company_name;
