#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Create the first Super Admin user.
 * Usage:
 *   php bin/create_super_admin.php --email=admin@example.com --name="Super Admin" --password='...'
 *   php bin/create_super_admin.php --email=admin@example.com --name="Super Admin"
 *     (prompts for password if omitted)
 */

$root = dirname(__DIR__);
require_once $root . '/includes/bootstrap.php';

function arg_value(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, "--{$name}=")) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return null;
}

$email = arg_value($argv, 'email');
$name = arg_value($argv, 'name') ?? 'Super Admin';
$password = arg_value($argv, 'password');

if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create_super_admin.php --email=user@example.com [--name=\"Name\"] [--password=...]\n");
    exit(1);
}

if ($password === null || $password === '') {
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "Password required.\n");
        exit(1);
    }
    echo 'Enter password: ';
    system('stty -echo');
    $password = trim(fgets(STDIN) ?: '');
    system('stty echo');
    echo PHP_EOL . 'Confirm password: ';
    system('stty -echo');
    $confirm = trim(fgets(STDIN) ?: '');
    system('stty echo');
    echo PHP_EOL;
    if ($password === '' || $password !== $confirm) {
        fwrite(STDERR, "Passwords do not match or empty.\n");
        exit(1);
    }
}

if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

try {
    $pdo = db();
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE code_active = 'super_admin' LIMIT 1");
    $roleStmt->execute();
    $roleId = $roleStmt->fetchColumn();
    if (!$roleId) {
        fwrite(STDERR, "Super Admin role missing. Run migrate.php first.\n");
        exit(1);
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE email_active = :email LIMIT 1');
    $check->execute([':email' => strtolower($email)]);
    if ($check->fetchColumn()) {
        fwrite(STDERR, "A user with that email already exists.\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role_id, is_active, created_at)
         VALUES (:name, :email, :hash, :role_id, 1, :created)'
    );
    $stmt->execute([
        ':name' => $name,
        ':email' => strtolower($email),
        ':hash' => $hash,
        ':role_id' => (int) $roleId,
        ':created' => now_utc(),
    ]);
    $id = (int) $pdo->lastInsertId();
    audit_log($id, 'create', 'users', $id, ['via' => 'cli_bootstrap', 'email' => strtolower($email)]);
    echo "Super Admin created successfully (id={$id}, email=" . strtolower($email) . ").\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
