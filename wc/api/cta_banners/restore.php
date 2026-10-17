<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('cta_banners.restore');
auth_verify_csrf($actor);

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid CTA banner id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM cta_banners WHERE id = :id AND deleted_at IS NOT NULL');
$stmt->execute([':id' => $id]);
if (!$stmt->fetch()) {
    json_error('Deleted CTA banner not found', 404);
}
if (!restore_row($pdo, 'cta_banners', $id, (int) $actor['id'])) {
    json_error('Restore failed', 500);
}

$sps = $pdo->prepare('SELECT id, page_id FROM cta_banner_pages WHERE cta_banner_id = :id AND deleted_at IS NOT NULL');
$sps->execute([':id' => $id]);
foreach ($sps->fetchAll() ?: [] as $row) {
    $dup = $pdo->prepare(
        'SELECT id FROM cta_banner_pages WHERE cta_banner_id = :s AND page_id = :p AND deleted_at IS NULL LIMIT 1'
    );
    $dup->execute([':s' => $id, ':p' => $row['page_id']]);
    if (!$dup->fetchColumn()) {
        restore_row($pdo, 'cta_banner_pages', (int) $row['id'], (int) $actor['id']);
    }
}

audit_log((int) $actor['id'], 'restore', 'cta_banners', $id);
json_ok(['id' => $id], 'CTA banner restored');
