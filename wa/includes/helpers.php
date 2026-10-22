<?php
declare(strict_types=1);

function wa_load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $path = dirname(__DIR__) . '/.env';
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
        putenv(trim($k) . '=' . trim($v));
    }
}

function wa_env(string $key, mixed $default = null): mixed
{
    wa_load_env();
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

function wa_e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function wa_script_base_path(): string
{
    $configured = rtrim((string) wa_env('WA_BASE_URL', ''), '/');
    if ($configured !== '') {
        $urlPath = parse_url($configured, PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '' && $urlPath !== '/') {
            return rtrim(str_replace('\\', '/', $urlPath), '/');
        }
        return '';
    }

    // PHP built-in server sets SCRIPT_NAME to the request URI with a router.
    if (PHP_SAPI === 'cli-server') {
        return '';
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '' || !str_ends_with(strtolower($script), '.php')) {
        return '';
    }
    $dir = dirname($script);
    if ($dir === '/' || $dir === '\\' || $dir === '.' || $dir === '') {
        return '';
    }
    return rtrim($dir, '/');
}

function wa_request_is_local_host(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = explode(':', $host)[0];
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function wa_url_looks_local(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
    return $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function wa_request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

function wa_request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = '/' . trim(str_replace('\\', '/', $path), '/');
    if ($path === '//') {
        $path = '/';
    }
    $base = wa_script_base_path();
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

function wa_base_url(): string
{
    $configured = rtrim((string) wa_env('WA_BASE_URL', ''), '/');
    if ($configured !== '' && !(wa_url_looks_local($configured) && !wa_request_is_local_host())) {
        return $configured;
    }
    return wa_request_origin() . wa_script_base_path();
}

function wa_wc_api_url(): string
{
    $configured = rtrim((string) wa_env('WC_API_URL', ''), '/');
    if ($configured !== '' && !(wa_url_looks_local($configured) && !wa_request_is_local_host())) {
        return $configured;
    }
    $origin = wa_request_origin();
    $basePath = wa_script_base_path();
    if ($basePath !== '' && preg_match('#/wa/public$#', $basePath) === 1) {
        return $origin . preg_replace('#/wa/public$#', '/wc/public', $basePath);
    }
    if ($configured !== '') {
        return $configured;
    }
    return $origin . '/wc/public';
}

function wa_url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return wa_base_url() . ($path === '/' ? '' : $path);
}

function wa_redirect(string $path): never
{
    header('Location: ' . wa_url($path));
    exit;
}

function wa_flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function wa_consume_flash(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function wa_start_session(): void
{
    wa_load_env();
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name((string) wa_env('SESSION_NAME', 'wms_wa_session'));
    $cookiePath = wa_script_base_path();
    if ($cookiePath === '') {
        $cookiePath = '/';
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => in_array(strtolower((string) wa_env('COOKIE_SECURE', '0')), ['1', 'true'], true)
            || !wa_request_is_local_host(),
        'httponly' => true,
        'samesite' => (string) wa_env('COOKIE_SAMESITE', 'Lax'),
    ]);
    session_start();
}
