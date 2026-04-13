-- Migration v20.0: Client email branding (color, logo, footer)
-- Adds branding columns to client_invoice_email_templates for per-client email customization

ALTER TABLE client_invoice_email_templates ADD COLUMN IF NOT EXISTS header_color VARCHAR(7) DEFAULT '#0B7285';
ALTER TABLE client_invoice_email_templates ADD COLUMN IF NOT EXISTS logo_in_emails TINYINT(1) DEFAULT 0;
ALTER TABLE client_invoice_email_templates ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) DEFAULT NULL;
ALTER TABLE client_invoice_email_templates ADD COLUMN IF NOT EXISTS footer_text TEXT DEFAULT NULL;
