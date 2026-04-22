-- Migration v46.0: HR Payroll Corrections (Retroactive)
-- Enables unlocking locked payroll runs and creating corrections

ALTER TABLE hr_payroll_runs
    ADD COLUMN IF NOT EXISTS is_correction     TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS corrects_run_id   INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS unlock_reason     TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS unlocked_at       DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS unlocked_by_type  VARCHAR(16) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS unlocked_by_id    INT UNSIGNED DEFAULT NULL;

ALTER TABLE hr_payroll_items
    ADD COLUMN IF NOT EXISTS is_correction       TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS original_item_id    INT UNSIGNED DEFAULT NULL;
