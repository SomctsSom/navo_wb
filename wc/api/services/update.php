<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('services.edit');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$id = (int) ($input['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid service id', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Service not found', 404);
}

$title = trim((string) ($input['title'] ?? $existing['title']));
$description = array_key_exists('description', $input) ? trim((string) $input['description']) : (string) $existing['description'];
$iconClass = array_key_exists('icon_class', $input)
    ? service_normalize_icon_class($input['icon_class'])
    : service_normalize_icon_class($existing['icon_class'] ?? service_default_icon_class());
$buttonText = array_key_exists('button_text', $input) ? trim((string) $input['button_text']) : (string) $existing['button_text'];
$buttonUrl = array_key_exists('button_url', $input) ? trim((string) $input['button_url']) : (string) $existing['button_url'];
$sortOrder = (int) ($input['sort_order'] ?? $existing['sort_order']);
$isActive = array_key_exists('is_active', $input) ? to_bool_int($input['is_active'], (int) $existing['is_active']) : (int) $existing['is_active'];

$pageAssignments = null;
if (array_key_exists('pages_json', $input) && (string) $input['pages_json'] !== '') {
    $pageAssignments = json_decode((string) $input['pages_json'], true);
    if (!is_array($pageAssignments)) {
        json_error('Invalid pages_json', 422);
    }
}
$items = null;
if (array_key_exists('items_json', $input)) {
    $items = json_decode((string) ($input['items_json'] ?? '[]'), true);
    if (!is_array($items)) {
        json_error('Invalid items_json', 422);
    }
}

if ($title === '') {
    json_error('Title is required', 422);
}
if ($buttonUrl !== '' && !is_safe_url($buttonUrl)) {
    json_error('Invalid button URL', 422);
}

$imagePath = $existing['image_path'];
$newUpload = null;
if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $newUpload = secure_upload_image($_FILES['image'], 'services');
    if (!$newUpload['ok']) {
        json_error($newUpload['error'], 422);
    }
    $imagePath = $newUpload['relative_path'];
}
if (array_key_exists('remove_image', $input) && to_bool_int($input['remove_image']) === 1 && !$newUpload) {
    $imagePath = null;
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE services SET title = :title, description = :description, icon_class = :icon, image_path = :image, button_text = :bt,
         button_url = :bu, sort_order = :sort, is_active = :active, updated_at = :u, updated_by = :by WHERE id = :id'
    )->execute([
        ':title' => $title,
        ':description' => $description !== '' ? $description : null,
        ':icon' => $iconClass,
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
        sync_service_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    }
    if ($items !== null) {
        sync_service_items($pdo, $id, $items, (int) $actor['id']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($newUpload) {
        @unlink($newUpload['absolute_path']);
    }
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'update', 'services', $id, ['title' => $title]);
json_ok(['id' => $id], 'Service updated');
