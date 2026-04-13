-- Migration v7.6: Remove KSeF token-based authentication
-- Only certificate-based auth (qualified cert + KSeF cert) is supported now

-- Remove legacy token column from clients table
ALTER TABLE clients DROP COLUMN IF EXISTS ksef_api_token;

-- Remove encrypted token from ksef configs
ALTER TABLE client_ksef_configs DROP COLUMN IF EXISTS ksef_token_encrypted;

-- Update auth_method: convert any 'token' entries to 'none'
UPDATE client_ksef_configs SET auth_method = 'none' WHERE auth_method = 'token';

-- Remove token-related settings
DELETE FROM settings WHERE setting_key IN ('ksef_api_token', 'ksef_token_auth_enabled');
