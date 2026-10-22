<?php
declare(strict_types=1);

wa_require_perm('contact_form.view');

$error = '';
$settings = [
    'show_full_name' => 1,
    'show_company_name' => 1,
    'show_phone' => 1,
    'show_email' => 1,
    'show_preferred_service' => 1,
    'show_message' => 1,
];

$res = wa_api('GET', '/api/contact-form');
if (wa_api_ok($res)) {
    $settings = array_merge($settings, $res['data'] ?? []);
} elseif (!$error) {
    $error = (string) ($res['message'] ?? 'Could not load contact form settings');
}
$formPages = is_array($settings['pages'] ?? null) ? $settings['pages'] : [];
unset($settings['pages']);

$fieldsRes = wa_api('GET', '/api/contact-form/fields');
$customFields = wa_api_ok($fieldsRes) ? ($fieldsRes['data']['items'] ?? []) : [];
$fieldTypes = wa_api_ok($fieldsRes) ? ($fieldsRes['data']['field_types'] ?? ['text', 'textarea', 'number', 'date', 'select']) : ['text', 'textarea', 'number', 'date', 'select'];
$columnWidths = wa_api_ok($fieldsRes) ? ($fieldsRes['data']['column_widths'] ?? [3, 4, 6, 8, 12]) : [3, 4, 6, 8, 12];
$availableTables = wa_api_ok($fieldsRes) ? ($fieldsRes['data']['available_tables'] ?? []) : [];
if (!wa_api_ok($fieldsRes) && !$error) {
    $error = (string) ($fieldsRes['message'] ?? 'Could not load form fields');
}

