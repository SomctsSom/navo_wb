<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
wa_require_perm($isEdit ? 'hero.edit' : 'hero.create');

$hero = [
    'title' => '', 'subtitle' => '', 'description' => '', 'button_text' => '', 'button_url' => '',
    'sort_order' => 0, 'is_active' => 1, 'pages' => [], 'image_url' => null, 'buttons' => [],
];
if ($isEdit) {
    $view = wa_api('GET', '/api/hero/view', ['id' => $id]);
    if (!wa_api_ok($view)) {
        wa_flash('error', $view['message'] ?? 'Not found');
        wa_redirect('/hero');
    }
    $hero = $view['data'];
}

$pagesRes = wa_api('GET', '/api/pages', ['page_type' => 'website', 'per_page' => 100, 'q' => (string) ($_GET['page_q'] ?? '')]);
$pages = wa_api_ok($pagesRes) ? ($pagesRes['data']['items'] ?? []) : [];
$assignedMap = [];
foreach ($hero['pages'] ?? [] as $p) {
    $assignedMap[(int) $p['page_id']] = $p;
}

$buttonRows = [];
foreach ($hero['buttons'] ?? [] as $button) {
    $label = trim((string) ($button['label'] ?? ''));
    if ($label === '') {
        continue;
    }
    $buttonRows[] = [
        'id' => (int) ($button['id'] ?? 0),
        'label' => $label,
        'url' => trim((string) ($button['url'] ?? '')),
    ];
}
if (!$buttonRows) {
    $buttonRows[] = ['id' => 0, 'label' => '', 'url' => ''];
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
            'is_featured' => 0,
            'sort_order' => (int) ($_POST['page_sort'][$pid] ?? 0),
        ];
    }
    $postedLabels = $_POST['button_label'] ?? [];
    $postedUrls = $_POST['button_link'] ?? [];
    $postedIds = $_POST['button_id'] ?? [];
    $buttonsPayload = [];
    $buttonRows = [];
    if (is_array($postedLabels)) {
        foreach ($postedLabels as $i => $label) {
            $label = trim((string) $label);
            $url = trim((string) ($postedUrls[$i] ?? ''));
            $buttonId = (int) ($postedIds[$i] ?? 0);
            $buttonRows[] = ['id' => $buttonId, 'label' => $label, 'url' => $url];
            if ($label === '' && $url === '') {
                continue;
            }
            $buttonsPayload[] = [
                'id' => $buttonId,
                'label' => $label,
                'url' => $url,
                'sort_order' => count($buttonsPayload),
                'is_active' => 1,
            ];
        }
    }
    if (!$buttonRows) {
        $buttonRows[] = ['id' => 0, 'label' => '', 'url' => ''];
    }
    $primaryButton = $buttonsPayload[0] ?? ['label' => '', 'url' => ''];
    $fields = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'subtitle' => trim((string) ($_POST['subtitle'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'button_text' => (string) ($primaryButton['label'] ?? ''),
        'button_url' => (string) ($primaryButton['url'] ?? ''),
        'sort_order' => (string) ((int) ($_POST['sort_order'] ?? 0)),
        'is_active' => isset($_POST['is_active']) ? '1' : '0',
        'pages_json' => json_encode($assignments, JSON_UNESCAPED_UNICODE),
        'buttons_json' => json_encode($buttonsPayload, JSON_UNESCAPED_UNICODE),
    ];
    if ($isEdit) {
        $fields['id'] = (string) $id;
        $res = wa_api_multipart('/api/hero/update', $fields, $_FILES['image'] ?? []);
    } else {
        $res = wa_api_multipart('/api/hero/create', $fields, $_FILES['image'] ?? []);
    }
    if (wa_api_ok($res)) {
        wa_flash('success', $res['message'] ?? 'Saved');
        wa_redirect('/hero');
    }
    $error = (string) ($res['message'] ?? 'Save failed');
    $hero = array_merge($hero, [
        'title' => $fields['title'],
        'subtitle' => $fields['subtitle'],
        'description' => $fields['description'],
        'button_text' => $fields['button_text'],
        'button_url' => $fields['button_url'],
        'buttons' => $buttonsPayload,
        'sort_order' => (int) $fields['sort_order'],
        'is_active' => (int) $fields['is_active'],
    ]);
    $assignedMap = [];
    foreach ($assignments as $a) {
        $assignedMap[$a['page_id']] = $a;
    }
}

ob_start();
?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit hero' : 'Create hero' ?></h1></div>
  <a class="btn btn-ghost" href="<?= wa_e(wa_url('/hero')) ?>">Back</a></div>
<?php if ($error): ?><div class="alert alert-error"><?= wa_e($error) ?></div><?php endif; ?>
<div class="panel">
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= wa_csrf_field() ?>
    <label>Title<input name="title" required value="<?= wa_e((string)$hero['title']) ?>"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int)$hero['sort_order'] ?>"></label>
    <label class="full">Subtitle<input name="subtitle" maxlength="200" value="<?= wa_e((string)($hero['subtitle'] ?? '')) ?>" placeholder="Shown above the title"></label>
    <label class="full">Description<textarea name="description" rows="3"><?= wa_e((string)($hero['description'] ?? '')) ?></textarea></label>
    <div class="full">
      <strong>Buttons</strong>
      <p class="muted">Add one button, or several. Each button uses the text and URL saved for this hero. Leave the URL blank to show the label only.</p>
      <div id="buttonRows" class="page-checks">
        <?php foreach ($buttonRows as $button): ?>
          <div class="page-check hero-button-row" style="grid-template-columns:1fr 1fr auto">
            <input type="hidden" name="button_id[]" value="<?= (int) ($button['id'] ?? 0) ?>">
            <label>Button text<input name="button_label[]" value="<?= wa_e((string) ($button['label'] ?? '')) ?>" maxlength="100"></label>
            <label>Button URL<input name="button_link[]" value="<?= wa_e((string) ($button['url'] ?? '')) ?>" maxlength="255" placeholder="/about or https://..."></label>
            <button type="button" class="btn btn-ghost remove-button" style="padding:0.3rem 0.6rem">Remove</button>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-secondary" id="addButtonBtn" style="margin-top:0.6rem">Add button</button>
    </div>
    <label>Image<?= $isEdit ? ' (optional replace)' : '' ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" <?= $isEdit ? '' : 'required' ?>>
    </label>
    <label><span>Active</span><input type="checkbox" name="is_active" value="1" <?= (int)$hero['is_active']===1?'checked':'' ?>></label>
    <?php if (!empty($hero['image_url'])): ?>
      <div class="full"><img class="thumb" style="width:180px;height:100px" src="<?= wa_e($hero['image_url']) ?>" alt=""></div>
    <?php endif; ?>
    <div class="full">
      <strong>Website pages</strong>
      <p class="muted">Select the pages that show this hero, then set Active and order for each page.</p>
      <div class="page-checks">
        <?php foreach ($pages as $p):
            $pid = (int) $p['id'];
            $a = $assignedMap[$pid] ?? null;
            $checked = $a !== null;
            $active = $a ? (int)($a['is_active'] ?? 1) === 1 : true;
            $sort = $a ? (int)($a['sort_order'] ?? 0) : 0;
        ?>
          <div class="page-check">
            <label><input type="checkbox" name="page_selected[]" value="<?= $pid ?>" <?= $checked ? 'checked' : '' ?>> <?= wa_e($p['title']) ?> <span class="muted">/<?= wa_e($p['slug']) ?></span></label>
            <label>Active <input type="checkbox" name="page_active[<?= $pid ?>]" value="1" <?= $active ? 'checked' : '' ?>></label>
            <label>Order <input type="number" name="page_sort[<?= $pid ?>]" value="<?= $sort ?>" style="width:4.5rem"></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="actions full"><button class="btn" type="submit">Save</button></div>
  </form>
</div>
<template id="buttonRowTpl">
  <div class="page-check hero-button-row" style="grid-template-columns:1fr 1fr auto">
    <input type="hidden" name="button_id[]" value="0">
    <label>Button text<input name="button_label[]" value="" maxlength="100"></label>
    <label>Button URL<input name="button_link[]" value="" maxlength="255" placeholder="/about or https://"></label>
    <button type="button" class="btn btn-ghost remove-button" style="padding:0.3rem 0.6rem">Remove</button>
  </div>
</template>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const rows = document.getElementById('buttonRows');
  const tpl = document.getElementById('buttonRowTpl');
  const btn = document.getElementById('addButtonBtn');
  btn?.addEventListener('click', () => {
    if (!rows || !tpl) return;
    rows.insertAdjacentHTML('beforeend', tpl.innerHTML);
  });
  rows?.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element) || !target.classList.contains('remove-button')) {
      return;
    }
    target.closest('.hero-button-row')?.remove();
  });
});
</script>
<?php
wa_render($isEdit ? 'Edit hero' : 'Create hero', ob_get_clean());
