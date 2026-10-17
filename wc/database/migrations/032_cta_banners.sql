-- CTA Banner module (horizontal call-to-action strip + multi-page assignment)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS cta_banners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    eyebrow VARCHAR(200) NULL,
    title VARCHAR(255) NOT NULL,
    subtitle TEXT NULL,
    primary_button_text VARCHAR(120) NULL,
    primary_button_url VARCHAR(500) NULL,
    secondary_button_text VARCHAR(120) NULL,
    secondary_button_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_cta_banners_active (is_active),
    KEY idx_cta_banners_sort (sort_order),
    KEY idx_cta_banners_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cta_banner_pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cta_banner_id BIGINT UNSIGNED NOT NULL,
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
    cta_banner_page_active VARCHAR(64) GENERATED ALWAYS AS (
        IF(deleted_at IS NULL, CONCAT(cta_banner_id, ':', page_id), NULL)
    ) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cta_banner_pages_active (cta_banner_page_active),
    KEY idx_cbp_banner (cta_banner_id),
    KEY idx_cbp_page (page_id),
    KEY idx_cbp_featured (is_featured),
    KEY idx_cbp_active (is_active),
    KEY idx_cbp_deleted (deleted_at),
    CONSTRAINT fk_cbp_banner FOREIGN KEY (cta_banner_id) REFERENCES cta_banners (id),
    CONSTRAINT fk_cbp_page FOREIGN KEY (page_id) REFERENCES pages (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
