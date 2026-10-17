<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('partners.delete');
auth_verify_csrf($actor);

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid partner id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM partners WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $id]);
if (!$stmt->fetchColumn()) {
    json_error('Partner not found', 404);
}

$q = $pdo->prepare('SELECT id FROM partner_pages WHERE partner_id = :id AND deleted_at IS NULL');
$q->execute([':id' => $id]);
foreach ($q->fetchAll(PDO::FETCH_COLUMN) ?: [] as $rowId) {
    soft_delete_row($pdo, 'partner_pages', (int) $rowId, (int) $actor['id']);
}

if (!soft_delete_row($pdo, 'partners', $id, (int) $actor['id'])) {
    json_error('Soft delete failed', 500);
}
audit_log((int) $actor['id'], 'soft_delete', 'partners', $id);
json_ok(['id' => $id], 'Partner soft-deleted');
