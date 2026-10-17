<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('partners.edit');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$id = (int) ($input['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid partner id', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM partners WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Partner not found', 404);
}

$sectionTitle = array_key_exists('section_title', $input)
    ? trim((string) $input['section_title'])
    : (string) ($existing['section_title'] ?? '');
$sectionSubtitle = array_key_exists('section_subtitle', $input)
    ? trim((string) $input['section_subtitle'])
    : (string) ($existing['section_subtitle'] ?? '');
$iconClass = array_key_exists('icon_class', $input)
    ? trim((string) $input['icon_class'])
    : (string) ($existing['icon_class'] ?? '');
$badgeText = array_key_exists('badge_text', $input)
    ? trim((string) $input['badge_text'])
    : (string) ($existing['badge_text'] ?? '');
$name = array_key_exists('name', $input)
    ? trim((string) $input['name'])
    : (string) $existing['name'];
$category = array_key_exists('category', $input)
    ? trim((string) $input['category'])
    : (string) ($existing['category'] ?? '');
$description = array_key_exists('description', $input)
    ? trim((string) $input['description'])
    : (string) ($existing['description'] ?? '');
$usefulFor = array_key_exists('useful_for', $input)
    ? trim((string) $input['useful_for'])
    : (string) ($existing['useful_for'] ?? '');
$buttonText = array_key_exists('button_text', $input)
    ? trim((string) $input['button_text'])
    : (string) ($existing['button_text'] ?? '');
$buttonUrl = array_key_exists('button_url', $input)
    ? trim((string) $input['button_url'])
    : (string) ($existing['button_url'] ?? '');
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

if ($name === '') {
    json_error('Partner name is required', 422);
}

$pdo->beginTransaction();
try {
    $pdo->prepare(
        'UPDATE partners SET
            section_title = :section_title, section_subtitle = :section_subtitle,
            icon_class = :icon_class, badge_text = :badge_text,
            name = :name, category = :category, description = :description, useful_for = :useful_for,
            button_text = :button_text, button_url = :button_url,
            sort_order = :sort, is_active = :active,
            updated_at = :u, updated_by = :by
         WHERE id = :id'
    )->execute([
        ':section_title' => $sectionTitle !== '' ? $sectionTitle : null,
        ':section_subtitle' => $sectionSubtitle !== '' ? $sectionSubtitle : null,
        ':icon_class' => $iconClass !== '' ? $iconClass : null,
        ':badge_text' => $badgeText !== '' ? $badgeText : null,
        ':name' => $name,
        ':category' => $category !== '' ? $category : null,
        ':description' => $description !== '' ? $description : null,
        ':useful_for' => $usefulFor !== '' ? $usefulFor : null,
        ':button_text' => $buttonText !== '' ? $buttonText : null,
        ':button_url' => $buttonUrl !== '' ? $buttonUrl : null,
        ':sort' => $sortOrder,
        ':active' => $isActive,
        ':u' => now_utc(),
        ':by' => $actor['id'],
        ':id' => $id,
    ]);
    if ($pageAssignments !== null) {
        sync_partner_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'update', 'partners', $id, ['name' => $name]);
json_ok(['id' => $id], 'Partner updated');
