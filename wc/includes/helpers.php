<?php
declare(strict_types=1);

/**
 * Shared helpers for Website Core.
 */

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function format_display_time(?string $utcDatetime): string
{
    if ($utcDatetime === null || $utcDatetime === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($utcDatetime, new DateTimeZone('UTC'));
        $tz = (string) app_config('display_timezone', 'Africa/Mogadishu');
        return $dt->setTimezone(new DateTimeZone($tz))->format('Y-m-d H:i:s T');
    } catch (Throwable) {
        return $utcDatetime;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(mixed $data = null, string $message = 'OK', int $status = 200): never
{
    json_response([
        'success' => true,
        'message' => $message,
        'data' => $data,
    ], $status);
}

function json_error(string $message, int $status = 400, mixed $errors = null): never
{
    $payload = [
        'success' => false,
        'message' => $message,
    ];
    if ($errors !== null) {
        $payload['errors'] = $errors;
    }
    json_response($payload, $status);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Directory of the front controller, e.g. "/wc/public" on shared hosting.
 */
function wc_script_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($script);
    if ($dir === '/' || $dir === '\\' || $dir === '.' || $dir === '') {
        return '';
    }
    return rtrim($dir, '/');
}

function wc_request_is_local_host(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = explode(':', $host)[0];
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function wc_url_looks_local(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
    return $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function wc_request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

/**
 * Public Website Core base URL (no trailing slash).
 */
function wc_public_base_url(): string
{
    $configured = rtrim((string) app_config('wc_base_url', ''), '/');
    if ($configured !== '' && !(wc_url_looks_local($configured) && !wc_request_is_local_host())) {
        return $configured;
    }
    return wc_request_origin() . wc_script_base_path();
}

/**
 * Normalize REQUEST_URI path relative to this app's public folder.
 */
function wc_request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = '/' . trim(str_replace('\\', '/', $path), '/');
    if ($path === '//') {
        $path = '/';
    }
    $base = wc_script_base_path();
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base)) ?: '/';
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }
    }
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    return $path === '' ? '/' : $path;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST ?: [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $_POST ?: [];
    }
    return $decoded;
}

function request_input(): array
{
    $json = request_json();
    return array_merge($_GET, $_POST, $json);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function client_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

function is_safe_url(?string $url): bool
{
    if ($url === null || $url === '') {
        return true;
    }
    $url = trim($url);
    if (str_starts_with($url, '/')) {
        return !str_contains($url, '..');
    }
    if (preg_match('#^(https?)://#i', $url) !== 1) {
        return false;
    }
    if (preg_match('#^(javascript|data|vbscript):#i', $url) === 1) {
        return false;
    }
    return true;
}

function require_method(string ...$methods): void
{
    $current = request_method();
    $allowed = array_map('strtoupper', $methods);
    if (!in_array($current, $allowed, true)) {
        json_error('Method not allowed', 405);
    }
}

function paginate_params(array $input, int $defaultPerPage = 20, int $maxPerPage = 100): array
{
    $page = max(1, (int) ($input['page'] ?? 1));
    $perPage = min($maxPerPage, max(1, (int) ($input['per_page'] ?? $defaultPerPage)));
    $offset = ($page - 1) * $perPage;
    return [$page, $perPage, $offset];
}

function to_bool_int(mixed $value, int $default = 0): int
{
    if ($value === null) {
        return $default;
    }
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_int($value) || is_float($value)) {
        return ((int) $value) !== 0 ? 1 : 0;
    }
    $s = strtolower(trim((string) $value));
    if (in_array($s, ['1', 'true', 'yes', 'on'], true)) {
        return 1;
    }
    if (in_array($s, ['0', 'false', 'no', 'off', ''], true)) {
        return 0;
    }
    return $default;
}

function soft_delete_row(PDO $pdo, string $table, int $id, ?int $actorId): bool
{
    $sql = "UPDATE {$table} SET deleted_at = :deleted_at, deleted_by = :deleted_by, updated_at = :updated_at, updated_by = :updated_by
            WHERE id = :id AND deleted_at IS NULL";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':deleted_at' => now_utc(),
        ':deleted_by' => $actorId,
        ':updated_at' => now_utc(),
        ':updated_by' => $actorId,
        ':id' => $id,
    ]) && $stmt->rowCount() > 0;
}

function restore_row(PDO $pdo, string $table, int $id, ?int $actorId): bool
{
    $sql = "UPDATE {$table} SET deleted_at = NULL, deleted_by = NULL, updated_at = :updated_at, updated_by = :updated_by
            WHERE id = :id AND deleted_at IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':updated_at' => now_utc(),
        ':updated_by' => $actorId,
        ':id' => $id,
    ]) && $stmt->rowCount() > 0;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1');
    $stmt->execute([':t' => $table]);
    return (bool) $stmt->fetchColumn();
}
