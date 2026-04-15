-- =============================================
-- BiLLU: COMMERCIAL MODULE SYSTEM (v31.0)
-- Module dependencies, bundles, subscription metadata
-- Safe to run multiple times
-- =============================================

-- ══════════════════════════════════════════════
-- 1. MODULE DEPENDENCIES — tracks which modules require others
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS module_dependencies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    depends_on_module_id INT UNSIGNED NOT NULL,
    dependency_type ENUM('required', 'recommended') NOT NULL DEFAULT 'required',
    UNIQUE KEY unique_dep (module_id, depends_on_module_id),
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (depends_on_module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 2. MODULE BUNDLES — commercial packages
-- ══════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS module_bundles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    modules_json JSON NOT NULL COMMENT 'Array of module slugs included in bundle',
    price_base_monthly DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Opłata bazowa za biuro/mies. netto',
    price_per_client DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Opłata za klienta/mies. netto',
    is_addon TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Czy jest dodatkiem do innego pakietu',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════
-- 3. EXTEND OFFICE_MODULES — subscription metadata
-- ══════════════════════════════════════════════

ALTER TABLE office_modules ADD COLUMN IF NOT EXISTS bundle_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE office_modules ADD COLUMN IF NOT EXISTS expires_at DATETIME DEFAULT NULL;

-- ══════════════════════════════════════════════
-- 4. SEED MODULE DEPENDENCIES
-- ══════════════════════════════════════════════

-- sales → requires company-profile
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug = 'sales' AND m2.slug = 'company-profile';

-- sales → requires contractors
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug = 'sales' AND m2.slug = 'contractors';

-- ksef → requires invoices
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug = 'ksef' AND m2.slug = 'invoices';

-- analytics → requires invoices
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug = 'analytics' AND m2.slug = 'invoices';

-- reports → requires invoices
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug = 'reports' AND m2.slug = 'invoices';

-- erp-export → requires invoices
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug = 'erp-export' AND m2.slug = 'invoices';

-- duplicates → requires invoices
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug = 'duplicates' AND m2.slug = 'invoices';

-- All payroll sub-modules → require hr
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug IN ('payroll-calc', 'payroll-lists', 'payroll-contracts', 'payroll-leave', 'payroll-pit', 'payroll-zus')
  AND m2.slug = 'hr';

-- payroll-lists → requires payroll-calc (internally uses calculator)
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'required'
FROM modules m1, modules m2
WHERE m1.slug = 'payroll-lists' AND m2.slug = 'payroll-calc';

-- ksef → recommended with sales (for sending)
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT m1.id, m2.id, 'recommended'
FROM modules m1, modules m2
WHERE m1.slug = 'sales' AND m2.slug = 'ksef';

-- ══════════════════════════════════════════════
-- 5. SEED COMMERCIAL BUNDLES
-- ══════════════════════════════════════════════

-- Pricing model: base per office + per client per month (netto PLN)
-- Competitive vs: wFirma 18 PLN/klient, SaldeoSMART 259-799 base, Comarch 500-800+/stanowisko

INSERT IGNORE INTO module_bundles (name, slug, description, modules_json, price_base_monthly, price_per_client, is_addon, sort_order) VALUES
(
    'Starter',
    'starter',
    'Weryfikacja faktur zakupowych, kontrahenci, komunikacja z klientem. Idealny na start.',
    '["invoices", "security", "contractors", "company-profile", "messages", "files"]',
    0.00,
    9.00,
    0,
    1
),
(
    'Professional',
    'professional',
    'Fakturowanie sprzedażowe, integracja KSeF, wykrywanie duplikatów, kalendarz podatkowy.',
    '["invoices", "security", "contractors", "company-profile", "messages", "files", "sales", "ksef", "duplicates", "tax-calendar"]',
    0.00,
    14.00,
    0,
    2
),
(
    'Tax & Finance',
    'tax-finance',
    'Pełna obsługa podatkowa: kalkulatory, płatności, raporty JPK/VAT, analityka, eksport ERP.',
    '["invoices", "security", "contractors", "company-profile", "messages", "files", "sales", "ksef", "duplicates", "tax-calendar", "tax-calculator", "tax-payments", "reports", "analytics", "erp-export"]',
    49.00,
    16.00,
    0,
    3
),
(
    'HR & Payroll',
    'hr-payroll',
    'Dodatek Kadry i Płace: pracownicy, umowy, listy płac, urlopy, deklaracje PIT/ZUS. Dokupowany do dowolnego pakietu.',
    '["hr", "payroll-calc", "payroll-lists", "payroll-contracts", "payroll-leave", "payroll-pit", "payroll-zus"]',
    29.00,
    4.00,
    1,
    4
),
(
    'Enterprise',
    'enterprise',
    'Pełny dostęp do wszystkich modułów BiLLU z HR i zadaniami. Najlepsza wartość dla dużych biur.',
    '["invoices", "security", "contractors", "company-profile", "messages", "files", "tasks", "sales", "ksef", "duplicates", "tax-calendar", "tax-calculator", "tax-payments", "reports", "analytics", "erp-export", "hr", "payroll-calc", "payroll-lists", "payroll-contracts", "payroll-leave", "payroll-pit", "payroll-zus"]',
    49.00,
    19.00,
    0,
    5
);

-- ══════════════════════════════════════════════
-- 6. TRACK MIGRATION
-- ══════════════════════════════════════════════

INSERT IGNORE INTO schema_migrations (filename) VALUES ('migration_v31.0_module_commerce.sql');
