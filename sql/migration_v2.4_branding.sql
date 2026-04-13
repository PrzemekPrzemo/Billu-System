-- Migration v2.4: Rebrand to BiLLU
-- Updates default branding settings

UPDATE settings SET setting_value = 'BiLLU' WHERE setting_key = 'system_name' AND setting_value = 'Faktury KSeF';
UPDATE settings SET setting_value = 'System weryfikacji i akceptacji faktur kosztowych z KSeF' WHERE setting_key = 'system_description' AND setting_value LIKE '%Witamy w systemie akceptacji%';
UPDATE settings SET setting_value = '#0B7285' WHERE setting_key = 'primary_color' AND setting_value = '#2563EB';
UPDATE settings SET setting_value = '#1B2A4A' WHERE setting_key = 'secondary_color' AND setting_value = '#1e40af';
UPDATE settings SET setting_value = '#22C55E' WHERE setting_key = 'accent_color' AND setting_value = '#16a34a';
UPDATE settings SET setting_value = '/assets/img/logo.svg' WHERE setting_key = 'logo_path' AND (setting_value = '' OR setting_value IS NULL);
