<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';

/**
 * Session / auth for Core API.
 * WA stores the opaque session token server-side and sends:
 *   Authorization: Bearer <token>
 *   X-CSRF-Token: <csrf>
 */

function auth_create_session(int $userId): array
{
    $pdo = db();
    $token = bin2hex(random_bytes(32));
    $csrf = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $lifetime = (int) app_config('session_lifetime', 7200);
    $expires = gmdate('Y-m-d H:i:s', time() + $lifetime);

    $stmt = $pdo->prepare(
        'INSERT INTO api_sessions (token_hash, user_id, csrf_token, ip_address, user_agent, expires_at, created_at, last_seen_at)
         VALUES (:token_hash, :user_id, :csrf, :ip, :ua, :expires, :created, :seen)'
    );
    $stmt->execute([
        ':token_hash' => $tokenHash,
        ':user_id' => $userId,
        ':csrf' => $csrf,
        ':ip' => client_ip(),
        ':ua' => client_user_agent(),
        ':expires' => $expires,
        ':created' => now_utc(),
        ':seen' => now_utc(),
    ]);

    return [
        'token' => $token,
        'csrf_token' => $csrf,
        'expires_at' => $expires,
    ];
}

function auth_extract_bearer(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m) === 1) {
        return $m[1];
    }
    $alt = $_SERVER['HTTP_X_WMS_SESSION'] ?? '';
    return $alt !== '' ? $alt : null;
}

function auth_current_session(): ?array
{
    static $cached = false;
    static $session = null;
    if ($cached) {
        return $session;
    }
    $cached = true;

    $token = auth_extract_bearer();
    if ($token === null || $token === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT s.*, u.id AS uid, u.name, u.email, u.role_id, u.is_active, u.deleted_at AS user_deleted,
                r.code AS role_code, r.name AS role_name, r.deleted_at AS role_deleted
         FROM api_sessions s
         JOIN users u ON u.id = s.user_id
         JOIN roles r ON r.id = u.role_id
         WHERE s.token_hash = :hash
           AND s.revoked_at IS NULL
           AND s.expires_at > :now
         LIMIT 1'
    );
    $stmt->execute([
        ':hash' => hash('sha256', $token),
        ':now' => now_utc(),
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if ((int) $row['is_active'] !== 1 || $row['user_deleted'] !== null || $row['role_deleted'] !== null) {
        return null;
    }

    // Sliding expiry: keep active admins authenticated while they use the API.
    $lifetime = (int) app_config('session_lifetime', 7200);
    $newExpires = gmdate('Y-m-d H:i:s', time() + $lifetime);
    $upd = $pdo->prepare('UPDATE api_sessions SET last_seen_at = :seen, expires_at = :expires WHERE id = :id');
    $upd->execute([
        ':seen' => now_utc(),
        ':expires' => $newExpires,
        ':id' => $row['id'],
    ]);
    $row['expires_at'] = $newExpires;

    $session = $row;
    return $session;
}

function auth_user(): ?array
{
    $s = auth_current_session();
    if ($s === null) {
        return null;
    }
    return [
        'id' => (int) $s['uid'],
        'name' => $s['name'],
        'email' => $s['email'],
        'role_id' => (int) $s['role_id'],
        'role_code' => $s['role_code'],
        'role_name' => $s['role_name'],
        'csrf_token' => $s['csrf_token'],
        'session_id' => (int) $s['id'],
    ];
}

function auth_require_user(): array
{
    $user = auth_user();
    if ($user === null) {
        json_error('Unauthenticated', 401);
    }
    return $user;
}

function auth_permissions(int $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT p.code
         FROM users u
         JOIN role_permissions rp ON rp.role_id = u.role_id AND rp.deleted_at IS NULL
         JOIN permissions p ON p.id = rp.permission_id AND p.deleted_at IS NULL
         WHERE u.id = :id AND u.deleted_at IS NULL'
    );
    $stmt->execute([':id' => $userId]);
    $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $cache[$userId] = array_values(array_unique($codes ?: []));
    return $cache[$userId];
}

function auth_can(array $user, string $permission): bool
{
    if (($user['role_code'] ?? '') === 'super_admin') {
        return true;
    }
    return in_array($permission, auth_permissions((int) $user['id']), true);
}

function auth_require_permission(string $permission): array
{
    $user = auth_require_user();
    if (!auth_can($user, $permission)) {
        json_error('Forbidden', 403);
    }
    return $user;
}

function auth_verify_csrf(?array $user = null): void
{
    $user = $user ?? auth_user();
    if ($user === null) {
        json_error('Unauthenticated', 401);
    }
    $method = request_method();
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($provided === '') {
        $input = request_input();
        $name = (string) app_config('csrf_token_name', '_csrf');
        $provided = (string) ($input[$name] ?? $input['csrf_token'] ?? '');
    }
    if ($provided === '' || !hash_equals((string) $user['csrf_token'], $provided)) {
        json_error('Invalid CSRF token', 419);
    }
}

function auth_revoke_session(?string $token = null): void
{
    $token = $token ?? auth_extract_bearer();
    if ($token === null) {
        return;
    }
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE api_sessions SET revoked_at = :now WHERE token_hash = :hash AND revoked_at IS NULL');
    $stmt->execute([':now' => now_utc(), ':hash' => hash('sha256', $token)]);
}

function auth_login_rate_limited(string $identifier): bool
{
    $pdo = db();
    $max = (int) app_config('login_max_attempts', 5);
    $window = (int) app_config('login_lockout_seconds', 900);
    $since = gmdate('Y-m-d H:i:s', time() - $window);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE identifier = :id AND ip_address = :ip AND succeeded = 0 AND attempted_at >= :since'
    );
    $stmt->execute([
        ':id' => $identifier,
        ':ip' => client_ip(),
        ':since' => $since,
    ]);
    return (int) $stmt->fetchColumn() >= $max;
}

function auth_record_login_attempt(string $identifier, bool $succeeded): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (identifier, ip_address, attempted_at, succeeded)
         VALUES (:id, :ip, :at, :ok)'
    );
    $stmt->execute([
        ':id' => $identifier,
        ':ip' => client_ip(),
        ':at' => now_utc(),
        ':ok' => $succeeded ? 1 : 0,
    ]);
}

function auth_count_active_super_admins(?int $excludeUserId = null): int
{
    $pdo = db();
    $sql = 'SELECT COUNT(*) FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.deleted_at IS NULL AND u.is_active = 1
              AND r.deleted_at IS NULL AND r.code = :code';
    $params = [':code' => 'super_admin'];
    if ($excludeUserId !== null) {
        $sql .= ' AND u.id <> :uid';
        $params[':uid'] = $excludeUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function auth_is_super_admin_user(int $userId): bool
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT 1 FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.deleted_at IS NULL AND r.code = :code LIMIT 1'
    );
    $stmt->execute([':id' => $userId, ':code' => 'super_admin']);
    return (bool) $stmt->fetchColumn();
}

function auth_protect_last_super_admin(int $targetUserId, string $operation): void
{
    if (!auth_is_super_admin_user($targetUserId)) {
        return;
    }
    if (auth_count_active_super_admins($targetUserId) > 0) {
        return;
    }
    // This is the last active Super Admin
    json_error("Cannot {$operation} the last active Super Admin", 422);
}
