-- Migration v42.0: Company-level HR documents
-- Regulamin pracy, regulamin wynagradzania, ZFSś, układ zbiorowy etc.

CREATE TABLE IF NOT EXISTS hr_company_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    document_type ENUM('regulamin_pracy','regulamin_wynagradzania','zfss','uklad_zbiorowy','obwieszczenie','inne') NOT NULL,
    title VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(512) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    uploaded_by_type ENUM('office','employee') NOT NULL DEFAULT 'office',
    uploaded_by_id INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_type (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
