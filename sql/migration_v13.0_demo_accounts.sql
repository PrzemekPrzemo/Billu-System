-- Demo accounts support
ALTER TABLE offices ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v13.0_demo_accounts.sql');
