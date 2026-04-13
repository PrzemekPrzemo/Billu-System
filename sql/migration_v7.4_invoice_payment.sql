-- Migration v7.4: Add payment tracking to cost invoices
ALTER TABLE invoices ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER cost_center_id;
ALTER TABLE invoices ADD COLUMN payment_due_date DATE NULL AFTER is_paid;
ALTER TABLE invoices ADD COLUMN payment_method_detected VARCHAR(50) NULL AFTER payment_due_date;
