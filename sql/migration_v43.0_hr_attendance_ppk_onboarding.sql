-- Migration v43.0: HR aggregate (FINAL) — attendance, PPK, onboarding, client settings,
-- employee documents, payroll budgets, leave types
-- Schema fully aligned with Faktury hr-payroll-system-design-WnwSV

CREATE TABLE IF NOT EXISTS hr_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    type ENUM('work','vacation','sick','holiday','remote','other') NOT NULL DEFAULT 'work',
    work_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 480,
    overtime_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    notes VARCHAR(500) DEFAULT NULL,
    created_by_type ENUM('office','employee','client') NOT NULL DEFAULT 'office',
    created_by_id INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_employee_date (employee_id, work_date),
    INDEX idx_client_period (client_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_ppk_enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    action ENUM('enroll','opt_out') NOT NULL,
    effective_date DATE NOT NULL,
    institution VARCHAR(255) DEFAULT NULL,
    employee_rate DECIMAL(4,2) NOT NULL DEFAULT 2.00,
    employer_rate DECIMAL(4,2) NOT NULL DEFAULT 1.50,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_onboarding_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    phase ENUM('onboarding','offboarding') NOT NULL DEFAULT 'onboarding',
    category VARCHAR(50) NOT NULL DEFAULT 'other',
    title VARCHAR(255) NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    done_at DATETIME DEFAULT NULL,
    done_by_type ENUM('office','employee','client') DEFAULT NULL,
    done_by_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee_phase (employee_id, phase),
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_client_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    hr_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    document_type ENUM('pit2','swiadectwo_pracy','certyfikat','umowa','aneks','badania','bhp','inne') NOT NULL,
    title VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(512) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    expiry_date DATE DEFAULT NULL,
    alert_sent_30 TINYINT(1) NOT NULL DEFAULT 0,
    alert_sent_14 TINYINT(1) NOT NULL DEFAULT 0,
    alert_sent_7 TINYINT(1) NOT NULL DEFAULT 0,
    is_confidential TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by_type ENUM('office','employee','client') NOT NULL DEFAULT 'office',
    uploaded_by_id INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_client (client_id),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_payroll_budget (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    budget_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    planned_gross DECIMAL(14,2) NOT NULL DEFAULT 0,
    planned_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_client_period (client_id, budget_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_leave_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL = global default',
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    days_per_year TINYINT UNSIGNED DEFAULT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 1,
    requires_approval TINYINT(1) NOT NULL DEFAULT 1,
    color VARCHAR(7) NOT NULL DEFAULT '#008F8F',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO hr_leave_types (client_id, name, code, days_per_year, is_paid, requires_approval, color) VALUES
(NULL, 'Urlop wypoczynkowy', 'UW', 26, 1, 1, '#008F8F'),
(NULL, 'Urlop na żądanie', 'UZ', 4, 1, 0, '#0B2430'),
(NULL, 'Urlop bezpłatny', 'UB', NULL, 0, 1, '#6c757d'),
(NULL, 'Zwolnienie lekarskie', 'L4', NULL, 1, 0, '#dc3545'),
(NULL, 'Opieka nad dzieckiem', 'OD', 2, 1, 1, '#fd7e14'),
(NULL, 'Urlop macierzyński', 'UM', NULL, 1, 1, '#e83e8c'),
(NULL, 'Urlop ojcowski', 'UO', 14, 1, 1, '#20c997');
