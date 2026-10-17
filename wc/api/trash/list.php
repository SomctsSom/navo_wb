<?php
declare(strict_types=1);

require_method('GET');
auth_require_permission('trash.view');

$input = request_input();
$module = (string) ($input['module'] ?? 'users');
[$page, $perPage, $offset] = paginate_params($input);
$q = trim((string) ($input['q'] ?? ''));

$allowed = [
    'users' => ['table' => 'users', 'label' => 'name', 'extra' => 'email'],
    'roles' => ['table' => 'roles', 'label' => 'name', 'extra' => 'code'],
    'permissions' => ['table' => 'permissions', 'label' => 'code', 'extra' => 'description'],
    'menus' => ['table' => 'menus', 'label' => 'title', 'extra' => 'menu_type'],
    'pages' => ['table' => 'pages', 'label' => 'title', 'extra' => 'slug'],
    'hero' => ['table' => 'hero', 'label' => 'title', 'extra' => 'image_path'],
    'services' => ['table' => 'services', 'label' => 'title', 'extra' => 'image_path'],
    'profile_overviews' => ['table' => 'profile_overviews', 'label' => 'title', 'extra' => 'image_top_path'],
    'vision_missions' => ['table' => 'vision_missions', 'label' => 'title', 'extra' => 'statement_type'],
    'features' => ['table' => 'features', 'label' => 'title', 'extra' => 'description'],
    'testimonials' => ['table' => 'testimonials', 'label' => 'author_name', 'extra' => 'quote'],
    'cta_banners' => ['table' => 'cta_banners', 'label' => 'title', 'extra' => 'subtitle'],
    'partners' => ['table' => 'partners', 'label' => 'name', 'extra' => 'category'],
    'social_links' => ['table' => 'social_links', 'label' => 'label', 'extra' => 'link_url'],
    'contact_infos' => ['table' => 'contact_infos', 'label' => 'company_summary', 'extra' => 'id'],
];

if (!isset($allowed[$module])) {
    json_error('Invalid module', 422);
}
$meta = $allowed[$module];
$table = $meta['table'];
$label = $meta['label'];
$extra = $meta['extra'];

$where = 'deleted_at IS NOT NULL';
$params = [];
if ($q !== '') {
    $where .= " AND ({$label} LIKE :q OR CAST({$extra} AS CHAR) LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$pdo = db();
$c = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
$c->execute($params);
$total = (int) $c->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT id, {$label} AS label, {$extra} AS extra, deleted_at, deleted_by
     FROM `{$table}` WHERE {$where}
     ORDER BY deleted_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];
foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['module'] = $module;
    $row['deleted_at_display'] = format_display_time($row['deleted_at']);
}
unset($row);

json_ok([
    'module' => $module,
    'modules' => array_keys($allowed),
    'items' => $rows,
    'pagination' => [
        'page' => $page, 'per_page' => $perPage, 'total' => $total,
        'total_pages' => (int) ceil($total / max(1, $perPage)),
    ],
]);
