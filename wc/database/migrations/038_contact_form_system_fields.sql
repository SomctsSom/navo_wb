-- Built-in contact form fields as system rows (shared sort order with custom fields)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'contact_form_fields'
      AND COLUMN_NAME = 'is_system'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE contact_form_fields ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER is_visible',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Seed built-in fields (sort leaves room for custom e.g. MC number = 3 between company=2 and phone=4)
INSERT INTO contact_form_fields
    (field_key, label, field_type, select_source, source_table, source_value_column, source_label_column,
     is_required, is_visible, is_system, sort_order, created_at)
SELECT v.field_key, v.label, v.field_type, v.select_source, v.source_table, v.source_value_column, v.source_label_column,
       v.is_required, v.is_visible, 1, v.sort_order, UTC_TIMESTAMP()
FROM (
    SELECT 'full_name' AS field_key, 'Full Name' AS label, 'text' AS field_type, 'manual' AS select_source,
           NULL AS source_table, NULL AS source_value_column, NULL AS source_label_column,
           1 AS is_required, 1 AS is_visible, 1 AS sort_order
    UNION ALL
    SELECT 'company_name', 'Company Name', 'text', 'manual', NULL, NULL, NULL, 0, 1, 2
    UNION ALL
    SELECT 'phone', 'Phone Number', 'text', 'manual', NULL, NULL, NULL, 1, 1, 4
    UNION ALL
    SELECT 'email', 'Email Address', 'text', 'manual', NULL, NULL, NULL, 1, 1, 5
    UNION ALL
    SELECT 'preferred_service_id', 'Preferred Service', 'select', 'table', 'services', 'id', 'title', 1, 1, 6
    UNION ALL
    SELECT 'message', 'Comments / Message', 'textarea', 'manual', NULL, NULL, NULL, 1, 1, 7
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM contact_form_fields f
    WHERE f.field_key = v.field_key AND f.deleted_at IS NULL
);
