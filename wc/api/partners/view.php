<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('partners.view');

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid partner id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('Partner not found', 404);
}
$row = partner_normalize_row($row);
$ps = $pdo->prepare(
    'SELECT pp.id AS partner_page_id, pp.page_id, pp.is_featured, pp.is_active, pp.sort_order, p.title, p.slug, p.page_type
     FROM partner_pages pp
     JOIN pages p ON p.id = pp.page_id
     WHERE pp.partner_id = :id AND pp.deleted_at IS NULL
     ORDER BY pp.sort_order, p.title'
);
$ps->execute([':id' => $id]);
$row['pages'] = $ps->fetchAll() ?: [];
$row['created_at_display'] = format_display_time($row['created_at']);
json_ok($row);
