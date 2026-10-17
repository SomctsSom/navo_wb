<?php
declare(strict_types=1);

/**
 * Registered public content sections (extend when new modules ship).
 * Hero / page banner stay at the top and are not ordered here.
 *
 * @return array<string,string> key => label
 */
function page_content_section_catalog(): array
{
    return [
        'services' => 'Services',
        'testimonials' => 'Testimonials',
        'cta_banner' => 'CTA Banner',
        'partners' => 'Partners',
        'contact_social' => 'Contact & Social',
        'contact_form' => 'Contact Form',
        'profile_overviews' => 'Profile Overview',
        'vision_missions' => 'Vision & Mission',
        'features' => 'Features',
    ];
}

/**
 * Default order when a page has no saved rows.
 *
 * @return list<array{section_key:string,label:string,sort_order:int,is_enabled:int}>
 */
function page_content_section_defaults(): array
{
    $out = [];
    $i = 10;
    foreach (page_content_section_catalog() as $key => $label) {
        $out[] = [
            'section_key' => $key,
            'label' => $label,
            'sort_order' => $i,
            // Contact form is opt-in per page (managed from Contact Form admin / page sections).
            'is_enabled' => $key === 'contact_form' ? 0 : 1,
        ];
        $i += 10;
    }
    return $out;
}

/**
 * @return list<array{section_key:string,label:string,sort_order:int,is_enabled:int}>
 */
function get_page_section_order(PDO $pdo, int $pageId): array
{
    $catalog = page_content_section_catalog();
    $defaults = page_content_section_defaults();
    if ($pageId < 1) {
        return $defaults;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT section_key, sort_order, is_enabled
             FROM page_section_orders
             WHERE page_id = :p
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':p' => $pageId]);
        $rows = $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return $defaults;
    }

    $byKey = [];
    foreach ($rows as $row) {
        $key = (string) $row['section_key'];
        if (!isset($catalog[$key])) {
            continue;
        }
        $byKey[$key] = [
            'section_key' => $key,
            'label' => $catalog[$key],
            'sort_order' => (int) $row['sort_order'],
            'is_enabled' => (int) $row['is_enabled'] === 1 ? 1 : 0,
        ];
    }

    // Merge new catalog keys that are not saved yet
    $maxSort = 0;
    foreach ($byKey as $item) {
        $maxSort = max($maxSort, (int) $item['sort_order']);
    }
    foreach ($catalog as $key => $label) {
        if (!isset($byKey[$key])) {
            $maxSort += 10;
            $byKey[$key] = [
                'section_key' => $key,
                'label' => $label,
                'sort_order' => $maxSort,
                'is_enabled' => $key === 'contact_form' ? 0 : 1,
            ];
        }
    }

    $list = array_values($byKey);
    usort($list, static function (array $a, array $b): int {
        return [$a['sort_order'], $a['section_key']] <=> [$b['sort_order'], $b['section_key']];
    });
    // Normalize sort_order to 10,20,30…
    $n = 10;
    foreach ($list as &$item) {
        $item['sort_order'] = $n;
        $n += 10;
    }
    unset($item);
    return $list;
}

/**
 * @param array<int,array<string,mixed>> $items
 * @return list<array{section_key:string,label:string,sort_order:int,is_enabled:int}>
 */
function sync_page_section_order(PDO $pdo, int $pageId, array $items, ?int $actorId = null): array
{
    $catalog = page_content_section_catalog();
    $normalized = [];
    $sort = 10;
    $seen = [];
    foreach ($items as $item) {
        $key = strtolower(trim((string) ($item['section_key'] ?? '')));
        if ($key === '' || !isset($catalog[$key]) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $normalized[] = [
            'section_key' => $key,
            'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $sort,
            'is_enabled' => to_bool_int($item['is_enabled'] ?? true, 1),
        ];
        $sort += 10;
    }
    // Ensure all catalog keys exist
    foreach ($catalog as $key => $_label) {
        if (!isset($seen[$key])) {
            $normalized[] = [
                'section_key' => $key,
                'sort_order' => $sort,
                'is_enabled' => $key === 'contact_form' ? 0 : 1,
            ];
            $sort += 10;
        }
    }
    usort($normalized, static fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
    $n = 10;
    foreach ($normalized as &$row) {
        $row['sort_order'] = $n;
        $n += 10;
    }
    unset($row);

    $pdo->prepare('DELETE FROM page_section_orders WHERE page_id = :p')->execute([':p' => $pageId]);
    $ins = $pdo->prepare(
        'INSERT INTO page_section_orders (page_id, section_key, sort_order, is_enabled, created_at)
         VALUES (:p, :k, :s, :e, :c)'
    );
    foreach ($normalized as $row) {
        $ins->execute([
            ':p' => $pageId,
            ':k' => $row['section_key'],
            ':s' => $row['sort_order'],
            ':e' => $row['is_enabled'],
            ':c' => now_utc(),
        ]);
    }

    return get_page_section_order($pdo, $pageId);
}

/**
 * Enable/disable a single section on a page without wiping other section rows.
 */
function set_page_section_enabled(PDO $pdo, int $pageId, string $sectionKey, int $enabled, ?int $actorId = null): void
{
    $catalog = page_content_section_catalog();
    if ($pageId < 1 || !isset($catalog[$sectionKey])) {
        return;
    }
    $enabled = $enabled === 1 ? 1 : 0;

    $stmt = $pdo->prepare(
        'SELECT id, sort_order FROM page_section_orders
         WHERE page_id = :p AND section_key = :k LIMIT 1'
    );
    $stmt->execute([':p' => $pageId, ':k' => $sectionKey]);
    $existing = $stmt->fetch();
    if ($existing) {
        $pdo->prepare(
            'UPDATE page_section_orders SET is_enabled = ? WHERE id = ?'
        )->execute([$enabled, (int) $existing['id']]);
        return;
    }

    $maxStmt = $pdo->prepare(
        'SELECT COALESCE(MAX(sort_order), 0) FROM page_section_orders WHERE page_id = :p'
    );
    $maxStmt->execute([':p' => $pageId]);
    $max = (int) $maxStmt->fetchColumn();

    $pdo->prepare(
        'INSERT INTO page_section_orders (page_id, section_key, sort_order, is_enabled, created_at)
         VALUES (:p, :k, :s, :e, :c)'
    )->execute([
        ':p' => $pageId,
        ':k' => $sectionKey,
        ':s' => $max + 10,
        ':e' => $enabled,
        ':c' => now_utc(),
    ]);
}
