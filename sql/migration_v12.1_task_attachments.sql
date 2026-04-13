-- Task attachments support
ALTER TABLE client_tasks
    ADD COLUMN attachment_path VARCHAR(500) DEFAULT NULL AFTER description,
    ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL AFTER attachment_path;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v12.1_task_attachments.sql');
