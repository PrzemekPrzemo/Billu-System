-- BiLLU Financial Solutions - System Weryfikacji Faktur
-- Schemat bazy danych

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Baza danych jest tworzona przez Plesk/hosting — nie wymuszamy CREATE DATABASE
-- CREATE DATABASE IF NOT EXISTS ... ;
-- USE ... ;

-- ============================================================
-- Tabela: users (administratorzy systemu)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'superadmin') NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: offices (biura księgowe)
-- ============================================================
CREATE TABLE IF NOT EXISTS offices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    address TEXT DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE COMMENT 'Used as login identifier',
    phone VARCHAR(50) DEFAULT NULL,
    representative_name VARCHAR(255) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    language ENUM('pl', 'en') NOT NULL DEFAULT 'pl',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    -- Per-office settings (override global)
    verification_deadline_day TINYINT DEFAULT NULL COMMENT 'NULL = use global setting',
    auto_accept_on_deadline TINYINT(1) DEFAULT NULL COMMENT 'NULL = use global setting',
    notification_days_before TINYINT DEFAULT NULL COMMENT 'NULL = use global setting',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nip (nip),
    INDEX idx_email (email),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: clients (klienci)
-- ============================================================
CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED DEFAULT NULL COMMENT 'Powiązane biuro księgowe',
    nip VARCHAR(10) NOT NULL UNIQUE,
    company_name VARCHAR(255) NOT NULL,
    representative_name VARCHAR(255) NOT NULL,
    address TEXT DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    report_email VARCHAR(255) NOT NULL COMMENT 'Email do wysyłki raportów',
    phone VARCHAR(50) DEFAULT NULL,
    regon VARCHAR(14) DEFAULT NULL,
    has_cost_centers TINYINT(1) NOT NULL DEFAULT 0,
    ksef_api_token VARCHAR(500) DEFAULT NULL COMMENT 'Token API KSeF per klient',
    ksef_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = integracja KSeF aktywna',
    password_hash VARCHAR(255) NOT NULL,
    password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    force_password_change TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = must change on next login',
    language ENUM('pl', 'en') NOT NULL DEFAULT 'pl',
    privacy_accepted TINYINT(1) NOT NULL DEFAULT 0,
    privacy_accepted_at TIMESTAMP NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE SET NULL,
    INDEX idx_nip (nip),
    INDEX idx_office (office_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: client_cost_centers (centra kosztów klientów)
-- ============================================================
CREATE TABLE IF NOT EXISTS client_cost_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client (client_id),
    INDEX idx_active (client_id, is_active)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: password_resets (tokeny resetowania hasła)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('client', 'office') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: user_sessions (aktywne sesje)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'client', 'office') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT DEFAULT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_type, user_id),
    INDEX idx_session (session_id),
    INDEX idx_activity (last_activity)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: login_history (historia logowań)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'client', 'office') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_type, user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: invoice_batches (paczki importów faktur)
-- ============================================================
CREATE TABLE IF NOT EXISTS invoice_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    office_id INT UNSIGNED DEFAULT NULL,
    period_month TINYINT NOT NULL COMMENT '1-12',
    period_year SMALLINT NOT NULL,
    import_filename VARCHAR(255) DEFAULT NULL,
    imported_by_type ENUM('admin', 'office', 'client') NOT NULL DEFAULT 'admin',
    imported_by_id INT UNSIGNED NOT NULL,
    verification_deadline DATE NOT NULL,
    is_finalized TINYINT(1) NOT NULL DEFAULT 0,
    finalized_at TIMESTAMP NULL,
    notification_sent TINYINT(1) NOT NULL DEFAULT 0,
    notification_sent_at TIMESTAMP NULL,
    source ENUM('file', 'ksef_api') NOT NULL DEFAULT 'file',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE SET NULL,
    UNIQUE KEY uk_client_period (client_id, period_month, period_year),
    INDEX idx_deadline (verification_deadline),
    INDEX idx_finalized (is_finalized),
    INDEX idx_office (office_id)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: invoices (faktury)
