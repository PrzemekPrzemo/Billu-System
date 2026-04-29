-- =============================================
-- Migration v46.0: External register notes (GUS / KRS / CEIDG / CRBR)
-- Append-only history of register lookups attached to a client or
-- contractor. Notes are visible ONLY to the office that owns the
-- target entity (office_admin + assigned office_employee). Clients
-- and client-employees never see these notes.
--
-- Source ENUM is forward-compatible: 'eus' for e-Urząd Skarbowy and
-- 'manual' for hand-entered office notes can land in the same table
-- without a future migration.
--
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

CREATE TABLE IF NOT EXISTS client_external_notes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id       INT UNSIGNED NOT NULL COMMENT 'tenant scope — the office of this client owns the note',
    target_type     ENUM('client','contractor') NOT NULL,
    target_id       INT UNSIGNED NOT NULL COMMENT 'clients.id when target_type=client; contractors.id when contractor',
    source          ENUM('gus','krs','ceidg','crbr','eus','manual') NOT NULL,
    source_ref      VARCHAR(64) DEFAULT NULL COMMENT 'NIP / KRS no / REGON used as the lookup key',
    raw_json        JSON NOT NULL COMMENT 'full API response (PESEL/PII redacted before insert)',
    formatted_html  MEDIUMTEXT NOT NULL COMMENT 'pre-rendered HTML for fast display',
    fetched_at      DATETIME NOT NULL,
    fetched_by_type VARCHAR(32) NOT NULL COMMENT 'office | office_employee | system',
    fetched_by_id   INT UNSIGNED NOT NULL,

    -- Tenant scope: every list/detail query goes through client_id first.
    INDEX idx_extnote_client_source_time (client_id, source, fetched_at DESC),
    -- Per-target lookup (client view "Notatki" tab, contractor view).
    INDEX idx_extnote_target_time (target_type, target_id, fetched_at DESC),
    -- Latest-by-source-per-target (for "show only newest" queries).
    INDEX idx_extnote_target_source_time (target_type, target_id, source, fetched_at DESC),

    CONSTRAINT fk_extnote_client
        FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
