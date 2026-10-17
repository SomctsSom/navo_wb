<?php
declare(strict_types=1);

/**
 * @return list<string>
 */
function contact_inquiry_statuses(): array
{
    return ['new', 'in_progress', 'replied', 'closed'];
}

function contact_inquiry_status_label(string $status): string
{
    return match ($status) {
        'new' => 'New',
        'in_progress' => 'In progress',
        'replied' => 'Replied',
        'closed' => 'Closed',
        default => $status,
    };
}

/**
 * Next status in the workflow: new → in_progress → replied → closed.
 */
function contact_inquiry_next_status(string $status): ?string
{
    return match ($status) {
        'new' => 'in_progress',
        'in_progress' => 'replied',
        'replied' => 'closed',
        default => null,
    };
}

/**
 * @return array{
 *   show_full_name:int,
 *   show_company_name:int,
 *   show_phone:int,
 *   show_email:int,
 *   show_preferred_service:int,
 *   show_message:int
 * }
 */
function contact_form_field_defaults(): array
{
    return [
        'show_full_name' => 1,
        'show_company_name' => 1,
        'show_phone' => 1,
        'show_email' => 1,
        'show_preferred_service' => 1,
        'show_message' => 1,
    ];
}

function contact_form_bool($value, int $default = 1): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
        return 1;
    }
    if (in_array($v, ['0', 'false', 'no', 'off'], true)) {
        return 0;
    }
    return ((int) $value) === 1 ? 1 : 0;
}

/**
 * @return array{
 *   show_full_name:int,
 *   show_company_name:int,
 *   show_phone:int,
 *   show_email:int,
 *   show_preferred_service:int,
 *   show_message:int
 * }
 */
function get_contact_form_settings(?PDO $pdo = null): array
{
    $defaults = contact_form_field_defaults();
    $pdo = $pdo ?? db();
    try {
        $stmt = $pdo->query(
            "SELECT setting_key, setting_value FROM site_settings
             WHERE setting_key IN (
                'contact_form_show_full_name','contact_form_show_company_name',
                'contact_form_show_phone','contact_form_show_email',
                'contact_form_show_preferred_service','contact_form_show_message'
             )"
        );
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []) : [];
        $map = [
            'contact_form_show_full_name' => 'show_full_name',
            'contact_form_show_company_name' => 'show_company_name',
            'contact_form_show_phone' => 'show_phone',
            'contact_form_show_email' => 'show_email',
            'contact_form_show_preferred_service' => 'show_preferred_service',
            'contact_form_show_message' => 'show_message',
        ];
        foreach ($map as $key => $field) {
            if (isset($rows[$key])) {
                $defaults[$field] = contact_form_bool($rows[$key], 1);
            }
        }
    } catch (Throwable $e) {
        // Table may not exist yet
    }
    return $defaults;
}

/**
 * @param array<string,mixed> $input
 * @return array{
 *   show_full_name:int,
 *   show_company_name:int,
 *   show_phone:int,
 *   show_email:int,
 *   show_preferred_service:int,
 *   show_message:int
 * }
 */
function set_contact_form_settings(PDO $pdo, array $input, ?int $actorId = null): array
{
    $current = get_contact_form_settings($pdo);
    $fields = [
        'show_full_name' => 'contact_form_show_full_name',
        'show_company_name' => 'contact_form_show_company_name',
        'show_phone' => 'contact_form_show_phone',
        'show_email' => 'contact_form_show_email',
        'show_preferred_service' => 'contact_form_show_preferred_service',
        'show_message' => 'contact_form_show_message',
    ];
    $out = [];
    $stmt = $pdo->prepare(
        "INSERT INTO site_settings (setting_key, setting_value, updated_at, updated_by)
         VALUES (:k, :v, :u, :by)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
           updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)"
    );
    foreach ($fields as $field => $key) {
        $val = array_key_exists($field, $input)
            ? contact_form_bool($input[$field], 1)
            : $current[$field];
        $out[$field] = $val;
        $stmt->execute([
            ':k' => $key,
            ':v' => (string) $val,
            ':u' => now_utc(),
            ':by' => $actorId,
        ]);
    }
    return $out;
}

/**
 * @return list<string>
 */
function contact_form_custom_field_types(): array
{
    return ['text', 'textarea', 'number', 'date', 'select'];
}

/**
 * Built-in / reserved column names that custom fields must not use.
 *
 * @return list<string>
 */
function contact_form_reserved_keys(): array
{
    return [
        'id', 'full_name', 'company_name', 'phone', 'email',
        'preferred_service_id', 'preferred_service_title', 'message',
        'status', 'page_id', 'created_at', 'created_by', 'updated_at',
        'updated_by', 'deleted_at', 'deleted_by',
    ];
}

