<?php
declare(strict_types=1);

wa_require_perm('features.view');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && wa_can('features.delete')) {
        $res = wa_api('POST', '/api/features/delete', ['id' => $id]);
        wa_flash(wa_api_ok($res) ? 'success' : 'error', (string) ($res['message'] ?? 'Done'));
    } elseif ($action === 'toggle' && wa_can('features.edit')) {
        $view = wa_api('GET', '/api/features/view', ['id' => $id]);
        if (wa_api_ok($view)) {
            $p = $view['data'];
            $pagesJson = json_encode(array_map(static function ($row) {
                return [
                    'page_id' => (int) $row['page_id'],
                    'is_featured' => (int) $row['is_featured'],
                    'is_active' => (int) $row['is_active'],
                    'sort_order' => (int) $row['sort_order'],
                ];
            }, $p['pages'] ?? []), JSON_UNESCAPED_UNICODE);
            $cardsJson = json_encode(array_map(static function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'icon_key' => (string) ($row['icon_key'] ?? 'star'),
                    'title' => (string) $row['title'],
                    'description' => (string) ($row['description'] ?? ''),
                    'sort_order' => (int) $row['sort_order'],
                    'is_active' => (int) $row['is_active'],
                ];
            }, $p['cards'] ?? []), JSON_UNESCAPED_UNICODE);
            $res = wa_api('POST', '/api/features/update', [
                'id' => $id,
                'title' => (string) $p['title'],
                'main_title' => (string) ($p['main_title'] ?? ''),
                'description' => (string) ($p['description'] ?? ''),
                'sort_order' => (int) $p['sort_order'],
                'is_active' => ((int) $p['is_active'] === 1) ? 0 : 1,
                'pages_json' => $pagesJson,
                'cards_json' => $cardsJson,
            ]);
            wa_flash(wa_api_ok($res) ? 'success' : 'error', (string) ($res['message'] ?? 'Done'));
        }
    }
    wa_redirect('/features?' . http_build_query(array_filter([
        'q' => $_GET['q'] ?? null,
        'page_id' => $_GET['page_id'] ?? null,
        'featured' => $_GET['featured'] ?? null,
        'status' => $_GET['status'] ?? null,
    ], static fn($v) => $v !== null && $v !== '')));
}

$q = trim((string) ($_GET['q'] ?? ''));
$pageId = (string) ($_GET['page_id'] ?? '');
$featured = (string) ($_GET['featured'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$res = wa_api('GET', '/api/features', [
    'q' => $q, 'page_id' => $pageId, 'featured' => $featured, 'status' => $status,
    'page' => (int) ($_GET['page'] ?? 1),
]);
$items = wa_api_ok($res) ? ($res['data']['items'] ?? []) : [];
$pagination = wa_api_ok($res) ? ($res['data']['pagination'] ?? []) : [];
$pagesRes = wa_api('GET', '/api/pages', ['page_type' => 'website', 'per_page' => 100]);
$pages = wa_api_ok($pagesRes) ? ($pagesRes['data']['items'] ?? []) : [];

ob_start();
?>
<div class="page-head">
  <div><h1>Features</h1><p>Sections like “Our Values” with one or many cards. Assign to website pages.</p></div>
  <?php if (wa_can('features.create')): ?><a class="btn" href="<?= wa_e(wa_url('/features/create')) ?>">Create feature section</a><?php endif; ?>
</div>
<div class="panel">
  <form class="toolbar" method="get">
    <input type="search" name="q" value="<?= wa_e($q) ?>" placeholder="Search">
    <select name="page_id">
      <option value="">All pages</option>
      <?php foreach ($pages as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $pageId === (string)$p['id'] ? 'selected' : '' ?>><?= wa_e($p['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="featured">
      <option value="">Featured: any</option>
      <option value="1" <?= $featured==='1'?'selected':'' ?>>Featured</option>
      <option value="0" <?= $featured==='0'?'selected':'' ?>>Not featured</option>
    </select>
    <select name="status">
      <option value="">Status: any</option>
      <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
    </select>
    <button class="btn btn-secondary" type="submit">Filter</button>
  </form>
  <table class="data">
    <thead><tr><th>Title</th><th>Cards</th><th>Status</th><th>Assigned pages</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($items as $row): ?>
      <tr>
        <td>
          <strong><?= wa_e($row['title']) ?></strong>
          <?php if (!empty($row['description'])): ?>
            <div class="muted" style="max-width:28rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= wa_e((string)$row['description']) ?></div>
          <?php endif; ?>
        </td>
        <td><?= (int)($row['card_count'] ?? 0) ?></td>
        <td><?= wa_badge((int)$row['is_active']) ?></td>
        <td>
          <?php foreach ($row['pages'] ?? [] as $p): ?>
            <div><?= wa_e($p['title']) ?> <?= (int)$p['is_featured']===1?'★':'' ?></div>
          <?php endforeach; ?>
        </td>
        <td>
          <a href="<?= wa_e(wa_url('/features/view?id='.$row['id'])) ?>">View</a>
          <?php if (wa_can('features.edit')): ?> · <a href="<?= wa_e(wa_url('/features/edit?id='.$row['id'])) ?>">Edit</a>
            · <form class="confirm-form" method="post"><?= wa_csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-ghost" style="padding:0.2rem 0.5rem" type="submit"><?= (int)$row['is_active']===1?'Deactivate':'Activate' ?></button></form>
          <?php endif; ?>
          <?php if (wa_can('features.delete')): ?>
            · <form class="confirm-form" method="post"><?= wa_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-danger" style="padding:0.2rem 0.5rem" type="submit" data-confirm="Soft-delete this feature section?">Delete</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= wa_pagination($pagination, '/features', ['q'=>$q,'page_id'=>$pageId,'featured'=>$featured,'status'=>$status]) ?>
</div>
<?php
wa_render('Features', ob_get_clean());
