-- Migration v39.0: BHP training registry + Medical examinations tracking
-- Polish legal requirements: Kodeks pracy Art. 237(3) (BHP) + Art. 229 (badania)

CREATE TABLE IF NOT EXISTS hr_bhp_trainings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    training_type ENUM('wstepne','okresowe','stanowiskowe','bhp_ogolne') NOT NULL,
    completed_at DATE NOT NULL,
    expires_at DATE DEFAULT NULL,
    certificate_number VARCHAR(100) DEFAULT NULL,
    trainer_name VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    alert_sent_30 TINYINT(1) NOT NULL DEFAULT 0,
    alert_sent_14 TINYINT(1) NOT NULL DEFAULT 0,
    alert_sent_7 TINYINT(1) NOT NULL DEFAULT 0,
    created_by_type ENUM('office','employee','client') NOT NULL DEFAULT 'office',
    created_by_id INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_client (client_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_medical_exams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    exam_type ENUM('wstepne','okresowe','kontrolne') NOT NULL,
    exam_date DATE NOT NULL,
    valid_until DATE DEFAULT NULL,
    result ENUM('zdolny','niezdolny','ograniczenia') DEFAULT NULL,
    doctor_name VARCHAR(255) DEFAULT NULL,
    certificate_number VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    alert_sent_30 TINYINT(1) NOT NULL DEFAULT 0,
    alert_sent_14 TINYINT(1) NOT NULL DEFAULT 0,
    alert_sent_7 TINYINT(1) NOT NULL DEFAULT 0,
    created_by_type ENUM('office','employee','client') NOT NULL DEFAULT 'office',
    created_by_id INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee (employee_id),
    INDEX idx_client (client_id),
    INDEX idx_valid_until (valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
