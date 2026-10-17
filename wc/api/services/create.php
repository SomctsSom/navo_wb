<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('services.create');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$title = trim((string) ($input['title'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$iconClass = service_normalize_icon_class($input['icon_class'] ?? service_default_icon_class());
$buttonText = trim((string) ($input['button_text'] ?? ''));
$buttonUrl = trim((string) ($input['button_url'] ?? ''));
$sortOrder = (int) ($input['sort_order'] ?? 0);
$isActive = array_key_exists('is_active', $input) ? to_bool_int($input['is_active'], 1) : 1;
$pagesJson = (string) ($input['pages_json'] ?? '[]');
$itemsJson = (string) ($input['items_json'] ?? '[]');
$pageAssignments = json_decode($pagesJson, true);
$items = json_decode($itemsJson, true);
if (!is_array($pageAssignments)) {
    $pageAssignments = [];
}
if (!is_array($items)) {
    $items = [];
}

if ($title === '') {
    json_error('Title is required', 422);
}
if ($buttonUrl !== '' && !is_safe_url($buttonUrl)) {
    json_error('Invalid button URL', 422);
}

$imagePath = null;
$newUpload = null;
if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $newUpload = secure_upload_image($_FILES['image'], 'services');
    if (!$newUpload['ok']) {
        json_error($newUpload['error'], 422);
    }
    $imagePath = $newUpload['relative_path'];
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO services (title, description, icon_class, image_path, button_text, button_url, sort_order, is_active, created_at, created_by)
         VALUES (:title, :description, :icon, :image, :bt, :bu, :sort, :active, :created, :by)'
    );
    $stmt->execute([
        ':title' => $title,
        ':description' => $description !== '' ? $description : null,
        ':icon' => $iconClass,
        ':image' => $imagePath,
        ':bt' => $buttonText !== '' ? $buttonText : null,
        ':bu' => $buttonUrl !== '' ? $buttonUrl : null,
        ':sort' => $sortOrder,
        ':active' => $isActive,
        ':created' => now_utc(),
        ':by' => $actor['id'],
    ]);
    $id = (int) $pdo->lastInsertId();
    sync_service_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    sync_service_items($pdo, $id, $items, (int) $actor['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    if ($newUpload) {
        @unlink($newUpload['absolute_path']);
    }
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'create', 'services', $id, ['title' => $title]);
json_ok(['id' => $id, 'image_path' => $imagePath], 'Service created', 201);
