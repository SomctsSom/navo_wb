<?php
declare(strict_types=1);

/**
 * Sanitize Font Awesome class string, e.g. "fa fa-users" or "fas fa-shield-alt".
 */
function sanitize_feature_icon_class(mixed $raw): string
{
    $raw = trim(preg_replace('/\s+/', ' ', (string) $raw) ?? '');
    if ($raw === '') {
        return 'fa fa-star';
    }

    // Map legacy preset keys from the old dropdown
    $legacy = [
        'user' => 'fa fa-user',
        'target' => 'fa fa-bullseye',
        'shield' => 'fa fa-shield',
        'heart' => 'fa fa-heart',
        'star' => 'fa fa-star',
        'check' => 'fa fa-check',
        'bolt' => 'fa fa-bolt',
        'handshake' => 'fa fa-handshake-o',
        'chat' => 'fa fa-comments',
        'layers' => 'fa fa-layer-group',
    ];
    $lower = strtolower($raw);
    if (isset($legacy[$lower])) {
        return $legacy[$lower];
    }

    $clean = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $raw) ?? '';
    $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    if ($clean === '') {
        return 'fa fa-star';
    }

    // Allow only FA-looking tokens (fa, fas, far, fab, fal, fad, fa-*)
    $parts = preg_split('/\s+/', $clean) ?: [];
    $safe = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (preg_match('/^fa[a-z0-9_-]*$/i', $part)) {
            $safe[] = $part;
        }
    }
    if (!$safe) {
        return 'fa fa-star';
    }

    $out = implode(' ', $safe);
    if (strlen($out) > 100) {
        $out = substr($out, 0, 100);
    }
    return $out;
}

/** @deprecated use sanitize_feature_icon_class */
function normalize_feature_icon_key(mixed $key): string
{
    return sanitize_feature_icon_class($key);
}

/**
 * Safe <i> tag for Font Awesome class.
 */
