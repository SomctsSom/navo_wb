<?php
declare(strict_types=1);

/**
 * @param array<int,array<string,mixed>> $assignments
 */
function sync_partner_pages(PDO $pdo, int $partnerId, array $assignments, int $actorId): void
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
            throw new RuntimeException('A page may feature a partner only when the assignment is active');
        }

        $existing = $pdo->prepare(
            'SELECT id FROM partner_pages WHERE partner_id = :s AND page_id = :p AND deleted_at IS NULL LIMIT 1'
        );
        $existing->execute([':s' => $partnerId, ':p' => $pageId]);
        $exId = $existing->fetchColumn();
        if ($exId) {
            $pdo->prepare(
                'UPDATE partner_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $exId]);
            continue;
        }
        $old = $pdo->prepare(
            'SELECT id FROM partner_pages WHERE partner_id = :s AND page_id = :p AND deleted_at IS NOT NULL ORDER BY id DESC LIMIT 1'
        );
        $old->execute([':s' => $partnerId, ':p' => $pageId]);
        $oldId = $old->fetchColumn();
        if ($oldId) {
            restore_row($pdo, 'partner_pages', (int) $oldId, $actorId);
            $pdo->prepare(
                'UPDATE partner_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $oldId]);
        } else {
            $pdo->prepare(
                'INSERT INTO partner_pages (partner_id, page_id, is_featured, is_active, sort_order, created_at, created_by)
                 VALUES (:s, :p, :f, :a, :sort, :c, :by)'
            )->execute([
                ':s' => $partnerId, ':p' => $pageId, ':f' => $isFeatured, ':a' => $isActive,
                ':sort' => $sort, ':c' => now_utc(), ':by' => $actorId,
            ]);
        }
    }

    $cur = $pdo->prepare('SELECT id, page_id FROM partner_pages WHERE partner_id = :s AND deleted_at IS NULL');
    $cur->execute([':s' => $partnerId]);
    foreach ($cur->fetchAll() ?: [] as $row) {
        if (!isset($seen[(int) $row['page_id']])) {
            soft_delete_row($pdo, 'partner_pages', (int) $row['id'], $actorId);
        }
    }
}

/**
 * @return list<string>
 */
function partner_parse_useful_for(mixed $raw): array
{
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $raw);
        $items = preg_split('/\n+/', $text) ?: [];
    }
    $out = [];
    foreach ($items as $item) {
        $line = trim((string) $item);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

function partner_normalize_icon_class(string $iconClass): string
{
    $iconClass = trim($iconClass);
    if ($iconClass === '') {
        return 'fas fa-handshake';
    }
    if (preg_match('/^fa-[a-z0-9-]+$/i', $iconClass) === 1) {
        return 'fas ' . $iconClass;
    }
    return $iconClass;
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function partner_normalize_row(array $row): array
{
    $row['id'] = (int) ($row['id'] ?? 0);
    $row['is_active'] = (int) ($row['is_active'] ?? 0);
    $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
    $row['section_title'] = (string) ($row['section_title'] ?? '');
    $row['section_subtitle'] = (string) ($row['section_subtitle'] ?? '');
    $row['icon_class'] = (string) ($row['icon_class'] ?? '');
    $row['badge_text'] = (string) ($row['badge_text'] ?? '');
    $row['name'] = (string) ($row['name'] ?? '');
    $row['category'] = (string) ($row['category'] ?? '');
    $row['description'] = (string) ($row['description'] ?? '');
    $row['useful_for'] = (string) ($row['useful_for'] ?? '');
    $row['useful_for_items'] = partner_parse_useful_for($row['useful_for']);
    $row['button_text'] = (string) ($row['button_text'] ?? '');
    $row['button_url'] = (string) ($row['button_url'] ?? '');
    $icon = partner_normalize_icon_class($row['icon_class'] !== '' ? $row['icon_class'] : 'fa-handshake');
    $row['icon_class_normalized'] = $icon;
    $row['icon_html'] = '<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
    return $row;
}
