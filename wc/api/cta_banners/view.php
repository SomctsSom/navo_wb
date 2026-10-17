<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('cta_banners.view');

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid CTA banner id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM cta_banners WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('CTA banner not found', 404);
}
$row = cta_banner_normalize_row($row);
$ps = $pdo->prepare(
    'SELECT cp.id AS cta_banner_page_id, cp.page_id, cp.is_featured, cp.is_active, cp.sort_order, p.title, p.slug, p.page_type
     FROM cta_banner_pages cp
     JOIN pages p ON p.id = cp.page_id
     WHERE cp.cta_banner_id = :id AND cp.deleted_at IS NULL
     ORDER BY cp.sort_order, p.title'
);
$ps->execute([':id' => $id]);
$row['pages'] = $ps->fetchAll() ?: [];
$row['created_at_display'] = format_display_time($row['created_at']);
json_ok($row);
