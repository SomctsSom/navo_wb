-- Partners module (partner cards + multi-page assignment)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS partners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    section_title VARCHAR(200) NULL,
    section_subtitle VARCHAR(255) NULL,
    icon_class VARCHAR(80) NULL,
    badge_text VARCHAR(200) NULL,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(200) NULL,
    description TEXT NULL,
    useful_for TEXT NULL,
    button_text VARCHAR(120) NULL,
    button_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_partners_active (is_active),
    KEY idx_partners_sort (sort_order),
    KEY idx_partners_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_id BIGINT UNSIGNED NOT NULL,
    page_id BIGINT UNSIGNED NOT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    partner_page_active VARCHAR(64) GENERATED ALWAYS AS (
        IF(deleted_at IS NULL, CONCAT(partner_id, ':', page_id), NULL)
    ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_partner_pages_active (partner_page_active),
    KEY idx_pp_partner (partner_id),
    KEY idx_pp_page (page_id),
    KEY idx_pp_featured (is_featured),
    KEY idx_pp_active (is_active),
    KEY idx_pp_deleted (deleted_at),
    CONSTRAINT fk_pp_partner FOREIGN KEY (partner_id) REFERENCES partners (id),
    CONSTRAINT fk_pp_page FOREIGN KEY (page_id) REFERENCES pages (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
