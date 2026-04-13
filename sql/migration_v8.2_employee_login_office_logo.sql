-- v8.2: Employee login system + office logo

-- Employee authentication fields
ALTER TABLE office_employees
  ADD COLUMN password_hash VARCHAR(255) NULL AFTER position,
  ADD COLUMN password_changed_at TIMESTAMP NULL AFTER password_hash,
  ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 1 AFTER password_changed_at,
  ADD COLUMN last_login_at TIMESTAMP NULL AFTER force_password_change;

-- Unique email for employee login (only non-null emails)
ALTER TABLE office_employees ADD UNIQUE INDEX idx_employee_email (email);

-- Office logo
ALTER TABLE offices ADD COLUMN logo_path VARCHAR(500) NULL AFTER representative_name;
