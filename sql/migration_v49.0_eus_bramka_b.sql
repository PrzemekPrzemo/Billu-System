-- =============================================
-- Migration v49.0: e-US Bramka B (JPK_V7M submission state)
--
-- Adds sub-state columns to client_monthly_status so the office UI
-- can show the e-US journey alongside the existing 'jpk' workflow
-- step. The legacy 'status' ENUM stays unchanged — this migration is
-- a SUPERSET, never a rename, so old reports keep working.
--
-- jpk_eus_status timeline (per period):
--   none → queued → submitted → przyjety → zaakceptowany   (happy path)
--                                       → odrzucony        (sad path)
--   none → error                                            (cert/UPL-1 issue)
--
-- jpk_eus_reference_no  — numer referencyjny e-US, fillable on submit
-- jpk_eus_upo_path      — relative path under storage/eus/{client_id}/
--                         when UPO is downloaded
--
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

-- ── client_monthly_status: e-US sub-state ──────────────
ALTER TABLE client_monthly_status
    ADD COLUMN IF NOT EXISTS jpk_eus_status        ENUM('none','queued','submitted','przyjety','zaakceptowany','odrzucony','error') NOT NULL DEFAULT 'none' AFTER status,
    ADD COLUMN IF NOT EXISTS jpk_eus_reference_no  VARCHAR(80) DEFAULT NULL  AFTER jpk_eus_status,
    ADD COLUMN IF NOT EXISTS jpk_eus_upo_path      VARCHAR(500) DEFAULT NULL AFTER jpk_eus_reference_no,
    ADD COLUMN IF NOT EXISTS jpk_eus_submitted_at  DATETIME DEFAULT NULL     AFTER jpk_eus_upo_path,
    ADD COLUMN IF NOT EXISTS jpk_eus_finalized_at  DATETIME DEFAULT NULL     AFTER jpk_eus_submitted_at;

-- One pass index for the "still in progress" report — covers
-- queued / submitted / przyjety states across all clients.
ALTER TABLE client_monthly_status
    ADD INDEX IF NOT EXISTS idx_jpk_eus_status (jpk_eus_status, period_year, period_month);

-- ── eus_documents: link back to client_monthly_status row ──
-- Already in v47.0 (related_status_id INT UNSIGNED). This migration
-- adds an FK-style index so per-period lookups are fast.
ALTER TABLE eus_documents
    ADD INDEX IF NOT EXISTS idx_doc_related_status (related_status_id);

-- ── Self-register migration ───────────────────────────
INSERT IGNORE INTO schema_migrations (filename) VALUES
  ('migration_v49.0_eus_bramka_b.sql');
