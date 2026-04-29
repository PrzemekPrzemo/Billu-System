-- =============================================
-- Migration v40.0: Client Employee authentication
-- Dodaje kolumny logowania, 2FA i tokenów aktywacyjnych
-- do client_employees, plus rozszerza password_resets
-- o typ 'client_employee'.
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

-- ── Login & password fields ─────────────────────
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS login_email VARCHAR(255) DEFAULT NULL AFTER email;
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL;
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS can_login TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Czy pracownik może logować się do panelu';
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS force_password_change TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS password_changed_at DATETIME DEFAULT NULL;
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS last_login_at DATETIME DEFAULT NULL;

-- ── Activation token (jednorazowy link aktywacyjny w mailu) ───
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS activation_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS activation_expires_at DATETIME DEFAULT NULL;

-- ── 2FA (opt-in, jak office/client) ─────────────
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) DEFAULT NULL;
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS two_factor_enabled_at DATETIME DEFAULT NULL;
ALTER TABLE client_employees ADD COLUMN IF NOT EXISTS recovery_codes TEXT DEFAULT NULL COMMENT 'JSON-encoded array of one-time recovery codes';

-- ── Indices ────────────────────────────────────
ALTER TABLE client_employees ADD UNIQUE INDEX IF NOT EXISTS uniq_login_email (login_email);
ALTER TABLE client_employees ADD INDEX IF NOT EXISTS idx_activation_token (activation_token);
ALTER TABLE client_employees ADD INDEX IF NOT EXISTS idx_can_login (client_id, can_login, is_active);

-- ── password_resets — dopuść client_employee ────
ALTER TABLE password_resets MODIFY COLUMN user_type ENUM('client', 'office', 'client_employee') NOT NULL;

-- ── Audit log type extension is implicit (varchar field, no enum)

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v40.0_client_employee_auth.sql');
