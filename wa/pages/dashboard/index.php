<?php
declare(strict_types=1);

wa_require_perm('dashboard.view');

ob_start();
?>
<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p>Manage website content and access control through Core APIs.</p>
  </div>
</div>
<div class="panel">
  <p>Welcome, <strong><?= wa_e(wa_user()['name'] ?? '') ?></strong>. Use the navigation to manage users, roles, menus, pages, and hero content.</p>
  <div class="actions">
    <?php if (wa_can('users.view')): ?><a class="btn" href="<?= wa_e(wa_url('/users')) ?>">Users</a><?php endif; ?>
    <?php if (wa_can('hero.view')): ?><a class="btn btn-secondary" href="<?= wa_e(wa_url('/hero')) ?>">Hero</a><?php endif; ?>
    <?php if (wa_can('pages.view')): ?><a class="btn btn-ghost" href="<?= wa_e(wa_url('/website-pages')) ?>">Website Pages</a><?php endif; ?>
    <?php if (wa_can('contact_inquiries.view')): ?><a class="btn" href="<?= wa_e(wa_url('/contact-inquiries')) ?>">Contact Inquiries</a><?php endif; ?>
  </div>
</div>
<?php
wa_render('Dashboard', ob_get_clean());
