-- =============================================
-- BiLLU Financial Solutions: FULL DATABASE MIGRATION (v2.1 - v18.1)
-- Safe to run multiple times on any database state
-- Run in phpMyAdmin: paste in SQL tab and execute
-- Generated: 2026-04-07
-- =============================================

-- ══════════════════════════════════════════════
-- 1. SCHEMA MIGRATIONS TRACKING (v6.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ══════════════════════════════════════════════
-- 2. ALTER clients TABLE (v2.1, v2.2, v5.0, v5.1, v13.0, v15.0, mobile)
-- ══════════════════════════════════════════════
ALTER TABLE clients ADD COLUMN IF NOT EXISTS has_cost_centers TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS ksef_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS ip_whitelist TEXT DEFAULT NULL;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) DEFAULT NULL;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS two_factor_recovery_codes TEXT DEFAULT NULL;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS is_demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS can_send_invoices TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS mobile_app_enabled TINYINT(1) NOT NULL DEFAULT 1;

-- ══════════════════════════════════════════════
-- 3. ALTER offices TABLE (v5.1, v8.2, v13.0, v14.0, mobile)
-- ══════════════════════════════════════════════
ALTER TABLE offices ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS two_factor_recovery_codes TEXT DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS is_demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS max_employees INT UNSIGNED DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS max_clients INT UNSIGNED DEFAULT NULL;
ALTER TABLE offices ADD COLUMN IF NOT EXISTS mobile_app_enabled TINYINT(1) NOT NULL DEFAULT 1;

-- ══════════════════════════════════════════════
-- 4. ALTER users TABLE (v5.1)
-- ══════════════════════════════════════════════
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_recovery_codes TEXT DEFAULT NULL;

-- ══════════════════════════════════════════════
-- 5. ALTER reports TABLE (v2.1, v2.2)
-- ══════════════════════════════════════════════
ALTER TABLE reports ADD COLUMN IF NOT EXISTS cost_center_name VARCHAR(255) DEFAULT NULL;
ALTER TABLE reports ADD COLUMN IF NOT EXISTS report_format ENUM('excel','jpk_xml') NOT NULL DEFAULT 'excel';
ALTER TABLE reports ADD COLUMN IF NOT EXISTS xml_path VARCHAR(500) DEFAULT NULL;

-- ══════════════════════════════════════════════
-- 6. CLIENT COST CENTERS (v2.1)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS client_cost_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_active (client_id, is_active)
) ENGINE=InnoDB;

ALTER TABLE invoices ADD COLUMN IF NOT EXISTS cost_center_id INT UNSIGNED DEFAULT NULL;

-- ══════════════════════════════════════════════
-- 7. KSEF CONFIGS + OPERATIONS LOG (v3.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS client_ksef_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    auth_method ENUM('none','token','certificate','ksef_cert') NOT NULL DEFAULT 'none',
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    cert_pfx_encrypted MEDIUMBLOB DEFAULT NULL,
    cert_fingerprint VARCHAR(64) DEFAULT NULL,
    cert_subject_cn VARCHAR(255) DEFAULT NULL,
    cert_subject_nip VARCHAR(10) DEFAULT NULL,
    cert_issuer VARCHAR(255) DEFAULT NULL,
    cert_valid_from DATETIME DEFAULT NULL,
    cert_valid_to DATETIME DEFAULT NULL,
    cert_type ENUM('personal','seal','ksef','ksef_enrolled') DEFAULT NULL,
    cert_serial_number VARCHAR(128) DEFAULT NULL,
    ksef_environment ENUM('test','demo','production') NOT NULL DEFAULT 'test',
    ksef_context_nip VARCHAR(10) DEFAULT NULL,
    ksef_permissions JSON DEFAULT NULL,
    access_token TEXT DEFAULT NULL,
    access_token_expires_at DATETIME DEFAULT NULL,
    refresh_token TEXT DEFAULT NULL,
    refresh_token_expires_at DATETIME DEFAULT NULL,
    configured_by_type ENUM('admin','office','client') DEFAULT NULL,
    configured_by_id INT UNSIGNED DEFAULT NULL,
    last_import_at DATETIME DEFAULT NULL,
    last_import_status VARCHAR(50) DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_auth_method (auth_method),
    INDEX idx_active (is_active),
    INDEX idx_cert_fingerprint (cert_fingerprint),
    INDEX idx_cert_valid_to (cert_valid_to)
) ENGINE=InnoDB;

