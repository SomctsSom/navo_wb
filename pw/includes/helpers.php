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

function pw_api(string $path, array $query = []): array
{
    $base = rtrim((string) pw_env('WC_API_URL', 'http://127.0.0.1:8090'), '/');
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
    $base = rtrim((string) pw_env('WC_API_URL', 'http://127.0.0.1:8090'), '/');
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
    $base = rtrim((string) pw_env('PW_BASE_URL', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function pw_public_href(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url) === 1) {
        return $url;
    }
    return pw_url($url);
}
