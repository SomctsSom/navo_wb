<?php
declare(strict_types=1);

require_method('GET');

$configured = [
    'host' => (string) env('DB_HOST', ''),
    'port' => (string) env('DB_PORT', '3306'),
    'database' => (string) env('DB_DATABASE', ''),
    'username' => (string) env('DB_USERNAME', ''),
    'password_set' => trim((string) env('DB_PASSWORD', '')) !== '',
];

try {
    $pdo = db();
    $pdo->query('SELECT 1');
    $pages = 0;
    try {
        $pages = (int) $pdo->query(
            "SELECT COUNT(*) FROM pages WHERE page_type = 'website' AND deleted_at IS NULL AND is_active = 1"
        )->fetchColumn();
    } catch (Throwable) {
        $pages = -1;
    }
    json_ok([
        'database' => 'ok',
        'configured' => [
            'host' => $configured['host'],
            'port' => $configured['port'],
            'database' => $configured['database'],
            'username' => $configured['username'],
            'password_set' => $configured['password_set'],
        ],
        'active_website_pages' => $pages,
    ]);
} catch (Throwable $e) {
    json_error('Database connection failed: ' . $e->getMessage(), 500, [
        'configured' => [
            'host' => $configured['host'],
            'port' => $configured['port'],
            'database' => $configured['database'],
            'username' => $configured['username'],
            'password_set' => $configured['password_set'],
        ],
    ]);
}
