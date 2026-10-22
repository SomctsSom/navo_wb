<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('features.edit');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$id = (int) ($input['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid feature id', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM features WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Feature section not found', 404);
}

$title = trim((string) ($input['title'] ?? $existing['title']));
$mainTitle = array_key_exists('main_title', $input)
    ? trim((string) $input['main_title'])
    : (string) ($existing['main_title'] ?? '');
$description = array_key_exists('description', $input)
    ? trim((string) $input['description'])
    : (string) ($existing['description'] ?? '');
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
$cards = null;
if (array_key_exists('cards_json', $input)) {
    $cards = json_decode((string) ($input['cards_json'] ?? '[]'), true);
    if (!is_array($cards)) {
        json_error('Invalid cards_json', 422);
    }
    try {
        $cards = feature_cards_apply_uploads($cards);
    } catch (Throwable $e) {
        json_error($e->getMessage(), 422);
    }
}

if ($title === '') {
    json_error('Title is required', 422);
}
if (mb_strlen($mainTitle) > 200) {
    json_error('Main title must be 200 characters or less', 422);
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE features SET title = :title, main_title = :main_title, description = :description,
         sort_order = :sort, is_active = :active, updated_at = :u, updated_by = :by WHERE id = :id'
    )->execute([
        ':title' => $title,
        ':main_title' => $mainTitle !== '' ? $mainTitle : null,
        ':description' => $description !== '' ? $description : null,
        ':sort' => $sortOrder,
        ':active' => $isActive,
        ':u' => now_utc(),
        ':by' => $actor['id'],
        ':id' => $id,
    ]);
    if ($pageAssignments !== null) {
        sync_feature_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    }
    if ($cards !== null) {
        sync_feature_cards($pdo, $id, $cards, (int) $actor['id']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'update', 'features', $id, ['title' => $title]);
json_ok(['id' => $id], 'Feature section updated');
