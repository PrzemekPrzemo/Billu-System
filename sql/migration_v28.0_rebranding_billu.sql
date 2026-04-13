-- Rebranding: FaktuPilot -> BiLLU Financial Solutions
UPDATE settings SET setting_value = 'BiLLU' WHERE setting_key = 'system_name' AND setting_value = 'FaktuPilot';
UPDATE settings SET setting_value = '#008F8F' WHERE setting_key = 'primary_color';
UPDATE settings SET setting_value = '#0B2430' WHERE setting_key = 'secondary_color';
UPDATE settings SET setting_value = '#882D61' WHERE setting_key = 'accent_color';
UPDATE settings SET setting_value = '/assets/img/logo.svg' WHERE setting_key = 'logo_path';
