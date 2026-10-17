<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('hero.restore');
auth_verify_csrf($actor);

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid hero id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM hero WHERE id = :id AND deleted_at IS NOT NULL');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('Deleted hero not found', 404);
}
if (!restore_row($pdo, 'hero', $id, (int) $actor['id'])) {
    json_error('Restore failed', 500);
}

// Restore page assignments soft-deleted with this hero
$hps = $pdo->prepare('SELECT id FROM hero_pages WHERE hero_id = :id AND deleted_at IS NOT NULL');
$hps->execute([':id' => $id]);
foreach ($hps->fetchAll(PDO::FETCH_COLUMN) ?: [] as $hpId) {
    // Skip if an active duplicate assignment already exists for same page
    $row = $pdo->prepare('SELECT page_id FROM hero_pages WHERE id = :id');
    $row->execute([':id' => $hpId]);
    $pageId = (int) $row->fetchColumn();
    $dup = $pdo->prepare('SELECT id FROM hero_pages WHERE hero_id = :h AND page_id = :p AND deleted_at IS NULL LIMIT 1');
    $dup->execute([':h' => $id, ':p' => $pageId]);
    if ($dup->fetchColumn()) {
        continue;
    }
    restore_row($pdo, 'hero_pages', (int) $hpId, (int) $actor['id']);
}

if (table_exists($pdo, 'hero_buttons')) {
    $btns = $pdo->prepare('SELECT id FROM hero_buttons WHERE hero_id = :id AND deleted_at IS NOT NULL');
    $btns->execute([':id' => $id]);
    foreach ($btns->fetchAll(PDO::FETCH_COLUMN) ?: [] as $buttonId) {
        restore_row($pdo, 'hero_buttons', (int) $buttonId, (int) $actor['id']);
    }
}

audit_log((int) $actor['id'], 'restore', 'hero', $id);
json_ok(['id' => $id], 'Hero restored');
