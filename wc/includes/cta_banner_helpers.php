<?php
declare(strict_types=1);

/**
 * @param array<int,array<string,mixed>> $assignments
 */
function sync_cta_banner_pages(PDO $pdo, int $ctaBannerId, array $assignments, int $actorId): void
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
            throw new RuntimeException('A page may feature a CTA banner only when the assignment is active');
        }

        $existing = $pdo->prepare(
            'SELECT id FROM cta_banner_pages WHERE cta_banner_id = :s AND page_id = :p AND deleted_at IS NULL LIMIT 1'
        );
        $existing->execute([':s' => $ctaBannerId, ':p' => $pageId]);
        $exId = $existing->fetchColumn();
        if ($exId) {
            $pdo->prepare(
                'UPDATE cta_banner_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $exId]);
            continue;
        }
        $old = $pdo->prepare(
            'SELECT id FROM cta_banner_pages WHERE cta_banner_id = :s AND page_id = :p AND deleted_at IS NOT NULL ORDER BY id DESC LIMIT 1'
        );
        $old->execute([':s' => $ctaBannerId, ':p' => $pageId]);
        $oldId = $old->fetchColumn();
        if ($oldId) {
            restore_row($pdo, 'cta_banner_pages', (int) $oldId, $actorId);
            $pdo->prepare(
                'UPDATE cta_banner_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $oldId]);
        } else {
            $pdo->prepare(
                'INSERT INTO cta_banner_pages (cta_banner_id, page_id, is_featured, is_active, sort_order, created_at, created_by)
                 VALUES (:s, :p, :f, :a, :sort, :c, :by)'
            )->execute([
                ':s' => $ctaBannerId, ':p' => $pageId, ':f' => $isFeatured, ':a' => $isActive,
                ':sort' => $sort, ':c' => now_utc(), ':by' => $actorId,
            ]);
        }
    }

    $cur = $pdo->prepare('SELECT id, page_id FROM cta_banner_pages WHERE cta_banner_id = :s AND deleted_at IS NULL');
    $cur->execute([':s' => $ctaBannerId]);
    foreach ($cur->fetchAll() ?: [] as $row) {
        if (!isset($seen[(int) $row['page_id']])) {
            soft_delete_row($pdo, 'cta_banner_pages', (int) $row['id'], $actorId);
        }
    }
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function cta_banner_normalize_row(array $row): array
{
    $row['id'] = (int) ($row['id'] ?? 0);
    $row['is_active'] = (int) ($row['is_active'] ?? 0);
    $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
    $row['eyebrow'] = (string) ($row['eyebrow'] ?? '');
    $row['title'] = (string) ($row['title'] ?? '');
    $row['subtitle'] = (string) ($row['subtitle'] ?? '');
    $row['primary_button_text'] = (string) ($row['primary_button_text'] ?? '');
    $row['primary_button_url'] = (string) ($row['primary_button_url'] ?? '');
    $row['secondary_button_text'] = (string) ($row['secondary_button_text'] ?? '');
    $row['secondary_button_url'] = (string) ($row['secondary_button_url'] ?? '');
    return $row;
}
