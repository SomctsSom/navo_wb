<?php
declare(strict_types=1);

require_method('GET');

$input = request_input();
$all = (int) ($input['all'] ?? 0) === 1;
$pageId = isset($input['page_id']) ? (int) $input['page_id'] : 0;
$slug = trim((string) ($input['slug'] ?? ''));

$pdo = db();

if ($all) {
    $stmt = $pdo->query(
        "SELECT s.id, s.title, s.description, s.icon_class, s.image_path, s.button_text, s.button_url, s.sort_order
         FROM services s
         WHERE s.deleted_at IS NULL AND s.is_active = 1
         ORDER BY s.sort_order ASC, s.id ASC"
    );
    $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['icon_class'] = service_normalize_icon_class($row['icon_class'] ?? service_default_icon_class());
        $row['icon_html'] = feature_icon_html($row['icon_class']);
        $row['image_url'] = $row['image_path'] ? secure_public_media_url($row['image_path']) : null;
        unset($row['image_path']);
    }
    unset($row);
    json_ok(['items' => $rows]);
}

if ($pageId < 1 && $slug !== '') {
    $s = $pdo->prepare(
        "SELECT id FROM pages WHERE page_type = 'website' AND slug = :slug AND deleted_at IS NULL AND is_active = 1 LIMIT 1"
    );
    $s->execute([':slug' => $slug]);
    $pageId = (int) ($s->fetchColumn() ?: 0);
}
if ($pageId < 1) {
    json_error('page_id or slug is required', 422);
}

$p = $pdo->prepare(
    "SELECT id, title, slug FROM pages
     WHERE id = :id AND page_type = 'website' AND deleted_at IS NULL AND is_active = 1"
);
$p->execute([':id' => $pageId]);
$page = $p->fetch();
if (!$page) {
    json_error('Page not found', 404);
}

$stmt = $pdo->prepare(
    "SELECT s.id, s.title, s.description, s.icon_class, s.image_path, s.button_text, s.button_url, s.sort_order AS service_sort,
            sp.is_featured, sp.sort_order AS page_sort
     FROM service_pages sp
     JOIN services s ON s.id = sp.service_id
     WHERE sp.page_id = :page_id
       AND sp.deleted_at IS NULL AND sp.is_active = 1
       AND s.deleted_at IS NULL AND s.is_active = 1
     ORDER BY sp.is_featured DESC, sp.sort_order ASC, s.sort_order ASC, s.id ASC"
);
$stmt->execute([':page_id' => $pageId]);
$rows = $stmt->fetchAll() ?: [];
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['is_featured'] = (int) $row['is_featured'];
    $row['icon_class'] = service_normalize_icon_class($row['icon_class'] ?? service_default_icon_class());
    $row['icon_html'] = feature_icon_html($row['icon_class']);
    $row['image_url'] = $row['image_path'] ? secure_public_media_url($row['image_path']) : null;
    unset($row['image_path']);
    $row['items'] = service_load_items($pdo, (int) $row['id'], true);
}
unset($row);

json_ok([
    'page' => [
        'id' => (int) $page['id'],
        'title' => $page['title'],
        'slug' => $page['slug'],
    ],
    'items' => $rows,
]);
