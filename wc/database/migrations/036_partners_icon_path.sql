-- Partners optional uploaded icon/image (alongside icon_class)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'partners'
      AND COLUMN_NAME = 'icon_path'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE partners ADD COLUMN icon_path VARCHAR(255) NULL AFTER icon_class',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
