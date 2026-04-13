-- Migration v11.1: Add attachment support to messages
-- Run this in phpMyAdmin or via CLI: mysql -u root -p faktury < sql/migration_v11.1_message_attachments.sql

ALTER TABLE messages
    ADD COLUMN attachment_path VARCHAR(500) DEFAULT NULL AFTER body,
    ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL AFTER attachment_path;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v11.1_message_attachments.sql');
