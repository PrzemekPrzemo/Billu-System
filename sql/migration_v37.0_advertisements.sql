-- Migration v37.0: Advertisement/banner system
-- Allows admin to publish targeted banners per panel placement.

CREATE TABLE IF NOT EXISTS advertisements (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    placement   ENUM('client_panel','office_panel','ksef') NOT NULL,
    title       VARCHAR(255) NOT NULL,
    content     TEXT NOT NULL,
    link_url    VARCHAR(500) DEFAULT NULL,
    link_text   VARCHAR(100) DEFAULT NULL,
    type        ENUM('info','promo','warning','success') NOT NULL DEFAULT 'info',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    starts_at   DATETIME DEFAULT NULL,
    ends_at     DATETIME DEFAULT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_placement_active (placement, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
