-- ============================================================
-- Migration v45.0 — HR Payslip Email Distribution
-- ============================================================
-- Run against: billu_hr database
-- Adds payslip email settings, employee email preferences, and send log
-- ============================================================

-- 1. Add payslip email settings to hr_client_settings
ALTER TABLE hr_client_settings
    ADD COLUMN IF NOT EXISTS payslip_email_enabled          TINYINT(1)   NOT NULL DEFAULT 0          AFTER zus_payer_name,
    ADD COLUMN IF NOT EXISTS payslip_email_from             VARCHAR(255) DEFAULT NULL                AFTER payslip_email_enabled,
    ADD COLUMN IF NOT EXISTS payslip_email_subject_template VARCHAR(255) DEFAULT NULL                AFTER payslip_email_from;

UPDATE hr_client_settings
SET payslip_email_subject_template = 'Odcinek płacowy za {month} {year} — {company}'
WHERE payslip_email_subject_template IS NULL;

-- 2. Add email distribution fields to hr_employees
ALTER TABLE hr_employees
    ADD COLUMN IF NOT EXISTS receive_payslip_email TINYINT(1)   NOT NULL DEFAULT 0  AFTER notes,
    ADD COLUMN IF NOT EXISTS email_payslip         VARCHAR(512) DEFAULT NULL          AFTER receive_payslip_email;

-- 3. Create payslip email send log
CREATE TABLE IF NOT EXISTS hr_payslip_email_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id  INT UNSIGNED NOT NULL,
    employee_id     INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    recipient_email VARCHAR(512) NOT NULL,
    status          ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    error_message   TEXT DEFAULT NULL,
    sent_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_run   (payroll_run_id),
    INDEX idx_emp   (employee_id),
    INDEX idx_client(client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;
