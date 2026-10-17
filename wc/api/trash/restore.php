<?php
declare(strict_types=1);

require_method('POST');
$actor = auth_require_permission('trash.restore');
auth_verify_csrf($actor);

$input = request_input();
$module = (string) ($input['module'] ?? '');
$id = (int) ($input['id'] ?? 0);

$map = [
    'users' => ['perm' => 'users.restore', 'endpoint_hint' => 'users'],
    'roles' => ['perm' => 'roles.restore', 'endpoint_hint' => 'roles'],
    'permissions' => ['perm' => 'permissions.restore', 'endpoint_hint' => 'permissions'],
    'menus' => ['perm' => 'menus.restore', 'endpoint_hint' => 'menus'],
    'pages' => ['perm' => 'pages.restore', 'endpoint_hint' => 'pages'],
    'hero' => ['perm' => 'hero.restore', 'endpoint_hint' => 'hero'],
    'services' => ['perm' => 'services.restore', 'endpoint_hint' => 'services'],
    'profile_overviews' => ['perm' => 'profile_overviews.restore', 'endpoint_hint' => 'profile-overviews'],
    'vision_missions' => ['perm' => 'vision_missions.restore', 'endpoint_hint' => 'vision-missions'],
    'features' => ['perm' => 'features.restore', 'endpoint_hint' => 'features'],
    'testimonials' => ['perm' => 'testimonials.restore', 'endpoint_hint' => 'testimonials'],
    'cta_banners' => ['perm' => 'cta_banners.restore', 'endpoint_hint' => 'cta-banners'],
    'partners' => ['perm' => 'partners.restore', 'endpoint_hint' => 'partners'],
    'social_links' => ['perm' => 'contact_social.restore', 'endpoint_hint' => 'social-links'],
    'contact_infos' => ['perm' => 'contact_social.restore', 'endpoint_hint' => 'contact-infos'],
];

if (!isset($map[$module]) || $id < 1) {
    json_error('Invalid module or id', 422);
}
if (!auth_can($actor, $map[$module]['perm'])) {
    json_error('Forbidden', 403);
}

$table = $module;
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id AND deleted_at IS NOT NULL");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    json_error('Record not found in trash', 404);
}

// Uniqueness checks for key modules
if ($module === 'users') {
    $dup = $pdo->prepare('SELECT id FROM users WHERE email_active = :e LIMIT 1');
    $dup->execute([':e' => $row['email']]);
    if ($dup->fetchColumn()) {
        json_error('Cannot restore: email already in use', 422);
    }
} elseif ($module === 'roles') {
    $dup = $pdo->prepare('SELECT id FROM roles WHERE code_active = :c LIMIT 1');
    $dup->execute([':c' => $row['code']]);
    if ($dup->fetchColumn()) {
        json_error('Cannot restore: role code already in use', 422);
    }
} elseif ($module === 'permissions') {
    $dup = $pdo->prepare('SELECT id FROM permissions WHERE code_active = :c LIMIT 1');
    $dup->execute([':c' => $row['code']]);
    if ($dup->fetchColumn()) {
        json_error('Cannot restore: permission code already in use', 422);
    }
} elseif ($module === 'pages') {
    $dup = $pdo->prepare('SELECT id FROM pages WHERE type_slug_active = :k LIMIT 1');
    $dup->execute([':k' => $row['page_type'] . ':' . $row['slug']]);
    if ($dup->fetchColumn()) {
        json_error('Cannot restore: slug already in use', 422);
    }
}

if (!restore_row($pdo, $table, $id, (int) $actor['id'])) {
    json_error('Restore failed', 500);
}
audit_log((int) $actor['id'], 'restore', $module, $id, ['via' => 'trash']);
json_ok(['id' => $id, 'module' => $module], 'Restored');
