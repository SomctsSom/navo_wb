<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
wa_require_perm($isEdit ? 'services.edit' : 'services.create');

$service = [
    'title' => '', 'description' => '', 'icon_class' => 'fa-truck', 'button_text' => '', 'button_url' => '',
    'sort_order' => 0, 'is_active' => 1, 'pages' => [], 'items' => [], 'image_url' => null,
];
if ($isEdit) {
    $view = wa_api('GET', '/api/services/view', ['id' => $id]);
    if (!wa_api_ok($view)) {
        wa_flash('error', $view['message'] ?? 'Not found');
        wa_redirect('/services');
    }
    $service = $view['data'];
}

$pagesRes = wa_api('GET', '/api/pages', ['page_type' => 'website', 'per_page' => 100]);
$pages = wa_api_ok($pagesRes) ? ($pagesRes['data']['items'] ?? []) : [];
$assignedMap = [];
foreach ($service['pages'] ?? [] as $p) {
    $assignedMap[(int) $p['page_id']] = $p;
}
$listItems = $service['items'] ?? [];
if (!$listItems) {
    $listItems = [];
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

    $itemTitles = $_POST['item_title'] ?? [];
    $itemBodies = $_POST['item_body'] ?? [];
    $itemIds = $_POST['item_id'] ?? [];
    $itemActive = $_POST['item_active'] ?? [];
    $itemsPayload = [];
    if (is_array($itemTitles)) {
        foreach ($itemTitles as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $itemsPayload[] = [
                'id' => (int) ($itemIds[$i] ?? 0),
                'title' => $title,
                'body' => trim((string) ($itemBodies[$i] ?? '')),
                'sort_order' => (int) $i,
                'is_active' => isset($itemActive[$i]) ? 1 : 0,
            ];
        }
    }

    $fields = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'icon_class' => trim((string) ($_POST['icon_class'] ?? 'fa-truck')),
        'button_text' => trim((string) ($_POST['button_text'] ?? '')),
        'button_url' => trim((string) ($_POST['button_url'] ?? '')),
        'sort_order' => (string) ((int) ($_POST['sort_order'] ?? 0)),
        'is_active' => isset($_POST['is_active']) ? '1' : '0',
        'pages_json' => json_encode($assignments, JSON_UNESCAPED_UNICODE),
        'items_json' => json_encode($itemsPayload, JSON_UNESCAPED_UNICODE),
    ];
    if ($isEdit) {
        $fields['id'] = (string) $id;
        $res = wa_api_multipart('/api/services/update', $fields, $_FILES['image'] ?? []);
    } else {
        $res = wa_api_multipart('/api/services/create', $fields, $_FILES['image'] ?? []);
    }
    if (wa_api_ok($res)) {
        wa_flash('success', $res['message'] ?? 'Saved');
        wa_redirect('/services');
    }
    $error = (string) ($res['message'] ?? 'Save failed');
    $service = array_merge($service, [
        'title' => $fields['title'],
        'description' => $fields['description'],
        'icon_class' => $fields['icon_class'],
        'button_text' => $fields['button_text'],
        'button_url' => $fields['button_url'],
        'sort_order' => (int) $fields['sort_order'],
        'is_active' => (int) $fields['is_active'],
    ]);
    $assignedMap = [];
    foreach ($assignments as $a) {
        $assignedMap[$a['page_id']] = $a;
    }
    $listItems = $itemsPayload;
}

ob_start();
?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit service' : 'Create service' ?></h1></div>
  <a class="btn btn-ghost" href="<?= wa_e(wa_url('/services')) ?>">Back</a></div>