-- ============================================================
CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,

    -- Dane sprzedawcy (Podmiot1)
    seller_nip VARCHAR(10) NOT NULL,
    seller_name VARCHAR(255) NOT NULL,
    seller_address TEXT DEFAULT NULL,
    seller_contact VARCHAR(255) DEFAULT NULL,

    -- Dane nabywcy (Podmiot2)
    buyer_nip VARCHAR(20) NOT NULL,
    buyer_name VARCHAR(255) NOT NULL,
    buyer_address TEXT DEFAULT NULL,

    -- Dane faktury (Fa)
    invoice_number VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    sale_date DATE DEFAULT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PLN',
    net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    gross_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,

    -- Szczegóły pozycji (JSON)
    line_items JSON DEFAULT NULL COMMENT 'Pozycje towarowe/usługowe',
    vat_details JSON DEFAULT NULL COMMENT 'Szczegółowe kwoty VAT per stawka',

    -- KSeF reference
    ksef_reference_number VARCHAR(100) DEFAULT NULL,

    -- Status weryfikacji
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    comment TEXT DEFAULT NULL COMMENT 'Komentarz klienta (akceptacja lub odrzucenie)',
    cost_center VARCHAR(255) DEFAULT NULL COMMENT 'Miejsce powstania kosztów',
    cost_center_id INT UNSIGNED DEFAULT NULL,
    verified_at TIMESTAMP NULL,
    verified_by_auto TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = auto-akceptacja po terminie',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (batch_id) REFERENCES invoice_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (cost_center_id) REFERENCES client_cost_centers(id) ON DELETE SET NULL,
    INDEX idx_client (client_id),
    INDEX idx_batch (batch_id),
    INDEX idx_status (status),
    INDEX idx_issue_date (issue_date),
    INDEX idx_seller_nip (seller_nip),
    INDEX idx_ksef_ref (ksef_reference_number)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: reports (wygenerowane raporty)
-- ============================================================
CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL,
    report_type ENUM('accepted', 'rejected', 'full') NOT NULL DEFAULT 'accepted',
    cost_center_name VARCHAR(255) DEFAULT NULL,
    report_format ENUM('excel','jpk_xml') NOT NULL DEFAULT 'excel',
    pdf_path VARCHAR(500) DEFAULT NULL,
    xls_path VARCHAR(500) DEFAULT NULL,
    xml_path VARCHAR(500) DEFAULT NULL COMMENT 'Ścieżka do pliku JPK XML',
    email_sent TINYINT(1) NOT NULL DEFAULT 0,
    email_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES invoice_batches(id) ON DELETE CASCADE,
    INDEX idx_client_batch (client_id, batch_id)
) ENGINE=InnoDB;

-- ============================================================
-- Tabela: settings (ustawienia systemowe)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Domyślne ustawienia
INSERT INTO settings (setting_key, setting_value, description) VALUES
('verification_deadline_day', '25', 'Dzień miesiąca - termin weryfikacji faktur'),
('auto_accept_on_deadline', '1', 'Automatyczna akceptacja po upływie terminu (1=tak, 0=nie)'),
('password_expiry_days', '90', 'Wymuszenie zmiany hasła co X dni'),
('company_name', 'Biuro Księgowe', 'Nazwa biura księgowego'),
('company_email', 'biuro@example.com', 'Główny email biura księgowego'),
('notification_days_before', '3', 'Dni przed terminem - wysyłka przypomnienia'),
('session_timeout_minutes', '30', 'Auto-wylogowanie po X minutach nieaktywności'),
('max_sessions_per_user', '2', 'Maksymalna liczba równoległych sesji'),
-- GUS API
('gus_api_key', '', 'Klucz API do GUS (BIR1)'),
('gus_api_url', 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzworWorking/UslugaBIRzworWorking.svc', 'URL API GUS'),
('gus_api_env', 'test', 'Środowisko GUS API: test lub production'),
-- KSeF API
('ksef_api_token', '', 'Token autoryzacyjny KSeF'),
('ksef_api_url', 'https://api-test.ksef.mf.gov.pl/api/v2', 'URL API KSeF v2'),
('ksef_api_env', 'test', 'Środowisko KSeF: test, demo lub production'),
('ksef_nip', '', 'NIP podmiotu w KSeF'),
-- Branding
('system_name', 'BiLLU', 'Nazwa systemu wyświetlana w interfejsie'),
('system_description', 'System weryfikacji i akceptacji faktur kosztowych z KSeF', 'Opis na stronie logowania'),
('primary_color', '#008F8F', 'Kolor główny interfejsu'),
('secondary_color', '#0B2430', 'Kolor dodatkowy'),
('accent_color', '#882D61', 'Kolor akcentu'),
('logo_path', '/assets/img/logo.svg', 'Ścieżka do logo systemu'),
-- Privacy policy
('privacy_policy_enabled', '1', 'Wymóg akceptacji polityki prywatności'),
('privacy_policy_text', '', 'Treść polityki przetwarzania danych');

-- ============================================================
-- Tabela: audit_log (log działań - rozszerzony)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'client', 'office', 'system') NOT NULL,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL COMMENT 'np. client, invoice, batch, office',
    entity_id INT UNSIGNED DEFAULT NULL,
    details TEXT DEFAULT NULL,
    old_values JSON DEFAULT NULL COMMENT 'Wartości przed zmianą',
    new_values JSON DEFAULT NULL COMMENT 'Wartości po zmianie',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    impersonated_by INT UNSIGNED DEFAULT NULL COMMENT 'ID admina jeśli sesja impersonowana',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_type, user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at),
    INDEX idx_impersonated (impersonated_by)
) ENGINE=InnoDB;