$canEdit = wa_can('contact_form.edit');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $canEdit) {
    $action = (string) ($_POST['form_action'] ?? 'save_settings');

    if ($action === 'save_settings') {
        $payload = [
            'show_full_name' => isset($_POST['show_full_name']) ? 1 : 0,
            'show_company_name' => isset($_POST['show_company_name']) ? 1 : 0,
            'show_phone' => isset($_POST['show_phone']) ? 1 : 0,
            'show_email' => isset($_POST['show_email']) ? 1 : 0,
            'show_preferred_service' => isset($_POST['show_preferred_service']) ? 1 : 0,
            'show_message' => isset($_POST['show_message']) ? 1 : 0,
        ];
        $save = wa_api('POST', '/api/contact-form', $payload);
        if (wa_api_ok($save)) {
            wa_flash('success', $save['message'] ?? 'Saved');
            wa_redirect('/contact-form');
        }
        $error = (string) ($save['message'] ?? 'Save failed');
        $settings = array_merge($settings, $payload);
    }

    if ($action === 'save_pages') {
        $pageIds = array_map('intval', $_POST['page_ids'] ?? []);
        $save = wa_api('POST', '/api/contact-form/pages', ['page_ids' => $pageIds]);
        if (wa_api_ok($save)) {
            wa_flash('success', $save['message'] ?? 'Page visibility saved');
            wa_redirect('/contact-form');
        }
        $error = (string) ($save['message'] ?? 'Could not save page visibility');
        $formPages = is_array($save['data']['pages'] ?? null) ? $save['data']['pages'] : $formPages;
    }

    if ($action === 'add_field') {
        $payload = [
            'label' => trim((string) ($_POST['label'] ?? '')),
            'field_type' => (string) ($_POST['field_type'] ?? 'text'),
            'select_source' => (string) ($_POST['select_source'] ?? 'manual'),
            'source_table' => (string) ($_POST['source_table'] ?? ''),
            'options' => (string) ($_POST['options'] ?? ''),
            'is_required' => isset($_POST['is_required']) ? 1 : 0,
            'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'column_width' => (int) ($_POST['column_width'] ?? 6),
        ];
        $save = wa_api('POST', '/api/contact-form/fields', $payload);
        if (wa_api_ok($save)) {
            wa_flash('success', $save['message'] ?? 'Field added');
            wa_redirect('/contact-form');
        }
        $error = (string) ($save['message'] ?? 'Could not add field');
    }

    if ($action === 'update_field' || $action === 'update_all_fields') {
        $onlyId = (int) ($_POST['update_field_id'] ?? ($_POST['id'] ?? 0));
        if ($action === 'update_field' && $onlyId < 1) {
            $onlyId = (int) ($_POST['id'] ?? 0);
        }
        $rows = $_POST['fields'] ?? null;
        if (!is_array($rows) && $action === 'update_field') {
            // Legacy single-field payload (flat POST)
            $rows = [
                (string) $onlyId => [
                    'label' => (string) ($_POST['label'] ?? ''),
                    'options' => (string) ($_POST['options'] ?? ''),
                    'is_required' => isset($_POST['is_required']) ? 1 : 0,
                    'is_visible' => isset($_POST['is_visible']) ? 1 : 0,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                    'column_width' => (int) ($_POST['column_width'] ?? 6),
                ],
            ];
        }
        if (!is_array($rows)) {
            $error = 'No fields to update';
        } else {
            $updated = 0;
            $failMessage = '';
            foreach ($rows as $rowId => $data) {
                $fid = (int) $rowId;
                if ($fid < 1 || !is_array($data)) {
                    continue;
                }
                if ($action === 'update_field' && $onlyId > 0 && $fid !== $onlyId) {
                    continue;
                }
                if ($action === 'update_all_fields' && $onlyId > 0 && $fid !== $onlyId) {
                    continue;
                }
                $payload = [
                    'id' => $fid,
                    'label' => trim((string) ($data['label'] ?? '')),
                    'options' => (string) ($data['options'] ?? ''),
                    'is_required' => ((string) ($data['is_required'] ?? '0') === '1') ? 1 : 0,
                    'is_visible' => ((string) ($data['is_visible'] ?? '0') === '1') ? 1 : 0,
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'column_width' => (int) ($data['column_width'] ?? 6),
                ];
                $save = wa_api('POST', '/api/contact-form/fields/update', $payload);
                if (!wa_api_ok($save)) {
                    $failMessage = (string) ($save['message'] ?? 'Could not update field');
                    break;
                }
                $updated++;
            }
            if ($failMessage !== '') {
                $error = $failMessage;
            } elseif ($updated < 1) {
                $error = 'No fields to update';
            } else {
                $msg = $onlyId > 0
                    ? 'Field updated'
                    : ($updated === 1 ? '1 field updated' : $updated . ' fields updated');
                wa_flash('success', $msg);
                wa_redirect('/contact-form');
            }
        }
    }

    if ($action === 'hide_field') {
        $save = wa_api('POST', '/api/contact-form/fields/hide', [
            'id' => (int) ($_POST['id'] ?? 0),
        ]);
        wa_flash(wa_api_ok($save) ? 'success' : 'error', (string) ($save['message'] ?? 'Done'));
        wa_redirect('/contact-form');
    }

    if ($action === 'delete_field') {
        $save = wa_api('POST', '/api/contact-form/fields/delete', [
            'id' => (int) ($_POST['id'] ?? 0),
        ]);
        wa_flash(wa_api_ok($save) ? 'success' : 'error', (string) ($save['message'] ?? 'Done'));
        wa_redirect('/contact-form');
    }
}

ob_start();
?>
<div class="page-head">
  <div>
    <h1>Contact Form</h1>
    <p>Manage all form fields (built-in + custom) with shared sort order, choose which pages show the form, and add custom columns.</p>
  </div>
  <div style="display:flex;gap:0.5rem">
    <?php if (wa_can('contact_inquiries.view')): ?>
      <a class="btn btn-ghost" href="<?= wa_e(wa_url('/contact-inquiries')) ?>">Inquiries</a>
    <?php endif; ?>
  </div>
</div>
<?php if ($error): ?><div class="alert alert-error"><?= wa_e($error) ?></div><?php endif; ?>

