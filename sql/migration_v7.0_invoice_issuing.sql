-- Migration v7.0 - Invoice Issuing System
-- Adds tables for client invoice issuing, contractor management, company profiles

-- Company profiles - client's company details for invoice headers
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

-- Company bank accounts
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

-- Company services/products catalog
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

-- Contractors (client's buyers)
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

-- Issued invoices (sales invoices created by clients)
CREATE TABLE IF NOT EXISTS issued_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    contractor_id INT UNSIGNED,

    -- Document type and number
    invoice_type ENUM('FV','FV_KOR','FP') DEFAULT 'FV',
    invoice_number VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    sale_date DATE NOT NULL,
    due_date DATE,

    -- Seller (= client)
    seller_nip VARCHAR(20) NOT NULL,
    seller_name VARCHAR(255) NOT NULL,
    seller_address TEXT,

    -- Buyer (= contractor)
    buyer_nip VARCHAR(20),
    buyer_name VARCHAR(255) NOT NULL,
    buyer_address TEXT,

    -- Amounts
    currency VARCHAR(3) DEFAULT 'PLN',
    net_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    gross_amount DECIMAL(15,2) NOT NULL DEFAULT 0,

    -- Details (JSON)
    line_items JSON NOT NULL DEFAULT ('[]'),
    vat_details JSON NOT NULL DEFAULT ('[]'),

    -- Payment
    payment_method ENUM('przelew','gotowka','karta','kompensata','barter') DEFAULT 'przelew',
    bank_account_id INT UNSIGNED,
    bank_account_number VARCHAR(34),
    bank_name VARCHAR(255),

    -- Extra
    notes TEXT,
    internal_notes TEXT,

    -- KSeF
    ksef_reference_number VARCHAR(100),
    ksef_sent_at TIMESTAMP NULL,
    ksef_status ENUM('none','pending','sent','accepted','rejected','error') DEFAULT 'none',
    ksef_error TEXT,

    -- Correction reference
    corrected_invoice_id INT UNSIGNED,
    correction_reason TEXT,

    -- PDF
    pdf_path VARCHAR(500),

    -- Status
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
