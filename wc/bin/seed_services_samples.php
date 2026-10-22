#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Idempotent English sample Services (with and without optional items).
 * Usage: php wc/bin/seed_services_samples.php
 */

$root = dirname(__DIR__);
require_once $root . '/includes/bootstrap.php';

function seed_service_image(string $path, string $colorHex, string $label): void
{
    $w = 1200;
    $h = 750;
    $im = imagecreatetruecolor($w, $h);
    $r = hexdec(substr($colorHex, 0, 2));
    $g = hexdec(substr($colorHex, 2, 2));
    $b = hexdec(substr($colorHex, 4, 2));
    $bg = imagecolorallocate($im, $r, $g, $b);
    $fg = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    $font = 5;
    $tw = imagefontwidth($font) * strlen($label);
    imagestring($im, $font, (int) (($w - $tw) / 2), (int) ($h * 0.45), $label, $fg);
    imagejpeg($im, $path, 85);
    imagedestroy($im);
    @chmod($path, 0644);
}

try {
    $pdo = db();
    $uploadDir = $root . '/uploads/services';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Cannot create uploads/services');
    }

    $homeId = (int) $pdo->query("SELECT id FROM pages WHERE page_type='website' AND slug='home' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
    if ($homeId < 1) {
        throw new RuntimeException('Home page missing');
    }

    // Soft-delete old Somali/test sample titles so English samples can take over cleanly
    $legacyTitles = [
        'Adeegga Macmiilka', 'Taageero Teknik', 'Qorsheyn Ganacsi', 'Tijaabo Aan Liis Lahayn',
    ];
    foreach ($legacyTitles as $legacy) {
        $now = now_utc();
        $pdo->prepare(
            'UPDATE services SET deleted_at = :deleted_at, updated_at = :updated_at WHERE title = :t AND deleted_at IS NULL'
        )->execute([':deleted_at' => $now, ':updated_at' => $now, ':t' => $legacy]);
    }

    $samples = [
        [
            'title' => 'Customer Support',
            'description' => 'Fast, friendly customer support tailored to your needs.',
            'icon_class' => 'fa-truck',
            'button_text' => 'Contact us',
            'button_url' => '/about',
            'sort_order' => 10,
            'color' => '0f6a5a',
            'featured' => 1,
            'items' => [
                ['title' => '24/7 phone line', 'body' => 'Always-on telephone support'],
                ['title' => 'Live chat', 'body' => 'Quick answers when you need them'],
                ['title' => 'Order tracking', 'body' => 'Follow your request status'],
            ],
        ],
        [
            'title' => 'Technical Support',
            'description' => 'Technical solutions and maintenance for your systems.',
            'icon_class' => 'fa-truck',
            'button_text' => null,
            'button_url' => null,
            'sort_order' => 20,
            'color' => '1d3b34',
            'featured' => 0,
            'items' => [
                ['title' => 'Installation', 'body' => ''],
                ['title' => 'Maintenance', 'body' => ''],
                ['title' => 'Training', 'body' => 'Staff workshops'],
            ],
        ],
        [
            'title' => 'Business Planning',
            'description' => 'A concise planning service — no list items under the description (optional).',
            'icon_class' => 'fa-truck',
            'button_text' => 'Read more',
            'button_url' => '/about',
            'sort_order' => 30,
            'color' => 'c45c26',
            'featured' => 0,
            'items' => [],
        ],
    ];

    foreach ($samples as $sample) {
        $exists = $pdo->prepare('SELECT id FROM services WHERE title = :t AND deleted_at IS NULL LIMIT 1');
        $exists->execute([':t' => $sample['title']]);
        $serviceId = (int) ($exists->fetchColumn() ?: 0);

        if ($serviceId < 1) {
            $filename = 'sample_' . bin2hex(random_bytes(6)) . '.jpg';
            $abs = $uploadDir . '/' . $filename;
            seed_service_image($abs, $sample['color'], $sample['title']);
            $rel = 'services/' . $filename;
            $ins = $pdo->prepare(
                'INSERT INTO services (title, description, icon_class, image_path, button_text, button_url, sort_order, is_active, created_at)
                 VALUES (:title, :description, :icon, :image, :bt, :bu, :sort, 1, :created)'
            );
            $ins->execute([
                ':title' => $sample['title'],
                ':description' => $sample['description'],
                ':icon' => $sample['icon_class'] ?? 'fa-truck',
                ':image' => $rel,
                ':bt' => $sample['button_text'],
                ':bu' => $sample['button_url'],
                ':sort' => $sample['sort_order'],
                ':created' => now_utc(),
            ]);
            $serviceId = (int) $pdo->lastInsertId();
            echo "Created service #{$serviceId}: {$sample['title']}\n";
        } else {
            $pdo->prepare(
                'UPDATE services SET description = :d, icon_class = :icon, button_text = :bt, button_url = :bu, sort_order = :sort_order, is_active = 1, updated_at = :u WHERE id = :id'
            )->execute([
                ':d' => $sample['description'],
                ':icon' => $sample['icon_class'] ?? 'fa-truck',
                ':bt' => $sample['button_text'],
                ':bu' => $sample['button_url'],
                ':sort_order' => $sample['sort_order'],
                ':u' => now_utc(),
                ':id' => $serviceId,
            ]);
            echo "Updated service #{$serviceId}: {$sample['title']}\n";
        }

        sync_service_pages($pdo, $serviceId, [[
            'page_id' => $homeId,
            'is_featured' => $sample['featured'],
            'is_active' => 1,
            'sort_order' => $sample['sort_order'],
        ]], 0);

        sync_service_items($pdo, $serviceId, $sample['items'], 0);
        echo "  items=" . count($sample['items']) . " linked to home\n";
    }

    echo "English sample services ready.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
