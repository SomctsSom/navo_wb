<?php
declare(strict_types=1);

function hero_size_presets(): array
{
    return [
        'sm' => 'Small',
        'md' => 'Medium',
        'lg' => 'Large',
        'xl' => 'Extra large',
    ];
}

function normalize_hero_size_preset(?string $value, string $default = 'md'): string
{
    $value = strtolower(trim((string) $value));
    return array_key_exists($value, hero_size_presets()) ? $value : $default;
}

function get_hero_size_preset(?PDO $pdo = null): string
{
    $pdo = $pdo ?? db();
    try {
        $stmt = $pdo->prepare(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'hero_size_preset' LIMIT 1"
        );
        $stmt->execute();
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null) {
            return normalize_hero_size_preset((string) $value);
        }
    } catch (Throwable $e) {
        // Table may not exist yet during early migrate
    }
    return 'md';
}

function set_hero_size_preset(PDO $pdo, string $preset, ?int $actorId = null): string
{
    $preset = normalize_hero_size_preset($preset);
    $pdo->prepare(
        "INSERT INTO site_settings (setting_key, setting_value, updated_at, updated_by)
         VALUES ('hero_size_preset', :v, :u, :by)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
           updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)"
    )->execute([
        ':v' => $preset,
        ':u' => now_utc(),
        ':by' => $actorId,
    ]);
    return $preset;
}

/**
 * @param array<int,array<string,mixed>> $assignments
 */
function sync_hero_pages(PDO $pdo, int $heroId, array $assignments, int $actorId): void
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
            throw new RuntimeException('A page may feature a hero only when the assignment is active');
        }

        $existing = $pdo->prepare('SELECT id FROM hero_pages WHERE hero_id = :h AND page_id = :p AND deleted_at IS NULL LIMIT 1');
        $existing->execute([':h' => $heroId, ':p' => $pageId]);
        $exId = $existing->fetchColumn();
        if ($exId) {
            $pdo->prepare(
                'UPDATE hero_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $exId]);
            continue;
        }
        $old = $pdo->prepare('SELECT id FROM hero_pages WHERE hero_id = :h AND page_id = :p AND deleted_at IS NOT NULL ORDER BY id DESC LIMIT 1');
        $old->execute([':h' => $heroId, ':p' => $pageId]);
        $oldId = $old->fetchColumn();
        if ($oldId) {
            restore_row($pdo, 'hero_pages', (int) $oldId, $actorId);
            $pdo->prepare(
                'UPDATE hero_pages SET is_featured = :f, is_active = :a, sort_order = :s, updated_at = :u, updated_by = :by WHERE id = :id'
            )->execute([':f' => $isFeatured, ':a' => $isActive, ':s' => $sort, ':u' => now_utc(), ':by' => $actorId, ':id' => $oldId]);
        } else {
            $pdo->prepare(
                'INSERT INTO hero_pages (hero_id, page_id, is_featured, is_active, sort_order, created_at, created_by)
                 VALUES (:h, :p, :f, :a, :s, :c, :by)'
            )->execute([
                ':h' => $heroId, ':p' => $pageId, ':f' => $isFeatured, ':a' => $isActive,
                ':s' => $sort, ':c' => now_utc(), ':by' => $actorId,
            ]);
        }
    }

    $cur = $pdo->prepare('SELECT id, page_id FROM hero_pages WHERE hero_id = :h AND deleted_at IS NULL');
    $cur->execute([':h' => $heroId]);
    foreach ($cur->fetchAll() ?: [] as $row) {
        if (!isset($seen[(int) $row['page_id']])) {
            soft_delete_row($pdo, 'hero_pages', (int) $row['id'], $actorId);
        }
    }
}

/**
 * Normalize admin button rows. A label is required; the URL may be empty.
 * One row is a single button. Several rows are multiple buttons.
 *
 * @param array<int,mixed> $items
 * @return array<int,array{id:int,label:string,url:string,sort_order:int,is_active:int}>
 */
function hero_normalize_buttons(array $items): array
{
    $normalized = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        if ($label === '' && $url === '') {
            continue;
        }
        if ($label === '') {
            throw new RuntimeException('Each hero button needs button text');
        }
        if (mb_strlen($label) > 100) {
            throw new RuntimeException('Button text must be 100 characters or less');
        }
        if ($url !== '' && !is_safe_url($url)) {
            throw new RuntimeException('Invalid button URL');
        }
        if (mb_strlen($url) > 255) {
            throw new RuntimeException('Button URL must be 255 characters or less');
        }
        $normalized[] = [
            'id' => isset($item['id']) ? (int) $item['id'] : 0,
            'label' => $label,
            'url' => $url,
            'sort_order' => count($normalized),
            'is_active' => to_bool_int($item['is_active'] ?? true, 1),
        ];
    }
    return $normalized;
}

/**
 * @param array<int,array<string,mixed>> $buttons
 * @return array{label:string,url:string}
 */
