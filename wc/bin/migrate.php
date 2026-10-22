#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Apply migrations and seeds.
 * Usage: php bin/migrate.php
 */

$root = dirname(__DIR__);
require_once $root . '/config/env.php';
require_once $root . '/config/database.php';

wms_load_env();

$host = (string) env('DB_HOST', '127.0.0.1');
$port = (string) env('DB_PORT', '3306');
$name = (string) env('DB_DATABASE', 'wms_core');
$user = (string) env('DB_USERNAME', 'root');
$pass = (string) env('DB_PASSWORD', '');
$charset = (string) env('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset={$charset}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$name}`");
    $pdo->exec("SET time_zone = '+00:00'");

    $files = array_merge(
        glob($root . '/database/migrations/*.sql') ?: [],
        glob($root . '/database/seeds/*.sql') ?: []
    );
    sort($files);

    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            fwrite(STDERR, "Cannot read {$file}\n");
            exit(1);
        }
        echo 'Applying ' . basename($file) . "...\n";
        $pdo->exec($sql);
    }

    echo "Migrations and seeds applied successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
