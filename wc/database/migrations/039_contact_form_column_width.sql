-- Per-field public form width (Bootstrap-style 12-column: col-md-N)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contact_form_fields'
      AND COLUMN_NAME = 'column_width'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE contact_form_fields ADD COLUMN column_width TINYINT UNSIGNED NOT NULL DEFAULT 6 AFTER sort_order',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Sensible defaults matching previous hard-coded layout
UPDATE contact_form_fields SET column_width = 12 WHERE field_key = 'message' AND deleted_at IS NULL;
UPDATE contact_form_fields SET column_width = 4 WHERE field_key = 'phone' AND deleted_at IS NULL;
UPDATE contact_form_fields SET column_width = 8 WHERE field_key = 'email' AND deleted_at IS NULL;
UPDATE contact_form_fields SET column_width = 6
WHERE field_key IN ('full_name', 'company_name', 'preferred_service_id') AND deleted_at IS NULL;
UPDATE contact_form_fields SET column_width = 6
WHERE field_type = 'textarea' AND field_key <> 'message' AND deleted_at IS NULL AND column_width = 6;