<?php if ($error): ?><div class="alert alert-error"><?= wa_e($error) ?></div><?php endif; ?>
<div class="panel">
  <form method="post" enctype="multipart/form-data" class="form-grid" id="serviceForm">
    <?= wa_csrf_field() ?>
    <label>Title<input name="title" required value="<?= wa_e((string)$service['title']) ?>"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int)$service['sort_order'] ?>"></label>
    <label class="full">Description<textarea name="description" rows="3"><?= wa_e((string)($service['description'] ?? '')) ?></textarea></label>
    <label>Icon class
      <input name="icon_class" value="<?= wa_e((string)($service['icon_class'] ?? 'fa-truck')) ?>" placeholder="fa-truck" maxlength="100">
      <span class="muted" style="display:block;margin-top:0.35rem">Font Awesome class, e.g. <code>fa-truck</code> or <code>fas fa-ambulance</code>.</span>
    </label>
    <label>Button text<input name="button_text" value="<?= wa_e((string)($service['button_text'] ?? '')) ?>"></label>
    <label>Button URL<input name="button_url" value="<?= wa_e((string)($service['button_url'] ?? '')) ?>"></label>
    <label>Image (optional)
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
    </label>
    <label><span>Active</span><input type="checkbox" name="is_active" value="1" <?= (int)$service['is_active']===1?'checked':'' ?>></label>
    <?php if (!empty($service['image_url'])): ?>
      <div class="full"><img class="thumb" style="width:180px;height:100px" src="<?= wa_e($service['image_url']) ?>" alt=""></div>
    <?php endif; ?>

    <div class="full">
      <strong>Optional list under description</strong>
      <p class="muted">Leave empty if this service has no bullet list.</p>
      <div id="itemRows" class="page-checks">
        <?php
        if (!$listItems) {
            // no default empty row required — optional
        }
        foreach ($listItems as $idx => $item):
        ?>
          <div class="page-check service-item-row" style="grid-template-columns:1fr 1fr auto auto">
            <input type="hidden" name="item_id[]" value="<?= (int)($item['id'] ?? 0) ?>">
            <label>Item title<input name="item_title[]" value="<?= wa_e((string)$item['title']) ?>"></label>
            <label>Body (optional)<input name="item_body[]" value="<?= wa_e((string)($item['body'] ?? '')) ?>"></label>
            <label>Active <input type="checkbox" name="item_active[<?= (int)$idx ?>]" value="1" <?= ((int)($item['is_active'] ?? 1)===1)?'checked':'' ?>></label>
            <button type="button" class="btn btn-ghost remove-item" style="padding:0.3rem 0.6rem">Remove</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-secondary" id="addItemBtn" style="margin-top:0.6rem">Add list item</button>
    </div>

    <div class="full">
      <strong>Website pages</strong>
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
            <label><input type="checkbox" name="page_selected[]" value="<?= $pid ?>" <?= $checked ? 'checked' : '' ?>> <?= wa_e($p['title']) ?></label>
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
<template id="itemRowTpl">
  <div class="page-check service-item-row" style="grid-template-columns:1fr 1fr auto auto">
    <input type="hidden" name="item_id[]" value="0">
    <label>Item title<input name="item_title[]" value=""></label>
    <label>Body (optional)<input name="item_body[]" value=""></label>
    <label>Active <input type="checkbox" name="item_active[__IDX__]" value="1" checked></label>
    <button type="button" class="btn btn-ghost remove-item" style="padding:0.3rem 0.6rem">Remove</button>
  </div>
</template>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const rows = document.getElementById('itemRows');
  const tpl = document.getElementById('itemRowTpl');
  const btn = document.getElementById('addItemBtn');
  let idx = <?= (int) count($listItems) ?>;
  btn?.addEventListener('click', () => {
    const html = tpl.innerHTML.replaceAll('__IDX__', String(idx++));
    rows.insertAdjacentHTML('beforeend', html);
  });
  rows?.addEventListener('click', (e) => {
    const t = e.target;
    if (t && t.classList && t.classList.contains('remove-item')) {
      t.closest('.service-item-row')?.remove();
    }
  });
});
</script>
<?php
wa_render($isEdit ? 'Edit service' : 'Create service', ob_get_clean());
