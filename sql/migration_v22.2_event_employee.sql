-- Migration v22.2: Allow tax calendar events for employees (not just clients)
ALTER TABLE tax_custom_events
  MODIFY client_id INT UNSIGNED DEFAULT NULL,
  ADD COLUMN employee_id INT UNSIGNED DEFAULT NULL AFTER client_id,
  ADD INDEX idx_employee_date (employee_id, event_date);

-- Drop NOT NULL + FK constraint on client_id to allow employee-only events
-- (client_id stays for client events, employee_id for employee events, both NULL = office-wide)

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v22.2_event_employee.sql');
