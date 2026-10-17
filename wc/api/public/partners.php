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
    "SELECT pr.id, pr.section_title, pr.section_subtitle, pr.icon_class, pr.badge_text, pr.name, pr.category,
            pr.description, pr.useful_for, pr.button_text, pr.button_url,
            pr.sort_order AS partner_sort, pp.is_featured, pp.sort_order AS page_sort
     FROM partner_pages pp
     JOIN partners pr ON pr.id = pp.partner_id
     WHERE pp.page_id = :page_id
       AND pp.deleted_at IS NULL AND pp.is_active = 1
       AND pr.deleted_at IS NULL AND pr.is_active = 1
     ORDER BY pp.is_featured DESC, pp.sort_order ASC, pr.sort_order ASC, pr.id ASC"
);
$stmt->execute([':page_id' => $pageId]);
$rows = $stmt->fetchAll() ?: [];
$sectionTitle = '';
$sectionSubtitle = '';
foreach ($rows as &$row) {
    $row = partner_normalize_row($row);
    $row['is_featured'] = (int) ($row['is_featured'] ?? 0);
    if ($sectionTitle === '' && $row['section_title'] !== '') {
        $sectionTitle = $row['section_title'];
    }
    if ($sectionSubtitle === '' && $row['section_subtitle'] !== '') {
        $sectionSubtitle = $row['section_subtitle'];
    }
}
unset($row);
if ($sectionTitle === '') {
    $sectionTitle = 'Our Partners';
}

json_ok([
    'page' => [
        'id' => (int) $page['id'],
        'title' => $page['title'],
        'slug' => $page['slug'],
    ],
    'section_title' => $sectionTitle,
    'section_subtitle' => $sectionSubtitle,
    'items' => $rows,
]);
