-- Migration v27.0: Client file sharing system
-- Allows clients to upload files to their accounting office
-- Office can configure custom storage path per client

CREATE TABLE IF NOT EXISTS client_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    uploaded_by_type ENUM('office','employee','client') NOT NULL,
    uploaded_by_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    category ENUM('general','invoice','contract','tax','correspondence','other') NOT NULL DEFAULT 'general',
    description VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_category (client_id, category),
    INDEX idx_created (client_id, created_at DESC),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add configurable storage path per client (set by office)
ALTER TABLE clients ADD COLUMN IF NOT EXISTS file_storage_path VARCHAR(500) DEFAULT NULL;
-- NULL means use default: storage/client_files/{nip}/
-- If set, files are stored in this absolute path (e.g. /mnt/shared/clients/firma_xyz/)
