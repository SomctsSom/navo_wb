<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('hero.edit');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$id = (int) ($input['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid hero id', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM hero WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Hero not found', 404);
}

$title = trim((string) ($input['title'] ?? $existing['title']));
$subtitle = array_key_exists('subtitle', $input) ? trim((string) $input['subtitle']) : (string) ($existing['subtitle'] ?? '');
$description = array_key_exists('description', $input) ? trim((string) $input['description']) : (string) $existing['description'];
$buttonText = array_key_exists('button_text', $input) ? trim((string) $input['button_text']) : (string) $existing['button_text'];
$buttonUrl = array_key_exists('button_url', $input) ? trim((string) $input['button_url']) : (string) $existing['button_url'];
$buttons = null;
if (array_key_exists('buttons_json', $input)) {
    $decodedButtons = json_decode((string) $input['buttons_json'], true);
    if (!is_array($decodedButtons)) {
        json_error('Invalid buttons_json', 422);
    }
    try {
        $buttons = hero_normalize_buttons($decodedButtons);
    } catch (RuntimeException $e) {
        json_error($e->getMessage(), 422);
    }
    $primaryButton = hero_first_button($buttons);
    $buttonText = $primaryButton['label'];
    $buttonUrl = $primaryButton['url'];
}
$sortOrder = (int) ($input['sort_order'] ?? $existing['sort_order']);
$isActive = array_key_exists('is_active', $input) ? to_bool_int($input['is_active'], (int) $existing['is_active']) : (int) $existing['is_active'];
$pagesJson = (string) ($input['pages_json'] ?? '');
$pageAssignments = null;
if ($pagesJson !== '') {
    $pageAssignments = json_decode($pagesJson, true);
    if (!is_array($pageAssignments)) {
        json_error('Invalid pages_json', 422);
    }
}

if ($title === '') {
    json_error('Title is required', 422);
}
if (mb_strlen($subtitle) > 200) {
    json_error('Subtitle must be 200 characters or less', 422);
}
if ($buttonUrl !== '' && !is_safe_url($buttonUrl)) {
    json_error('Invalid button URL', 422);
}

$imagePath = $existing['image_path'];
$newUpload = null;
if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $newUpload = secure_upload_image($_FILES['image'], 'hero');
    if (!$newUpload['ok']) {
        json_error($newUpload['error'], 422);
    }
    $imagePath = $newUpload['relative_path'];
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE hero SET title = :title, subtitle = :subtitle, description = :description, image_path = :image, button_text = :bt,
         button_url = :bu, sort_order = :sort, is_active = :active, updated_at = :u, updated_by = :by WHERE id = :id'
    )->execute([
        ':title' => $title,
        ':subtitle' => $subtitle !== '' ? $subtitle : null,
        ':description' => $description !== '' ? $description : null,
        ':image' => $imagePath,
        ':bt' => $buttonText !== '' ? $buttonText : null,
        ':bu' => $buttonUrl !== '' ? $buttonUrl : null,
        ':sort' => $sortOrder,
        ':active' => $isActive,
        ':u' => now_utc(),
        ':by' => $actor['id'],
        ':id' => $id,
    ]);
    if ($pageAssignments !== null) {
        sync_hero_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    }
    if ($buttons !== null) {
        sync_hero_buttons($pdo, $id, $buttons, (int) $actor['id']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($newUpload) {
        @unlink($newUpload['absolute_path']);
    }
    json_error($e->getMessage(), 422);
}

// Keep old image on disk for restore/history safety (do not delete on replace failure recovery either)
audit_log((int) $actor['id'], 'update', 'hero', $id, ['title' => $title, 'image_replaced' => $newUpload !== null]);
json_ok(['id' => $id], 'Hero updated');
