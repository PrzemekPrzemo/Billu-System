-- Migration v11.0: Messages (communication) + Client Tasks
-- Date: 2026-04-03

-- ── Wiadomości (wątki konwersacyjne biuro ↔ klient) ──────────────
CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED DEFAULT NULL,
    batch_id INT UNSIGNED DEFAULT NULL,
    sender_type ENUM('office','employee','client') NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    body TEXT NOT NULL,
    is_read_by_client TINYINT(1) NOT NULL DEFAULT 0,
    is_read_by_office TINYINT(1) NOT NULL DEFAULT 0,
    parent_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_parent (parent_id),
    INDEX idx_unread_client (client_id, is_read_by_client),
    INDEX idx_unread_office (client_id, is_read_by_office),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Preferencje powiadomień o wiadomościach ──────────────────────
CREATE TABLE IF NOT EXISTS message_notification_prefs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('office','employee','client') NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    notify_new_thread TINYINT(1) NOT NULL DEFAULT 1,
    notify_new_reply TINYINT(1) NOT NULL DEFAULT 1,
    notify_email TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user (user_type, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Zadania biura dla klienta ────────────────────────────────────
CREATE TABLE IF NOT EXISTS client_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    created_by_type ENUM('office','employee','admin') NOT NULL,
    created_by_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    status ENUM('open','in_progress','done') NOT NULL DEFAULT 'open',
    due_date DATE DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    completed_by_type ENUM('office','employee','client') DEFAULT NULL,
    completed_by_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_status (client_id, status),
    INDEX idx_due_date (due_date, status),
    INDEX idx_client_priority (client_id, priority, status),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Schema migration record ──────────────────────────────────────
INSERT IGNORE INTO schema_migrations (version, description) VALUES
    ('11.0', 'Messages (biuro↔klient) + notification prefs + client tasks');
