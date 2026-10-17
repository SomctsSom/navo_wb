<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('cta_banners.view');

$input = request_input();
[$page, $perPage, $offset] = paginate_params($input);
$q = trim((string) ($input['q'] ?? ''));
$pageId = isset($input['page_id']) && $input['page_id'] !== '' ? (int) $input['page_id'] : null;
$status = (string) ($input['status'] ?? '');
$trashed = ((string) ($input['trashed'] ?? '')) === '1';

$where = [$trashed ? 'c.deleted_at IS NOT NULL' : 'c.deleted_at IS NULL'];
$params = [];
$join = '';

if ($q !== '') {
    $where[] = '(c.title LIKE :q OR c.eyebrow LIKE :q OR c.subtitle LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($status === 'active') {
    $where[] = 'c.is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'c.is_active = 0';
}
if ($pageId !== null) {
    $join = 'JOIN cta_banner_pages cp ON cp.cta_banner_id = c.id AND cp.deleted_at IS NULL';
    $where[] = 'cp.page_id = :page_id';
    $params[':page_id'] = $pageId;
}

$sqlWhere = 'WHERE ' . implode(' AND ', $where);
$pdo = db();
$c = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM cta_banners c {$join} {$sqlWhere}");
$c->execute($params);
$total = (int) $c->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT DISTINCT c.* FROM cta_banners c {$join} {$sqlWhere}
     ORDER BY c.sort_order ASC, c.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];

foreach ($rows as &$row) {
    $row = cta_banner_normalize_row($row);
    $ps = $pdo->prepare(
        'SELECT cp.page_id, cp.is_featured, cp.is_active, cp.sort_order, pg.title, pg.slug
         FROM cta_banner_pages cp
         JOIN pages pg ON pg.id = cp.page_id AND pg.deleted_at IS NULL
         WHERE cp.cta_banner_id = :id AND cp.deleted_at IS NULL
         ORDER BY cp.sort_order, pg.title'
    );
    $ps->execute([':id' => $row['id']]);
    $pages = $ps->fetchAll() ?: [];
    $row['pages'] = $pages;
    $row['page_count'] = count($pages);
    $row['created_at_display'] = format_display_time($row['created_at']);
}
unset($row);

json_ok([
    'items' => $rows,
    'pagination' => [
        'page' => $page, 'per_page' => $perPage, 'total' => $total,
        'total_pages' => (int) ceil($total / max(1, $perPage)),
    ],
]);
