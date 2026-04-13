-- Migration v24.0: Invoice types, split payment, payer, tasks billing
-- Date: 2026-04-12

-- ===== INVOICE ENHANCEMENTS =====

-- Add split payment (MPP) flag
ALTER TABLE issued_invoices ADD COLUMN is_split_payment TINYINT(1) NOT NULL DEFAULT 0;

-- Add payer data (Podmiot3 in KSeF) - JSON with name, nip, address
ALTER TABLE issued_invoices ADD COLUMN payer_data TEXT DEFAULT NULL;

-- Extend invoice_type ENUM with new types: proforma, advance, final
ALTER TABLE issued_invoices MODIFY COLUMN invoice_type ENUM('FV','FV_KOR','FP','FV_ZAL','FV_KON') NOT NULL DEFAULT 'FV';

-- Advance invoice fields
ALTER TABLE issued_invoices ADD COLUMN advance_amount DECIMAL(12,2) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN advance_order_description TEXT DEFAULT NULL;

-- Final invoice: references to advance invoices
ALTER TABLE issued_invoices ADD COLUMN related_advance_ids JSON DEFAULT NULL;

-- Cash invoice payment status
ALTER TABLE issued_invoices ADD COLUMN cash_payment_status ENUM('paid','to_pay') DEFAULT NULL;

-- ===== COMPANY PROFILE: NUMBERING =====

-- Proforma numbering
ALTER TABLE company_profiles ADD COLUMN proforma_number_pattern VARCHAR(100) DEFAULT 'PRO/{NR}/{MM}/{RRRR}';
ALTER TABLE company_profiles ADD COLUMN next_proforma_number INT NOT NULL DEFAULT 1;

-- Advance invoice numbering
ALTER TABLE company_profiles ADD COLUMN advance_number_pattern VARCHAR(100) DEFAULT 'ZAL/{NR}/{MM}/{RRRR}';
ALTER TABLE company_profiles ADD COLUMN next_advance_number INT NOT NULL DEFAULT 1;

-- Numbering reset mode (monthly, yearly, continuous)
ALTER TABLE company_profiles ADD COLUMN numbering_reset_mode ENUM('monthly','yearly','continuous') NOT NULL DEFAULT 'monthly';

-- ===== TASKS: BILLING =====

-- Add billing fields to tasks
ALTER TABLE client_tasks ADD COLUMN is_billable TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE client_tasks ADD COLUMN task_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE client_tasks ADD COLUMN billing_status ENUM('none','to_invoice','invoiced') NOT NULL DEFAULT 'none';

-- Index for billing queries
CREATE INDEX idx_tasks_billing ON client_tasks (status, billing_status);
CREATE INDEX idx_tasks_billable ON client_tasks (is_billable, billing_status);
