-- Migration v2.5: KSeF auto-import settings
-- Adds ksef_auto_import_day setting (0 = last day of month)

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('ksef_auto_import_day', '0');
