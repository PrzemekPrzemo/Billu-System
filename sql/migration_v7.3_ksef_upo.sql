-- Migration v7.3: Add UPO (Urzędowe Poświadczenie Odbioru) storage for KSeF
-- Safe to run multiple times (IF NOT EXISTS / IF NOT)

ALTER TABLE issued_invoices
    ADD COLUMN IF NOT EXISTS ksef_upo_path VARCHAR(500) DEFAULT NULL AFTER ksef_error;
