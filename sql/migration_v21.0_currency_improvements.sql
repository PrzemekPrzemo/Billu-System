-- Migration v21.0: Add exchange rate fields to purchase invoices
-- Mirrors issued_invoices columns from migration_v17.0
-- Required for storing KSeF KursWalutyZ from foreign currency invoices

ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(10,6) DEFAULT NULL AFTER currency,
  ADD COLUMN IF NOT EXISTS exchange_rate_date DATE DEFAULT NULL AFTER exchange_rate,
  ADD COLUMN IF NOT EXISTS exchange_rate_table VARCHAR(20) DEFAULT NULL AFTER exchange_rate_date;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v21.0_currency_improvements.sql');
