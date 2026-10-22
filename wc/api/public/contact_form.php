<?php
declare(strict_types=1);

require_method('GET');

$pdo = db();
$settings = get_contact_form_settings($pdo);
$allFields = list_contact_form_fields($pdo, true);
$publicFields = [];
foreach ($allFields as $field) {
    $publicFields[] = [
        'field_key' => $field['field_key'],
        'label' => $field['label'],
        'field_type' => $field['field_type'],
        'select_source' => $field['select_source'] ?? 'manual',
        'options' => $field['options'],
        'is_required' => $field['is_required'],
        'is_system' => $field['is_system'] ?? 0,
        'sort_order' => $field['sort_order'],
        'column_width' => $field['column_width'] ?? 6,
    ];
}
$settings['fields'] = $publicFields;
// Keep custom_fields for older clients: non-system only
$settings['custom_fields'] = array_values(array_filter(
    $publicFields,
    static fn(array $f): bool => (int) ($f['is_system'] ?? 0) !== 1
));
json_ok($settings);
