<?php
declare(strict_types=1);

wa_require_perm('partners.view');
$id = (int) ($_GET['id'] ?? 0);
$res = wa_api('GET', '/api/partners/view', ['id' => $id]);
if (!wa_api_ok($res)) {
    wa_flash('error', $res['message'] ?? 'Not found');
    wa_redirect('/partners');
}
$p = $res['data'];
ob_start();
?>
<div class="page-head"><div><h1><?= wa_e((string)$p['name']) ?></h1></div>
  <div class="actions">
    <?php if (wa_can('partners.edit')): ?><a class="btn" href="<?= wa_e(wa_url('/partners/edit?id='.$p['id'])) ?>">Edit</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= wa_e(wa_url('/partners')) ?>">Back</a>
  </div>
</div>
<div class="panel">
  <p>Status: <?= wa_badge((int)$p['is_active']) ?></p>
  <p>
    <span style="display:inline-flex;width:48px;height:48px;align-items:center;justify-content:center;background:#f3f4f6;border-radius:10px;color:#e0893a">
      <?php if (!empty($p['icon_url'])): ?>
        <img src="<?= wa_e((string)$p['icon_url']) ?>" alt="" style="width:32px;height:32px;object-fit:contain">
      <?php else: ?>
        <?= (string)($p['icon_html'] ?? '') ?>
      <?php endif; ?>
    </span>
    <?php if (empty($p['icon_url']) && !empty($p['icon_class'])): ?>
      <span class="muted" style="margin-left:0.5rem"><?= wa_e((string)$p['icon_class']) ?></span>
    <?php elseif (!empty($p['icon_url'])): ?>
      <span class="muted" style="margin-left:0.5rem">Uploaded image</span>
    <?php endif; ?>
  </p>
  <?php if (!empty($p['section_title'])): ?><p class="muted">Section: <?= wa_e((string)$p['section_title']) ?></p><?php endif; ?>
  <?php if (!empty($p['section_subtitle'])): ?><p class="muted"><?= wa_e((string)$p['section_subtitle']) ?></p><?php endif; ?>
  <?php if (!empty($p['badge_text'])): ?><p><span class="muted"><?= wa_e((string)$p['badge_text']) ?></span></p><?php endif; ?>
  <?php if (!empty($p['category'])): ?><p><?= wa_e((string)$p['category']) ?></p><?php endif; ?>
  <?php if (!empty($p['description'])): ?><p><?= wa_e((string)$p['description']) ?></p><?php endif; ?>
  <?php if (!empty($p['useful_for_items'])): ?>
    <h3>Useful for</h3>
    <ul><?php foreach ($p['useful_for_items'] as $line): ?><li><?= wa_e((string)$line) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if (!empty($p['button_text'])): ?>
    <p><strong><?= wa_e((string)$p['button_text']) ?></strong>
      <?php if (!empty($p['button_url'])): ?> → <span class="muted"><?= wa_e((string)$p['button_url']) ?></span><?php endif; ?>
    </p>
  <?php endif; ?>
  <h3>Assigned pages</h3>
  <table class="data">
    <thead><tr><th>Page</th><th>Active</th><th>Featured</th><th>Order</th></tr></thead>
    <tbody>
    <?php foreach ($p['pages'] ?? [] as $row): ?>
      <tr>
        <td><?= wa_e($row['title']) ?></td>
        <td><?= wa_badge((int)$row['is_active']) ?></td>
        <td><?= wa_badge((int)$row['is_featured'], 'Featured', 'No') ?></td>
        <td><?= (int)$row['sort_order'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
wa_render('View partner', ob_get_clean());
