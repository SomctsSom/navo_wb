<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
wa_require_perm($isEdit ? 'menus.edit' : 'menus.create');

$menu = [
    'menu_type' => (string) ($_GET['menu_type'] ?? 'website'),
    'title' => '', 'icon' => '', 'url' => '', 'sort_order' => 0, 'is_active' => 1, 'parent_menu_id' => null,
    'pages' => [],
];
if ($isEdit) {
    $view = wa_api('GET', '/api/menus/view', ['id' => $id]);
    if (!wa_api_ok($view)) {
        wa_flash('error', $view['message'] ?? 'Not found');
        wa_redirect('/website-menus');
    }
    $menu = $view['data'];
}

$parentsRes = wa_api('GET', '/api/menus', ['menu_type' => $menu['menu_type'], 'tree' => 0]);
$parents = wa_api_ok($parentsRes) ? ($parentsRes['data']['items'] ?? []) : [];
$pagesRes = wa_api('GET', '/api/pages', ['page_type' => $menu['menu_type'], 'per_page' => 100]);
$pages = wa_api_ok($pagesRes) ? ($pagesRes['data']['items'] ?? []) : [];
$assigned = array_map(static fn($p) => (int) $p['id'], $menu['pages'] ?? []);

$error = '';
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $payload = [
        'menu_type' => $menu['menu_type'],
        'title' => trim((string) ($_POST['title'] ?? '')),
        'icon' => trim((string) ($_POST['icon'] ?? '')),
        'url' => trim((string) ($_POST['url'] ?? '')),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'parent_menu_id' => ($_POST['parent_menu_id'] ?? '') === '' ? null : (int) $_POST['parent_menu_id'],
    ];
    if ($isEdit) {
        $payload['id'] = $id;
        $res = wa_api('POST', '/api/menus/update', $payload);
    } else {
        $res = wa_api('POST', '/api/menus/create', $payload);
        $id = (int) ($res['data']['id'] ?? 0);
    }
    if (wa_api_ok($res)) {
        $pageIds = array_map('intval', $_POST['page_ids'] ?? []);
        wa_api('POST', '/api/menus/assign-pages', ['menu_id' => $id, 'page_ids' => $pageIds]);
        wa_flash('success', 'Menu saved');
        wa_redirect($menu['menu_type'] === 'admin' ? '/admin-menus' : '/website-menus');
    }
    $error = (string) ($res['message'] ?? 'Save failed');
}

$list = $menu['menu_type'] === 'admin' ? '/admin-menus' : '/website-menus';
ob_start();
?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit menu' : 'Create menu' ?></h1></div>
  <a class="btn btn-ghost" href="<?= wa_e(wa_url($list)) ?>">Back</a></div>
<?php if ($error): ?><div class="alert alert-error"><?= wa_e($error) ?></div><?php endif; ?>
<div class="panel">
  <form method="post" class="form-grid">
    <?= wa_csrf_field() ?>
    <label>Title<input name="title" required value="<?= wa_e((string)$menu['title']) ?>"></label>
    <label>URL
      <input name="url" value="<?= wa_e((string)($menu['url'] ?? '')) ?>" placeholder="/about or https://example.com">
      <span class="muted">Absolute http(s) links open in a new tab on the public site.</span>
    </label>
    <label>Icon<input name="icon" value="<?= wa_e((string)($menu['icon'] ?? '')) ?>"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int)$menu['sort_order'] ?>"></label>
    <label>Parent
      <select name="parent_menu_id">
        <option value="">— None —</option>
        <?php foreach ($parents as $p): if ($isEdit && (int)$p['id'] === $id) continue; ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)($menu['parent_menu_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= wa_e($p['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><span>Active</span><input type="checkbox" name="is_active" value="1" <?= (int)$menu['is_active']===1?'checked':'' ?>></label>
    <div class="full">
      <strong>Linked <?= wa_e($menu['menu_type']) ?> pages</strong>
      <div class="page-checks">
        <?php foreach ($pages as $p): ?>
          <label class="page-check" style="grid-template-columns:auto 1fr">
            <input type="checkbox" name="page_ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $assigned, true) ? 'checked' : '' ?>>
            <span><?= wa_e($p['title']) ?> <span class="muted">/<?= wa_e($p['slug']) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="actions full"><button class="btn" type="submit">Save</button></div>
  </form>
</div>
<?php
wa_render($isEdit ? 'Edit menu' : 'Create menu', ob_get_clean());
