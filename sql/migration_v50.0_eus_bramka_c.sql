-- =============================================
-- Migration v50.0: e-US Bramka C — KAS correspondence inbox
--
-- Extends messages + client_tasks with 'system' / 'eus' sender types
-- and eus_document_id FKs so KAS letters arrive as message threads
-- and replies live in the same thread.
--
-- Also includes a forward fix for PR-2 / PR-3 — those PRs introduced
-- 'system' as a sender_type / created_by_type but the ENUMs hadn't
-- been extended yet (latent issue: any 'system' message_create would
-- have failed at INSERT). v50 retroactively makes those ENUMs valid.
--
-- Bezpieczne do wielokrotnego uruchomienia (ALTER … MODIFY is
-- idempotent for ENUM extensions).
-- =============================================

-- ── messages: extend sender_type + add eus_document_id ─
ALTER TABLE messages
    MODIFY sender_type ENUM('office','employee','client','system','eus') NOT NULL;

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS eus_document_id INT UNSIGNED DEFAULT NULL AFTER parent_id,
    ADD INDEX IF NOT EXISTS idx_msg_eus_document (eus_document_id);

-- ── client_tasks: extend created_by_type + add eus_document_id ─
ALTER TABLE client_tasks
    MODIFY created_by_type ENUM('office','employee','admin','client','system') NOT NULL;

ALTER TABLE client_tasks
    ADD COLUMN IF NOT EXISTS eus_document_id INT UNSIGNED DEFAULT NULL AFTER status,
    ADD INDEX IF NOT EXISTS idx_task_eus_document (eus_document_id);

-- ── KAS retention settings key (used by RodoDeleteService in PR-5) ─
INSERT IGNORE INTO settings (setting_key, setting_value)
VALUES ('eus_kas_letters_retain_years', '10');

-- ── Self-register migration ───────────────────────────
INSERT IGNORE INTO schema_migrations (filename) VALUES
  ('migration_v50.0_eus_bramka_c.sql');
