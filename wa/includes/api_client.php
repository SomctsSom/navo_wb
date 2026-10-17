<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Server-side HTTP client to Website Core.
 * Session token and CSRF stay in PHP session — never exposed to browser JS as privileged secrets
 * beyond the CSRF token needed for form posts (which is session-bound).
 */
function wa_api(string $method, string $path, array $data = [], array $files = []): array
{
    wa_start_session();
    $base = rtrim(wa_wc_api_url(), '/');
    $url = $base . '/' . ltrim($path, '/');

    $headers = ['Accept: application/json'];
    $token = $_SESSION['api_token'] ?? null;
    $csrf = $_SESSION['csrf_token'] ?? null;
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($csrf && strtoupper($method) !== 'GET') {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }

    $ch = curl_init();
    $method = strtoupper($method);

    if ($files) {
        $post = $data;
        foreach ($files as $field => $file) {
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                continue;
            }
            $post[$field] = new CURLFile($file['tmp_name'], $file['type'] ?? null, $file['name'] ?? 'upload');
        }
        if ($csrf) {
            $post['_csrf'] = $csrf;
            $post['csrf_token'] = $csrf;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    } elseif ($method === 'GET') {
        if ($data) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
        }
    } else {
        $payload = $data;
        if ($csrf) {
            $payload['_csrf'] = $csrf;
            $payload['csrf_token'] = $csrf;
        }
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['success' => false, 'message' => 'API connection failed: ' . $err, 'http_status' => 0, 'data' => null];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'Invalid API response', 'http_status' => $status, 'data' => null, 'raw' => $body];
    }
    $decoded['http_status'] = $status;
    return $decoded;
}

function wa_api_ok(array $response): bool
{
    return !empty($response['success']);
}

/**
 * Multipart POST for hero create/update (supports image upload).
 */
function wa_api_multipart(string $path, array $fields, array $file = [], string $fileField = 'image'): array
{
    wa_start_session();
    $base = rtrim(wa_wc_api_url(), '/');
    $url = $base . '/' . ltrim($path, '/');
    $headers = ['Accept: application/json'];
    $token = $_SESSION['api_token'] ?? null;
    $csrf = $_SESSION['csrf_token'] ?? null;
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($csrf) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
        $fields['_csrf'] = $csrf;
        $fields['csrf_token'] = $csrf;
    }
    // Single file (legacy): $_FILES['image'] style
    if ($file && isset($file['tmp_name']) && !is_array($file['tmp_name'] ?? null)) {
        if (is_uploaded_file((string) $file['tmp_name'])) {
            $fields[$fileField] = new CURLFile($file['tmp_name'], $file['type'] ?? null, $file['name'] ?? 'upload');
        }
    } elseif ($file) {
        // Multiple named files: ['image_top' => $_FILES['image_top'], ...]
        foreach ($file as $name => $one) {
            if (!is_array($one) || !isset($one['tmp_name']) || is_array($one['tmp_name'])) {
                continue;
            }
            if ((int) ($one['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (is_uploaded_file((string) $one['tmp_name'])) {
                $fields[(string) $name] = new CURLFile($one['tmp_name'], $one['type'] ?? null, $one['name'] ?? 'upload');
            }
        }
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fields,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        return ['success' => false, 'message' => 'API connection failed: ' . $err, 'http_status' => 0];
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'message' => 'Invalid API response', 'http_status' => $status];
    }
    $decoded['http_status'] = $status;
    return $decoded;
}
