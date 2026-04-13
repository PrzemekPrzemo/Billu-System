-- FaktuPilot v5.1 - Two-Factor Authentication (TOTP)

-- Add 2FA columns to users (admin) table
ALTER TABLE users
    ADD COLUMN two_factor_secret VARCHAR(64) DEFAULT NULL AFTER is_active,
    ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER two_factor_secret,
    ADD COLUMN two_factor_recovery_codes TEXT DEFAULT NULL AFTER two_factor_enabled;

-- Add 2FA columns to clients table
ALTER TABLE clients
    ADD COLUMN two_factor_secret VARCHAR(64) DEFAULT NULL AFTER is_active,
    ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER two_factor_secret,
    ADD COLUMN two_factor_recovery_codes TEXT DEFAULT NULL AFTER two_factor_enabled;

-- Add 2FA columns to offices table
ALTER TABLE offices
    ADD COLUMN two_factor_secret VARCHAR(64) DEFAULT NULL AFTER is_active,
    ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER two_factor_secret,
    ADD COLUMN two_factor_recovery_codes TEXT DEFAULT NULL AFTER two_factor_enabled;

-- Admin settings for 2FA policy
INSERT INTO settings (setting_key, setting_value, description) VALUES
    ('2fa_enabled', '1', 'Allow users to enable 2FA'),
    ('2fa_required', '0', 'Require 2FA for all users'),
    ('2fa_required_admin', '0', 'Require 2FA for admin users')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