-- ============================================================
-- Domyślny administrator (hasło: Admin123!@#$ - ZMIEŃ PO INSTALACJI!)
-- ============================================================
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@example.com', '$2y$12$placeholder_hash_change_this', 'superadmin');

-- ============================================================
-- Domyślna polityka prywatności
-- ============================================================
UPDATE settings SET setting_value = 'POLITYKA PRZETWARZANIA DANYCH OSOBOWYCH I FIRMOWYCH

System Weryfikacji Faktur KSeF

1. ADMINISTRATOR DANYCH
Administratorem danych przetwarzanych w niniejszym systemie jest Sendormeco Holding, NIP: 525-28-66-457 (dalej: \"Administrator\").

2. PODSTAWA PRZETWARZANIA
Dane przetwarzane są na podstawie upoważnienia wydanego przez biuro księgowe obsługujące Klienta, zgodnie z art. 6 ust. 1 lit. b) i f) RODO, w związku z realizacją usług księgowych oraz weryfikacją faktur kosztowych w systemie KSeF.

3. CEL PRZETWARZANIA
Dane osobowe i firmowe przetwarzane są w celu:
a) umożliwienia weryfikacji (akceptacji/odrzucenia) faktur kosztowych zarejestrowanych w Krajowym Systemie e-Faktur (KSeF),
b) generowania raportów z zaakceptowanych i odrzuconych faktur,
c) komunikacji z Klientem w zakresie weryfikacji faktur,
d) prowadzenia dokumentacji księgowej zgodnie z obowiązującymi przepisami.

4. ZAKRES PRZETWARZANYCH DANYCH
W systemie przetwarzane są następujące kategorie danych:
a) Dane identyfikacyjne firmy: NIP, nazwa firmy, adres, REGON,
b) Dane kontaktowe: adres e-mail, numer telefonu, imię i nazwisko przedstawiciela,
c) Dane fakturowe: numery faktur, kwoty, dane sprzedawców i nabywców,
d) Dane logowania: adres IP, data i czas logowania, identyfikator sesji,
e) Dane dotyczące decyzji: status weryfikacji faktury, komentarze, miejsce powstania kosztów.

5. OKRES PRZECHOWYWANIA
Dane przechowywane są przez okres niezbędny do realizacji celów przetwarzania, nie krócej niż wymagają tego przepisy prawa podatkowego i rachunkowego (minimum 5 lat od końca roku podatkowego).

6. ODBIORCY DANYCH
Dane mogą być udostępniane:
a) biuru księgowemu obsługującemu Klienta (na podstawie upoważnienia),
b) organom podatkowym i kontrolnym (na podstawie przepisów prawa),
c) dostawcom usług IT zapewniającym utrzymanie systemu.

7. PRAWA OSOBY, KTÓREJ DANE DOTYCZĄ
Przysługuje Państwu prawo do:
a) dostępu do swoich danych,
b) sprostowania danych,
c) usunięcia danych (w zakresie nieprzeciwnym obowiązkom prawnym),
d) ograniczenia przetwarzania,
e) przenoszenia danych,
f) wniesienia sprzeciwu wobec przetwarzania,
g) wniesienia skargi do Prezesa UODO.

8. ZABEZPIECZENIA
System stosuje następujące środki bezpieczeństwa:
a) szyfrowane połączenie (SSL/TLS),
b) hashowanie haseł algorytmem bcrypt,
c) automatyczne wylogowanie po 30 minutach nieaktywności,
d) wymuszenie zmiany hasła co 90 dni,
e) wymagania dotyczące siły hasła (min. 12 znaków),
f) rejestrowanie wszystkich zdarzeń w dzienniku audytu,
g) ochrona przed atakami CSRF, SQL Injection, XSS.

9. KONTAKT
W sprawach dotyczących przetwarzania danych prosimy o kontakt z Administratorem lub biurem księgowym obsługującym Państwa firmę.

Data ostatniej aktualizacji: 2026-03-26' WHERE setting_key = 'privacy_policy_text';
