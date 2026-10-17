<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('cta_banners.create');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$eyebrow = trim((string) ($input['eyebrow'] ?? ''));
$title = trim((string) ($input['title'] ?? ''));
$subtitle = trim((string) ($input['subtitle'] ?? ''));
$primaryText = trim((string) ($input['primary_button_text'] ?? ''));
$primaryUrl = trim((string) ($input['primary_button_url'] ?? ''));
$secondaryText = trim((string) ($input['secondary_button_text'] ?? ''));
$secondaryUrl = trim((string) ($input['secondary_button_url'] ?? ''));
$sortOrder = (int) ($input['sort_order'] ?? 0);
$isActive = array_key_exists('is_active', $input) ? to_bool_int($input['is_active'], 1) : 1;
$pagesJson = (string) ($input['pages_json'] ?? '[]');
$pageAssignments = json_decode($pagesJson, true);
if (!is_array($pageAssignments)) {
    $pageAssignments = [];
}

if ($title === '') {
    json_error('Title is required', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO cta_banners (
            eyebrow, title, subtitle,
            primary_button_text, primary_button_url,
            secondary_button_text, secondary_button_url,
            sort_order, is_active, created_at, created_by
         ) VALUES (
            :eyebrow, :title, :subtitle,
            :ptext, :purl, :stext, :surl,
            :sort, :active, :created, :by
         )'
    );
    $stmt->execute([
        ':eyebrow' => $eyebrow !== '' ? $eyebrow : null,
        ':title' => $title,
        ':subtitle' => $subtitle !== '' ? $subtitle : null,
        ':ptext' => $primaryText !== '' ? $primaryText : null,
        ':purl' => $primaryUrl !== '' ? $primaryUrl : null,
        ':stext' => $secondaryText !== '' ? $secondaryText : null,
        ':surl' => $secondaryUrl !== '' ? $secondaryUrl : null,
        ':sort' => $sortOrder,
        ':active' => $isActive,
        ':created' => now_utc(),
        ':by' => $actor['id'],
    ]);
    $id = (int) $pdo->lastInsertId();
    sync_cta_banner_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'create', 'cta_banners', $id, ['title' => $title]);
json_ok(['id' => $id], 'CTA banner created', 201);
