<?php
declare(strict_types=1);

wa_require_perm('hero.view');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && wa_can('hero.delete')) {
        $res = wa_api('POST', '/api/hero/delete', ['id' => $id]);
        wa_flash(wa_api_ok($res) ? 'success' : 'error', (string) ($res['message'] ?? 'Done'));
    } elseif ($action === 'size' && wa_can('hero.edit')) {
        $res = wa_api('POST', '/api/hero/size', [
            'size_preset' => (string) ($_POST['size_preset'] ?? 'md'),
        ]);
        wa_flash(wa_api_ok($res) ? 'success' : 'error', (string) ($res['message'] ?? 'Done'));
    } elseif ($action === 'toggle' && wa_can('hero.edit')) {
        $view = wa_api('GET', '/api/hero/view', ['id' => $id]);
        if (wa_api_ok($view)) {
            $h = $view['data'];
            $pagesJson = json_encode(array_map(static function ($p) {
                return [
                    'page_id' => (int) $p['page_id'],
                    'is_featured' => (int) $p['is_featured'],
                    'is_active' => (int) $p['is_active'],
                    'sort_order' => (int) $p['sort_order'],
                ];
            }, $h['pages'] ?? []), JSON_UNESCAPED_UNICODE);
            $res = wa_api_multipart('/api/hero/update', [
                'id' => (string) $id,
                'title' => (string) $h['title'],
                'subtitle' => (string) ($h['subtitle'] ?? ''),
                'description' => (string) ($h['description'] ?? ''),
                'button_text' => (string) ($h['button_text'] ?? ''),
                'button_url' => (string) ($h['button_url'] ?? ''),
                'sort_order' => (string) $h['sort_order'],
                'is_active' => ((int) $h['is_active'] === 1) ? '0' : '1',
                'pages_json' => (string) $pagesJson,
            ]);
            wa_flash(wa_api_ok($res) ? 'success' : 'error', (string) ($res['message'] ?? 'Done'));
        }
    }
    wa_redirect('/hero?' . http_build_query(array_filter([
        'q' => $_GET['q'] ?? null,
        'page_id' => $_GET['page_id'] ?? null,
        'status' => $_GET['status'] ?? null,
    ], static fn($v) => $v !== null && $v !== '')));
}

$q = trim((string) ($_GET['q'] ?? ''));
$pageId = (string) ($_GET['page_id'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$res = wa_api('GET', '/api/hero', [
    'q' => $q, 'page_id' => $pageId, 'status' => $status,
    'page' => (int) ($_GET['page'] ?? 1),
]);
$items = wa_api_ok($res) ? ($res['data']['items'] ?? []) : [];
$pagination = wa_api_ok($res) ? ($res['data']['pagination'] ?? []) : [];
$heroSize = wa_api_ok($res) ? ($res['data']['hero_size'] ?? []) : [];
$sizePreset = (string) ($heroSize['size_preset'] ?? 'md');
$sizeOptions = $heroSize['options'] ?? [
    'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large',
];
$pagesRes = wa_api('GET', '/api/pages', ['page_type' => 'website', 'per_page' => 100]);
$pages = wa_api_ok($pagesRes) ? ($pagesRes['data']['items'] ?? []) : [];

ob_start();
?>
<div class="page-head">
  <div><h1>Hero</h1><p>Manage hero banners and per-page Active/Featured assignments.</p></div>
  <?php if (wa_can('hero.create')): ?><a class="btn" href="<?= wa_e(wa_url('/hero/create')) ?>">Create hero</a><?php endif; ?>
</div>
<?php if (wa_can('hero.edit') || wa_can('hero.view')): ?>
<div class="panel">
  <form class="toolbar" method="post" style="align-items:end">
    <?= wa_csrf_field() ?>
    <input type="hidden" name="action" value="size">
    <label style="display:flex;flex-direction:column;gap:0.35rem;min-width:14rem">
      <span>Section size (all heroes)</span>
      <select name="size_preset" <?= wa_can('hero.edit') ? '' : 'disabled' ?>>
        <?php foreach ($sizeOptions as $value => $label): ?>
          <option value="<?= wa_e((string) $value) ?>" <?= $sizePreset === (string) $value ? 'selected' : '' ?>><?= wa_e((string) $label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (wa_can('hero.edit')): ?>
      <button class="btn" type="submit">Save size</button>
    <?php endif; ?>
    <p class="muted" style="margin:0;flex:1">One size applies to every hero on the public site.</p>
  </form>
</div>
<?php endif; ?>
<div class="panel">
  <form class="toolbar" method="get">
    <input type="search" name="q" value="<?= wa_e($q) ?>" placeholder="Search hero">
    <select name="page_id">
      <option value="">All pages</option>
      <?php foreach ($pages as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= $pageId === (string)$p['id'] ? 'selected' : '' ?>><?= wa_e($p['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">Status: any</option>
      <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
    </select>
    <button class="btn btn-secondary" type="submit">Filter</button>
  </form>
  <table class="data">
    <thead><tr><th>Image</th><th>Title</th><th>Status</th><th>Assigned pages</th><th>#</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($items as $row): ?>
      <tr>
        <td><?php if (!empty($row['image_url'])): ?><img class="thumb" src="<?= wa_e($row['image_url']) ?>" alt=""><?php endif; ?></td>
        <td><?= wa_e($row['title']) ?></td>
        <td><?= wa_badge((int)$row['is_active']) ?></td>
        <td>
          <?php foreach ($row['pages'] ?? [] as $p): ?>
            <div><?= wa_e($p['title']) ?> <?= (int)$p['is_active']===1?'':'(inactive)' ?></div>
          <?php endforeach; ?>
        </td>
        <td><?= (int)($row['page_count'] ?? 0) ?></td>
        <td>
          <a href="<?= wa_e(wa_url('/hero/view?id='.$row['id'])) ?>">View</a>
          <?php if (wa_can('hero.edit')): ?> · <a href="<?= wa_e(wa_url('/hero/edit?id='.$row['id'])) ?>">Edit</a>
            · <form class="confirm-form" method="post"><?= wa_csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-ghost" style="padding:0.2rem 0.5rem" type="submit"><?= (int)$row['is_active']===1?'Deactivate':'Activate' ?></button></form>
          <?php endif; ?>
          <?php if (wa_can('hero.delete')): ?>
            · <form class="confirm-form" method="post"><?= wa_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-danger" style="padding:0.2rem 0.5rem" type="submit" data-confirm="Soft-delete this hero? Image files are retained.">Delete</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?= wa_pagination($pagination, '/hero', ['q'=>$q,'page_id'=>$pageId,'status'=>$status]) ?>
</div>
<?php
wa_render('Hero', ob_get_clean());