function hero_first_button(array $buttons): array
{
    foreach ($buttons as $button) {
        if ((int) ($button['is_active'] ?? 1) !== 1) {
            continue;
        }
        $label = trim((string) ($button['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        return [
            'label' => $label,
            'url' => trim((string) ($button['url'] ?? '')),
        ];
    }
    return ['label' => '', 'url' => ''];
}

/**
 * Replace a hero's buttons. An empty list clears them.
 * Also keeps hero.button_text / button_url aligned with the first active button.
 *
 * @param array<int,array<string,mixed>> $buttons
 */
function sync_hero_buttons(PDO $pdo, int $heroId, array $buttons, int $actorId): void
{
    $normalized = hero_normalize_buttons($buttons);
    $keepIds = [];
    foreach ($normalized as $button) {
        $url = $button['url'] !== '' ? $button['url'] : null;
        if ($button['id'] > 0) {
            $chk = $pdo->prepare('SELECT id FROM hero_buttons WHERE id = :id AND hero_id = :h AND deleted_at IS NULL');
            $chk->execute([':id' => $button['id'], ':h' => $heroId]);
            if ($chk->fetchColumn()) {
                $pdo->prepare(
                    'UPDATE hero_buttons SET label = :label, url = :url, sort_order = :sort, is_active = :active,
                     updated_at = :u, updated_by = :by WHERE id = :id'
                )->execute([
                    ':label' => $button['label'],
                    ':url' => $url,
                    ':sort' => $button['sort_order'],
                    ':active' => $button['is_active'],
                    ':u' => now_utc(),
                    ':by' => $actorId,
                    ':id' => $button['id'],
                ]);
                $keepIds[] = $button['id'];
                continue;
            }
        }
        $pdo->prepare(
            'INSERT INTO hero_buttons (hero_id, label, url, sort_order, is_active, created_at, created_by)
             VALUES (:h, :label, :url, :sort, :active, :c, :by)'
        )->execute([
            ':h' => $heroId,
            ':label' => $button['label'],
            ':url' => $url,
            ':sort' => $button['sort_order'],
            ':active' => $button['is_active'],
            ':c' => now_utc(),
            ':by' => $actorId,
        ]);
        $keepIds[] = (int) $pdo->lastInsertId();
    }

    $cur = $pdo->prepare('SELECT id FROM hero_buttons WHERE hero_id = :h AND deleted_at IS NULL');
    $cur->execute([':h' => $heroId]);
    foreach ($cur->fetchAll(PDO::FETCH_COLUMN) ?: [] as $id) {
        if (!in_array((int) $id, $keepIds, true)) {
            soft_delete_row($pdo, 'hero_buttons', (int) $id, $actorId);
        }
    }

    $primary = hero_first_button($normalized);
    $pdo->prepare(
        'UPDATE hero SET button_text = :bt, button_url = :bu WHERE id = :id'
    )->execute([
        ':bt' => $primary['label'] !== '' ? $primary['label'] : null,
        ':bu' => $primary['url'] !== '' ? $primary['url'] : null,
        ':id' => $heroId,
    ]);
}

/**
 * @return array<int,array<string,mixed>>
 */
function hero_load_buttons(PDO $pdo, int $heroId, bool $activeOnly = false): array
{
    $grouped = hero_load_buttons_for($pdo, [$heroId], $activeOnly);
    return $grouped[$heroId] ?? [];
}

/**
 * @param array<int,int> $heroIds
 * @return array<int,array<int,array<string,mixed>>>
 */
function hero_load_buttons_for(PDO $pdo, array $heroIds, bool $activeOnly = false): array
{
    $heroIds = array_values(array_unique(array_filter(array_map('intval', $heroIds), static fn(int $id): bool => $id > 0)));
    if (!$heroIds || !table_exists($pdo, 'hero_buttons')) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($heroIds), '?'));
    $sql = "SELECT id, hero_id, label, url, sort_order, is_active
            FROM hero_buttons
            WHERE deleted_at IS NULL AND hero_id IN ({$placeholders})";
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($heroIds);
    $grouped = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $heroId = (int) $row['hero_id'];
        $grouped[$heroId][] = [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'url' => (string) ($row['url'] ?? ''),
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (int) $row['is_active'],
        ];
    }
    return $grouped;
}

/**
 * Buttons saved on the hero, or the original single button when no rows exist yet.
 *
 * @param array<string,mixed> $hero
 * @param array<int,array<string,mixed>> $buttons
 * @return array<int,array<string,mixed>>
 */
function hero_buttons_or_legacy(array $hero, array $buttons): array
{
    if ($buttons) {
        return $buttons;
    }
    $label = trim((string) ($hero['button_text'] ?? ''));
    if ($label === '') {
        return [];
    }
    return [[
        'id' => 0,
        'label' => $label,
        'url' => trim((string) ($hero['button_url'] ?? '')),
        'sort_order' => 0,
        'is_active' => 1,
    ]];
}
