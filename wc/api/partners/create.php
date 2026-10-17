<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('partners.create');
auth_verify_csrf($actor);

$input = array_merge(request_input(), $_POST);
$sectionTitle = trim((string) ($input['section_title'] ?? ''));
$sectionSubtitle = trim((string) ($input['section_subtitle'] ?? ''));
$iconClass = trim((string) ($input['icon_class'] ?? ''));
$badgeText = trim((string) ($input['badge_text'] ?? ''));
$name = trim((string) ($input['name'] ?? ''));
$category = trim((string) ($input['category'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$usefulFor = trim((string) ($input['useful_for'] ?? ''));
$buttonText = trim((string) ($input['button_text'] ?? ''));
$buttonUrl = trim((string) ($input['button_url'] ?? ''));
$sortOrder = (int) ($input['sort_order'] ?? 0);
$isActive = array_key_exists('is_active', $input) ? to_bool_int($input['is_active'], 1) : 1;
$pagesJson = (string) ($input['pages_json'] ?? '[]');
$pageAssignments = json_decode($pagesJson, true);
if (!is_array($pageAssignments)) {
    $pageAssignments = [];
}

if ($name === '') {
    json_error('Partner name is required', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO partners (
            section_title, section_subtitle, icon_class, badge_text, name, category, description, useful_for,
            button_text, button_url, sort_order, is_active, created_at, created_by
         ) VALUES (
            :section_title, :section_subtitle, :icon_class, :badge_text, :name, :category, :description, :useful_for,
            :button_text, :button_url, :sort, :active, :created, :by
         )'
    );
    $stmt->execute([
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
        ':created' => now_utc(),
        ':by' => $actor['id'],
    ]);
    $id = (int) $pdo->lastInsertId();
    sync_partner_pages($pdo, $id, $pageAssignments, (int) $actor['id']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 422);
}

audit_log((int) $actor['id'], 'create', 'partners', $id, ['name' => $name]);
json_ok(['id' => $id], 'Partner created', 201);
