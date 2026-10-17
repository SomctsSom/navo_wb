<?php
declare(strict_types=1);

require_method('GET');

$input = request_input();
$pageId = isset($input['page_id']) ? (int) $input['page_id'] : 0;
$slug = trim((string) ($input['slug'] ?? ''));

$pdo = db();
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
    "SELECT f.id, f.title, f.main_title, f.description, f.sort_order AS feature_sort,
            fp.is_featured, fp.sort_order AS page_sort
     FROM feature_pages fp
     JOIN features f ON f.id = fp.feature_id
     WHERE fp.page_id = :page_id
       AND fp.deleted_at IS NULL AND fp.is_active = 1
       AND f.deleted_at IS NULL AND f.is_active = 1
     ORDER BY fp.is_featured DESC, fp.sort_order ASC, f.sort_order ASC, f.id ASC"
);
$stmt->execute([':page_id' => $pageId]);
$rows = $stmt->fetchAll() ?: [];
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['is_featured'] = (int) $row['is_featured'];
    $row['cards'] = feature_load_cards($pdo, (int) $row['id'], true);
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
