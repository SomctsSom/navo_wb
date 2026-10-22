<?php
declare(strict_types=1);

function pw_load_env(): void
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
    }
}

function pw_env(string $key, mixed $default = null): mixed
{
    pw_load_env();
    $v = $_ENV[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

function pw_e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trusted/sanitized rich HTML from Core (already allowlisted). */
function pw_rich(?string $html): string
{
    return (string) $html;
}

/**
 * Directory of the front controller, e.g. "/pw/public" when hosted in a subdirectory.
 *
 * Prefer PW_BASE_URL path. PHP's built-in server sets SCRIPT_NAME to the
 * request URI when using a router, which would incorrectly strip path prefixes.
 */
function pw_script_base_path(): string
{
    $configured = rtrim((string) pw_env('PW_BASE_URL', ''), '/');
    if ($configured !== '') {
        $urlPath = parse_url($configured, PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '' && $urlPath !== '/') {
            return rtrim(str_replace('\\', '/', $urlPath), '/');
        }
        return '';
    }

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

/**
 * Normalize REQUEST_URI path relative to this app's public folder.
 */
function pw_request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = '/' . trim(str_replace('\\', '/', $path), '/');
    if ($path === '//') {
        $path = '/';
    }
    $base = pw_script_base_path();
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

function pw_request_is_local_host(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = explode(':', $host)[0];
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function pw_request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

function pw_url_looks_local(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
    return $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

/**
 * Public site base URL (no trailing slash). Auto-detects subdirectory hosts.
 */
function pw_base_url(): string
{
    $configured = rtrim((string) pw_env('PW_BASE_URL', ''), '/');
    if ($configured !== '' && !(pw_url_looks_local($configured) && !pw_request_is_local_host())) {
        return $configured;
    }
    $origin = pw_request_origin();
    $basePath = pw_script_base_path();
    return $origin . $basePath;
}

/**
 * Website Core API base for server-side calls.
 */
function pw_wc_api_url(): string
{
    $configured = rtrim((string) pw_env('WC_API_URL', ''), '/');
    if ($configured !== '' && !(pw_url_looks_local($configured) && !pw_request_is_local_host())) {
        return $configured;
    }
    // Same host sibling: /pw/public → /wc/public
    $origin = pw_request_origin();
    $basePath = pw_script_base_path();
    if ($basePath !== '' && preg_match('#/pw/public$#', $basePath) === 1) {
        return $origin . preg_replace('#/pw/public$#', '/wc/public', $basePath);
    }
    if ($configured !== '') {
        return $configured;
    }
    return $origin . '/wc/public';
}

function pw_api(string $path, array $query = []): array
{
    $base = pw_wc_api_url();
    $url = $base . '/' . ltrim($path, '/');
    if ($query) {
        $url .= '?' . http_build_query($query);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'API error', 'http_status' => $status];
    }
    $decoded['http_status'] = $status;
    return $decoded;
}

/**
 * @param array<string,mixed> $data
 */
function pw_api_post(string $path, array $data = []): array
{
    $base = pw_wc_api_url();
    $url = $base . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'API error', 'http_status' => $status];
    }
    $decoded['http_status'] = $status;
    return $decoded;
}

function pw_url(string $path = '/'): string
{
    $base = pw_base_url();
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function pw_is_external_url(string $url): bool
{
    return preg_match('#^https?://#i', trim($url)) === 1;
}

function pw_public_href(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (pw_is_external_url($url)) {
        return $url;
    }
    return pw_url($url);
}