-- v4.0 columns for KSeF certificate enrollment
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_private_key_encrypted TEXT DEFAULT NULL;
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_pem TEXT DEFAULT NULL;
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_serial_number VARCHAR(128) DEFAULT NULL;
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_name VARCHAR(255) DEFAULT NULL;
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_valid_from DATETIME DEFAULT NULL;
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_valid_to DATETIME DEFAULT NULL;
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_status ENUM('none','enrolling','active','revoked','expired') NOT NULL DEFAULT 'none';
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_enrollment_ref VARCHAR(255) DEFAULT NULL;
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS cert_ksef_type ENUM('Authentication','Offline') DEFAULT 'Authentication';

-- v10.2 connection status
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS ksef_connection_status ENUM('ok','failed','unknown') DEFAULT 'unknown';
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS ksef_connection_checked_at DATETIME DEFAULT NULL;
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS ksef_connection_error TEXT DEFAULT NULL;

CREATE TABLE IF NOT EXISTS ksef_operations_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    operation ENUM(
        'authenticate','token_refresh','session_open','session_close',
        'invoice_query','invoice_download','invoice_send',
        'permissions_query','permissions_grant','permissions_revoke',
        'certificate_upload','certificate_delete','token_generate',
        'import_batch','export_async','status_check',
        'cert_enroll_start','cert_enroll_poll','cert_enroll_complete',
        'cert_retrieve','cert_revoke','cert_limits_check'
    ) NOT NULL,
    status ENUM('started','success','failed','timeout') NOT NULL DEFAULT 'started',
    request_summary TEXT DEFAULT NULL,
    response_summary TEXT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    ksef_reference_number VARCHAR(255) DEFAULT NULL,
    duration_ms INT UNSIGNED DEFAULT NULL,
    performed_by_type ENUM('admin','office','client','system') NOT NULL,
    performed_by_id INT UNSIGNED NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_op (client_id, operation),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_ksef_ref (ksef_reference_number)
) ENGINE=InnoDB;

-- ══════════════════════════════════════════════
-- 8. NOTIFICATIONS + WEBHOOKS (v5.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','office','client') NOT NULL,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_type, user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED DEFAULT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(255) NOT NULL,
    events VARCHAR(500) NOT NULL DEFAULT 'all',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_triggered_at DATETIME DEFAULT NULL,
    last_status_code INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    event VARCHAR(50) NOT NULL,
    payload TEXT,
    response_code INT DEFAULT NULL,
    response_body TEXT DEFAULT NULL,
    duration_ms INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook (webhook_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════
-- 9. ERP EXPORT + IMPORT TEMPLATES (v6.1, v6.3)
-- ══════════════════════════════════════════════
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

INSERT INTO export_templates (name, format_type, column_mapping, is_default)
SELECT 'Comarch Optima', 'comarch_optima', '{"columns":["lp","document_type","invoice_number","issue_date","sale_date","seller_nip","seller_name","seller_address","net_amount","vat_rate","vat_amount","gross_amount","currency","cost_center"]}', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM export_templates WHERE name = 'Comarch Optima');

INSERT INTO export_templates (name, format_type, column_mapping, is_default)
SELECT 'Sage Symfonia', 'sage', '{"columns":["invoice_number","issue_date","seller_nip","seller_name","seller_address","net_amount","vat_amount","gross_amount","payment_type","currency"]}', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM export_templates WHERE name = 'Sage Symfonia');

INSERT INTO export_templates (name, format_type, column_mapping, is_default)
SELECT 'enova365', 'enova', '{"columns":["document_type","invoice_number","issue_date","sale_date","seller_nip","seller_name","seller_address","net_amount","vat_23","vat_8","vat_5","vat_0","gross_amount"]}', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM export_templates WHERE name = 'enova365');

