-- =============================================
-- Migration v45.0: Contracts module
-- Office uploads an AcroForm PDF template, system parses fields,
-- generates a public-link form for the client to fill in, then
-- pdftk fills the PDF and SIGNIUS API sends it for e-signature.
-- Bezpieczne do wielokrotnego uruchomienia.
-- =============================================

-- ── Register the module ────────────────────────
INSERT IGNORE INTO modules (name, slug, description, icon, category, is_system, sort_order)
VALUES ('Umowy', 'contracts', 'Generowanie umów i aneksów z aktywnych PDF + integracja SIGNIUS', 'fas fa-file-signature', 'tools', 0, 50);

-- Auto-enable for existing offices (consistent with v29 / v30 pattern).
INSERT IGNORE INTO office_modules (office_id, module_id, is_enabled, enabled_at)
SELECT o.id, m.id, 1, NOW()
FROM offices o CROSS JOIN modules m
WHERE m.slug = 'contracts';

-- ── Templates: PDF blueprint with AcroForm ─────
CREATE TABLE IF NOT EXISTS contract_templates (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id           INT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    slug                VARCHAR(255) NOT NULL,
    description         TEXT DEFAULT NULL,
    original_filename   VARCHAR(255) NOT NULL,
    stored_path         VARCHAR(1024) NOT NULL COMMENT 'relative to project root, e.g. storage/contract_templates/{office_id}/{id}_{slug}.pdf',
    fields_json         JSON DEFAULT NULL COMMENT '[{name,type,label,required,default}]',
    signers_json        JSON DEFAULT NULL COMMENT '[{role,label,email_field,order}]',
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_by_type     ENUM('office','employee','admin') DEFAULT 'office',
    created_by_id       INT UNSIGNED DEFAULT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_office_slug (office_id, slug),
    INDEX idx_office_active (office_id, is_active),
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Forms: instance of a shared link to fill the template ──
CREATE TABLE IF NOT EXISTS contract_forms (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id         INT UNSIGNED NOT NULL,
    office_id           INT UNSIGNED NOT NULL COMMENT 'denormalized from template for fast tenant filter',
    client_id           INT UNSIGNED DEFAULT NULL COMMENT 'NULL when the form goes to a brand-new prospect (email only)',
    token               CHAR(64) NOT NULL UNIQUE COMMENT '32 random bytes, hex',
    expires_at          DATETIME NOT NULL,
    status              ENUM('pending','filled','submitted','signed','rejected','expired','cancelled') NOT NULL DEFAULT 'pending',
    recipient_email     VARCHAR(255) DEFAULT NULL,
    recipient_name      VARCHAR(255) DEFAULT NULL,
    form_data           JSON DEFAULT NULL,
    filled_pdf_path     VARCHAR(1024) DEFAULT NULL,
    signed_pdf_path     VARCHAR(1024) DEFAULT NULL,
    signius_package_id  VARCHAR(128) DEFAULT NULL,
    created_by_type     ENUM('office','employee') DEFAULT 'office',
    created_by_id       INT UNSIGNED DEFAULT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    submitted_at        DATETIME DEFAULT NULL,
    signed_at           DATETIME DEFAULT NULL,
    INDEX idx_token (token),
    INDEX idx_office_status (office_id, status, created_at),
    INDEX idx_client (client_id),
    INDEX idx_signius (signius_package_id),
    FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE RESTRICT,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Signing events (SIGNIUS webhook log + idempotency) ──
CREATE TABLE IF NOT EXISTS contract_signing_events (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id             INT UNSIGNED NOT NULL,
    event_type          VARCHAR(40) NOT NULL COMMENT 'sent / viewed / signed / rejected / expired / error',
    signer_email        VARCHAR(255) DEFAULT NULL,
    payload_json        JSON DEFAULT NULL,
    received_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_form_received (form_id, received_at),
    FOREIGN KEY (form_id) REFERENCES contract_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v45.0_contracts_module.sql');
