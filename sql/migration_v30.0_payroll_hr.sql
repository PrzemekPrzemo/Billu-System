-- =============================================
-- BiLLU: PAYROLL & HR MODULE (v30.0)
-- Kadry i Płace — pełny moduł kadrowo-płacowy
-- Safe to run multiple times (IF NOT EXISTS, ON DUPLICATE KEY)
-- =============================================

-- ══════════════════════════════════════════════
-- 1. CLIENT MODULES — per-client module toggle
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS client_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    enabled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    enabled_by_id INT DEFAULT NULL,
    UNIQUE KEY unique_client_module (client_id, module_id),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 2. CLIENT EMPLOYEES — pracownicy klienta (nie biura)
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS client_employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    pesel VARCHAR(11) DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address_street VARCHAR(255) DEFAULT NULL,
    address_city VARCHAR(100) DEFAULT NULL,
    address_postal_code VARCHAR(10) DEFAULT NULL,
    tax_office VARCHAR(255) DEFAULT NULL,
    bank_account VARCHAR(32) DEFAULT NULL,
    nfz_branch VARCHAR(4) DEFAULT NULL COMMENT 'Oddział NFZ (01-16)',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    hired_at DATE DEFAULT NULL,
    terminated_at DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_active (client_id, is_active),
    INDEX idx_pesel (pesel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 3. EMPLOYEE CONTRACTS — umowy pracownicze
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS employee_contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    contract_type ENUM('umowa_o_prace', 'umowa_zlecenie', 'umowa_o_dzielo') NOT NULL,
    -- umowa o pracę
    work_time_fraction DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Wymiar etatu: 1.00, 0.50, 0.75',
    position VARCHAR(255) DEFAULT NULL,
    workplace VARCHAR(255) DEFAULT NULL,
    -- wynagrodzenie
    gross_salary DECIMAL(12,2) NOT NULL,
    salary_type ENUM('monthly', 'hourly', 'task') NOT NULL DEFAULT 'monthly',
    -- składki ZUS (flagi dla zlecenia)
    zus_emerytalna TINYINT(1) NOT NULL DEFAULT 1,
    zus_rentowa TINYINT(1) NOT NULL DEFAULT 1,
    zus_chorobowa TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Dobrowolna dla zlecenia',
    zus_wypadkowa TINYINT(1) NOT NULL DEFAULT 1,
    zus_zdrowotna TINYINT(1) NOT NULL DEFAULT 1,
    zus_fp TINYINT(1) NOT NULL DEFAULT 1,
    zus_fgsp TINYINT(1) NOT NULL DEFAULT 1,
    -- podatek
    tax_deductible_costs ENUM('basic', 'elevated') DEFAULT 'basic' COMMENT '250 lub 300 PLN',
    pit_exempt TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ulga dla młodych (<26 lat)',
    uses_kwota_wolna TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'PIT-2 złożony',
    -- PPK
    ppk_employee_rate DECIMAL(4,2) DEFAULT 2.00 COMMENT 'Stawka PPK pracownik %',
    ppk_employer_rate DECIMAL(4,2) DEFAULT 1.50 COMMENT 'Stawka PPK pracodawca %',
    ppk_active TINYINT(1) NOT NULL DEFAULT 0,
    -- KUP dla umowy o dzieło
    dzielo_kup_rate DECIMAL(4,2) DEFAULT 20.00 COMMENT '20% lub 50% KUP',
    -- daty
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    termination_date DATE DEFAULT NULL,
    status ENUM('active', 'terminated', 'expired', 'draft') NOT NULL DEFAULT 'draft',
    notes TEXT DEFAULT NULL,
    created_by_type ENUM('office', 'employee', 'admin') DEFAULT 'office',
    created_by_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES client_employees(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_employee (employee_id),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 4. PAYROLL LISTS — listy płac (nagłówki)
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS payroll_lists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    status ENUM('draft', 'calculated', 'approved', 'exported') NOT NULL DEFAULT 'draft',
    total_gross DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_net DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_employer_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    approved_by_type VARCHAR(20) DEFAULT NULL,
    approved_by_id INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by_type ENUM('office', 'employee', 'admin') DEFAULT 'office',
    created_by_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_client_period (client_id, year, month),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 5. PAYROLL ENTRIES — pozycje list płac (per pracownik)
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS payroll_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_list_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    contract_id INT UNSIGNED NOT NULL,
    -- brutto
    gross_salary DECIMAL(12,2) NOT NULL,
    overtime_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    bonus_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    other_additions DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_gross DECIMAL(12,2) NOT NULL,
    -- składki ZUS pracownik
    zus_emerytalna_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_rentowa_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_chorobowa_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_total_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    -- ubezpieczenie zdrowotne
    health_insurance_base DECIMAL(12,2) NOT NULL DEFAULT 0,
    health_insurance_full DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '9% podstawy',
    health_insurance_deductible DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '7.75% do odliczenia PIT',
    -- PIT
    tax_deductible_costs DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_base DECIMAL(12,2) NOT NULL DEFAULT 0,
    pit_advance DECIMAL(10,2) NOT NULL DEFAULT 0,
    -- PPK
    ppk_employee DECIMAL(10,2) NOT NULL DEFAULT 0,
    ppk_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    -- netto
    net_salary DECIMAL(12,2) NOT NULL,
    -- koszty pracodawcy (ponad brutto)
    zus_emerytalna_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_rentowa_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_wypadkowa_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_fp_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    zus_fgsp_employer DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_employer_cost DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Brutto + ZUS pracodawca + PPK pracodawca',
    -- metadane
    calculation_json TEXT DEFAULT NULL COMMENT 'Pełny breakdown kalkulacji',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payroll_list_id) REFERENCES payroll_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES client_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES employee_contracts(id) ON DELETE CASCADE,
    INDEX idx_payroll (payroll_list_id),
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 6. EMPLOYEE LEAVES — urlopy
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS employee_leaves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    contract_id INT UNSIGNED NOT NULL,
    leave_type ENUM('wypoczynkowy', 'chorobowy', 'macierzynski', 'ojcowski', 'wychowawczy', 'bezplatny', 'okolicznosciowy', 'na_zadanie', 'opieka_art188') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    business_days INT NOT NULL COMMENT 'Liczba dni roboczych',
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    approved_by_type VARCHAR(20) DEFAULT NULL,
    approved_by_id INT DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES client_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES employee_contracts(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_employee (employee_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 7. EMPLOYEE LEAVE BALANCES — salda urlopowe
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS employee_leave_balances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    contract_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    annual_entitlement INT NOT NULL DEFAULT 20 COMMENT '20 lub 26 dni',
    carried_over INT NOT NULL DEFAULT 0,
    used_days INT NOT NULL DEFAULT 0,
    on_demand_used INT NOT NULL DEFAULT 0 COMMENT 'Wykorzystane urlopy na żądanie (max 4)',
    UNIQUE KEY unique_employee_year (employee_id, contract_id, year),
    FOREIGN KEY (employee_id) REFERENCES client_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES employee_contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 8. PAYROLL DECLARATIONS — deklaracje PIT/ZUS
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS payroll_declarations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    declaration_type ENUM('PIT-11', 'PIT-4R', 'ZUS-DRA', 'ZUS-RCA') NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED DEFAULT NULL COMMENT 'NULL dla deklaracji rocznych',
    employee_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL dla deklaracji pracodawcy',
    status ENUM('draft', 'generated', 'sent', 'corrected') NOT NULL DEFAULT 'draft',
    xml_content MEDIUMTEXT DEFAULT NULL,
    pdf_path VARCHAR(500) DEFAULT NULL,
    generated_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by_type ENUM('office', 'employee', 'admin') DEFAULT 'office',
    created_by_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_type_year (client_id, declaration_type, year),
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 9. UPDATE EXISTING HR MODULE + ADD PAYROLL SUB-MODULES
-- ══════════════════════════════════════════════

-- Aktualizacja istniejącego modułu HR
UPDATE modules SET
    name = 'Kadry i Płace',
    description = 'Zarządzanie kadrami i płacami pracowników klienta',
    icon = 'fas fa-users-cog'
WHERE slug = 'hr';

-- Nowe pod-moduły płacowe
INSERT IGNORE INTO modules (name, slug, description, icon, category, is_system, sort_order) VALUES
('Kalkulacje płacowe',   'payroll-calc',      'Obliczanie wynagrodzeń brutto-netto',          'fas fa-calculator',     'hr', 0, 20),
('Listy płac',           'payroll-lists',     'Generowanie miesięcznych list płac',            'fas fa-list-alt',       'hr', 0, 21),
('Umowy pracownicze',    'payroll-contracts', 'Umowy o pracę, zlecenie, o dzieło',            'fas fa-file-contract',  'hr', 0, 22),
('Urlopy',               'payroll-leave',     'Zarządzanie urlopami pracowników',              'fas fa-umbrella-beach', 'hr', 0, 23),
('Deklaracje PIT',       'payroll-pit',       'Generowanie deklaracji PIT-11, PIT-4R',         'fas fa-file-invoice',   'hr', 0, 24),
('Deklaracje ZUS',       'payroll-zus',       'Generowanie deklaracji ZUS DRA/RCA',            'fas fa-landmark',       'hr', 0, 25);

-- Auto-enable new modules for all existing offices
INSERT INTO office_modules (office_id, module_id, is_enabled)
SELECT o.id, m.id, 1
FROM offices o
CROSS JOIN modules m
WHERE m.slug IN ('payroll-calc', 'payroll-lists', 'payroll-contracts', 'payroll-leave', 'payroll-pit', 'payroll-zus')
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;

-- ══════════════════════════════════════════════
-- 10. TRACK MIGRATION
-- ══════════════════════════════════════════════

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v30.0_payroll_hr.sql');
