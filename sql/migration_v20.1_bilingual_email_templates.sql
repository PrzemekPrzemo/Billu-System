-- Migration v20.1: Bilingual (PL/EN) client invoice email templates
-- Renames single-language columns to _pl and adds _en variants

ALTER TABLE client_invoice_email_templates
    CHANGE COLUMN subject_template subject_template_pl VARCHAR(500) NOT NULL DEFAULT 'Faktura {{invoice_number}}';

ALTER TABLE client_invoice_email_templates
    CHANGE COLUMN body_template body_template_pl TEXT NOT NULL;

ALTER TABLE client_invoice_email_templates
    ADD COLUMN IF NOT EXISTS subject_template_en VARCHAR(500) DEFAULT 'Invoice {{invoice_number}}';

ALTER TABLE client_invoice_email_templates
    ADD COLUMN IF NOT EXISTS body_template_en TEXT DEFAULT NULL;
