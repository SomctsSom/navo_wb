<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('cta_banners.delete');
auth_verify_csrf($actor);

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid CTA banner id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM cta_banners WHERE id = :id AND deleted_at IS NULL');
$stmt->execute([':id' => $id]);
if (!$stmt->fetchColumn()) {
    json_error('CTA banner not found', 404);
}

$q = $pdo->prepare('SELECT id FROM cta_banner_pages WHERE cta_banner_id = :id AND deleted_at IS NULL');
$q->execute([':id' => $id]);
foreach ($q->fetchAll(PDO::FETCH_COLUMN) ?: [] as $rowId) {
    soft_delete_row($pdo, 'cta_banner_pages', (int) $rowId, (int) $actor['id']);
}

if (!soft_delete_row($pdo, 'cta_banners', $id, (int) $actor['id'])) {
    json_error('Soft delete failed', 500);
}
audit_log((int) $actor['id'], 'soft_delete', 'cta_banners', $id);
json_ok(['id' => $id], 'CTA banner soft-deleted');
