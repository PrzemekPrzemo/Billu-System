-- Migration v19.0: Store VAT and net amounts converted to PLN for foreign currency invoices
-- Required by art. 106e ust. 11 ustawy o VAT and KSeF FA(3) fields P_14_xW

ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS vat_amount_pln DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS net_amount_pln DECIMAL(15,2) DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v19.0_vat_pln.sql');
