-- Migration v29.0: Module Management System
-- Allows master admin to enable/disable feature modules per accounting office

CREATE TABLE IF NOT EXISTS modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'fas fa-puzzle-piece',
    category VARCHAR(50) DEFAULT 'general',
    is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System module - cannot be disabled',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Globally active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    enabled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    enabled_by_id INT DEFAULT NULL COMMENT 'Admin user ID who toggled',
    UNIQUE KEY unique_office_module (office_id, module_id),
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    INDEX idx_office (office_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default modules
INSERT INTO modules (name, slug, description, icon, category, is_system, sort_order) VALUES
('Faktury zakupowe', 'invoices', 'Import i weryfikacja faktur zakupowych z KSeF', 'fas fa-file-invoice', 'core', 1, 1),
('Faktury sprzedazowe', 'sales', 'Wystawianie i zarzadzanie fakturami sprzedazowymi', 'fas fa-file-invoice-dollar', 'core', 0, 2),
('KSeF', 'ksef', 'Integracja z Krajowym Systemem e-Faktur', 'fas fa-exchange-alt', 'core', 0, 3),
('Kontrahenci', 'contractors', 'Baza kontrahentow i dostawcow', 'fas fa-address-book', 'core', 0, 4),
('Kalendarz podatkowy', 'tax-calendar', 'Kalendarz obowiazkow podatkowych', 'fas fa-calendar-alt', 'tax', 0, 5),
('Kalkulator podatkowy', 'tax-calculator', 'Symulacje i kalkulacje podatkowe', 'fas fa-calculator', 'tax', 0, 6),
('Platnosci podatkowe', 'tax-payments', 'Zarzadzanie platnosciami podatkowymi', 'fas fa-money-check-alt', 'tax', 0, 7),
('Wiadomosci', 'messages', 'System wiadomosci miedzy biurem a klientem', 'fas fa-envelope', 'communication', 0, 8),
('Zadania', 'tasks', 'Zarzadzanie zadaniami i rozliczanie czasu pracy', 'fas fa-tasks', 'communication', 0, 9),
('Pliki', 'files', 'Udostepnianie plikow miedzy biurem a klientem', 'fas fa-folder-open', 'communication', 0, 10),
('Analityka', 'analytics', 'Raporty i analizy danych', 'fas fa-chart-bar', 'reporting', 0, 11),
('Raporty', 'reports', 'Generowanie raportow JPK, VAT', 'fas fa-file-alt', 'reporting', 0, 12),
('Eksport ERP', 'erp-export', 'Eksport danych do systemow ERP', 'fas fa-download', 'reporting', 0, 13),
('Duplikaty', 'duplicates', 'Wykrywanie duplikatow faktur', 'fas fa-clone', 'tools', 0, 14),
('Profil firmy', 'company-profile', 'Zarzadzanie danymi firmy klienta', 'fas fa-building', 'core', 0, 15),
('Kadry / HR', 'hr', 'Zarzadzanie pracownikami biura', 'fas fa-users-cog', 'hr', 0, 16),
('Bezpieczenstwo', 'security', 'Ustawienia bezpieczenstwa i audyt', 'fas fa-shield-alt', 'system', 1, 17);

-- Enable all modules for existing offices
INSERT INTO office_modules (office_id, module_id, is_enabled)
SELECT o.id, m.id, 1
FROM offices o
CROSS JOIN modules m
ON DUPLICATE KEY UPDATE is_enabled = is_enabled;
