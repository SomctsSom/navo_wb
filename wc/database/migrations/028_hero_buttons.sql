-- Hero buttons: one hero can have a single button or several.
-- Compatible with MySQL 8 / MariaDB 11.x
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS hero_buttons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hero_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME NULL,
    deleted_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_hero_buttons_hero (hero_id),
    KEY idx_hero_buttons_sort (sort_order),
    KEY idx_hero_buttons_active (is_active),
    KEY idx_hero_buttons_deleted (deleted_at),
    CONSTRAINT fk_hero_buttons_hero FOREIGN KEY (hero_id) REFERENCES hero (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copy the original single button onto the new table once.
INSERT INTO hero_buttons (hero_id, label, url, sort_order, is_active, created_at)
SELECT h.id, TRIM(h.button_text), NULLIF(TRIM(h.button_url), ''), 0, 1, h.created_at
FROM hero h
WHERE h.deleted_at IS NULL
  AND h.button_text IS NOT NULL
  AND TRIM(h.button_text) <> ''
  AND h.id NOT IN (
      SELECT hero_id FROM (
          SELECT hero_id FROM hero_buttons WHERE deleted_at IS NULL
      ) AS existing_buttons
  );
