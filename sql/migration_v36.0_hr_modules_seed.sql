-- Migration v36.0: Seed new HR sub-modules + advertisement system module
-- Adds 15 new module slugs required by the upcoming Faktury HR sync.
-- All HR sub-modules depend on the parent `hr` module (cascade on disable).
-- Idempotent: safe to run multiple times (INSERT IGNORE on unique slug).

-- ── New HR sub-modules (14) + advertisement system module (1) ──
INSERT IGNORE INTO modules (name, slug, description, icon, category, is_system, sort_order) VALUES
('Ewidencja czasu pracy',   'hr-attendance',   'Siatka obecnosci P/U/L/S/Z/I, nadgodziny, PDF karta pracy',         'fas fa-clock',             'hr',     0, 20),
('Szkolenia BHP',           'hr-bhp',          'Rejestr szkolen BHP z alertami wygasania (KP art. 237 par.3)',       'fas fa-hard-hat',          'hr',     0, 21),
('Badania lekarskie',       'hr-medical',      'Rejestr badan lekarskich wstepnych/okresowych/kontrolnych (KP 229)', 'fas fa-stethoscope',       'hr',     0, 22),
('Deklaracje PFRON',        'hr-pfron',        'Kalkulator wplat PFRON wg art. 21 ustawy o rehabilitacji',           'fas fa-wheelchair',        'hr',     0, 23),
('PPK',                     'hr-ppk',          'Pracownicze Plany Kapitalowe: enrollment, opt-out, raporty',         'fas fa-piggy-bank',        'hr',     0, 24),
('Onboarding pracownika',   'hr-onboarding',   'Checklist onboardingowa z progress barem',                           'fas fa-user-plus',         'hr',     0, 25),
('Teczka pracownika',       'hr-documents',    'e-Teczka: PIT-2, certyfikaty, BHP, badania, inne',                    'fas fa-folder',            'hr',     0, 26),
('Dokumenty firmowe',       'hr-company-docs', 'Regulamin pracy/wynagradzania, ZFSS, uklad zbiorowy',                 'fas fa-archive',           'hr',     0, 27),
('Raporty GUS Z-06',        'hr-gus',          'Kwartalne raporty statystyczne GUS',                                  'fas fa-chart-line',        'hr',     0, 28),
('Analityka HR',            'hr-analytics',    'Wykresy kosztow, donut typow umow, KPI pracownikow',                 'fas fa-chart-pie',         'hr',     0, 29),
('Komunikacja HR',          'hr-messaging',    'Wiadomosci z kontekstem pracownika/umowy/payroll/urlopu',            'fas fa-comments',          'hr',     0, 30),
('Masowe operacje HR',      'hr-mass-ops',     'Bulk update wynagrodzen (%/kwota) + eksport Excel',                   'fas fa-users-cog',         'hr',     0, 31),
('Matryca compliance',      'hr-compliance',   'Traffic lights: payroll / ZUS DRA / PIT-4 per klient',               'fas fa-traffic-light',     'hr',     0, 32),
('Dashboard wieloklient.',  'hr-batch',        'Dashboard biura po wszystkich klientach z HR',                        'fas fa-layer-group',       'hr',     0, 33),
('Reklamy',                 'advertisements',  'Banery reklamowe w panelach klienta, biura i KSeF',                  'fas fa-bullhorn',          'system', 1, 34);

-- ── Dependencies: all hr-* sub-modules require the parent `hr` module ──
-- Resolves module IDs by slug to stay robust against reordering.
INSERT IGNORE INTO module_dependencies (module_id, depends_on_module_id, dependency_type)
SELECT child.id, parent.id, 'required'
FROM modules child
CROSS JOIN modules parent
WHERE parent.slug = 'hr'
  AND child.slug IN (
    'hr-attendance', 'hr-bhp', 'hr-medical', 'hr-pfron', 'hr-ppk',
    'hr-onboarding', 'hr-documents', 'hr-company-docs', 'hr-gus',
    'hr-analytics', 'hr-messaging', 'hr-mass-ops', 'hr-compliance', 'hr-batch'
  );

-- ── Enable all new HR sub-modules for existing offices (default-on) ──
INSERT IGNORE INTO office_modules (office_id, module_id, is_enabled)
SELECT o.id, m.id, 1
FROM offices o
CROSS JOIN modules m
WHERE m.slug IN (
    'hr-attendance', 'hr-bhp', 'hr-medical', 'hr-pfron', 'hr-ppk',
    'hr-onboarding', 'hr-documents', 'hr-company-docs', 'hr-gus',
    'hr-analytics', 'hr-messaging', 'hr-mass-ops', 'hr-compliance', 'hr-batch',
    'advertisements'
  );