<div class="panel" style="margin-top:0">
  <form method="post">
    <?= wa_csrf_field() ?>
    <input type="hidden" name="form_action" value="save_pages">
    <div class="page-head" style="margin-bottom:0.75rem">
      <div>
        <h2 style="margin:0;font-size:1.15rem">Display on pages</h2>
        <p class="muted" style="margin:0.25rem 0 0">Checked pages show the contact form section on the public website.</p>
      </div>
    </div>
    <?php if (!$formPages): ?>
      <p class="muted">No website pages found.</p>
    <?php else: ?>
      <div class="page-checks">
        <?php foreach ($formPages as $pg): ?>
          <label class="page-check" style="grid-template-columns:auto 1fr">
            <input type="checkbox" name="page_ids[]" value="<?= (int) ($pg['id'] ?? 0) ?>"
              <?= (int) ($pg['is_visible'] ?? 0) === 1 ? 'checked' : '' ?>
              <?= $canEdit ? '' : 'disabled' ?>>
            <span>
              <?= wa_e((string) ($pg['title'] ?? '')) ?>
              <span class="muted">(/<?= wa_e((string) ($pg['slug'] ?? '')) ?>)</span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($canEdit && $formPages): ?>
      <div class="actions" style="margin-top:1rem"><button class="btn" type="submit">Save page visibility</button></div>
    <?php endif; ?>
  </form>
</div>

