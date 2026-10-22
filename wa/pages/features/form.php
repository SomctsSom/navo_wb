<?php
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
wa_require_perm($isEdit ? 'features.edit' : 'features.create');

$feature = [
    'title' => '', 'main_title' => '', 'description' => '', 'sort_order' => 0, 'is_active' => 1,
    'pages' => [], 'cards' => [],
];
if ($isEdit) {
    $view = wa_api('GET', '/api/features/view', ['id' => $id]);
    if (!wa_api_ok($view)) {
        wa_flash('error', $view['message'] ?? 'Not found');
        wa_redirect('/features');
    }
    $feature = $view['data'];
}

$pagesRes = wa_api('GET', '/api/pages', ['page_type' => 'website', 'per_page' => 100]);
$pages = wa_api_ok($pagesRes) ? ($pagesRes['data']['items'] ?? []) : [];
$assignedMap = [];
foreach ($feature['pages'] ?? [] as $p) {
    $assignedMap[(int) $p['page_id']] = $p;
}
$listCards = $feature['cards'] ?? [];
if (!$listCards) {
    $listCards = [];
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

    $cardTitles = $_POST['card_title'] ?? [];
    $cardDescs = $_POST['card_description'] ?? [];
    $cardIcons = $_POST['card_icon'] ?? [];
    $cardIds = $_POST['card_id'] ?? [];
    $cardActive = $_POST['card_active'] ?? [];
    $clearIcons = $_POST['clear_card_icon'] ?? [];
    $cardsPayload = [];
    $filesMap = [];
    $payloadIdx = 0;
    if (is_array($cardTitles)) {
        foreach ($cardTitles as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $cardsPayload[] = [
                'id' => (int) ($cardIds[$i] ?? 0),
                'icon_key' => trim((string) ($cardIcons[$i] ?? 'fa fa-star')),
                'title' => $title,
                'description' => trim((string) ($cardDescs[$i] ?? '')),
                'sort_order' => $payloadIdx,
                'is_active' => isset($cardActive[$i]) ? 1 : 0,
                'clear_icon' => isset($clearIcons[$i]) ? 1 : 0,
            ];
            $srcFile = $_FILES['card_icon_' . $i] ?? null;
            if (is_array($srcFile) && (int) ($srcFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $filesMap['card_icon_' . $payloadIdx] = $srcFile;
            }
            $payloadIdx++;
        }
    }

    $fields = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'main_title' => trim((string) ($_POST['main_title'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'pages_json' => json_encode($assignments, JSON_UNESCAPED_UNICODE),
        'cards_json' => json_encode($cardsPayload, JSON_UNESCAPED_UNICODE),
    ];
    if ($isEdit) {
        $fields['id'] = $id;
        $res = wa_api_multipart('/api/features/update', $fields, $filesMap);
    } else {
        $res = wa_api_multipart('/api/features/create', $fields, $filesMap);
    }
    if (wa_api_ok($res)) {
        wa_flash('success', $res['message'] ?? 'Saved');
        wa_redirect('/features');
    }
    $error = (string) ($res['message'] ?? 'Save failed');
    $feature = array_merge($feature, [
        'title' => $fields['title'],
        'main_title' => $fields['main_title'],
        'description' => $fields['description'],
        'sort_order' => (int) $fields['sort_order'],
        'is_active' => (int) $fields['is_active'],
    ]);
    $assignedMap = [];
    foreach ($assignments as $a) {
        $assignedMap[$a['page_id']] = $a;
    }
    // Preserve icon_url from previous list when re-rendering after error
    $prevById = [];
    foreach ($listCards as $prev) {
        if (!empty($prev['id'])) {
            $prevById[(int) $prev['id']] = $prev;
        }
    }
    foreach ($cardsPayload as &$cp) {
        $cid = (int) ($cp['id'] ?? 0);
        if ($cid > 0 && isset($prevById[$cid]['icon_url']) && empty($cp['clear_icon'])) {
            $cp['icon_url'] = $prevById[$cid]['icon_url'];
        }
    }
    unset($cp);
    $listCards = $cardsPayload;
}

ob_start();
?>
<div class="page-head"><div><h1><?= $isEdit ? 'Edit feature section' : 'Create feature section' ?></h1></div>
  <a class="btn btn-ghost" href="<?= wa_e(wa_url('/features')) ?>">Back</a></div>
<?php if ($error): ?><div class="alert alert-error"><?= wa_e($error) ?></div><?php endif; ?>
<div class="panel">
  <form method="post" class="form-grid" id="featureForm" enctype="multipart/form-data">
    <?= wa_csrf_field() ?>
    <label>Title<input name="title" required value="<?= wa_e((string)$feature['title']) ?>" placeholder="Why Choose Navo"></label>
    <label>Sort order<input type="number" name="sort_order" value="<?= (int)$feature['sort_order'] ?>"></label>
    <label class="full">Main title<input name="main_title" maxlength="200" value="<?= wa_e((string)($feature['main_title'] ?? '')) ?>" placeholder="Trusted dispatch support"></label>
    <label class="full">Description<textarea name="description" rows="3" placeholder="We focus on professional communication, organized operations, and dedicated support for transportation businesses."><?= wa_e((string)($feature['description'] ?? '')) ?></textarea></label>
    <label><span>Active</span><input type="checkbox" name="is_active" value="1" <?= (int)$feature['is_active']===1?'checked':'' ?>></label>

    <div class="full">
      <strong>Cards</strong>
      <p class="muted">Use a Font Awesome class (e.g. <code>fa fa-users</code>) and/or upload an image. Uploaded images display instead of the icon on the public site.</p>
      <div id="cardRows" class="page-checks">
        <?php foreach ($listCards as $idx => $card): ?>
          <div class="page-check feature-card-row">
            <input type="hidden" name="card_id[]" value="<?= (int)($card['id'] ?? 0) ?>">
            <div class="feature-card-row-fields">
              <label>Icon class
                <input name="card_icon[]" value="<?= wa_e((string)($card['icon_key'] ?? 'fa fa-star')) ?>" placeholder="fa fa-users" autocomplete="off">
              </label>
              <label>Card title<input name="card_title[]" value="<?= wa_e((string)$card['title']) ?>" placeholder="Professionalism"></label>
              <label>Card description<input name="card_description[]" value="<?= wa_e((string)($card['description'] ?? '')) ?>" placeholder="We represent your operation…"></label>
            </div>
            <div class="feature-card-row-meta">
              <label class="feature-card-image-field">Icon image
                <?php if (!empty($card['icon_url'])): ?>
                  <div>
                    <img src="<?= wa_e((string)$card['icon_url']) ?>" alt="" style="width:36px;height:36px;object-fit:contain;border-radius:6px;background:#1f2937;padding:4px">
                  </div>
                  <span style="display:flex;align-items:center;gap:0.35rem;font-weight:400">
                    <input type="checkbox" name="clear_card_icon[<?= (int)$idx ?>]" value="1"> Remove image
                  </span>
                <?php endif; ?>
                <input type="file" name="card_icon_<?= (int)$idx ?>" accept="image/jpeg,image/png,image/webp,image/gif">
              </label>
              <label class="feature-card-active">Active <input type="checkbox" name="card_active[<?= (int)$idx ?>]" value="1" <?= ((int)($card['is_active'] ?? 1)===1)?'checked':'' ?>></label>
              <button type="button" class="btn btn-ghost remove-card" style="padding:0.3rem 0.6rem">Remove</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-secondary" id="addCardBtn" style="margin-top:0.6rem">Add card</button>
    </div>

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
<template id="cardRowTpl">
  <div class="page-check feature-card-row">
    <input type="hidden" name="card_id[]" value="0">
    <div class="feature-card-row-fields">
      <label>Icon class
        <input name="card_icon[]" value="fa fa-users" placeholder="fa fa-users" autocomplete="off">
      </label>
      <label>Card title<input name="card_title[]" value="" placeholder="Professionalism"></label>
      <label>Card description<input name="card_description[]" value="" placeholder="We represent your operation…"></label>
    </div>
    <div class="feature-card-row-meta">
      <label class="feature-card-image-field">Icon image
        <input type="file" name="card_icon___IDX__" accept="image/jpeg,image/png,image/webp,image/gif">
      </label>
      <label class="feature-card-active">Active <input type="checkbox" name="card_active[__IDX__]" value="1" checked></label>
      <button type="button" class="btn btn-ghost remove-card" style="padding:0.3rem 0.6rem">Remove</button>
    </div>
  </div>
</template>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const rows = document.getElementById('cardRows');
  const tpl = document.getElementById('cardRowTpl');
  const btn = document.getElementById('addCardBtn');
  let idx = <?= (int) count($listCards) ?>;
  btn?.addEventListener('click', () => {
    const html = tpl.innerHTML.replaceAll('__IDX__', String(idx++));
    rows.insertAdjacentHTML('beforeend', html);
  });
  rows?.addEventListener('click', (e) => {
    const t = e.target;
    if (t && t.classList && t.classList.contains('remove-card')) {
      t.closest('.feature-card-row')?.remove();
    }
  });
});
</script>
<?php
wa_render($isEdit ? 'Edit feature section' : 'Create feature section', ob_get_clean());
