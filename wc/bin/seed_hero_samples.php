#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Idempotent sample Hero data with generated images.
 * Usage: php wc/bin/seed_hero_samples.php
 */

$root = dirname(__DIR__);
require_once $root . '/includes/bootstrap.php';

function seed_make_hero_image(string $path, string $colorHex, string $label): void
{
    $w = 1600;
    $h = 900;
    $im = imagecreatetruecolor($w, $h);
    $r = hexdec(substr($colorHex, 0, 2));
    $g = hexdec(substr($colorHex, 2, 2));
    $b = hexdec(substr($colorHex, 4, 2));
    $bg = imagecolorallocate($im, $r, $g, $b);
    $fg = imagecolorallocate($im, 255, 255, 255);
    $accent = imagecolorallocate($im, max(0, $r - 30), max(0, $g - 30), max(0, $b - 30));
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    imagefilledrectangle($im, 0, (int) ($h * 0.62), $w, $h, $accent);
    $font = 5;
    $tw = imagefontwidth($font) * strlen($label);
    imagestring($im, $font, (int) (($w - $tw) / 2), (int) ($h * 0.38), $label, $fg);
    imagejpeg($im, $path, 85);
    imagedestroy($im);
    @chmod($path, 0644);
}

try {
    $pdo = db();
    $uploadDir = $root . '/uploads/hero';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Cannot create uploads/hero');
    }

    $homeId = (int) $pdo->query("SELECT id FROM pages WHERE page_type='website' AND slug='home' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
    $aboutId = (int) $pdo->query("SELECT id FROM pages WHERE page_type='website' AND slug='about' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
    if ($homeId < 1 || $aboutId < 1) {
        throw new RuntimeException('Website Home/About pages missing. Run migrate.php first.');
    }

    $samples = [
        [
            'key' => 'sample_home_welcome',
            'title' => 'Welcome to Our Website',
            'description' => 'A clean public experience powered by Website Core menus, pages, and hero content.',
            'button_text' => 'Explore About',
            'button_url' => '/about',
            'sort_order' => 10,
            'color' => '0f6a5a',
            'pages' => [
                ['page_id' => $homeId, 'is_featured' => 1, 'is_active' => 1, 'sort_order' => 10],
                ['page_id' => $aboutId, 'is_featured' => 0, 'is_active' => 1, 'sort_order' => 30],
            ],
        ],
        [
            'key' => 'sample_home_community',
            'title' => 'Built for Your Community',
            'description' => 'Highlight programs, stories, and updates with responsive hero slides.',
            'button_text' => 'Learn more',
            'button_url' => '/about',
            'sort_order' => 20,
            'color' => '1d3b34',
            'pages' => [
                ['page_id' => $homeId, 'is_featured' => 0, 'is_active' => 1, 'sort_order' => 20],
            ],
        ],
        [
            'key' => 'sample_home_action',
            'title' => 'Ready When You Are',
            'description' => 'Call visitors to action with clear messaging across every screen size.',
            'button_text' => 'Get started',
            'button_url' => '/about',
            'sort_order' => 30,
            'color' => 'c45c26',
            'pages' => [
                ['page_id' => $homeId, 'is_featured' => 0, 'is_active' => 1, 'sort_order' => 30],
            ],
        ],
        [
            'key' => 'sample_about_story',
            'title' => 'Our Story',
            'description' => 'About page featured hero — active and featured on About only.',
            'button_text' => 'Back to Home',
            'button_url' => '/home',
            'sort_order' => 10,
            'color' => '245b7a',
            'pages' => [
                ['page_id' => $aboutId, 'is_featured' => 1, 'is_active' => 1, 'sort_order' => 10],
            ],
        ],
        [
            'key' => 'sample_about_mission',
            'title' => 'Our Mission',
            'description' => 'Second About slide — active but not featured, still included in the slider.',
            'button_text' => null,
            'button_url' => null,
            'sort_order' => 20,
            'color' => '5b4a3a',
            'pages' => [
                ['page_id' => $aboutId, 'is_featured' => 0, 'is_active' => 1, 'sort_order' => 20],
            ],
        ],
    ];

    foreach ($samples as $sample) {
        $exists = $pdo->prepare(
            'SELECT id FROM hero WHERE title = :title AND deleted_at IS NULL LIMIT 1'
        );
        $exists->execute([':title' => $sample['title']]);
        $heroId = (int) ($exists->fetchColumn() ?: 0);

        if ($heroId < 1) {
            $filename = 'sample_' . preg_replace('/[^a-z0-9_]+/', '_', $sample['key']) . '_' . bin2hex(random_bytes(4)) . '.jpg';
            $abs = $uploadDir . '/' . $filename;
            seed_make_hero_image($abs, $sample['color'], $sample['title']);
            $rel = 'hero/' . $filename;

            $ins = $pdo->prepare(
                'INSERT INTO hero (title, description, image_path, button_text, button_url, sort_order, is_active, created_at)
                 VALUES (:title, :description, :image, :bt, :bu, :sort, 1, :created)'
            );
            $ins->execute([
                ':title' => $sample['title'],
                ':description' => $sample['description'],
                ':image' => $rel,
                ':bt' => $sample['button_text'],
                ':bu' => $sample['button_url'],
                ':sort' => $sample['sort_order'],
                ':created' => now_utc(),
            ]);
            $heroId = (int) $pdo->lastInsertId();
            echo "Created hero #{$heroId}: {$sample['title']}\n";
        } else {
            echo "Hero already exists #{$heroId}: {$sample['title']}\n";
        }

        foreach ($sample['pages'] as $assignment) {
            $chk = $pdo->prepare(
                'SELECT id FROM hero_pages WHERE hero_id = :h AND page_id = :p AND deleted_at IS NULL LIMIT 1'
            );
            $chk->execute([':h' => $heroId, ':p' => $assignment['page_id']]);
            if ($chk->fetchColumn()) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO hero_pages (hero_id, page_id, is_featured, is_active, sort_order, created_at)
                 VALUES (:h, :p, :f, :a, :s, :c)'
            )->execute([
                ':h' => $heroId,
                ':p' => $assignment['page_id'],
                ':f' => $assignment['is_featured'],
                ':a' => $assignment['is_active'],
                ':s' => $assignment['sort_order'],
                ':c' => now_utc(),
            ]);
            echo "  linked page {$assignment['page_id']} featured={$assignment['is_featured']}\n";
        }
    }

    echo "Sample hero data ready.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
