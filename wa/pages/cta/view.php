<?php
declare(strict_types=1);

wa_require_perm('cta_banners.view');
$id = (int) ($_GET['id'] ?? 0);
$res = wa_api('GET', '/api/cta-banners/view', ['id' => $id]);
if (!wa_api_ok($res)) {
    wa_flash('error', $res['message'] ?? 'Not found');
    wa_redirect('/cta');
}
$p = $res['data'];
ob_start();
?>
<div class="page-head"><div><h1><?= wa_e((string)$p['title']) ?></h1></div>
  <div class="actions">
    <?php if (wa_can('cta_banners.edit')): ?><a class="btn" href="<?= wa_e(wa_url('/cta/edit?id='.$p['id'])) ?>">Edit</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= wa_e(wa_url('/cta')) ?>">Back</a>
  </div>
</div>
<div class="panel">
  <p>Status: <?= wa_badge((int)$p['is_active']) ?></p>
  <?php if (!empty($p['eyebrow'])): ?>
    <p class="muted"><?= wa_e((string)$p['eyebrow']) ?></p>
  <?php endif; ?>
  <h2><?= wa_e((string)$p['title']) ?></h2>
  <?php if (!empty($p['subtitle'])): ?>
    <p><?= wa_e((string)$p['subtitle']) ?></p>
  <?php endif; ?>
  <p>
    <?php if (!empty($p['primary_button_text'])): ?>
      <strong><?= wa_e((string)$p['primary_button_text']) ?></strong>
      <?php if (!empty($p['primary_button_url'])): ?>
        → <span class="muted"><?= wa_e((string)$p['primary_button_url']) ?></span>
      <?php endif; ?>
    <?php endif; ?>
  </p>
  <p>
    <?php if (!empty($p['secondary_button_text'])): ?>
      <strong><?= wa_e((string)$p['secondary_button_text']) ?></strong>
      <?php if (!empty($p['secondary_button_url'])): ?>
        → <span class="muted"><?= wa_e((string)$p['secondary_button_url']) ?></span>
      <?php endif; ?>
    <?php endif; ?>
  </p>
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
wa_render('View CTA', ob_get_clean());
