<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Secure image upload helper for Hero and future modules.
 */

function secure_upload_image(array $file, string $subdir = 'hero'): array
{
    $configMax = (int) app_config('upload_max_bytes', 5_242_880);
    $maxW = (int) app_config('upload_max_width', 4000);
    $maxH = (int) app_config('upload_max_height', 4000);

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $configMax) {
        return ['ok' => false, 'error' => 'File exceeds maximum allowed size.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Only JPEG, PNG, WebP, or GIF images are allowed.'];
    }

    $imageInfo = @getimagesize($tmp);
    if ($imageInfo === false) {
        return ['ok' => false, 'error' => 'File is not a valid image.'];
    }
    [$width, $height] = $imageInfo;
    if ($width < 1 || $height < 1 || $width > $maxW || $height > $maxH) {
        return ['ok' => false, 'error' => 'Image dimensions are outside allowed limits.'];
    }

    $ext = $allowed[$mime];
    $subdir = preg_replace('/[^a-z0-9_-]/i', '', $subdir) ?: 'hero';
    $baseDir = dirname(__DIR__) . '/uploads/' . $subdir;
    if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
        return ['ok' => false, 'error' => 'Upload directory is not writable.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $baseDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'error' => 'Failed to store uploaded file.'];
    }
    @chmod($dest, 0644);

    $relative = $subdir . '/' . $filename;
    return [
        'ok' => true,
        'relative_path' => $relative,
        'absolute_path' => $dest,
        'mime' => $mime,
        'width' => $width,
        'height' => $height,
    ];
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by extension.',
        default => 'Upload failed.',
    };
}

/**
 * Resolve a stored relative path under uploads/ safely.
 */
function secure_resolve_upload_path(string $relative): ?string
{
    $relative = str_replace('\\', '/', $relative);
    $relative = ltrim($relative, '/');
    if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
        return null;
    }
    if (!preg_match('#^[a-z0-9_-]+/[a-zA-Z0-9._-]+$#', $relative)) {
        return null;
    }
    $base = realpath(dirname(__DIR__) . '/uploads');
    if ($base === false) {
        return null;
    }
    $full = realpath($base . '/' . $relative);
    if ($full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }
    if (!is_file($full)) {
        return null;
    }
    return $full;
}

function secure_public_media_url(string $relative): string
{
    $base = rtrim(wc_public_base_url(), '/');
    return $base . '/media/' . ltrim(str_replace('\\', '/', $relative), '/');
}
