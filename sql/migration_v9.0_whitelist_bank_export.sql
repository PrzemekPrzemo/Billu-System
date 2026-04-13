-- Migration v9.0: White List VAT API settings for bank export
-- Adds settings for VAT white list verification before Elixir-O bank export

INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
('whitelist_api_url', 'https://wl-api.mf.gov.pl', 'URL API Białej Listy VAT (Ministerstwo Finansów)'),
('whitelist_check_enabled', '1', 'Weryfikacja białej listy VAT przy eksporcie bankowym Elixir-O (1=włączona, 0=wyłączona)');
