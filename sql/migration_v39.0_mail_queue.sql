-- Migration v39.0: Async email queue
-- Cel: rozdzielić wysyłkę SMTP od request/response cycle.
-- Worker (mail-worker.php uruchamiany z crona co minutę) zdejmuje wiersze
-- ze statusem 'pending', wysyła i aktualizuje status.

CREATE TABLE IF NOT EXISTS mail_queue (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email      VARCHAR(255) NOT NULL,
    subject       VARCHAR(500) NOT NULL,
    html_body     MEDIUMTEXT NOT NULL,
    client_id     INT UNSIGNED DEFAULT NULL COMMENT 'Dla brandingu/SMTP biura',
    status        ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
    retry_count   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    scheduled_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at       DATETIME DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mailq_status_scheduled (status, scheduled_at),
    INDEX idx_mailq_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
