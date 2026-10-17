<?php
declare(strict_types=1);

wa_require_perm('contact_inquiries.view');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && wa_can('contact_inquiries.delete')) {
        $res = wa_api('POST', '/api/contact-inquiries/delete', ['id' => $id]);
        wa_flash(wa_api_ok($res) ? 'success' : 'error', (string) ($res['message'] ?? 'Done'));
    }
    wa_redirect('/contact-inquiries?' . http_build_query(array_filter([
        'q' => $_GET['q'] ?? null,
        'status' => $_GET['status'] ?? null,
        'page' => $_GET['page'] ?? null,
    ], static fn ($v) => $v !== null && $v !== '')));
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$res = wa_api('GET', '/api/contact-inquiries', [
    'q' => $q,
    'status' => $status,
    'page' => $page,
    'per_page' => 20,
]);
$items = wa_api_ok($res) ? ($res['data']['items'] ?? []) : [];
$pagination = wa_api_ok($res) ? ($res['data']['pagination'] ?? []) : [];
$statuses = wa_api_ok($res) ? ($res['data']['statuses'] ?? []) : [];
$total = (int) ($pagination['total'] ?? count($items));

ob_start();
?>
<div class="page-head">
  <div>
    <h1>Contact Inquiries</h1>
    <p>Messages submitted from the public Contact Us form<?= $total > 0 ? ' · ' . $total . ' total' : '' ?>.</p>
  </div>
  <div class="actions" style="margin-top:0">
    <?php if (wa_can('contact_form.view')): ?>
      <a class="btn btn-ghost" href="<?= wa_e(wa_url('/contact-form')) ?>">Form settings</a>
    <?php endif; ?>
  </div>
</div>
<div class="panel">
  <form class="toolbar inquiry-toolbar" method="get">
    <input type="search" name="q" value="<?= wa_e($q) ?>" placeholder="Search name, email, phone…">
    <select name="status">
      <option value="">Status: any</option>
      <?php foreach ($statuses as $st): ?>
        <option value="<?= wa_e((string) $st['value']) ?>" <?= $status === (string) $st['value'] ? 'selected' : '' ?>><?= wa_e((string) $st['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary" type="submit">Filter</button>
  </form>

  <table class="data inquiry-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Service</th>
        <th>Status</th>
        <th>Received</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr>
          <td colspan="7" class="inquiry-empty">No inquiries found.</td>
        </tr>
      <?php endif; ?>
      <?php foreach ($items as $row): ?>
        <?php
          $stCode = strtolower(trim((string) ($row['status'] ?? 'new')));
          $stLabel = (string) ($row['status_label'] ?? $row['status'] ?? 'New');
          $received = (string) ($row['created_at_display'] ?? $row['created_at'] ?? '');
        ?>
        <tr>
          <td>
            <strong><?= wa_e((string) ($row['full_name'] ?? '—')) ?></strong>
            <?php if (!empty($row['company_name'])): ?>
              <div class="muted"><?= wa_e((string) $row['company_name']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= wa_e((string) ($row['email'] ?? '—')) ?></td>
          <td><?= wa_e((string) ($row['phone'] ?? '—')) ?></td>
          <td><?= wa_e((string) ($row['preferred_service_title'] ?? '—')) ?></td>
          <td><span class="inquiry-status inquiry-status-<?= wa_e($stCode) ?>"><?= wa_e($stLabel) ?></span></td>
          <td class="muted"><?= wa_e($received) ?></td>
          <td>
            <div class="inquiry-actions">
              <a class="btn btn-ghost btn-sm" href="<?= wa_e(wa_url('/contact-inquiries/view?id=' . (int) $row['id'])) ?>">View</a>
              <?php if (wa_can('contact_inquiries.delete')): ?>
                <form class="confirm-form" method="post">
                  <?= wa_csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit" data-confirm="Delete this inquiry?">Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= wa_pagination($pagination, '/contact-inquiries', ['q' => $q, 'status' => $status]) ?>
</div>
<?php
wa_render('Contact Inquiries', ob_get_clean());
