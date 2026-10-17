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
    "SELECT c.id, c.eyebrow, c.title, c.subtitle,
            c.primary_button_text, c.primary_button_url,
            c.secondary_button_text, c.secondary_button_url,
            c.sort_order AS banner_sort, cp.is_featured, cp.sort_order AS page_sort
     FROM cta_banner_pages cp
     JOIN cta_banners c ON c.id = cp.cta_banner_id
     WHERE cp.page_id = :page_id
       AND cp.deleted_at IS NULL AND cp.is_active = 1
       AND c.deleted_at IS NULL AND c.is_active = 1
     ORDER BY cp.is_featured DESC, cp.sort_order ASC, c.sort_order ASC, c.id ASC"
);
$stmt->execute([':page_id' => $pageId]);
$rows = $stmt->fetchAll() ?: [];
foreach ($rows as &$row) {
    $row = cta_banner_normalize_row($row);
    $row['is_featured'] = (int) ($row['is_featured'] ?? 0);
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
