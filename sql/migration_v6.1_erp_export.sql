-- ERP Export Templates
-- Predefined and custom column mappings for ERP system exports

CREATE TABLE IF NOT EXISTS export_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    format_type ENUM('comarch_optima','sage','enova','jpk_vat7','custom') NOT NULL,
    column_mapping JSON,
    `separator` VARCHAR(5) DEFAULT ';',
    encoding VARCHAR(20) DEFAULT 'Windows-1250',
    date_format VARCHAR(20) DEFAULT 'd.m.Y',
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- Default templates
INSERT INTO export_templates (name, format_type, column_mapping, is_default) VALUES
('Comarch Optima', 'comarch_optima', '{"columns":["lp","document_type","invoice_number","issue_date","sale_date","seller_nip","seller_name","seller_address","net_amount","vat_rate","vat_amount","gross_amount","currency","cost_center"]}', 1),
('Sage Symfonia', 'sage', '{"columns":["invoice_number","issue_date","seller_nip","seller_name","seller_address","net_amount","vat_amount","gross_amount","payment_type","currency"]}', 1),
('enova365', 'enova', '{"columns":["document_type","invoice_number","issue_date","sale_date","seller_nip","seller_name","seller_address","net_amount","vat_23","vat_8","vat_5","vat_0","gross_amount"]}', 1);
