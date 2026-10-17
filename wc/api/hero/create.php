<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('hero.create');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$title = trim((string) ($input['title'] ?? ''));
$subtitle = trim((string) ($input['subtitle'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$sortOrder = (int) ($input['sort_order'] ?? 0);
$isActive = array_key_exists('is_active', $input) ? to_bool_int($input['is_active'], 1) : 1;
$pagesJson = (string) ($input['pages_json'] ?? '[]');
$pageAssignments = json_decode($pagesJson, true);
if (!is_array($pageAssignments)) {
    $pageAssignments = [];
}
$buttonsJson = (string) ($input['buttons_json'] ?? '[]');
$buttonItems = json_decode($buttonsJson, true);
if (!is_array($buttonItems)) {
    $buttonItems = [];
}
try {
    $buttons = hero_normalize_buttons($buttonItems);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 422);
}
$primaryButton = hero_first_button($buttons);
$buttonText = $primaryButton['label'];
$buttonUrl = $primaryButton['url'];
if ($buttonText === '' && $buttonUrl === '') {
    $buttonText = trim((string) ($input['button_text'] ?? ''));
    $buttonUrl = trim((string) ($input['button_url'] ?? ''));
    if ($buttonText !== '' || $buttonUrl !== '') {
        try {
            $buttons = hero_normalize_buttons([[
                'label' => $buttonText,
                'url' => $buttonUrl,
            ]]);
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 422);
        }
        $primaryButton = hero_first_button($buttons);
        $buttonText = $primaryButton['label'];
        $buttonUrl = $primaryButton['url'];
    }
}

if ($title === '') {
    json_error('Title is required', 422);
}
if (mb_strlen($subtitle) > 200) {
    json_error('Subtitle must be 200 characters or less', 422);
}
if (!isset($_FILES['image'])) {
    json_error('Image is required', 422);
}

$upload = secure_upload_image($_FILES['image'], 'hero');
if (!$upload['ok']) {
    json_error($upload['error'], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO hero (title, subtitle, description, image_path, button_text, button_url, sort_order, is_active, created_at, created_by)
         VALUES (:title, :subtitle, :description, :image, :bt, :bu, :sort, :active, :created, :by)'
    );
    $stmt->execute([
        ':title' => $title,
        ':subtitle' => $subtitle !== '' ? $subtitle : null,
        ':description' => $description !== '' ? $description : null,
        ':image' => $upload['relative_path'],
        ':bt' => $buttonText !== '' ? $buttonText : null,
        ':bu' => $buttonUrl !== '' ? $buttonUrl : null,
        ':sort' => $sortOrder,
        ':active' => $isActive,
        ':created' => now_utc(),
        ':by' => $actor['id'],
    ]);
    $id = (int) $pdo->lastInsertId();
    sync_hero_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    sync_hero_buttons($pdo, $id, $buttons, (int) $actor['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($upload['absolute_path']);
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'create', 'hero', $id, ['title' => $title]);
json_ok(['id' => $id, 'image_path' => $upload['relative_path']], 'Hero created', 201);
