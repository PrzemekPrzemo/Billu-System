-- Migration v22.1: Add UPO download toggle to KSeF config
ALTER TABLE client_ksef_configs ADD COLUMN IF NOT EXISTS upo_enabled TINYINT(1) DEFAULT 1 AFTER is_active;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v22.1_upo_enabled.sql');
