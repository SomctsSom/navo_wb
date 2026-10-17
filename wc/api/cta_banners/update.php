<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('cta_banners.edit');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$id = (int) ($input['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid CTA banner id', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM cta_banners WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('CTA banner not found', 404);
}

$eyebrow = array_key_exists('eyebrow', $input)
    ? trim((string) $input['eyebrow'])
    : (string) ($existing['eyebrow'] ?? '');
$title = array_key_exists('title', $input)
    ? trim((string) $input['title'])
    : (string) $existing['title'];
$subtitle = array_key_exists('subtitle', $input)
    ? trim((string) $input['subtitle'])
    : (string) ($existing['subtitle'] ?? '');
$primaryText = array_key_exists('primary_button_text', $input)
    ? trim((string) $input['primary_button_text'])
    : (string) ($existing['primary_button_text'] ?? '');
$primaryUrl = array_key_exists('primary_button_url', $input)
    ? trim((string) $input['primary_button_url'])
    : (string) ($existing['primary_button_url'] ?? '');
$secondaryText = array_key_exists('secondary_button_text', $input)
    ? trim((string) $input['secondary_button_text'])
    : (string) ($existing['secondary_button_text'] ?? '');
$secondaryUrl = array_key_exists('secondary_button_url', $input)
    ? trim((string) $input['secondary_button_url'])
    : (string) ($existing['secondary_button_url'] ?? '');
$sortOrder = (int) ($input['sort_order'] ?? $existing['sort_order']);
$isActive = array_key_exists('is_active', $input)
    ? to_bool_int($input['is_active'], (int) $existing['is_active'])
    : (int) $existing['is_active'];

$pageAssignments = null;
if (array_key_exists('pages_json', $input) && (string) $input['pages_json'] !== '') {
    $pageAssignments = json_decode((string) $input['pages_json'], true);
    if (!is_array($pageAssignments)) {
        json_error('Invalid pages_json', 422);
    }
}

if ($title === '') {
    json_error('Title is required', 422);
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE cta_banners SET
            eyebrow = :eyebrow, title = :title, subtitle = :subtitle,
            primary_button_text = :ptext, primary_button_url = :purl,
            secondary_button_text = :stext, secondary_button_url = :surl,
            sort_order = :sort, is_active = :active,
            updated_at = :u, updated_by = :by
         WHERE id = :id'
    )->execute([
        ':eyebrow' => $eyebrow !== '' ? $eyebrow : null,
        ':title' => $title,
        ':subtitle' => $subtitle !== '' ? $subtitle : null,
        ':ptext' => $primaryText !== '' ? $primaryText : null,
        ':purl' => $primaryUrl !== '' ? $primaryUrl : null,
        ':stext' => $secondaryText !== '' ? $secondaryText : null,
        ':surl' => $secondaryUrl !== '' ? $secondaryUrl : null,
        ':sort' => $sortOrder,
        ':active' => $isActive,
        ':u' => now_utc(),
        ':by' => $actor['id'],
        ':id' => $id,
    ]);
    if ($pageAssignments !== null) {
        sync_cta_banner_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'update', 'cta_banners', $id, ['title' => $title]);
json_ok(['id' => $id], 'CTA banner updated');
