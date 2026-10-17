<?php
declare(strict_types=1);

wa_require_perm('hero.view');
$id = (int) ($_GET['id'] ?? 0);
$res = wa_api('GET', '/api/hero/view', ['id' => $id]);
if (!wa_api_ok($res)) {
    wa_flash('error', $res['message'] ?? 'Not found');
    wa_redirect('/hero');
}
$h = $res['data'];
ob_start();
?>
<div class="page-head"><div><h1><?= wa_e($h['title']) ?></h1></div>
  <div class="actions">
    <?php if (wa_can('hero.edit')): ?><a class="btn" href="<?= wa_e(wa_url('/hero/edit?id='.$h['id'])) ?>">Edit</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= wa_e(wa_url('/hero')) ?>">Back</a>
  </div>
</div>
<div class="panel">
  <?php if (!empty($h['image_url'])): ?><p><img src="<?= wa_e($h['image_url']) ?>" alt="" style="max-width:100%;border-radius:12px"></p><?php endif; ?>
  <?php if (trim((string) ($h['subtitle'] ?? '')) !== ''): ?><p><?= wa_e((string) $h['subtitle']) ?></p><?php endif; ?>
  <p><?= wa_e((string)$h['description']) ?></p>
  <p>Status: <?= wa_badge((int)$h['is_active']) ?></p>
  <h3>Buttons</h3>
  <?php $heroButtons = $h['buttons'] ?? []; ?>
  <?php if (!$heroButtons): ?>
    <p class="muted">No buttons.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($heroButtons as $button): ?>
        <li><?= wa_e((string) ($button['label'] ?? '')) ?><?php if (trim((string) ($button['url'] ?? '')) !== ''): ?> → <?= wa_e((string) $button['url']) ?><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <h3>Assigned pages</h3>
  <table class="data">
    <thead><tr><th>Page</th><th>Active</th><th>Order</th></tr></thead>
    <tbody>
    <?php foreach ($h['pages'] ?? [] as $p): ?>
      <tr>
        <td><?= wa_e($p['title']) ?></td>
        <td><?= wa_badge((int)$p['is_active']) ?></td>
        <td><?= (int)$p['sort_order'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
wa_render('View hero', ob_get_clean());
