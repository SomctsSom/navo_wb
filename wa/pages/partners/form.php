<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
wa_require_perm($isEdit ? 'partners.edit' : 'partners.create');

$item = [
    'section_title' => '',
    'section_subtitle' => '',
    'icon_class' => 'fa-handshake',
    'icon_url' => null,
    'badge_text' => 'RECOMMENDED PARTNER — PARTNERSHIP PENDING',
    'name' => '', 'category' => '', 'description' => '', 'useful_for' => '',
    'button_text' => '', 'button_url' => '',
    'sort_order' => 0, 'is_active' => 1, 'pages' => [],
];
if ($isEdit) {
    $view = wa_api('GET', '/api/partners/view', ['id' => $id]);
    if (!wa_api_ok($view)) {
        wa_flash('error', $view['message'] ?? 'Not found');
        wa_redirect('/partners');
    }
    $item = $view['data'];
}

$pagesRes = wa_api('GET', '/api/pages', ['page_type' => 'website', 'per_page' => 100]);
$pages = wa_api_ok($pagesRes) ? ($pagesRes['data']['items'] ?? []) : [];
$assignedMap = [];
foreach ($item['pages'] ?? [] as $p) {
    $assignedMap[(int) $p['page_id']] = $p;
}

$error = '';
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $selected = $_POST['page_selected'] ?? [];
    $assignments = [];
    foreach ($selected as $pid) {
        $pid = (int) $pid;
        $assignments[] = [
            'page_id' => $pid,
            'is_active' => isset($_POST['page_active'][$pid]) ? 1 : 0,
            'is_featured' => isset($_POST['page_featured'][$pid]) ? 1 : 0,
            'sort_order' => (int) ($_POST['page_sort'][$pid] ?? 0),
        ];
    }
    $fields = [
        'section_title' => trim((string) ($_POST['section_title'] ?? '')),
        'section_subtitle' => trim((string) ($_POST['section_subtitle'] ?? '')),
        'icon_class' => trim((string) ($_POST['icon_class'] ?? '')),
        'badge_text' => trim((string) ($_POST['badge_text'] ?? '')),
        'name' => trim((string) ($_POST['name'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'useful_for' => trim((string) ($_POST['useful_for'] ?? '')),
        'button_text' => trim((string) ($_POST['button_text'] ?? '')),
        'button_url' => trim((string) ($_POST['button_url'] ?? '')),
        'sort_order' => (string) ((int) ($_POST['sort_order'] ?? 0)),
        'is_active' => isset($_POST['is_active']) ? '1' : '0',
        'pages_json' => json_encode($assignments, JSON_UNESCAPED_UNICODE),
    ];
    if (!empty($_POST['clear_image'])) {
        $fields['clear_image'] = '1';
    }
    if ($isEdit) {
        $fields['id'] = (string) $id;
        $res = wa_api_multipart('/api/partners/update', $fields, $_FILES['image'] ?? []);
    } else {
        $res = wa_api_multipart('/api/partners/create', $fields, $_FILES['image'] ?? []);
    }
    if (wa_api_ok($res)) {
        wa_flash('success', $res['message'] ?? 'Saved');
        wa_redirect('/partners');
    }
    $error = (string) ($res['message'] ?? 'Save failed');
    $item = array_merge($item, [
        'section_title' => $fields['section_title'],
        'section_subtitle' => $fields['section_subtitle'],
        'icon_class' => $fields['icon_class'],
        'badge_text' => $fields['badge_text'],
        'name' => $fields['name'],
        'category' => $fields['category'],
        'description' => $fields['description'],
        'useful_for' => $fields['useful_for'],
        'button_text' => $fields['button_text'],
        'button_url' => $fields['button_url'],
        'sort_order' => (int) $fields['sort_order'],
        'is_active' => (int) $fields['is_active'],
    ]);
    if (!empty($fields['clear_image'])) {
        $item['icon_url'] = null;
    }
    $assignedMap = [];
    foreach ($assignments as $a) {
        $assignedMap[$a['page_id']] = $a;
    }
}

ob_start();
?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit partner' : 'Create partner' ?></h1></div>
  <a class="btn btn-ghost" href="<?= wa_e(wa_url('/partners')) ?>">Back</a></div>
<?php if ($error): ?><div class="alert alert-error"><?= wa_e($error) ?></div><?php endif; ?>
<div class="panel">
  <form method="post" class="form-grid" enctype="multipart/form-data">
    <?= wa_csrf_field() ?>
    <label>Section title<input name="section_title" value="<?= wa_e((string)($item['section_title'] ?? '')) ?>" placeholder="Section title"></label>
    <label>Sub title<input name="section_subtitle" value="<?= wa_e((string)($item['section_subtitle'] ?? '')) ?>" placeholder="Sub title"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int)$item['sort_order'] ?>"></label>
    <label>Icon class
      <input name="icon_class" value="<?= wa_e((string)($item['icon_class'] ?? '')) ?>" placeholder="fa-handshake">
    </label>
    <label>Icon / logo image
      <?php if (!empty($item['icon_url'])): ?>
        <div style="margin:0.35rem 0">
          <img src="<?= wa_e((string)$item['icon_url']) ?>" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:8px;background:#f3f4f6;padding:4px">
        </div>
        <label style="display:flex;align-items:center;gap:0.35rem;font-weight:400;margin:0 0 0.35rem">
          <input type="checkbox" name="clear_image" value="1"> Remove image
        </label>
      <?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
    </label>
    <label>Badge text<input name="badge_text" value="<?= wa_e((string)($item['badge_text'] ?? '')) ?>" placeholder="RECOMMENDED PARTNER — PARTNERSHIP PENDING"></label>
    <label>Partner name<input name="name" required value="<?= wa_e((string)$item['name']) ?>" placeholder="RouteGenie"></label>
    <label>Category<input name="category" value="<?= wa_e((string)($item['category'] ?? '')) ?>" placeholder="NEMT Software"></label>
    <label class="full">Description
      <textarea name="description" rows="3" placeholder="Short partner description…"><?= wa_e((string)($item['description'] ?? '')) ?></textarea>
    </label>
    <label class="full">Useful for (one item per line)
      <textarea name="useful_for" rows="5" placeholder="Scheduling&#10;Routing&#10;Dispatching"><?= wa_e((string)($item['useful_for'] ?? '')) ?></textarea>
    </label>
    <label>Button text<input name="button_text" value="<?= wa_e((string)($item['button_text'] ?? '')) ?>" placeholder="Explore RouteGenie"></label>
    <label>Button URL<input name="button_url" value="<?= wa_e((string)($item['button_url'] ?? '')) ?>" placeholder="https://…"></label>
    <label><span>Active</span><input type="checkbox" name="is_active" value="1" <?= (int)$item['is_active']===1?'checked':'' ?>></label>
    <div class="full">
      <strong>Assign to website pages</strong>
      <p class="muted">Select one or many pages.</p>
      <div class="page-checks">
        <?php foreach ($pages as $p):
            $pid = (int) $p['id'];
            $a = $assignedMap[$pid] ?? null;
            $checked = $a !== null;
            $active = $a ? (int)($a['is_active'] ?? 1) === 1 : true;
            $feat = $a ? (int)($a['is_featured'] ?? 0) === 1 : false;
            $sort = $a ? (int)($a['sort_order'] ?? 0) : 0;
        ?>
          <div class="page-check">
            <label><input type="checkbox" name="page_selected[]" value="<?= $pid ?>" <?= $checked ? 'checked' : '' ?>> <?= wa_e($p['title']) ?> <span class="muted">/<?= wa_e($p['slug']) ?></span></label>
            <label>Active <input type="checkbox" name="page_active[<?= $pid ?>]" value="1" <?= $active ? 'checked' : '' ?>></label>
            <label>Featured <input type="checkbox" name="page_featured[<?= $pid ?>]" value="1" <?= $feat ? 'checked' : '' ?>></label>
            <label>Order <input type="number" name="page_sort[<?= $pid ?>]" value="<?= $sort ?>" style="width:4.5rem"></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="actions full"><button class="btn" type="submit">Save</button></div>
  </form>
</div>
<?php
wa_render($isEdit ? 'Edit partner' : 'Create partner', ob_get_clean());
