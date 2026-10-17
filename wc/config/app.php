<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

wms_load_env();

date_default_timezone_set((string) env('APP_TIMEZONE', 'UTC'));

return [
    'env' => (string) env('APP_ENV', 'local'),
    'name' => (string) env('APP_NAME', 'Website Management System'),
    'debug' => env_bool('APP_DEBUG', false),
    'timezone' => (string) env('APP_TIMEZONE', 'UTC'),
    'display_timezone' => (string) env('APP_DISPLAY_TIMEZONE', 'Africa/Mogadishu'),
    'wc_base_url' => rtrim((string) env('WC_BASE_URL', 'http://127.0.0.1:8090'), '/'),
    'wa_base_url' => rtrim((string) env('WA_BASE_URL', 'http://127.0.0.1:8091'), '/'),
    'pw_base_url' => rtrim((string) env('PW_BASE_URL', 'http://127.0.0.1:8092'), '/'),
    'session_name' => (string) env('SESSION_NAME', 'wms_session'),
    'session_lifetime' => env_int('SESSION_LIFETIME', 7200),
    'csrf_token_name' => (string) env('CSRF_TOKEN_NAME', '_csrf'),
    'login_max_attempts' => env_int('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_seconds' => env_int('LOGIN_LOCKOUT_SECONDS', 900),
    'menu_max_depth' => env_int('MENU_MAX_DEPTH', 3),
    'upload_max_bytes' => env_int('UPLOAD_MAX_BYTES', 5_242_880),
    'upload_max_width' => env_int('UPLOAD_MAX_WIDTH', 4000),
    'upload_max_height' => env_int('UPLOAD_MAX_HEIGHT', 4000),
    'cookie_secure' => env_bool('COOKIE_SECURE', false),
    'cookie_samesite' => (string) env('COOKIE_SAMESITE', 'Lax'),
    'approved_admin_templates' => [
        'dashboard',
        'users',
        'roles',
        'permissions',
        'website_menus',
        'admin_menus',
        'website_pages',
        'admin_pages',
        'hero',
        'services',
        'profile_overviews',
        'vision_missions',
        'features',
        'navbar_colors',
        'footer_colors',
        'website_header',
        'testimonials',
        'cta',
        'partners',
        'contact_social',
        'contact_form',
        'contact_inquiries',
        'trash',
        'audit_logs',
        'account',
    ],
    'approved_website_templates' => [
        'home',
        'default',
        'about',
        'contact',
        'services',
    ],
    'reserved_slugs' => [
        'api', 'admin', 'login', 'logout', 'media', 'uploads', 'assets',
        'health', 'favicon.ico', 'robots.txt',
    ],
];
