-- Hero subtitle, shown above the hero title on the public site.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @c1 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'hero'
      AND COLUMN_NAME = 'subtitle'
);
SET @s1 := IF(
    @c1 = 0,
    'ALTER TABLE hero ADD COLUMN subtitle VARCHAR(200) NULL AFTER title',
    'SELECT 1'
);
PREPARE st1 FROM @s1; EXECUTE st1; DEALLOCATE PREPARE st1;
