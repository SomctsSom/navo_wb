<?php
declare(strict_types=1);

function service_default_icon_class(): string
{
    return 'fa-truck';
}

function service_normalize_icon_class(mixed $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        $raw = service_default_icon_class();
    }
    // Bare glyph names like "fa-truck" need a style prefix for Font Awesome 6.
    if (preg_match('/^fa-[a-z0-9-]+$/i', $raw) === 1) {
        $raw = 'fas ' . $raw;
    }
    return sanitize_feature_icon_class($raw);
}

/**
 * @param array<int,array<string,mixed>> $assignments
 */
function sync_service_pages(PDO $pdo, int $serviceId, array $assignments, int $actorId): void
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
            throw new RuntimeException('A page may feature a service only when the assignment is active');
        }

        $existing = $pdo->prepare('SELECT id FROM service_pages WHERE service_id = :s AND page_id = :p AND deleted_at IS NULL LIMIT 1');
        $existing->execute([':s' => $serviceId, ':p' => $pageId]);
        $exId = $existing->fetchColumn();
        if ($exId) {
            $pdo->prepare(
                'UPDATE service_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $exId]);
            continue;
        }
        $old = $pdo->prepare('SELECT id FROM service_pages WHERE service_id = :s AND page_id = :p AND deleted_at IS NOT NULL ORDER BY id DESC LIMIT 1');
        $old->execute([':s' => $serviceId, ':p' => $pageId]);
        $oldId = $old->fetchColumn();
        if ($oldId) {
            restore_row($pdo, 'service_pages', (int) $oldId, $actorId);
            $pdo->prepare(
                'UPDATE service_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $oldId]);
        } else {
            $pdo->prepare(
                'INSERT INTO service_pages (service_id, page_id, is_featured, is_active, sort_order, created_at, created_by)
                 VALUES (:s, :p, :f, :a, :sort, :c, :by)'
            )->execute([
                ':s' => $serviceId, ':p' => $pageId, ':f' => $isFeatured, ':a' => $isActive,
                ':sort' => $sort, ':c' => now_utc(), ':by' => $actorId,
            ]);
        }
    }

    $cur = $pdo->prepare('SELECT id, page_id FROM service_pages WHERE service_id = :s AND deleted_at IS NULL');
    $cur->execute([':s' => $serviceId]);
    foreach ($cur->fetchAll() ?: [] as $row) {
        if (!isset($seen[(int) $row['page_id']])) {
            soft_delete_row($pdo, 'service_pages', (int) $row['id'], $actorId);
        }
    }
}

/**
 * Optional list under description. Empty array clears active items (soft-delete).
 *
 * @param array<int,array<string,mixed>> $items
 */
function sync_service_items(PDO $pdo, int $serviceId, array $items, int $actorId): void
{
    $normalized = [];
    $sort = 0;
    foreach ($items as $item) {
        $title = trim((string) ($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $normalized[] = [
            'id' => isset($item['id']) ? (int) $item['id'] : 0,
            'title' => $title,
            'body' => trim((string) ($item['body'] ?? '')),
            'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $sort,
            'is_active' => to_bool_int($item['is_active'] ?? true, 1),
        ];
        $sort++;
    }

    $keepIds = [];
    foreach ($normalized as $n) {
        if ($n['id'] > 0) {
            $chk = $pdo->prepare('SELECT id FROM service_items WHERE id = :id AND service_id = :s AND deleted_at IS NULL');
            $chk->execute([':id' => $n['id'], ':s' => $serviceId]);
            if ($chk->fetchColumn()) {
                $pdo->prepare(
                    'UPDATE service_items SET title = :t, body = :b, sort_order = :s, is_active = :a, updated_at = :u, updated_by = :by WHERE id = :id'
                )->execute([
                    ':t' => $n['title'],
                    ':b' => $n['body'] !== '' ? $n['body'] : null,
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
        $pdo->prepare(
            'INSERT INTO service_items (service_id, title, body, sort_order, is_active, created_at, created_by)
             VALUES (:sid, :t, :b, :s, :a, :c, :by)'
        )->execute([
            ':sid' => $serviceId,
            ':t' => $n['title'],
            ':b' => $n['body'] !== '' ? $n['body'] : null,
            ':s' => $n['sort_order'],
            ':a' => $n['is_active'],
            ':c' => now_utc(),
            ':by' => $actorId,
        ]);
        $keepIds[] = (int) $pdo->lastInsertId();
    }

    $cur = $pdo->prepare('SELECT id FROM service_items WHERE service_id = :s AND deleted_at IS NULL');
    $cur->execute([':s' => $serviceId]);
    foreach ($cur->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
        if (!in_array((int) $id, $keepIds, true)) {
            soft_delete_row($pdo, 'service_items', (int) $id, $actorId);
        }
    }
}

function service_load_items(PDO $pdo, int $serviceId, bool $activeOnly = false): array
{
    $sql = 'SELECT id, title, body, sort_order, is_active FROM service_items WHERE service_id = :s AND deleted_at IS NULL';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':s' => $serviceId]);
    $rows = $stmt->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['is_active'] = (int) $row['is_active'];
    }
    unset($row);
    return $rows;
}
