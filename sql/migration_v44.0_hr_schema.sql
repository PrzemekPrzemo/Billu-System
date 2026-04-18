-- Migration v44.0: New HR core schema (CREATE TABLE only)
-- Replaces legacy Employee*/Payroll* models with Hr* equivalents.
-- Run BEFORE v44.1 (data migration) and v44.2 (DROP legacy tables).
-- DO NOT run v44.2 without verifying row counts after v44.1.

-- hr_employees: client workers (separate domain from office_employees)
CREATE TABLE IF NOT EXISTS hr_employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    pesel TEXT DEFAULT NULL COMMENT 'encrypted',
    pesel_hash VARCHAR(64) DEFAULT NULL COMMENT 'searchable hash',
    nip TEXT DEFAULT NULL COMMENT 'encrypted',
    birth_date TEXT DEFAULT NULL COMMENT 'encrypted',
    address_street TEXT DEFAULT NULL COMMENT 'encrypted',
    address_city TEXT DEFAULT NULL COMMENT 'encrypted',
    address_zip TEXT DEFAULT NULL COMMENT 'encrypted',
    email TEXT DEFAULT NULL COMMENT 'encrypted',
    phone TEXT DEFAULT NULL COMMENT 'encrypted',
    bank_account_iban TEXT DEFAULT NULL COMMENT 'encrypted',
    bank_name TEXT DEFAULT NULL COMMENT 'encrypted',
    tax_office_code TEXT DEFAULT NULL COMMENT 'encrypted',
    tax_office_name TEXT DEFAULT NULL COMMENT 'encrypted',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    employment_start DATE NOT NULL,
    employment_end DATE DEFAULT NULL,
    archived_at DATETIME DEFAULT NULL,
    archive_reason ENUM('end_of_contract','resignation','dismissal','other') DEFAULT NULL,
    swiadectwo_pdf_path VARCHAR(500) DEFAULT NULL,
    ppk_enrolled TINYINT(1) NOT NULL DEFAULT 0,
    ppk_enrolled_at DATE DEFAULT NULL,
    ppk_opted_out_at DATE DEFAULT NULL,
    ppk_institution VARCHAR(255) DEFAULT NULL,
    disability_level ENUM('none','mild','moderate','severe') NOT NULL DEFAULT 'none',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_pesel_hash (pesel_hash),
    INDEX idx_active (client_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hr_contracts: replaces employee_contracts
CREATE TABLE IF NOT EXISTS hr_contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    contract_type ENUM('uop','uz','uod') NOT NULL DEFAULT 'uop',
    position VARCHAR(255) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    base_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
    work_time_fraction DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    ppk_enrolled TINYINT(1) NOT NULL DEFAULT 0,
    ppk_employee_rate DECIMAL(4,2) NOT NULL DEFAULT 2.00,
    ppk_employer_rate DECIMAL(4,2) NOT NULL DEFAULT 1.50,
    umowa_pdf_path VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_client (client_id),
    INDEX idx_current (employee_id, is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hr_leave_requests: replaces employee_leaves
CREATE TABLE IF NOT EXISTS hr_leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    days_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    reviewed_by_type ENUM('office','employee','client') DEFAULT NULL,
    reviewed_by_id INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    rejection_reason VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_client_status (client_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hr_leave_balances: replaces employee_leave_balances
CREATE TABLE IF NOT EXISTS hr_leave_balances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    entitled_days SMALLINT NOT NULL DEFAULT 0,
    used_days SMALLINT NOT NULL DEFAULT 0,
    carried_over_days SMALLINT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_employee_type_year (employee_id, leave_type_id, year),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hr_payroll_runs: replaces payroll_lists
CREATE TABLE IF NOT EXISTS hr_payroll_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    status ENUM('draft','calculated','approved','locked') NOT NULL DEFAULT 'draft',
    total_gross DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_net DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_zus_employee DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_zus_employer DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_pit_advance DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_ppk_employee DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_ppk_employer DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_employer_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    employee_count INT UNSIGNED NOT NULL DEFAULT 0,
    processed_by_type VARCHAR(16) DEFAULT NULL,
    processed_by_id INT UNSIGNED DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,
    approved_by_type VARCHAR(16) DEFAULT NULL,
    approved_by_id INT UNSIGNED DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    unlock_reason TEXT DEFAULT NULL,
    unlocked_at DATETIME DEFAULT NULL,
    unlocked_by_type VARCHAR(16) DEFAULT NULL,
    unlocked_by_id INT UNSIGNED DEFAULT NULL,
    is_correction TINYINT(1) NOT NULL DEFAULT 0,
    corrects_run_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_period (client_id, period_month, period_year, is_correction),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hr_payroll_items: replaces payroll_entries
CREATE TABLE IF NOT EXISTS hr_payroll_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    gross_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    zus_emerytalne_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_rentowe_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_chorobowe_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_zdrowotne DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_emerytalne_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_rentowe_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_wypadkowe DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_fp DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_fgsp DECIMAL(10,2) NOT NULL DEFAULT 0,
    pit_advance DECIMAL(10,2) NOT NULL DEFAULT 0,
    ppk_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    ppk_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_employer_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_exempt TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ulga dla młodych art. 21 ust. 1 pkt 148',
    payslip_pdf_path VARCHAR(500) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_run_employee (run_id, employee_id),
    INDEX idx_employee (employee_id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hr_pit_declarations: replaces payroll_declarations (PIT type)
CREATE TABLE IF NOT EXISTS hr_pit_declarations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    declaration_type ENUM('pit4r','pit11') NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED DEFAULT NULL COMMENT 'NULL for annual declarations',
    status ENUM('draft','generated','submitted') NOT NULL DEFAULT 'draft',
    xml_path VARCHAR(500) DEFAULT NULL,
    pdf_path VARCHAR(500) DEFAULT NULL,
    generated_at DATETIME DEFAULT NULL,
    generated_by_type VARCHAR(16) DEFAULT NULL,
    generated_by_id INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_type_year (client_id, declaration_type, period_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hr_zus_declarations: replaces payroll_declarations (ZUS type)
CREATE TABLE IF NOT EXISTS hr_zus_declarations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    declaration_type ENUM('dra','rca','rza','rsza') NOT NULL DEFAULT 'dra',
    period_month TINYINT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    status ENUM('draft','generated','submitted') NOT NULL DEFAULT 'draft',
    xml_path VARCHAR(500) DEFAULT NULL,
    total_employer_contributions DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_employee_contributions DECIMAL(14,2) NOT NULL DEFAULT 0,
    generated_at DATETIME DEFAULT NULL,
    generated_by_type VARCHAR(16) DEFAULT NULL,
    generated_by_id INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_type_period (client_id, declaration_type, period_month, period_year),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
