<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('features.create');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$title = trim((string) ($input['title'] ?? ''));
$mainTitle = trim((string) ($input['main_title'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$sortOrder = (int) ($input['sort_order'] ?? 0);
$isActive = array_key_exists('is_active', $input) ? to_bool_int($input['is_active'], 1) : 1;
$pagesJson = (string) ($input['pages_json'] ?? '[]');
$cardsJson = (string) ($input['cards_json'] ?? '[]');
$pageAssignments = json_decode($pagesJson, true);
$cards = json_decode($cardsJson, true);
if (!is_array($pageAssignments)) {
    $pageAssignments = [];
}
if (!is_array($cards)) {
    $cards = [];
}

if ($title === '') {
    json_error('Title is required', 422);
}
if (mb_strlen($mainTitle) > 200) {
    json_error('Main title must be 200 characters or less', 422);
}

try {
    $cards = feature_cards_apply_uploads($cards);
} catch (Throwable $e) {
    json_error($e->getMessage(), 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO features (title, main_title, description, sort_order, is_active, created_at, created_by)
         VALUES (:title, :main_title, :description, :sort, :active, :created, :by)'
    );
    $stmt->execute([
        ':title' => $title,
        ':main_title' => $mainTitle !== '' ? $mainTitle : null,
        ':description' => $description !== '' ? $description : null,
        ':sort' => $sortOrder,
        ':active' => $isActive,
        ':created' => now_utc(),
        ':by' => $actor['id'],
    ]);
    $id = (int) $pdo->lastInsertId();
    sync_feature_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    sync_feature_cards($pdo, $id, $cards, (int) $actor['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'create', 'features', $id, ['title' => $title]);
json_ok(['id' => $id], 'Feature section created', 201);
