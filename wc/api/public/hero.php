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

// Ensure page is public-eligible
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
    "SELECT h.id, h.title, h.subtitle, h.description, h.space_subtitle, h.space_title, h.space_description,
            h.image_path, h.button_text, h.button_url, h.sort_order AS hero_sort,
            hp.is_featured, hp.sort_order AS page_sort
     FROM hero_pages hp
     JOIN hero h ON h.id = hp.hero_id
     WHERE hp.page_id = :page_id
       AND hp.deleted_at IS NULL AND hp.is_active = 1
       AND h.deleted_at IS NULL AND h.is_active = 1
     ORDER BY hp.is_featured DESC, hp.sort_order ASC, h.sort_order ASC, h.id ASC"
);
$stmt->execute([':page_id' => $pageId]);
$rows = $stmt->fetchAll() ?: [];
$buttonMap = hero_load_buttons_for($pdo, array_map(static fn(array $row): int => (int) $row['id'], $rows), true);
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['is_featured'] = (int) $row['is_featured'];
    $row['image_url'] = secure_public_media_url($row['image_path']);
    $row['buttons'] = hero_buttons_or_legacy($row, $buttonMap[$row['id']] ?? []);
    $row = hero_attach_spacing($row);
    unset($row['image_path']);
}
unset($row);

$sizePreset = get_hero_size_preset($pdo);

json_ok([
    'page' => [
        'id' => (int) $page['id'],
        'title' => $page['title'],
        'slug' => $page['slug'],
    ],
    'size_preset' => $sizePreset,
    'items' => $rows,
]);
