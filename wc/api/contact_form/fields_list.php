<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('contact_form.view');

$pdo = db();
$fields = list_contact_form_fields($pdo, false);
$connectable = [];
foreach (contact_form_connectable_tables() as $table => $meta) {
    $connectable[] = [
        'table' => $table,
        'label' => $meta['label'],
        'value_column' => $meta['value_column'],
        'label_column' => $meta['label_column'],
    ];
}
json_ok([
    'items' => $fields,
    'field_types' => contact_form_custom_field_types(),
    'column_widths' => contact_form_column_widths(),
    'available_tables' => contact_form_available_connect_tables($pdo),
    'connectable_tables' => $connectable,
]);
