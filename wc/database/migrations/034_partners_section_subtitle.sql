-- Partners section subtitle (shown under section title)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'partners'
      AND COLUMN_NAME = 'section_subtitle'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE partners ADD COLUMN section_subtitle VARCHAR(255) NULL AFTER section_title',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
