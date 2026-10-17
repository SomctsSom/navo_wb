<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('hero.delete');
auth_verify_csrf($actor);

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid hero id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM hero WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $id]);
if (!$stmt->fetchColumn()) {
    json_error('Hero not found', 404);
}

$hps = $pdo->prepare('SELECT id FROM hero_pages WHERE hero_id = :id AND deleted_at IS NULL');
$hps->execute([':id' => $id]);
foreach ($hps->fetchAll(PDO::FETCH_COLUMN) ?: [] as $hpId) {
    soft_delete_row($pdo, 'hero_pages', (int) $hpId, (int) $actor['id']);
}
if (table_exists($pdo, 'hero_buttons')) {
    $btns = $pdo->prepare('SELECT id FROM hero_buttons WHERE hero_id = :id AND deleted_at IS NULL');
    $btns->execute([':id' => $id]);
    foreach ($btns->fetchAll(PDO::FETCH_COLUMN) ?: [] as $buttonId) {
        soft_delete_row($pdo, 'hero_buttons', (int) $buttonId, (int) $actor['id']);
    }
}

if (!soft_delete_row($pdo, 'hero', $id, (int) $actor['id'])) {
    json_error('Soft delete failed', 500);
}
// Image files retained for restore
audit_log((int) $actor['id'], 'soft_delete', 'hero', $id);
json_ok(['id' => $id], 'Hero soft-deleted');
