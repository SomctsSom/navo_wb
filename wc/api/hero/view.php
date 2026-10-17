<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('hero.view');

$id = (int) (request_input()['id'] ?? 0);
if ($id < 1) {
    json_error('Invalid hero id', 422);
}
$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM hero WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('Hero not found', 404);
}
$row['id'] = (int) $row['id'];
$row['is_active'] = (int) $row['is_active'];
$row['image_url'] = secure_public_media_url($row['image_path']);
$ps = $pdo->prepare(
    'SELECT hp.id AS hero_page_id, hp.page_id, hp.is_featured, hp.is_active, hp.sort_order, p.title, p.slug, p.page_type
     FROM hero_pages hp
     JOIN pages p ON p.id = hp.page_id
     WHERE hp.hero_id = :id AND hp.deleted_at IS NULL
     ORDER BY hp.sort_order, p.title'
);
$ps->execute([':id' => $id]);
$row['pages'] = $ps->fetchAll() ?: [];
$row['buttons'] = hero_buttons_or_legacy($row, hero_load_buttons($pdo, $id));
$row['created_at_display'] = format_display_time($row['created_at']);
json_ok($row);