<div class="panel" style="margin-top:1.25rem">
  <div class="page-head" style="margin-bottom:0.75rem">
    <div>
      <h2 style="margin:0;font-size:1.15rem">Form fields</h2>
      <p class="muted" style="margin:0.25rem 0 0">Width uses a 12-column layout (like Bootstrap <code>col-md-*</code>). Hide keeps the field listed here; Remove is for custom fields only.</p>
    </div>
    <?php if ($canEdit && $customFields): ?>
      <div>
        <button class="btn" type="submit" form="cf-fields-bulk">Update all</button>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($canEdit && $customFields): ?>
    <form id="cf-fields-bulk" method="post">
      <?= wa_csrf_field() ?>
      <input type="hidden" name="form_action" value="update_all_fields">
  <?php endif; ?>

  <div class="table-wrap">
    <table class="data contact-fields-table">
      <thead>
        <tr>
          <th>Label</th>
          <th>Column</th>
          <th>Type</th>
          <th>Width</th>
          <th>Required</th>
          <th>Visible</th>
          <th>Sort</th>
          <?php if ($canEdit): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$customFields): ?>
          <tr><td colspan="<?= $canEdit ? 8 : 7 ?>" class="muted">No fields yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($customFields as $field): ?>
          <?php
            $fid = (int) ($field['id'] ?? 0);
            $isSystem = (int) ($field['is_system'] ?? 0) === 1;
            $opts = $field['options'] ?? [];
            $optLines = [];
            foreach ($opts as $opt) {
                if (is_array($opt)) {
                    $optLines[] = (string) ($opt['value'] ?? $opt['label'] ?? '');
                } else {
                    $optLines[] = (string) $opt;
                }
            }
            $optsText = implode("\n", array_filter($optLines, static fn ($v) => $v !== ''));
            $isTableSelect = (string) ($field['field_type'] ?? '') === 'select'
                && (string) ($field['select_source'] ?? 'manual') === 'table';
            $canEditOptions = (string) ($field['field_type'] ?? '') === 'select' && !$isTableSelect && !$isSystem;
            $typeLabel = (string) ($field['field_type'] ?? '');
            if ($isTableSelect) {
                $typeLabel .= ' → ' . (string) ($field['source_table'] ?? '');
            }
            $widthVal = (int) ($field['column_width'] ?? 6);
            if (!in_array($widthVal, $columnWidths, true)) {
                $widthVal = ((string) ($field['field_type'] ?? '') === 'textarea') ? 12 : 6;
            }
            $fname = 'fields[' . $fid . ']';
          ?>
          <?php if ($canEdit): ?>
            <tr>
              <td>
                <input name="<?= wa_e($fname) ?>[label]" required value="<?= wa_e((string) ($field['label'] ?? '')) ?>">
                <?php if (!$canEditOptions): ?>
                  <input type="hidden" name="<?= wa_e($fname) ?>[options]" value="">
                <?php endif; ?>
              </td>
              <td>
                <code><?= wa_e((string) ($field['field_key'] ?? '')) ?></code>
                <?php if ($isSystem): ?><div class="muted" style="font-size:0.8rem;margin-top:0.2rem">Built-in</div><?php endif; ?>
              </td>
              <td>
                <?= wa_e($typeLabel) ?>
                <?php if ($isTableSelect && !$isSystem): ?>
                  <div class="muted" style="font-size:0.8rem;margin-top:0.2rem">Table locked after create</div>
                <?php endif; ?>
              </td>
              <td>
                <select name="<?= wa_e($fname) ?>[column_width]">
                  <?php foreach ($columnWidths as $w): ?>
                    <option value="<?= (int) $w ?>" <?= $widthVal === (int) $w ? 'selected' : '' ?>>col-md-<?= (int) $w ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="vertical-align:middle">
                <input type="hidden" name="<?= wa_e($fname) ?>[is_required]" value="0">
                <input type="checkbox" name="<?= wa_e($fname) ?>[is_required]" value="1" <?= (int) ($field['is_required'] ?? 0) === 1 ? 'checked' : '' ?>>
              </td>
              <td style="vertical-align:middle">
                <input type="hidden" name="<?= wa_e($fname) ?>[is_visible]" value="0">
                <input type="checkbox" name="<?= wa_e($fname) ?>[is_visible]" value="1" <?= (int) ($field['is_visible'] ?? 0) === 1 ? 'checked' : '' ?>>
              </td>
              <td>
                <input type="number" name="<?= wa_e($fname) ?>[sort_order]" value="<?= (int) ($field['sort_order'] ?? 0) ?>" style="width:4.5rem">
              </td>
              <td class="row-actions" style="white-space:nowrap">
                <button class="btn" type="submit" name="update_field_id" value="<?= $fid ?>">Update</button>
                <button class="btn btn-ghost" type="submit" form="<?= wa_e('cf-hide-' . $fid) ?>" style="margin-left:0.35rem" onclick="return confirm('Hide this field on the public form?');">Hide</button>
                <?php if (!$isSystem): ?>
                  <button class="btn btn-ghost" type="submit" form="<?= wa_e('cf-delete-' . $fid) ?>" style="margin-left:0.35rem" onclick="return confirm('Remove this custom field from the form? The database column is kept.');">Remove</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($canEditOptions): ?>
              <tr>
                <td colspan="8" style="padding-top:0">
                  <label class="muted" style="display:grid;gap:0.35rem;font-weight:600;font-size:0.85rem;max-width:36rem">
                    Options (one per line)
                    <textarea name="<?= wa_e($fname) ?>[options]" rows="2"><?= wa_e($optsText) ?></textarea>
                  </label>
                </td>
              </tr>
            <?php endif; ?>
          <?php else: ?>
            <tr>
              <td><?= wa_e((string) ($field['label'] ?? '')) ?><?= $isSystem ? ' <span class="muted">(built-in)</span>' : '' ?></td>
              <td><code><?= wa_e((string) ($field['field_key'] ?? '')) ?></code></td>
              <td><?= wa_e($typeLabel) ?></td>
              <td><code>col-md-<?= (int) $widthVal ?></code></td>
              <td><?= (int) ($field['is_required'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
              <td><?= (int) ($field['is_visible'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
              <td><?= (int) ($field['sort_order'] ?? 0) ?></td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($canEdit && $customFields): ?>
      <div class="actions" style="margin-top:0.85rem">
        <button class="btn" type="submit">Update all</button>
      </div>
    </form>
    <?php foreach ($customFields as $field): ?>
      <?php
        $fid = (int) ($field['id'] ?? 0);
        $isSystem = (int) ($field['is_system'] ?? 0) === 1;
      ?>
      <form id="cf-hide-<?= $fid ?>" method="post" style="display:none">
        <?= wa_csrf_field() ?>
        <input type="hidden" name="form_action" value="hide_field">
        <input type="hidden" name="id" value="<?= $fid ?>">
      </form>
      <?php if (!$isSystem): ?>
        <form id="cf-delete-<?= $fid ?>" method="post" style="display:none">
          <?= wa_csrf_field() ?>
          <input type="hidden" name="form_action" value="delete_field">
          <input type="hidden" name="id" value="<?= $fid ?>">
        </form>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($canEdit): ?>
    <form method="post" class="form-grid" style="margin-top:1.25rem;border-top:1px solid #e5e7eb;padding-top:1rem">
      <?= wa_csrf_field() ?>
      <input type="hidden" name="form_action" value="add_field">
      <div class="full"><strong>Add custom field</strong></div>
      <label>Label<input name="label" required placeholder="e.g. Subject"></label>
      <label>Type
        <select name="field_type" id="cf-field-type">
          <?php foreach ($fieldTypes as $type): ?>
            <option value="<?= wa_e((string) $type) ?>"><?= wa_e((string) $type) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Width
        <select name="column_width">
          <?php foreach ($columnWidths as $w): ?>
            <option value="<?= (int) $w ?>" <?= (int) $w === 6 ? 'selected' : '' ?>>col-md-<?= (int) $w ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Sort order<input type="number" name="sort_order" value="0" placeholder="Auto"></label>
      <label style="display:flex;align-items:center;gap:0.5rem;padding-top:1.6rem">
        <input type="checkbox" name="is_required" value="1"> Required
      </label>
      <label style="display:flex;align-items:center;gap:0.5rem;padding-top:1.6rem">
        <input type="checkbox" name="is_visible" value="1" checked> Visible on public form
      </label>

      <div class="full" id="cf-select-source-wrap" style="display:none">
        <strong style="display:block;margin-bottom:0.5rem">Select options source</strong>
        <label style="display:inline-flex;align-items:center;gap:0.4rem;margin-right:1.25rem">
          <input type="radio" name="select_source" value="manual" id="cf-source-manual" checked> Manual
        </label>
        <label style="display:inline-flex;align-items:center;gap:0.4rem">
          <input type="radio" name="select_source" value="table" id="cf-source-table"> Connect table
        </label>
      </div>

      <label class="full" id="cf-options-wrap" style="display:none">Options (one per line, for select)
        <textarea name="options" rows="3" placeholder="Option A&#10;Option B"></textarea>
      </label>

      <div class="full" id="cf-tables-wrap" style="display:none">
        <strong style="display:block;margin-bottom:0.5rem">Connect table (foreign key)</strong>
        <?php if (!$availableTables): ?>
          <p class="muted" style="margin:0">All connectable tables are already linked. Remove a connected field to free a table.</p>
        <?php else: ?>
          <?php foreach ($availableTables as $tbl): ?>
            <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem">
              <input type="radio" name="source_table" value="<?= wa_e((string) ($tbl['table'] ?? '')) ?>">
              <?= wa_e((string) ($tbl['label'] ?? $tbl['table'] ?? '')) ?>
              <span class="muted">(<code><?= wa_e((string) ($tbl['table'] ?? '')) ?></code>)</span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="actions full"><button class="btn" type="submit">Save &amp; add column</button></div>
    </form>
    <script>
      (function () {
        var type = document.getElementById('cf-field-type');
        var sourceWrap = document.getElementById('cf-select-source-wrap');
        var optionsWrap = document.getElementById('cf-options-wrap');
        var tablesWrap = document.getElementById('cf-tables-wrap');
        var manual = document.getElementById('cf-source-manual');
        var table = document.getElementById('cf-source-table');
        if (!type || !sourceWrap || !optionsWrap || !tablesWrap) return;
        function sync() {
          var isSelect = type.value === 'select';
          sourceWrap.style.display = isSelect ? '' : 'none';
          if (!isSelect) {
            optionsWrap.style.display = 'none';
            tablesWrap.style.display = 'none';
            return;
          }
          var useTable = table && table.checked;
          optionsWrap.style.display = useTable ? 'none' : '';
          tablesWrap.style.display = useTable ? '' : 'none';
        }
        type.addEventListener('change', sync);
        if (manual) manual.addEventListener('change', sync);
        if (table) table.addEventListener('change', sync);
        sync();
      })();
    </script>
  <?php endif; ?>
</div>
<?php
wa_render('Contact Form', ob_get_clean());
