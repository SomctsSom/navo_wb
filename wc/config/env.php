<?php
declare(strict_types=1);

/**
 * Load environment variables from wc/.env into $_ENV and putenv.
 */
function wms_load_env(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = $path ?? dirname(__DIR__) . '/.env';
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Strip unquoted inline comments (keep # inside "..." or '...')
        if (
            $value !== ''
            && !str_starts_with($value, '"')
            && !str_starts_with($value, "'")
            && str_contains($value, '#')
        ) {
            $hashPos = strpos($value, '#');
            if ($hashPos !== false) {
                $value = rtrim(substr($value, 0, $hashPos));
            }
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env(string $key, mixed $default = null): mixed
{
    wms_load_env();
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env($key, null);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function env_int(string $key, int $default = 0): int
{
    $value = env($key, null);
    if ($value === null || $value === '') {
        return $default;
    }
    return (int) $value;
}
