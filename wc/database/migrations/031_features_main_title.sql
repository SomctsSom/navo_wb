-- Features: main_title (eyebrow above section title)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @c1 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'features'
      AND COLUMN_NAME = 'main_title'
);
SET @s1 := IF(
    @c1 = 0,
    'ALTER TABLE features ADD COLUMN main_title VARCHAR(200) NULL AFTER title',
    'SELECT 1'
);
PREPARE st1 FROM @s1; EXECUTE st1; DEALLOCATE PREPARE st1;
