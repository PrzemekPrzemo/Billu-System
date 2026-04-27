-- =============================================
-- Migration v42.0: Geolocation columns for trusted_devices
-- Filled at issue-time via IpGeoService (ip-api.com); legacy rows
-- are backfilled lazily when the user opens /trusted-devices.
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

ALTER TABLE trusted_devices ADD COLUMN IF NOT EXISTS geo_country VARCHAR(80) DEFAULT NULL;
ALTER TABLE trusted_devices ADD COLUMN IF NOT EXISTS geo_country_code VARCHAR(2) DEFAULT NULL;
ALTER TABLE trusted_devices ADD COLUMN IF NOT EXISTS geo_region VARCHAR(80) DEFAULT NULL;
ALTER TABLE trusted_devices ADD COLUMN IF NOT EXISTS geo_city VARCHAR(120) DEFAULT NULL;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v42.0_trusted_devices_geo.sql');
