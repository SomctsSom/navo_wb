-- Per-hero spacing (px) between subtitle, title, description, and buttons
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @col1 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hero' AND COLUMN_NAME = 'space_subtitle'
);
SET @sql1 := IF(
    @col1 = 0,
    'ALTER TABLE hero ADD COLUMN space_subtitle SMALLINT UNSIGNED NULL AFTER description',
    'SELECT 1'
);
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @col2 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hero' AND COLUMN_NAME = 'space_title'
);
SET @sql2 := IF(
    @col2 = 0,
    'ALTER TABLE hero ADD COLUMN space_title SMALLINT UNSIGNED NULL AFTER space_subtitle',
    'SELECT 1'
);
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @col3 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hero' AND COLUMN_NAME = 'space_description'
);
SET @sql3 := IF(
    @col3 = 0,
    'ALTER TABLE hero ADD COLUMN space_description SMALLINT UNSIGNED NULL AFTER space_title',
    'SELECT 1'
);
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;
