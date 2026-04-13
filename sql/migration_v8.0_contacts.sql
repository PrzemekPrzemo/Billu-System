-- Migration v8.0: Office employees & tech support contact
-- Run: mysql -u u_fp_ -p fp_ < sql/migration_v8.0_contacts.sql

-- Tabela pracowników biura księgowego
CREATE TABLE IF NOT EXISTS office_employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    position VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE CASCADE,
    INDEX idx_office (office_id)
) ENGINE=InnoDB;

-- Przypisanie pracowników do klientów
CREATE TABLE IF NOT EXISTS office_employee_clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES office_employees(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY uk_employee_client (employee_id, client_id),
    INDEX idx_client (client_id)
) ENGINE=InnoDB;

-- Dane kontaktowe wsparcia technicznego (ustawienia globalne)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('support_contact_name', ''),
    ('support_contact_email', ''),
    ('support_contact_phone', '');
