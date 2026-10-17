<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('services.view');

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid service id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('Service not found', 404);
}
$row['id'] = (int) $row['id'];
$row['is_active'] = (int) $row['is_active'];
$row['icon_class'] = service_normalize_icon_class($row['icon_class'] ?? service_default_icon_class());
$row['icon_html'] = feature_icon_html($row['icon_class']);
$row['image_url'] = $row['image_path'] ? secure_public_media_url($row['image_path']) : null;
$ps = $pdo->prepare(
    'SELECT sp.id AS service_page_id, sp.page_id, sp.is_featured, sp.is_active, sp.sort_order, p.title, p.slug, p.page_type
     FROM service_pages sp
     JOIN pages p ON p.id = sp.page_id
     WHERE sp.service_id = :id AND sp.deleted_at IS NULL
     ORDER BY sp.sort_order, p.title'
);
$ps->execute([':id' => $id]);
$row['pages'] = $ps->fetchAll() ?: [];
$row['items'] = service_load_items($pdo, $id, false);
$row['created_at_display'] = format_display_time($row['created_at']);
json_ok($row);
