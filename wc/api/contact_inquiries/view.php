<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('contact_inquiries.view');

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM contact_inquiries WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('Not found', 404);
}
$row['id'] = (int) $row['id'];
$row['preferred_service_id'] = $row['preferred_service_id'] !== null ? (int) $row['preferred_service_id'] : null;
$row['page_id'] = $row['page_id'] !== null ? (int) $row['page_id'] : null;
$row['status_label'] = contact_inquiry_status_label((string) $row['status']);
$row['created_at_display'] = format_display_time($row['created_at'] ?? null);
$row['replied_at_display'] = !empty($row['replied_at'])
    ? format_display_time((string) $row['replied_at'])
    : '';
$nextStatus = contact_inquiry_next_status((string) ($row['status'] ?? 'new'));
$row['next_status'] = $nextStatus;
$row['next_status_label'] = $nextStatus !== null
    ? contact_inquiry_status_label($nextStatus)
    : null;
$row['statuses'] = array_map(
    static fn (string $s): array => ['value' => $s, 'label' => contact_inquiry_status_label($s)],
    contact_inquiry_statuses()
);

$customValues = [];
try {
    $allDefs = $pdo->query(
        'SELECT * FROM contact_form_fields ORDER BY sort_order ASC, id ASC'
    )->fetchAll() ?: [];
} catch (Throwable $e) {
    $allDefs = [];
}
foreach ($allDefs as $def) {
    $norm = contact_form_field_normalize($def, $pdo);
    $key = (string) $norm['field_key'];
    if ($key === '' || !array_key_exists($key, $row)) {
        continue;
    }
    $displayValue = $row[$key];
    $labelKey = contact_form_label_column_key($key);
    if ((string) ($norm['select_source'] ?? '') === 'table' && array_key_exists($labelKey, $row) && ($row[$labelKey] ?? '') !== '') {
        $displayValue = $row[$labelKey];
    }
    $customValues[] = [
        'field_key' => $key,
        'label' => $norm['label'],
        'field_type' => $norm['field_type'],
        'select_source' => $norm['select_source'] ?? 'manual',
        'source_table' => $norm['source_table'] ?? null,
        'value' => $displayValue,
        'raw_value' => $row[$key],
        'is_deleted' => !empty($def['deleted_at']),
    ];
}
$row['custom_fields'] = $customValues;
json_ok($row);