function feature_icon_html(string $iconClass): string
{
    $iconClass = sanitize_feature_icon_class($iconClass);
    return '<i class="' . htmlspecialchars($iconClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" aria-hidden="true"></i>';
}

/**
 * @param array<int,array<string,mixed>> $assignments
 */
function sync_feature_pages(PDO $pdo, int $featureId, array $assignments, int $actorId): void
{
    $seen = [];
    foreach ($assignments as $a) {
        $pageId = (int) ($a['page_id'] ?? 0);
        if ($pageId < 1 || isset($seen[$pageId])) {
            continue;
        }
        $seen[$pageId] = true;
        $p = $pdo->prepare("SELECT id FROM pages WHERE id = :id AND page_type = 'website' AND deleted_at IS NULL");
        $p->execute([':id' => $pageId]);
        if (!$p->fetchColumn()) {
            throw new RuntimeException("Website page {$pageId} not found");
        }
        $isFeatured = to_bool_int($a['is_featured'] ?? false);
        $isActive = to_bool_int($a['is_active'] ?? true, 1);
        $sort = (int) ($a['sort_order'] ?? 0);
        if ($isFeatured === 1 && $isActive !== 1) {
            throw new RuntimeException('A page may feature a section only when the assignment is active');
        }

        $existing = $pdo->prepare(
            'SELECT id FROM feature_pages WHERE feature_id = :s AND page_id = :p AND deleted_at IS NULL LIMIT 1'
        );
        $existing->execute([':s' => $featureId, ':p' => $pageId]);
        $exId = $existing->fetchColumn();
        if ($exId) {
            $pdo->prepare(
                'UPDATE feature_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $exId]);
            continue;
        }
        $old = $pdo->prepare(
            'SELECT id FROM feature_pages WHERE feature_id = :s AND page_id = :p AND deleted_at IS NOT NULL ORDER BY id DESC LIMIT 1'
        );
        $old->execute([':s' => $featureId, ':p' => $pageId]);
        $oldId = $old->fetchColumn();
        if ($oldId) {
            restore_row($pdo, 'feature_pages', (int) $oldId, $actorId);
            $pdo->prepare(
                'UPDATE feature_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $oldId]);
        } else {
            $pdo->prepare(
                'INSERT INTO feature_pages (feature_id, page_id, is_featured, is_active, sort_order, created_at, created_by)
                 VALUES (:s, :p, :f, :a, :sort, :c, :by)'
            )->execute([
                ':s' => $featureId, ':p' => $pageId, ':f' => $isFeatured, ':a' => $isActive,
                ':sort' => $sort, ':c' => now_utc(), ':by' => $actorId,
            ]);
        }
    }

    $cur = $pdo->prepare('SELECT id, page_id FROM feature_pages WHERE feature_id = :s AND deleted_at IS NULL');
    $cur->execute([':s' => $featureId]);
    foreach ($cur->fetchAll() ?: [] as $row) {
        if (!isset($seen[(int) $row['page_id']])) {
            soft_delete_row($pdo, 'feature_pages', (int) $row['id'], $actorId);
        }
    }
}

/**
 * Apply uploaded card icon files from $_FILES keys card_icon_{index}.
 *
 * @param array<int,array<string,mixed>> $cards
 * @return array<int,array<string,mixed>>
 */
function feature_cards_apply_uploads(array $cards): array
{
    foreach ($cards as $i => &$card) {
        if (!is_array($card)) {
            continue;
        }
        $field = 'card_icon_' . (int) $i;
        $file = $_FILES[$field] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $upload = secure_upload_image($file, 'features');
        if (empty($upload['ok'])) {
            throw new RuntimeException((string) ($upload['error'] ?? 'Card icon upload failed'));
        }
        $card['icon_path'] = $upload['relative_path'];
        $card['clear_icon'] = 0;
    }
    unset($card);
    return $cards;
}

/**
 * Sync cards under a feature section. Empty array clears active cards (soft-delete).
 *
 * @param array<int,array<string,mixed>> $cards
 */
function sync_feature_cards(PDO $pdo, int $featureId, array $cards, int $actorId): void
{
    $normalized = [];
    $sort = 0;
    foreach ($cards as $card) {
        $title = trim((string) ($card['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $clearIcon = to_bool_int($card['clear_icon'] ?? false, 0) === 1;
        $newPath = array_key_exists('icon_path', $card) ? trim((string) $card['icon_path']) : null;
        if ($newPath === '') {
            $newPath = null;
        }
        $normalized[] = [
            'id' => isset($card['id']) ? (int) $card['id'] : 0,
            'icon_key' => sanitize_feature_icon_class($card['icon_key'] ?? 'fa fa-star'),
            'icon_path' => $newPath,
            'clear_icon' => $clearIcon,
            'has_new_path' => array_key_exists('icon_path', $card),
            'title' => $title,
            'description' => trim((string) ($card['description'] ?? '')),
            'sort_order' => isset($card['sort_order']) ? (int) $card['sort_order'] : $sort,
            'is_active' => to_bool_int($card['is_active'] ?? true, 1),
        ];
        $sort++;
    }

    $keepIds = [];
    foreach ($normalized as $n) {
        if ($n['id'] > 0) {
            $chk = $pdo->prepare(
                'SELECT id, icon_path FROM feature_cards WHERE id = :id AND feature_id = :s AND deleted_at IS NULL'
            );
            $chk->execute([':id' => $n['id'], ':s' => $featureId]);
            $existingCard = $chk->fetch();
            if ($existingCard) {
                $iconPath = $existingCard['icon_path'] ?? null;
                if ($n['clear_icon']) {
                    $iconPath = null;
                } elseif ($n['has_new_path'] && $n['icon_path'] !== null) {
                    $iconPath = $n['icon_path'];
                }
                $pdo->prepare(
                    'UPDATE feature_cards SET icon_key = :ik, icon_path = :ip, title = :t, description = :d,
                     sort_order = :s, is_active = :a, updated_at = :u, updated_by = :by WHERE id = :id'
                )->execute([
                    ':ik' => $n['icon_key'],
                    ':ip' => $iconPath,
                    ':t' => $n['title'],
                    ':d' => $n['description'] !== '' ? $n['description'] : null,
                    ':s' => $n['sort_order'],
                    ':a' => $n['is_active'],
                    ':u' => now_utc(),
                    ':by' => $actorId,
                    ':id' => $n['id'],
                ]);
                $keepIds[] = $n['id'];
                continue;
            }
        }
        $iconPath = $n['clear_icon'] ? null : $n['icon_path'];
        $pdo->prepare(
            'INSERT INTO feature_cards (feature_id, icon_key, icon_path, title, description, sort_order, is_active, created_at, created_by)
             VALUES (:fid, :ik, :ip, :t, :d, :s, :a, :c, :by)'
        )->execute([
            ':fid' => $featureId,
            ':ik' => $n['icon_key'],
            ':ip' => $iconPath,
            ':t' => $n['title'],
            ':d' => $n['description'] !== '' ? $n['description'] : null,
            ':s' => $n['sort_order'],
            ':a' => $n['is_active'],
            ':c' => now_utc(),
            ':by' => $actorId,
        ]);
        $keepIds[] = (int) $pdo->lastInsertId();
    }

    $cur = $pdo->prepare('SELECT id FROM feature_cards WHERE feature_id = :s AND deleted_at IS NULL');
    $cur->execute([':s' => $featureId]);
    foreach ($cur->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
        if (!in_array((int) $id, $keepIds, true)) {
            soft_delete_row($pdo, 'feature_cards', (int) $id, $actorId);
        }
    }
}

/**
 * @return list<array<string,mixed>>
 */
function feature_load_cards(PDO $pdo, int $featureId, bool $activeOnly = false): array
{
    $sql = 'SELECT id, icon_key, icon_path, title, description, sort_order, is_active
            FROM feature_cards WHERE feature_id = :s AND deleted_at IS NULL';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':s' => $featureId]);
    $rows = $stmt->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['is_active'] = (int) $row['is_active'];
        $row['icon_key'] = sanitize_feature_icon_class($row['icon_key'] ?? 'fa fa-star');
        $row['icon_url'] = !empty($row['icon_path'])
            ? secure_public_media_url((string) $row['icon_path']) : null;
        $row['icon_html'] = feature_icon_html($row['icon_key']);
        unset($row['icon_path']);
    }
    unset($row);
    return $rows;
}
