<?php
declare(strict_types=1);

wa_require_perm('contact_inquiries.view');
$id = (int) ($_GET['id'] ?? 0);
$res = wa_api('GET', '/api/contact-inquiries/view', ['id' => $id]);
if (!wa_api_ok($res)) {
    wa_flash('error', $res['message'] ?? 'Not found');
    wa_redirect('/contact-inquiries');
}
$row = $res['data'];
$error = '';
$replyDraft = (string) ($row['reply_message'] ?? '');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && wa_can('contact_inquiries.edit')) {
    $status = (string) ($_POST['status'] ?? '');
    $replyDraft = (string) ($_POST['reply_message'] ?? '');
    $save = wa_api('POST', '/api/contact-inquiries/update', [
        'id' => $id,
        'status' => $status,
        'reply_message' => $replyDraft,
    ]);
    if (wa_api_ok($save)) {
        wa_flash('success', $save['message'] ?? 'Saved');
        wa_redirect('/contact-inquiries/view?id=' . $id);
    }
    $error = (string) ($save['message'] ?? 'Save failed');
    $row['status'] = $status;
    $row['reply_message'] = $replyDraft;
}

$currentStatus = strtolower(trim((string) ($res['data']['status'] ?? 'new')));
$stCode = $currentStatus;
$statusLabels = [
    'new' => 'New',
    'in_progress' => 'In progress',
    'replied' => 'Replied',
    'closed' => 'Closed',
];
$stLabel = (string) ($res['data']['status_label'] ?? ($statusLabels[$currentStatus] ?? $currentStatus));
$savedReply = trim((string) ($row['reply_message'] ?? ''));
$repliedAt = (string) ($row['replied_at_display'] ?? $row['replied_at'] ?? '');

$nextMap = ['new' => 'in_progress', 'in_progress' => 'replied', 'replied' => 'closed'];
$nextStatus = (string) ($row['next_status'] ?? '');
$nextLabel = (string) ($row['next_status_label'] ?? '');
if ($nextStatus === '') {
    $nextStatus = $nextMap[$currentStatus] ?? '';
    $nextLabel = $statusLabels[$nextStatus] ?? $nextStatus;
}
if ($error !== '' && isset($_POST['status'])) {
    $attempt = (string) $_POST['status'];
    if (isset($statusLabels[$attempt])) {
        $nextStatus = $attempt;
        $nextLabel = $statusLabels[$attempt];
    }
}

ob_start();
?>
<div class="page-head">
  <div>
    <h1>Inquiry #<?= (int) $row['id'] ?></h1>
    <p>
      Received <?= wa_e((string) ($row['created_at_display'] ?? $row['created_at'] ?? '')) ?>
      · <span class="inquiry-status inquiry-status-<?= wa_e($stCode) ?>"><?= wa_e($stLabel) ?></span>
    </p>
  </div>
  <div class="actions" style="margin-top:0">
    <a class="btn btn-ghost" href="<?= wa_e(wa_url('/contact-inquiries')) ?>">Back to list</a>
  </div>
</div>
<?php if ($error): ?><div class="alert alert-error"><?= wa_e($error) ?></div><?php endif; ?>

<div class="inquiry-detail">
  <div class="panel inquiry-detail-main">
    <h2 class="inquiry-section-title">Contact details</h2>
    <dl class="inquiry-dl">
      <div><dt>Full name</dt><dd><?= wa_e((string) ($row['full_name'] ?? '—')) ?></dd></div>
      <div><dt>Company</dt><dd><?= wa_e((string) (($row['company_name'] ?? '') !== '' ? $row['company_name'] : '—')) ?></dd></div>
      <div><dt>Phone</dt><dd><?= wa_e((string) ($row['phone'] ?? '—')) ?></dd></div>
      <div><dt>Email</dt><dd>
        <?php if (!empty($row['email'])): ?>
          <a href="mailto:<?= wa_e((string) $row['email']) ?>"><?= wa_e((string) $row['email']) ?></a>
        <?php else: ?>
          —
        <?php endif; ?>
      </dd></div>
      <div><dt>Preferred service</dt><dd><?= wa_e((string) (($row['preferred_service_title'] ?? '') !== '' ? $row['preferred_service_title'] : '—')) ?></dd></div>
      <?php foreach (($row['custom_fields'] ?? []) as $cf): ?>
        <?php
          $cfVal = $cf['value'] ?? null;
          $cfDisplay = ($cfVal === null || $cfVal === '') ? '—' : (string) $cfVal;
        ?>
        <div<?= ((string) ($cf['field_type'] ?? '')) === 'textarea' ? ' class="inquiry-dl-full"' : '' ?>>
          <dt><?= wa_e((string) ($cf['label'] ?? $cf['field_key'] ?? 'Field')) ?></dt>
          <dd><?= ((string) ($cf['field_type'] ?? '')) === 'textarea' ? nl2br(wa_e($cfDisplay)) : wa_e($cfDisplay) ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>

    <h2 class="inquiry-section-title">Message</h2>
    <div class="inquiry-message"><?= nl2br(wa_e((string) ($row['message'] ?? ''))) ?></div>

    <?php if ($savedReply !== ''): ?>
      <h2 class="inquiry-section-title" style="margin-top:1.4rem">Reply message<?= $repliedAt !== '' ? ' · ' . wa_e($repliedAt) : '' ?></h2>
      <div class="inquiry-message inquiry-reply"><?= nl2br(wa_e($savedReply)) ?></div>
    <?php endif; ?>
  </div>

  <div class="panel inquiry-detail-side">
    <h2 class="inquiry-section-title">Manage</h2>
    <p class="muted" style="margin:0 0 0.85rem">Current: <strong><?= wa_e($stLabel) ?></strong></p>
    <?php if (wa_can('contact_inquiries.edit')): ?>
      <?php if ($nextStatus === ''): ?>
        <p class="muted" style="margin:0">This inquiry is closed. No further status changes.</p>
      <?php else: ?>
        <?php $showReply = $nextStatus === 'replied'; ?>
        <form method="post" class="form-grid" id="inquiry-manage-form">
          <?= wa_csrf_field() ?>
          <label class="full">Status
            <select name="status" id="inquiry-status">
              <option value="<?= wa_e($nextStatus) ?>" selected><?= wa_e($nextLabel) ?></option>
            </select>
          </label>
          <?php if ($showReply): ?>
            <label class="full inquiry-reply-field" id="inquiry-reply-wrap">
              Reply message <span class="req">*</span>
              <textarea name="reply_message" id="inquiry-reply-message" rows="6" placeholder="Write the reply you sent…" required><?= wa_e($replyDraft) ?></textarea>
            </label>
          <?php endif; ?>
          <div class="actions full" style="margin-top:0.35rem">
            <button class="btn" type="submit">Advance to <?= wa_e($nextLabel) ?></button>
          </div>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <p><span class="inquiry-status inquiry-status-<?= wa_e($stCode) ?>"><?= wa_e($stLabel) ?></span></p>
    <?php endif; ?>
  </div>
</div>
<?php
wa_render('Contact Inquiry', ob_get_clean());
