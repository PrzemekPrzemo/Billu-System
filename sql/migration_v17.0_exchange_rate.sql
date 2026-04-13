-- Migration v17.0: Add exchange rate fields for multi-currency invoicing
-- Stores NBP exchange rate used for VAT calculation (art. 31a ustawy o VAT)

ALTER TABLE issued_invoices
  ADD COLUMN exchange_rate DECIMAL(10,6) DEFAULT NULL AFTER currency,
  ADD COLUMN exchange_rate_date DATE DEFAULT NULL AFTER exchange_rate,
  ADD COLUMN exchange_rate_table VARCHAR(20) DEFAULT NULL AFTER exchange_rate_date;