/**
 * @return list<string>
 */
function contact_form_parse_options($raw): array
{
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/[\r\n,]+/', $s) ?: [];
        }
    }
    $out = [];
    foreach ($items as $item) {
        $v = trim((string) $item);
        if ($v !== '') {
            $out[] = mb_substr($v, 0, 190);
        }
    }
    return array_values(array_unique($out));
}

function contact_form_slugify_key(string $label): string
{
    $s = strtolower(trim($label));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? '';
    $s = trim($s, '_');
    if ($s === '') {
        $s = 'field';
    }
    $s = mb_substr($s, 0, 48);
    return 'cf_' . $s;
}

function contact_form_is_safe_field_key(string $key): bool
{
    return (bool) preg_match('/^cf_[a-z0-9_]{1,60}$/', $key);
}

function contact_form_mysql_type_for(string $fieldType): string
{
    return match ($fieldType) {
        'textarea' => 'TEXT NULL',
        'number' => 'DECIMAL(18,2) NULL',
        'date' => 'DATE NULL',
        default => 'VARCHAR(255) NULL',
    };
}

/**
 * Whitelisted tables that may be linked as select FK sources.
 *
 * @return array<string, array{label:string, value_column:string, label_column:string, where:string, order_by:string}>
 */
function contact_form_connectable_tables(): array
{
    return [
        'services' => [
            'label' => 'Services',
            'value_column' => 'id',
            'label_column' => 'title',
            'where' => 'deleted_at IS NULL AND is_active = 1',
            'order_by' => 'sort_order ASC, id ASC',
        ],
        'pages' => [
            'label' => 'Website pages',
            'value_column' => 'id',
            'label_column' => 'title',
            'where' => "deleted_at IS NULL AND is_active = 1 AND page_type = 'website'",
            'order_by' => 'title ASC, id ASC',
        ],
        'features' => [
            'label' => 'Features',
            'value_column' => 'id',
            'label_column' => 'title',
            'where' => 'deleted_at IS NULL AND is_active = 1',
            'order_by' => 'sort_order ASC, id ASC',
        ],
        'testimonials' => [
            'label' => 'Testimonials',
            'value_column' => 'id',
            'label_column' => 'author_name',
            'where' => 'deleted_at IS NULL AND is_active = 1',
            'order_by' => 'sort_order ASC, id ASC',
        ],
        'menus' => [
            'label' => 'Website menus',
            'value_column' => 'id',
            'label_column' => 'title',
            'where' => "deleted_at IS NULL AND is_active = 1 AND menu_type = 'website'",
            'order_by' => 'sort_order ASC, id ASC',
        ],
    ];
}

/**
 * @return list<string>
 */
function contact_form_connected_source_tables(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT DISTINCT source_table FROM contact_form_fields
             WHERE deleted_at IS NULL
               AND select_source = 'table'
               AND source_table IS NOT NULL
               AND source_table <> ''"
        );
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }
    return array_values(array_filter(array_map('strval', $rows)));
}

/**
 * Whitelist tables not yet connected by an active custom field.
 *
 * @return list<array{table:string,label:string,value_column:string,label_column:string}>
 */
function contact_form_available_connect_tables(PDO $pdo): array
{
    $connected = array_fill_keys(contact_form_connected_source_tables($pdo), true);
    $out = [];
    foreach (contact_form_connectable_tables() as $table => $meta) {
        if (isset($connected[$table])) {
            continue;
        }
        $out[] = [
            'table' => $table,
            'label' => (string) $meta['label'],
            'value_column' => (string) $meta['value_column'],
            'label_column' => (string) $meta['label_column'],
        ];
    }
    return $out;
}

function contact_form_label_column_key(string $fieldKey): string
{
    return $fieldKey . '_label';
}

/**
 * @return list<array{value:string,label:string}>
 */
function contact_form_manual_options_pairs(array $options): array
{
    $out = [];
    foreach ($options as $opt) {
        if (is_array($opt)) {
            $value = trim((string) ($opt['value'] ?? $opt['label'] ?? ''));
            $label = trim((string) ($opt['label'] ?? $opt['value'] ?? ''));
        } else {
            $value = trim((string) $opt);
            $label = $value;
        }
        if ($value === '') {
            continue;
        }
        $out[] = ['value' => mb_substr($value, 0, 255), 'label' => mb_substr($label !== '' ? $label : $value, 0, 255)];
    }
    return $out;
}

