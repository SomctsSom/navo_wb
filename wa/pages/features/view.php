<?php
declare(strict_types=1);

wa_require_perm('features.view');
$id = (int) ($_GET['id'] ?? 0);
$res = wa_api('GET', '/api/features/view', ['id' => $id]);
if (!wa_api_ok($res)) {
    wa_flash('error', $res['message'] ?? 'Not found');
    wa_redirect('/features');
}
$p = $res['data'];
ob_start();
?>
<div class="page-head"><div><h1><?= wa_e($p['title']) ?></h1></div>
  <div class="actions">
    <?php if (wa_can('features.edit')): ?><a class="btn" href="<?= wa_e(wa_url('/features/edit?id='.$p['id'])) ?>">Edit</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= wa_e(wa_url('/features')) ?>">Back</a>
  </div>
</div>
<div class="panel">
  <p>Status: <?= wa_badge((int)$p['is_active']) ?></p>
  <?php if (!empty($p['main_title'])): ?>
    <p><strong><?= wa_e((string)$p['main_title']) ?></strong></p>
  <?php endif; ?>
  <?php if (!empty($p['description'])): ?>
    <p class="muted"><?= wa_e((string)$p['description']) ?></p>
  <?php endif; ?>
  <h3>Cards (<?= count($p['cards'] ?? []) ?>)</h3>
  <table class="data">
    <thead><tr><th>Icon</th><th>Title</th><th>Description</th><th>Active</th></tr></thead>
    <tbody>
    <?php foreach ($p['cards'] ?? [] as $card): ?>
      <tr>
        <td>
          <span style="display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;background:#1f2937;border-radius:8px;color:#f97316">
            <?= (string)($card['icon_html'] ?? '') ?>
          </span>
          <div class="muted" style="font-size:0.8rem;margin-top:0.25rem"><?= wa_e((string)($card['icon_key'] ?? '')) ?></div>
        </td>
        <td><?= wa_e($card['title']) ?></td>
        <td><?= wa_e((string)($card['description'] ?? '')) ?></td>
        <td><?= wa_badge((int)$card['is_active']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
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
wa_render('View feature section', ob_get_clean());
