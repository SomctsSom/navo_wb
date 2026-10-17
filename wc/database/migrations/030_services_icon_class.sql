-- Services: Font Awesome icon class for public cards.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @c1 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'services'
      AND COLUMN_NAME = 'icon_class'
);
SET @s1 := IF(
    @c1 = 0,
    'ALTER TABLE services ADD COLUMN icon_class VARCHAR(100) NOT NULL DEFAULT ''fa-truck'' AFTER description',
    'SELECT 1'
);
PREPARE st1 FROM @s1; EXECUTE st1; DEALLOCATE PREPARE st1;

UPDATE services
SET icon_class = 'fa-truck'
WHERE deleted_at IS NULL
  AND (icon_class IS NULL OR TRIM(icon_class) = '');