CREATE TABLE IF NOT EXISTS import_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    column_mapping JSON NOT NULL,
    `separator` VARCHAR(5) DEFAULT ';',
    encoding VARCHAR(20) DEFAULT 'UTF-8',
    skip_rows INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_office (office_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

CREATE TABLE IF NOT EXISTS scheduled_exports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    format ENUM('excel','pdf','jpk_fa','jpk_vat7','comarch_optima','sage','enova') NOT NULL,
    frequency ENUM('monthly','weekly') NOT NULL DEFAULT 'monthly',
    day_of_month INT DEFAULT 5,
    email VARCHAR(255) NOT NULL,
    include_rejected TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    last_run_at TIMESTAMP NULL,
    next_run_at TIMESTAMP NULL,
    created_by_type ENUM('admin','office') NOT NULL,
    created_by_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_next_run (next_run_at, is_active),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ══════════════════════════════════════════════
-- 10. INVOICE COMMENTS (v6.2)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS invoice_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    user_type ENUM('admin','office','client') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- ══════════════════════════════════════════════
-- 11. INVOICE ISSUING SYSTEM (v7.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS company_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    trade_name VARCHAR(255),
    address_street VARCHAR(255),
    address_city VARCHAR(100),
    address_postal VARCHAR(10),
    address_country VARCHAR(2) DEFAULT 'PL',
    regon VARCHAR(14),
    krs VARCHAR(20),
    bdo VARCHAR(20),
    default_payment_method ENUM('przelew','gotowka','karta','kompensata','barter') DEFAULT 'przelew',
    default_payment_days INT DEFAULT 14,
    invoice_number_pattern VARCHAR(100) DEFAULT 'FV/{NR}/{MM}/{RRRR}',
    next_invoice_number INT UNSIGNED DEFAULT 1,
    invoice_notes TEXT,
    logo_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE company_profiles ADD COLUMN IF NOT EXISTS next_correction_number INT UNSIGNED DEFAULT 1;
ALTER TABLE company_profiles ADD COLUMN IF NOT EXISTS next_duplicate_number INT UNSIGNED DEFAULT 1;

CREATE TABLE IF NOT EXISTS company_bank_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    account_name VARCHAR(100),
    bank_name VARCHAR(255),
    account_number VARCHAR(34),
    swift VARCHAR(11),
    currency VARCHAR(3) DEFAULT 'PLN',
    is_default TINYINT(1) DEFAULT 0,
    sort_order TINYINT DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    unit VARCHAR(20) DEFAULT 'szt.',
    default_price DECIMAL(12,2),
    vat_rate ENUM('23','8','5','0','zw','np') DEFAULT '23',
    pkwiu VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    sort_order TINYINT DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contractors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    nip VARCHAR(20),
    company_name VARCHAR(255) NOT NULL,
    address_street VARCHAR(255),
    address_city VARCHAR(100),
    address_postal VARCHAR(10),
    address_country VARCHAR(2) DEFAULT 'PL',
    email VARCHAR(255),
    phone VARCHAR(50),
    contact_person VARCHAR(255),
    default_payment_days INT,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    KEY idx_client_nip (client_id, nip),
    KEY idx_client_active (client_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE contractors ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE contractors ADD COLUMN IF NOT EXISTS short_name VARCHAR(100) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS issued_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    contractor_id INT UNSIGNED,
    invoice_type ENUM('FV','FV_KOR','FP') DEFAULT 'FV',
    invoice_number VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    sale_date DATE NOT NULL,
    due_date DATE,
    seller_nip VARCHAR(20) NOT NULL,
    seller_name VARCHAR(255) NOT NULL,
    seller_address TEXT,
    buyer_nip VARCHAR(20),
    buyer_name VARCHAR(255) NOT NULL,
    buyer_address TEXT,
    currency VARCHAR(3) DEFAULT 'PLN',
    net_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    gross_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    line_items JSON NOT NULL,
    vat_details JSON NOT NULL,
    payment_method ENUM('przelew','gotowka','karta','kompensata','barter') DEFAULT 'przelew',
    bank_account_id INT UNSIGNED,
    bank_account_number VARCHAR(34),
    bank_name VARCHAR(255),
    notes TEXT,
    internal_notes TEXT,
    ksef_reference_number VARCHAR(100),
    ksef_sent_at TIMESTAMP NULL,
    ksef_status ENUM('none','pending','sent','accepted','rejected','error') DEFAULT 'none',
    ksef_error TEXT,
    corrected_invoice_id INT UNSIGNED,
    correction_reason TEXT,
    pdf_path VARCHAR(500),
    status ENUM('draft','issued','sent_ksef','cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (contractor_id) REFERENCES contractors(id) ON DELETE SET NULL,
    FOREIGN KEY (corrected_invoice_id) REFERENCES issued_invoices(id) ON DELETE SET NULL,
    UNIQUE KEY uk_client_invoice_number (client_id, invoice_number),
    KEY idx_client_status (client_id, status),
    KEY idx_client_period (client_id, issue_date),
    KEY idx_ksef_ref (ksef_reference_number),
    KEY idx_corrected_invoice_id (corrected_invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v7.3, v7.5, v10.0, v15.0, v16.1, v17.0 columns
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS ksef_upo_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS ksef_session_ref VARCHAR(100) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS duplicate_acknowledged TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS email_sent_at DATETIME DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS email_sent_to VARCHAR(255) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(10,6) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS exchange_rate_date DATE DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS exchange_rate_table VARCHAR(20) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS original_line_items JSON DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS original_net_amount DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS original_vat_amount DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS original_gross_amount DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS correction_type TINYINT(1) DEFAULT 1;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS is_split_payment TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS payer_data JSON DEFAULT NULL;

-- ══════════════════════════════════════════════
-- 12. OFFICE EMPLOYEES (v8.0, v8.2)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS office_employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    position VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE,
    INDEX idx_office (office_id)
) ENGINE=InnoDB;

ALTER TABLE office_employees ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL;
ALTER TABLE office_employees ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP NULL;
ALTER TABLE office_employees ADD COLUMN IF NOT EXISTS force_password_change TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE office_employees ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL;

CREATE TABLE IF NOT EXISTS office_employee_clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES office_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY uk_employee_client (employee_id, client_id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB;

-- ══════════════════════════════════════════════
-- 13. ALTER invoices TABLE (v7.4, v8.1, v9.4, v10.1)
-- ══════════════════════════════════════════════
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS is_paid TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS payment_due_date DATE DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS payment_method_detected VARCHAR(50) DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS whitelist_failed TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS ksef_xml MEDIUMTEXT DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS invoice_type VARCHAR(10) DEFAULT 'VAT';
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS corrected_invoice_number VARCHAR(100) DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS corrected_invoice_date DATE DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS corrected_ksef_number VARCHAR(100) DEFAULT NULL;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS correction_reason TEXT DEFAULT NULL;

-- ══════════════════════════════════════════════
-- 14. MESSAGES + TASKS (v11.0, v11.1, v12.1)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED DEFAULT NULL,
    batch_id INT UNSIGNED DEFAULT NULL,
    sender_type ENUM('office','employee','client') NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    body TEXT NOT NULL,
    is_read_by_client TINYINT(1) NOT NULL DEFAULT 0,
    is_read_by_office TINYINT(1) NOT NULL DEFAULT 0,
    parent_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_parent (parent_id),
    INDEX idx_unread_client (client_id, is_read_by_client),
    INDEX idx_unread_office (client_id, is_read_by_office),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS message_notification_prefs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('office','employee','client') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    notify_new_thread TINYINT(1) NOT NULL DEFAULT 1,
    notify_new_reply TINYINT(1) NOT NULL DEFAULT 1,
    notify_email TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user (user_type, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    created_by_type ENUM('office','employee','admin') NOT NULL,
    created_by_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    status ENUM('open','in_progress','done') NOT NULL DEFAULT 'open',
    due_date DATE DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    completed_by_type ENUM('office','employee','client') DEFAULT NULL,
    completed_by_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_status (client_id, status),
    INDEX idx_due_date (due_date, status),
    INDEX idx_client_priority (client_id, priority, status),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE client_tasks ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(500) DEFAULT NULL;
ALTER TABLE client_tasks ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) DEFAULT NULL;

-- ══════════════════════════════════════════════
-- 15. TAX PAYMENTS (v12.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS tax_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    tax_type ENUM('VAT','PIT','CIT') NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('do_zaplaty','do_przeniesienia') NOT NULL DEFAULT 'do_zaplaty',
    created_by_type ENUM('office','employee') NOT NULL,
    created_by_id INT UNSIGNED NOT NULL,
    updated_by_type ENUM('office','employee') DEFAULT NULL,
    updated_by_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_period_tax (client_id, year, month, tax_type),
    INDEX idx_client_year (client_id, year),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════
-- 16. OFFICE SMTP (v14.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS office_smtp_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED NOT NULL UNIQUE,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host VARCHAR(255) NOT NULL DEFAULT '',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_encryption ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
    smtp_user VARCHAR(255) NOT NULL DEFAULT '',
    smtp_pass_encrypted TEXT DEFAULT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ══════════════════════════════════════════════
-- 17. EMAIL SYSTEM (v15.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS client_smtp_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host VARCHAR(255) NOT NULL DEFAULT '',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_encryption ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
    smtp_user VARCHAR(255) NOT NULL DEFAULT '',
    smtp_pass_encrypted TEXT DEFAULT NULL,
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    subject_pl TEXT NOT NULL,
    body_pl TEXT NOT NULL,
    subject_en TEXT NOT NULL,
    body_en TEXT NOT NULL,
    available_placeholders TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_email_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED NOT NULL UNIQUE,
    header_color VARCHAR(7) DEFAULT '#008F8F',
    logo_in_emails TINYINT(1) NOT NULL DEFAULT 1,
    footer_text TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_invoice_email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    subject_template VARCHAR(500) NOT NULL DEFAULT 'Faktura {{invoice_number}}',
    body_template TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════
-- 18. TAX CALENDAR (v16.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS client_tax_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL UNIQUE,
    vat_period ENUM('monthly','quarterly','none') NOT NULL DEFAULT 'monthly',
    taxation_type ENUM('PIT','CIT','none') NOT NULL DEFAULT 'PIT',
    tax_form ENUM('liniowy','skala','ryczalt','karta') DEFAULT 'skala',
    zus_payer_type ENUM('employer','self_employed','none') NOT NULL DEFAULT 'self_employed',
    jpk_vat_required TINYINT(1) NOT NULL DEFAULT 1,
    alert_days_before TINYINT UNSIGNED NOT NULL DEFAULT 5,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tax_calendar_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    obligation_type VARCHAR(30) NOT NULL,
    deadline_date DATE NOT NULL,
    alert_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_obligation_date (client_id, obligation_type, deadline_date),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════
-- 19. DUPLICATE DETECTION (v16.1)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS duplicate_candidates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_type ENUM('purchase','sales') NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    duplicate_of_id INT UNSIGNED NOT NULL,
    match_score TINYINT UNSIGNED NOT NULL DEFAULT 100,
    match_details VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','dismissed','confirmed') NOT NULL DEFAULT 'pending',
    reviewed_by_type VARCHAR(20) DEFAULT NULL,
    reviewed_by_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_type, invoice_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ══════════════════════════════════════════════
-- 20. API + MOBILE (JWT tokens, FCM)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    refresh_token_hash VARCHAR(64) NOT NULL UNIQUE,
    device_name VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_fcm_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    fcm_token TEXT NOT NULL,
    device_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 21. CLIENT NOTES + WORKFLOW (v18.0, v18.1)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS client_internal_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    office_id INT UNSIGNED NOT NULL,
    note TEXT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    created_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_office (client_id, office_id),
    INDEX idx_pinned (office_id, is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_monthly_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    office_id INT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    status ENUM('import','weryfikacja','jpk','zamkniety') DEFAULT 'import',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_period (client_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 22. SETTINGS (various versions)
-- ══════════════════════════════════════════════
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('ksef_auto_import_day', '0'),
    ('support_contact_name', ''),
    ('support_contact_email', ''),
    ('support_contact_phone', '');

INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('2fa_enabled', '1', 'Allow users to enable 2FA'),
    ('2fa_required', '0', 'Require 2FA for all users'),
    ('2fa_required_admin', '0', 'Require 2FA for admin users'),
    ('whitelist_api_url', 'https://wl-api.mf.gov.pl', 'URL API Bialej Listy VAT'),
    ('whitelist_check_enabled', '1', 'Weryfikacja bialej listy VAT przy eksporcie bankowym'),
    ('ksef_cert_encryption_key', '', 'Base64-encoded 256-bit key for encrypting stored certificates'),
    ('ksef_max_cert_size_kb', '50', 'Maximum certificate file size in KB'),
    ('ksef_allowed_cert_types', 'pfx,p12', 'Allowed certificate file extensions'),
    ('ksef_cert_upload_enabled', '1', 'Allow clients to upload certificates'),
    ('ksef_default_environment', 'test', 'Default KSeF environment for new clients'),
    ('ksef_cert_enrollment_enabled', '1', 'Allow clients to enroll KSeF certificates'),
    ('ksef_api_path_prefix', '/v2', 'API path prefix for KSeF endpoints')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- ══════════════════════════════════════════════
-- 23. SEED EMAIL TEMPLATES (v15.0)
-- ══════════════════════════════════════════════
INSERT IGNORE INTO email_templates (template_key, name, subject_pl, body_pl, subject_en, body_en, available_placeholders) VALUES
('new_invoices_notification', 'Powiadomienie o nowych fakturach',
 'Nowe faktury do weryfikacji - {{period}}',
 '<p>Szanowni Panstwo <strong>{{company_name}}</strong>,</p><p>W systemie dostepnych jest <strong>{{invoice_count}}</strong> nowych faktur za okres <strong>{{period}}</strong> do weryfikacji.</p><p>Prosimy o zalogowanie sie i zaakceptowanie lub odrzucenie faktur przed <strong>{{deadline}}</strong>.</p>',
 'New invoices to verify - {{period}}',
 '<p>Dear <strong>{{company_name}}</strong>,</p><p>There are <strong>{{invoice_count}}</strong> new invoices for period <strong>{{period}}</strong> awaiting your verification.</p><p>Please log in and accept or reject invoices before <strong>{{deadline}}</strong>.</p>',
 'company_name,invoice_count,period,deadline,login_url'),

('deadline_reminder', 'Przypomnienie o terminie',
 'Przypomnienie: termin weryfikacji faktur - {{deadline}}',
 '<p>Szanowni Panstwo <strong>{{company_name}}</strong>,</p><p>Pozostalo <strong>{{pending_count}}</strong> faktur oczekujacych na weryfikacje.</p><p>Termin: <strong>{{deadline}}</strong>.</p>',
 'Reminder: Invoice verification deadline - {{deadline}}',
 '<p>Dear <strong>{{company_name}}</strong>,</p><p>You still have <strong>{{pending_count}}</strong> invoices pending verification.</p><p>The deadline is <strong>{{deadline}}</strong>.</p>',
 'company_name,pending_count,deadline,login_url'),

('password_reset', 'Reset hasla',
 'Reset hasla - BiLLU',
 '<p>Witaj <strong>{{name}}</strong>,</p><p>Kliknij ponizszy link, aby zresetowac haslo:</p><p><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;">Zresetuj haslo</a></p><p>Link wygasa za 1 godzine.</p>',
 'Password Reset - BiLLU',
 '<p>Hello <strong>{{name}}</strong>,</p><p>Click the link below to reset your password:</p><p><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;">Reset Password</a></p><p>This link expires in 1 hour.</p>',
 'name,reset_url'),

('initial_credentials', 'Dane logowania',
 'Dane logowania do systemu BiLLU',
 '<p>Szanowni Panstwo <strong>{{company_name}}</strong>,</p><p>Utworzono konto w systemie BiLLU.</p><p>NIP: <strong>{{nip}}</strong><br>Haslo: <strong>{{password}}</strong></p><p><a href="{{login_url}}" style="display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;">Zaloguj sie</a></p>',
 'Your BiLLU account credentials',
 '<p>Dear <strong>{{company_name}}</strong>,</p><p>An account has been created for you in BiLLU.</p><p>NIP: <strong>{{nip}}</strong><br>Password: <strong>{{password}}</strong></p><p><a href="{{login_url}}" style="display:inline-block;padding:12px 24px;background:#008F8F;color:white;text-decoration:none;border-radius:6px;">Log in</a></p>',
 'company_name,nip,password,login_url'),

('certificate_expiry', 'Wygasanie certyfikatu KSeF',
 'Ostrzezenie: certyfikat KSeF wygasa za {{days_left}} dni',
 '<p>Szanowni Panstwo <strong>{{company_name}}</strong>,</p><p>Certyfikat <strong>{{cert_type}}</strong> wygasa <strong>{{expiry_date}}</strong> (za {{days_left}} dni).</p><p>Prosimy o odnowienie certyfikatu w ustawieniach KSeF.</p>',
 'Warning: KSeF certificate expires in {{days_left}} days',
 '<p>Dear <strong>{{company_name}}</strong>,</p><p>Your <strong>{{cert_type}}</strong> certificate expires on <strong>{{expiry_date}}</strong> (in {{days_left}} days).</p><p>Please renew the certificate in your KSeF settings.</p>',
 'company_name,cert_type,expiry_date,days_left'),

('password_expiry', 'Wygasanie hasla',
 'Twoje haslo wygasa za {{days_left}} dni',
 '<p>Witaj <strong>{{company_name}}</strong>,</p><p>Twoje haslo wygasnie za <strong>{{days_left}}</strong> dni.</p><p>Zmien haslo, aby uniknac problemow z logowaniem.</p>',
 'Your password expires in {{days_left}} days',
 '<p>Hello <strong>{{company_name}}</strong>,</p><p>Your password will expire in <strong>{{days_left}}</strong> days.</p><p>Please change your password to avoid login issues.</p>',
 'company_name,days_left,login_url');

-- ══════════════════════════════════════════════
-- 24. BRANDING UPDATE (v2.4)
-- ══════════════════════════════════════════════
UPDATE settings SET setting_value = 'BiLLU Financial Solutions' WHERE setting_key = 'system_name' AND setting_value = 'Faktury KSeF';
UPDATE settings SET setting_value = '/assets/img/logo.svg' WHERE setting_key = 'logo_path' AND (setting_value = '' OR setting_value IS NULL);

-- ══════════════════════════════════════════════
-- 25. CLEANUP LEGACY (v7.6)
-- ══════════════════════════════════════════════
ALTER TABLE clients DROP COLUMN IF EXISTS ksef_api_token;
ALTER TABLE client_ksef_configs DROP COLUMN IF EXISTS ksef_token_encrypted;

-- ══════════════════════════════════════════════
-- v24.0 Invoice types, split payment, payer, tasks billing
-- ══════════════════════════════════════════════

ALTER TABLE issued_invoices MODIFY COLUMN invoice_type ENUM('FV','FV_KOR','FP','FV_ZAL','FV_KON') NOT NULL DEFAULT 'FV';
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS is_split_payment TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS payer_data TEXT DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS advance_amount DECIMAL(12,2) DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS advance_order_description TEXT DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS related_advance_ids JSON DEFAULT NULL;
ALTER TABLE issued_invoices ADD COLUMN IF NOT EXISTS cash_payment_status ENUM('paid','to_pay') DEFAULT NULL;

ALTER TABLE company_profiles ADD COLUMN IF NOT EXISTS proforma_number_pattern VARCHAR(100) DEFAULT 'PRO/{NR}/{MM}/{RRRR}';
ALTER TABLE company_profiles ADD COLUMN IF NOT EXISTS next_proforma_number INT NOT NULL DEFAULT 1;
ALTER TABLE company_profiles ADD COLUMN IF NOT EXISTS advance_number_pattern VARCHAR(100) DEFAULT 'ZAL/{NR}/{MM}/{RRRR}';
ALTER TABLE company_profiles ADD COLUMN IF NOT EXISTS next_advance_number INT NOT NULL DEFAULT 1;
ALTER TABLE company_profiles ADD COLUMN IF NOT EXISTS numbering_reset_mode ENUM('monthly','yearly','continuous') NOT NULL DEFAULT 'monthly';

ALTER TABLE client_tasks ADD COLUMN IF NOT EXISTS is_billable TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE client_tasks ADD COLUMN IF NOT EXISTS task_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE client_tasks ADD COLUMN IF NOT EXISTS billing_status ENUM('none','to_invoice','invoiced') NOT NULL DEFAULT 'none';

-- ══════════════════════════════════════════════
-- 27. CLIENT FILE SHARING (v27.0)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS client_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    uploaded_by_type ENUM('office','employee','client') NOT NULL,
    uploaded_by_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    category ENUM('general','invoice','contract','tax','correspondence','other') NOT NULL DEFAULT 'general',
    description VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_category (client_id, category),
    INDEX idx_created (client_id, created_at DESC),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE clients ADD COLUMN IF NOT EXISTS file_storage_path VARCHAR(500) DEFAULT NULL;

-- ══════════════════════════════════════════════
-- 29. MODULE MANAGEMENT SYSTEM (v29.0)
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'fas fa-puzzle-piece',
    category VARCHAR(50) DEFAULT 'general',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    enabled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    enabled_by_id INT DEFAULT NULL,
    UNIQUE KEY unique_office_module (office_id, module_id),
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    INDEX idx_office (office_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO modules (name, slug, description, icon, category, is_system, sort_order) VALUES
('Faktury zakupowe', 'invoices', 'Import i weryfikacja faktur zakupowych z KSeF', 'fas fa-file-invoice', 'core', 1, 1),
('Faktury sprzedazowe', 'sales', 'Wystawianie i zarzadzanie fakturami sprzedazowymi', 'fas fa-file-invoice-dollar', 'core', 0, 2),
('KSeF', 'ksef', 'Integracja z Krajowym Systemem e-Faktur', 'fas fa-exchange-alt', 'core', 0, 3),
('Kontrahenci', 'contractors', 'Baza kontrahentow i dostawcow', 'fas fa-address-book', 'core', 0, 4),
('Kalendarz podatkowy', 'tax-calendar', 'Kalendarz obowiazkow podatkowych', 'fas fa-calendar-alt', 'tax', 0, 5),
('Kalkulator podatkowy', 'tax-calculator', 'Symulacje i kalkulacje podatkowe', 'fas fa-calculator', 'tax', 0, 6),
('Platnosci podatkowe', 'tax-payments', 'Zarzadzanie platnosciami podatkowymi', 'fas fa-money-check-alt', 'tax', 0, 7),
('Wiadomosci', 'messages', 'System wiadomosci miedzy biurem a klientem', 'fas fa-envelope', 'communication', 0, 8),
('Zadania', 'tasks', 'Zarzadzanie zadaniami i rozliczanie czasu pracy', 'fas fa-tasks', 'communication', 0, 9),
('Pliki', 'files', 'Udostepnianie plikow miedzy biurem a klientem', 'fas fa-folder-open', 'communication', 0, 10),
('Analityka', 'analytics', 'Raporty i analizy danych', 'fas fa-chart-bar', 'reporting', 0, 11),
('Raporty', 'reports', 'Generowanie raportow JPK, VAT', 'fas fa-file-alt', 'reporting', 0, 12),
('Eksport ERP', 'erp-export', 'Eksport danych do systemow ERP', 'fas fa-download', 'reporting', 0, 13),
('Duplikaty', 'duplicates', 'Wykrywanie duplikatow faktur', 'fas fa-clone', 'tools', 0, 14),
('Profil firmy', 'company-profile', 'Zarzadzanie danymi firmy klienta', 'fas fa-building', 'core', 0, 15),
('Kadry / HR', 'hr', 'Zarzadzanie pracownikami biura', 'fas fa-users-cog', 'hr', 0, 16),
('Bezpieczenstwo', 'security', 'Ustawienia bezpieczenstwa i audyt', 'fas fa-shield-alt', 'system', 1, 17);

INSERT INTO office_modules (office_id, module_id, is_enabled)
SELECT o.id, m.id, 1
FROM offices o
CROSS JOIN modules m
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

-- ══════════════════════════════════════════════
-- DONE! All migrations applied.
-- ══════════════════════════════════════════════