/**
 * @param array<string,mixed> $meta
 * @return list<array{value:string,label:string}>
 */
function contact_form_load_table_options(PDO $pdo, string $table, array $meta): array
{
    $allowed = contact_form_connectable_tables();
    if (!isset($allowed[$table])) {
        return [];
    }
    $valueCol = (string) ($meta['value_column'] ?? $allowed[$table]['value_column']);
    $labelCol = (string) ($meta['label_column'] ?? $allowed[$table]['label_column']);
    $where = (string) ($meta['where'] ?? $allowed[$table]['where']);
    $orderBy = (string) ($meta['order_by'] ?? $allowed[$table]['order_by']);

    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)
        || !preg_match('/^[a-z_][a-z0-9_]*$/', $valueCol)
        || !preg_match('/^[a-z_][a-z0-9_]*$/', $labelCol)
    ) {
        return [];
    }

    try {
        $sql = "SELECT `{$valueCol}` AS v, `{$labelCol}` AS l FROM `{$table}` WHERE {$where} ORDER BY {$orderBy}";
        $stmt = $pdo->query($sql);
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $value = trim((string) ($row['v'] ?? ''));
        $label = trim((string) ($row['l'] ?? ''));
        if ($value === '') {
            continue;
        }
        $out[] = [
            'value' => $value,
            'label' => $label !== '' ? $label : $value,
        ];
    }
    return $out;
}

/**
 * Resolve a selected table row to value + label for storage.
 *
 * @return array{ok:bool,error:?string,value:?int,label:?string}
 */
