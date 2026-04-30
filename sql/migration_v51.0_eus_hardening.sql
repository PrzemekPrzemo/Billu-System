-- =============================================
-- Migration v51.0: e-US hardening — daily metrics table
--
-- Single new table eus_metrics_daily for the master dashboard
-- (AdminEusController). One row per day with aggregate counts
-- snapshotted by CronService::eusHealthMetrics() every cron tick
-- (the cron upserts on (captured_date) so multiple ticks per day
-- are safe — the latest values win).
--
-- eus_documents.purged_at and retain_until were already added in
-- v47.0 — no schema bump for those.
--
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

CREATE TABLE IF NOT EXISTS eus_metrics_daily (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    captured_date               DATE NOT NULL,
    submitted_count             INT UNSIGNED NOT NULL DEFAULT 0,
    accepted_count              INT UNSIGNED NOT NULL DEFAULT 0,
    rejected_count              INT UNSIGNED NOT NULL DEFAULT 0,
    error_count                 INT UNSIGNED NOT NULL DEFAULT 0,
    kas_letters_received_count  INT UNSIGNED NOT NULL DEFAULT 0,
    polling_errors_count        INT UNSIGNED NOT NULL DEFAULT 0,
    cert_expiry_warnings_count  INT UNSIGNED NOT NULL DEFAULT 0,
    upl1_expiry_warnings_count  INT UNSIGNED NOT NULL DEFAULT 0,
    captured_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY ux_metrics_date  (captured_date),
    KEY        ix_captured_at   (captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Self-register
INSERT IGNORE INTO schema_migrations (filename) VALUES
  ('migration_v51.0_eus_hardening.sql');