function contact_form_resolve_table_selection(PDO $pdo, array $field, $raw): array
{
    $label = (string) ($field['label'] ?? 'Field');
    $required = (int) ($field['is_required'] ?? 0) === 1;
    $str = trim((string) ($raw ?? ''));
    if ($str === '') {
        if ($required) {
            return ['ok' => false, 'error' => $label . ' is required', 'value' => null, 'label' => null];
        }
        return ['ok' => true, 'error' => null, 'value' => null, 'label' => null];
    }
    if (!ctype_digit($str) && !preg_match('/^\d+$/', $str)) {
        return ['ok' => false, 'error' => $label . ' is invalid', 'value' => null, 'label' => null];
    }
    $id = (int) $str;
    if ($id < 1) {
        return ['ok' => false, 'error' => $label . ' is invalid', 'value' => null, 'label' => null];
    }

    $table = (string) ($field['source_table'] ?? '');
    $registry = contact_form_connectable_tables();
    if ($table === '' || !isset($registry[$table])) {
        return ['ok' => false, 'error' => $label . ' source is invalid', 'value' => null, 'label' => null];
    }
    $meta = $registry[$table];
    $valueCol = (string) ($field['source_value_column'] ?: $meta['value_column']);
    $labelCol = (string) ($field['source_label_column'] ?: $meta['label_column']);
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)
        || !preg_match('/^[a-z_][a-z0-9_]*$/', $valueCol)
        || !preg_match('/^[a-z_][a-z0-9_]*$/', $labelCol)
    ) {
        return ['ok' => false, 'error' => $label . ' source is invalid', 'value' => null, 'label' => null];
    }

    try {
        $sql = "SELECT `{$valueCol}` AS v, `{$labelCol}` AS l FROM `{$table}`
                WHERE `{$valueCol}` = :id AND {$meta['where']} LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $label . ' is invalid', 'value' => null, 'label' => null];
    }
    if (!$row) {
        return ['ok' => false, 'error' => $label . ' is invalid', 'value' => null, 'label' => null];
    }
    $snap = trim((string) ($row['l'] ?? ''));
    return [
        'ok' => true,
        'error' => null,
        'value' => (int) $row['v'],
        'label' => $snap !== '' ? mb_substr($snap, 0, 255) : (string) $id,
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function list_contact_form_fields(PDO $pdo, bool $visibleOnly = false): array
{
    try {
        $sql = 'SELECT * FROM contact_form_fields WHERE deleted_at IS NULL';
        if ($visibleOnly) {
            $sql .= ' AND is_visible = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = $pdo->query($sql);
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[] = contact_form_field_normalize($row, $pdo);
    }
    return $out;
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function contact_form_field_normalize(array $row, ?PDO $pdo = null): array
{
    $selectSource = (string) ($row['select_source'] ?? 'manual');
    if ($selectSource !== 'table') {
        $selectSource = 'manual';
    }
    $sourceTable = $row['source_table'] ?? null;
    $manual = contact_form_manual_options_pairs(contact_form_parse_options($row['options_json'] ?? ''));
    $options = $manual;

    if ($selectSource === 'table' && $sourceTable && $pdo instanceof PDO) {
        $registry = contact_form_connectable_tables();
        $meta = $registry[(string) $sourceTable] ?? null;
        if ($meta) {
            $options = contact_form_load_table_options($pdo, (string) $sourceTable, array_merge($meta, [
                'value_column' => $row['source_value_column'] ?? $meta['value_column'],
                'label_column' => $row['source_label_column'] ?? $meta['label_column'],
            ]));
        } else {
            $options = [];
        }
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'field_key' => (string) ($row['field_key'] ?? ''),
        'label' => (string) ($row['label'] ?? ''),
        'field_type' => (string) ($row['field_type'] ?? 'text'),
        'select_source' => $selectSource,
        'source_table' => $sourceTable !== null && $sourceTable !== '' ? (string) $sourceTable : null,
        'source_value_column' => !empty($row['source_value_column']) ? (string) $row['source_value_column'] : null,
        'source_label_column' => !empty($row['source_label_column']) ? (string) $row['source_label_column'] : null,
        'options' => $options,
        'options_json' => $row['options_json'] ?? null,
        'is_required' => (int) ($row['is_required'] ?? 0),
        'is_visible' => (int) ($row['is_visible'] ?? 1),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function contact_form_column_exists(PDO $pdo, string $column): bool
{
    if (!contact_form_is_safe_field_key($column) && !preg_match('/^cf_[a-z0-9_]+_label$/', $column)) {
        return false;
    }
    if (!preg_match('/^[a-z0-9_]+$/', $column)) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = \'contact_inquiries\'
           AND COLUMN_NAME = :col'
    );
    $stmt->execute([':col' => $column]);
    return ((int) $stmt->fetchColumn()) > 0;
}

/**
 * @param array<string,mixed> $input
 * @return array{ok:bool,error:?string,field?:array<string,mixed>}
 */
function create_contact_form_field(PDO $pdo, array $input, ?int $actorId = null): array
{
    $label = trim((string) ($input['label'] ?? ''));
    if ($label === '') {
        return ['ok' => false, 'error' => 'Label is required'];
    }
    $label = mb_substr($label, 0, 190);

    $fieldType = strtolower(trim((string) ($input['field_type'] ?? 'text')));
    if (!in_array($fieldType, contact_form_custom_field_types(), true)) {
        return ['ok' => false, 'error' => 'Invalid field type'];
    }

    $selectSource = 'manual';
    $sourceTable = null;
    $sourceValueColumn = null;
    $sourceLabelColumn = null;
    $options = [];
    $optionsJson = null;

    if ($fieldType === 'select') {
        $selectSource = strtolower(trim((string) ($input['select_source'] ?? 'manual')));
        if (!in_array($selectSource, ['manual', 'table'], true)) {
            return ['ok' => false, 'error' => 'Invalid select source'];
        }

        if ($selectSource === 'table') {
            $sourceTable = trim((string) ($input['source_table'] ?? ''));
            $registry = contact_form_connectable_tables();
            if ($sourceTable === '' || !isset($registry[$sourceTable])) {
                return ['ok' => false, 'error' => 'Please choose a valid table to connect'];
            }
            if (in_array($sourceTable, contact_form_connected_source_tables($pdo), true)) {
                return ['ok' => false, 'error' => 'That table is already connected'];
            }
            $sourceValueColumn = $registry[$sourceTable]['value_column'];
            $sourceLabelColumn = $registry[$sourceTable]['label_column'];
        } else {
            $options = contact_form_parse_options($input['options'] ?? ($input['options_json'] ?? ''));
            if (count($options) < 1) {
                return ['ok' => false, 'error' => 'Select fields need at least one option'];
            }
            $optionsJson = json_encode(array_values($options), JSON_UNESCAPED_UNICODE);
        }
    } else {
        $options = contact_form_parse_options($input['options'] ?? ($input['options_json'] ?? ''));
        if ($options) {
            $optionsJson = json_encode(array_values($options), JSON_UNESCAPED_UNICODE);
        }
    }

    $isRequired = contact_form_bool($input['is_required'] ?? 0, 0);
    $isVisible = contact_form_bool($input['is_visible'] ?? 1, 1);
    $sortOrder = isset($input['sort_order']) ? (int) $input['sort_order'] : 0;

    $baseKey = contact_form_slugify_key($label);
    $fieldKey = $baseKey;
    $n = 2;
    while (true) {
        $labelKey = contact_form_label_column_key($fieldKey);
        if (in_array($fieldKey, contact_form_reserved_keys(), true)
            || in_array($labelKey, contact_form_reserved_keys(), true)
            || !contact_form_is_safe_field_key($fieldKey)
        ) {
            $fieldKey = $baseKey . '_' . $n;
            $n++;
            continue;
        }
        $chk = $pdo->prepare(
            'SELECT id FROM contact_form_fields WHERE field_key = :k LIMIT 1'
        );
        $chk->execute([':k' => $fieldKey]);
        if ($chk->fetch()) {
            $fieldKey = $baseKey . '_' . $n;
            $n++;
            continue;
        }
        if (contact_form_column_exists($pdo, $fieldKey) || contact_form_column_exists($pdo, $labelKey)) {
            $fieldKey = $baseKey . '_' . $n;
            $n++;
            continue;
        }
        break;
    }

    if ($sortOrder === 0) {
        $max = (int) $pdo->query(
            'SELECT COALESCE(MAX(sort_order), 0) FROM contact_form_fields WHERE deleted_at IS NULL'
        )->fetchColumn();
        $sortOrder = $max + 10;
    }

    $isTableSelect = $fieldType === 'select' && $selectSource === 'table';
    $labelKey = contact_form_label_column_key($fieldKey);

    try {
        if ($isTableSelect) {
            $pdo->exec(
                'ALTER TABLE contact_inquiries ADD COLUMN `' . $fieldKey . '` BIGINT UNSIGNED NULL'
            );
            $pdo->exec(
                'ALTER TABLE contact_inquiries ADD COLUMN `' . $labelKey . '` VARCHAR(255) NULL'
            );
            $fkName = 'fk_ci_' . substr(preg_replace('/[^a-z0-9_]/', '', $fieldKey) ?? $fieldKey, 0, 48);
            $pdo->exec(
                'ALTER TABLE contact_inquiries
                 ADD CONSTRAINT `' . $fkName . '`
                 FOREIGN KEY (`' . $fieldKey . '`) REFERENCES `' . $sourceTable . '` (`id`)
                 ON DELETE SET NULL'
            );
        } else {
            $mysqlType = $fieldType === 'select'
                ? 'VARCHAR(255) NULL'
                : contact_form_mysql_type_for($fieldType);
            $pdo->exec(
                'ALTER TABLE contact_inquiries ADD COLUMN `' . $fieldKey . '` ' . $mysqlType
            );
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not add field column: ' . $e->getMessage()];
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO contact_form_fields (
                field_key, label, field_type, select_source, source_table,
                source_value_column, source_label_column, options_json,
                is_required, is_visible, sort_order, created_at, created_by
             ) VALUES (
                :field_key, :label, :field_type, :select_source, :source_table,
                :source_value_column, :source_label_column, :options_json,
                :is_required, :is_visible, :sort_order, :created_at, :created_by
             )'
        );
        $stmt->execute([
            ':field_key' => $fieldKey,
            ':label' => $label,
            ':field_type' => $fieldType,
            ':select_source' => $fieldType === 'select' ? $selectSource : 'manual',
            ':source_table' => $isTableSelect ? $sourceTable : null,
            ':source_value_column' => $isTableSelect ? $sourceValueColumn : null,
            ':source_label_column' => $isTableSelect ? $sourceLabelColumn : null,
            ':options_json' => $optionsJson,
            ':is_required' => $isRequired,
            ':is_visible' => $isVisible,
            ':sort_order' => $sortOrder,
            ':created_at' => now_utc(),
            ':created_by' => $actorId,
        ]);
        $id = (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Column was added but field definition failed: ' . $e->getMessage()];
    }

    $row = $pdo->prepare('SELECT * FROM contact_form_fields WHERE id = :id LIMIT 1');
    $row->execute([':id' => $id]);
    $field = $row->fetch();
    return [
        'ok' => true,
        'error' => null,
        'field' => $field ? contact_form_field_normalize($field, $pdo) : null,
    ];
}

/**
 * @param array<string,mixed> $input
 * @return array{ok:bool,error:?string,field?:array<string,mixed>}
 */
function update_contact_form_field(PDO $pdo, int $id, array $input, ?int $actorId = null): array
{
    if ($id < 1) {
        return ['ok' => false, 'error' => 'Invalid id'];
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM contact_form_fields WHERE id = :id AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        return ['ok' => false, 'error' => 'Field not found'];
    }

    $label = array_key_exists('label', $input)
        ? trim((string) $input['label'])
        : (string) $existing['label'];
    if ($label === '') {
        return ['ok' => false, 'error' => 'Label is required'];
    }
    $label = mb_substr($label, 0, 190);

    $fieldType = (string) $existing['field_type'];
    $selectSource = (string) ($existing['select_source'] ?? 'manual');
    $options = contact_form_parse_options($existing['options_json'] ?? '');
    if ($selectSource !== 'table' && (array_key_exists('options', $input) || array_key_exists('options_json', $input))) {
        $options = contact_form_parse_options($input['options'] ?? ($input['options_json'] ?? ''));
    }
    $optionsJson = null;
    if ($fieldType === 'select' && $selectSource !== 'table') {
        if (count($options) < 1) {
            return ['ok' => false, 'error' => 'Select fields need at least one option'];
        }
        $optionsJson = json_encode(array_values($options), JSON_UNESCAPED_UNICODE);
    } elseif ($fieldType !== 'select' && $options) {
        $optionsJson = json_encode(array_values($options), JSON_UNESCAPED_UNICODE);
    } elseif ($selectSource === 'table') {
        $optionsJson = $existing['options_json'] ?? null;
    }

    $isRequired = array_key_exists('is_required', $input)
        ? contact_form_bool($input['is_required'], 0)
        : (int) $existing['is_required'];
    $isVisible = array_key_exists('is_visible', $input)
        ? contact_form_bool($input['is_visible'], 1)
        : (int) $existing['is_visible'];
    $sortOrder = array_key_exists('sort_order', $input)
        ? (int) $input['sort_order']
        : (int) $existing['sort_order'];

    $pdo->prepare(
        'UPDATE contact_form_fields SET
            label = :label,
            options_json = :options_json,
            is_required = :is_required,
            is_visible = :is_visible,
            sort_order = :sort_order,
            updated_at = :updated_at,
            updated_by = :updated_by
         WHERE id = :id'
    )->execute([
        ':label' => $label,
        ':options_json' => $optionsJson,
        ':is_required' => $isRequired,
        ':is_visible' => $isVisible,
        ':sort_order' => $sortOrder,
        ':updated_at' => now_utc(),
        ':updated_by' => $actorId,
        ':id' => $id,
    ]);

    $row = $pdo->prepare('SELECT * FROM contact_form_fields WHERE id = :id LIMIT 1');
    $row->execute([':id' => $id]);
    $field = $row->fetch();
    return [
        'ok' => true,
        'error' => null,
        'field' => $field ? contact_form_field_normalize($field, $pdo) : null,
    ];
}

/**
 * Soft-delete field definition; does not DROP the inquiry column.
 *
 * @return array{ok:bool,error:?string}
 */
function delete_contact_form_field(PDO $pdo, int $id, ?int $actorId = null): array
{
    if ($id < 1) {
        return ['ok' => false, 'error' => 'Invalid id'];
    }
    $stmt = $pdo->prepare(
        'SELECT id FROM contact_form_fields WHERE id = :id AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        return ['ok' => false, 'error' => 'Field not found'];
    }
    $now = now_utc();
    $pdo->prepare(
        'UPDATE contact_form_fields
         SET deleted_at = ?, deleted_by = ?, is_visible = 0, updated_at = ?, updated_by = ?
         WHERE id = ?'
    )->execute([$now, $actorId, $now, $actorId, $id]);
    return ['ok' => true, 'error' => null];
}

/**
 * Validate a single custom field value.
 *
 * @param array<string,mixed> $field
 * @return array{ok:bool,error:?string,value:mixed,label_value?:?string}
 */
function contact_form_validate_custom_value(array $field, $raw, ?PDO $pdo = null): array
{
    $label = (string) ($field['label'] ?? 'Field');
    $type = (string) ($field['field_type'] ?? 'text');
    $required = (int) ($field['is_required'] ?? 0) === 1;
    $value = is_string($raw) ? trim($raw) : $raw;

    if ($type === 'select' && (string) ($field['select_source'] ?? 'manual') === 'table') {
        if (!$pdo instanceof PDO) {
            return ['ok' => false, 'error' => $label . ' could not be validated', 'value' => null];
        }
        $resolved = contact_form_resolve_table_selection($pdo, $field, $raw);
        if (!$resolved['ok']) {
            return ['ok' => false, 'error' => $resolved['error'], 'value' => null];
        }
        return [
            'ok' => true,
            'error' => null,
            'value' => $resolved['value'],
            'label_value' => $resolved['label'],
        ];
    }

    if ($value === null || $value === '') {
        if ($required) {
            return ['ok' => false, 'error' => $label . ' is required', 'value' => null];
        }
        return ['ok' => true, 'error' => null, 'value' => null];
    }

    $str = trim((string) $value);
    switch ($type) {
        case 'number':
            if (!is_numeric($str)) {
                return ['ok' => false, 'error' => $label . ' must be a number', 'value' => null];
            }
            return ['ok' => true, 'error' => null, 'value' => $str];
        case 'date':
            $dt = DateTime::createFromFormat('Y-m-d', $str);
            if (!$dt || $dt->format('Y-m-d') !== $str) {
                return ['ok' => false, 'error' => $label . ' must be a valid date', 'value' => null];
            }
            return ['ok' => true, 'error' => null, 'value' => $str];
        case 'select':
            $pairs = contact_form_manual_options_pairs($field['options'] ?? contact_form_parse_options($field['options_json'] ?? ''));
            $allowed = array_column($pairs, 'value');
            if (!in_array($str, $allowed, true)) {
                return ['ok' => false, 'error' => $label . ' is invalid', 'value' => null];
            }
            return ['ok' => true, 'error' => null, 'value' => mb_substr($str, 0, 255)];
        case 'textarea':
            return ['ok' => true, 'error' => null, 'value' => $str];
        default:
            return ['ok' => true, 'error' => null, 'value' => mb_substr($str, 0, 255)];
    }
}

/**
 * Validate and normalize a public inquiry payload against visible fields.
 *
 * @param array<string,mixed> $input
 * @param array<string,int> $settings
 * @return array{ok:bool,error:?string,data?:array<string,mixed>}
 */
function contact_inquiry_validate(array $input, array $settings, PDO $pdo): array
{
    $fullName = trim((string) ($input['full_name'] ?? ''));
    $companyName = trim((string) ($input['company_name'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $serviceId = isset($input['preferred_service_id']) ? (int) $input['preferred_service_id'] : 0;
    $message = trim((string) ($input['message'] ?? ''));
    $pageId = isset($input['page_id']) ? (int) $input['page_id'] : 0;

    if ((int) ($settings['show_full_name'] ?? 1) === 1 && $fullName === '') {
        return ['ok' => false, 'error' => 'Full name is required'];
    }
    if ((int) ($settings['show_phone'] ?? 1) === 1 && $phone === '') {
        return ['ok' => false, 'error' => 'Phone number is required'];
    }
    if ((int) ($settings['show_email'] ?? 1) === 1) {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'A valid email address is required'];
        }
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Email address is invalid'];
    }
    if ((int) ($settings['show_message'] ?? 1) === 1 && $message === '') {
        return ['ok' => false, 'error' => 'Comments / message is required'];
    }

    $serviceTitle = null;
    if ((int) ($settings['show_preferred_service'] ?? 1) === 1) {
        if ($serviceId < 1) {
            return ['ok' => false, 'error' => 'Preferred service is required'];
        }
        $st = $pdo->prepare(
            'SELECT id, title FROM services WHERE id = :id AND deleted_at IS NULL AND is_active = 1 LIMIT 1'
        );
        $st->execute([':id' => $serviceId]);
        $svc = $st->fetch();
        if (!$svc) {
            return ['ok' => false, 'error' => 'Preferred service is invalid'];
        }
        $serviceTitle = (string) $svc['title'];
    } else {
        $serviceId = 0;
    }

    if ((int) ($settings['show_full_name'] ?? 1) !== 1) {
        $fullName = '';
    }
    if ((int) ($settings['show_company_name'] ?? 1) !== 1) {
        $companyName = '';
    }
    if ((int) ($settings['show_phone'] ?? 1) !== 1) {
        $phone = '';
    }
    if ((int) ($settings['show_email'] ?? 1) !== 1) {
        $email = '';
    }
    if ((int) ($settings['show_message'] ?? 1) !== 1) {
        $message = '';
    }

    if ($pageId > 0) {
        $p = $pdo->prepare(
            "SELECT id FROM pages WHERE id = :id AND page_type = 'website' AND deleted_at IS NULL LIMIT 1"
        );
        $p->execute([':id' => $pageId]);
        if (!(int) $p->fetchColumn()) {
            $pageId = 0;
        }
    }

    $customFields = list_contact_form_fields($pdo, true);
    $customValues = [];
    foreach ($customFields as $field) {
        $key = (string) $field['field_key'];
        $raw = $input[$key] ?? ($input['custom'][$key] ?? null);
        $validated = contact_form_validate_custom_value($field, $raw, $pdo);
        if (!$validated['ok']) {
            return ['ok' => false, 'error' => $validated['error']];
        }
        $customValues[$key] = $validated['value'];
        if ((string) ($field['select_source'] ?? '') === 'table') {
            $customValues[contact_form_label_column_key($key)] = $validated['label_value'] ?? null;
        }
    }

    return [
        'ok' => true,
        'error' => null,
        'data' => [
            'full_name' => $fullName !== '' ? mb_substr($fullName, 0, 190) : null,
            'company_name' => $companyName !== '' ? mb_substr($companyName, 0, 190) : null,
            'phone' => $phone !== '' ? mb_substr($phone, 0, 80) : null,
            'email' => $email !== '' ? mb_substr(strtolower($email), 0, 190) : null,
            'preferred_service_id' => $serviceId > 0 ? $serviceId : null,
            'preferred_service_title' => $serviceTitle,
            'message' => $message !== '' ? $message : null,
            'page_id' => $pageId > 0 ? $pageId : null,
            'status' => 'new',
            'custom' => $customValues,
        ],
    ];
}

/**
 * Insert inquiry including custom columns.
 *
 * @param array<string,mixed> $data from contact_inquiry_validate
 * @return array{ok:bool,error:?string,id?:int}
 */
function contact_inquiry_insert(PDO $pdo, array $data): array
{
    $custom = is_array($data['custom'] ?? null) ? $data['custom'] : [];
    $cols = [
        'full_name', 'company_name', 'phone', 'email',
        'preferred_service_id', 'preferred_service_title', 'message',
        'status', 'page_id', 'created_at',
    ];
    $params = [
        ':full_name' => $data['full_name'] ?? null,
        ':company_name' => $data['company_name'] ?? null,
        ':phone' => $data['phone'] ?? null,
        ':email' => $data['email'] ?? null,
        ':preferred_service_id' => $data['preferred_service_id'] ?? null,
        ':preferred_service_title' => $data['preferred_service_title'] ?? null,
        ':message' => $data['message'] ?? null,
        ':status' => $data['status'] ?? 'new',
        ':page_id' => $data['page_id'] ?? null,
        ':created_at' => now_utc(),
    ];

    foreach ($custom as $key => $value) {
        $key = (string) $key;
        if (!preg_match('/^cf_[a-z0-9_]+(?:_label)?$/', $key)) {
            continue;
        }
        if (!contact_form_column_exists($pdo, $key)) {
            continue;
        }
        $cols[] = $key;
        $params[':' . $key] = $value;
    }

    $colSql = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $cols));
    $phSql = implode(', ', array_map(static fn (string $c): string => ':' . $c, $cols));

    try {
        $pdo->prepare("INSERT INTO contact_inquiries ({$colSql}) VALUES ({$phSql})")->execute($params);
        return ['ok' => true, 'error' => null, 'id' => (int) $pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not save your message. Please try again.'];
    }
}

/**
 * Website pages with contact form visibility flags.
 *
 * @return list<array{id:int,title:string,slug:string,template_key:string,is_visible:int}>
 */
function list_contact_form_page_visibility(PDO $pdo): array
{
    try {
        $pages = $pdo->query(
            "SELECT id, title, slug, template_key
             FROM pages
             WHERE page_type = 'website' AND deleted_at IS NULL
             ORDER BY title ASC, id ASC"
        )->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($pages as $page) {
        $pageId = (int) ($page['id'] ?? 0);
        $order = get_page_section_order($pdo, $pageId);
        $visible = 0;
        foreach ($order as $sec) {
            if (($sec['section_key'] ?? '') === 'contact_form' && (int) ($sec['is_enabled'] ?? 0) === 1) {
                $visible = 1;
                break;
            }
        }
        $out[] = [
            'id' => $pageId,
            'title' => (string) ($page['title'] ?? ''),
            'slug' => (string) ($page['slug'] ?? ''),
            'template_key' => (string) ($page['template_key'] ?? ''),
            'is_visible' => $visible,
        ];
    }
    return $out;
}

/**
 * Sync which website pages show the contact form section.
 *
 * @param list<int|string> $pageIds
 * @return list<array{id:int,title:string,slug:string,template_key:string,is_visible:int}>
 */
function sync_contact_form_page_visibility(PDO $pdo, array $pageIds, ?int $actorId = null): array
{
    $wanted = [];
    foreach ($pageIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $wanted[$id] = true;
        }
    }

    try {
        $pages = $pdo->query(
            "SELECT id FROM pages WHERE page_type = 'website' AND deleted_at IS NULL"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    foreach ($pages as $pageId) {
        $pageId = (int) $pageId;
        $enabled = isset($wanted[$pageId]) ? 1 : 0;
        set_page_section_enabled($pdo, $pageId, 'contact_form', $enabled, $actorId);
    }

    return list_contact_form_page_visibility($pdo);
}
