<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/view.php';

wa_start_session();

$path = wa_request_path();
if ($path === '/') {
    $path = wa_user() ? '/dashboard' : '/login';
}

// Static assets are served directly by server; this is fallback
if (str_starts_with($path, '/assets/')) {
    $file = __DIR__ . $path;
    if (is_file($file)) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        readfile($file);
        exit;
    }
    http_response_code(404);
    echo 'Not found';
    exit;
}

$routes = [
    '/login' => 'auth/login.php',
    '/logout' => 'auth/logout.php',
    '/dashboard' => 'dashboard/index.php',
    '/account' => 'account/index.php',
    '/users' => 'users/index.php',
    '/users/create' => 'users/form.php',
    '/users/edit' => 'users/form.php',
    '/users/view' => 'users/view.php',
    '/roles' => 'roles/index.php',
    '/roles/create' => 'roles/form.php',
    '/roles/edit' => 'roles/form.php',
    '/roles/view' => 'roles/view.php',
    '/permissions' => 'permissions/index.php',
    '/website-menus' => 'menus/index.php',
    '/admin-menus' => 'menus/index.php',
    '/menus/create' => 'menus/form.php',
    '/menus/edit' => 'menus/form.php',
    '/menus/view' => 'menus/view.php',
    '/website-pages' => 'pages/index.php',
    '/admin-pages' => 'pages/index.php',
    '/pages/create' => 'pages/form.php',
    '/pages/edit' => 'pages/form.php',
    '/pages/view' => 'pages/view.php',
        '/hero' => 'hero/index.php',
    '/hero/create' => 'hero/form.php',
    '/hero/edit' => 'hero/form.php',
    '/hero/view' => 'hero/view.php',
    '/services' => 'services/index.php',
    '/services/create' => 'services/form.php',
    '/services/edit' => 'services/form.php',
    '/services/view' => 'services/view.php',
    '/profile-overviews' => 'profile_overviews/index.php',
    '/profile-overviews/create' => 'profile_overviews/form.php',
    '/profile-overviews/edit' => 'profile_overviews/form.php',
    '/profile-overviews/view' => 'profile_overviews/view.php',
    '/vision-missions' => 'vision_missions/index.php',
    '/vision-missions/create' => 'vision_missions/form.php',
    '/vision-missions/edit' => 'vision_missions/form.php',
    '/vision-missions/view' => 'vision_missions/view.php',
    '/features' => 'features/index.php',
    '/features/create' => 'features/form.php',
    '/features/edit' => 'features/form.php',
    '/features/view' => 'features/view.php',
    '/navbar-colors' => 'navbar_colors/index.php',
    '/footer-colors' => 'footer_colors/index.php',
    '/website-header' => 'website_header/index.php',
    '/testimonials' => 'testimonials/index.php',
    '/testimonials/create' => 'testimonials/form.php',
    '/testimonials/edit' => 'testimonials/form.php',
    '/testimonials/view' => 'testimonials/view.php',
    '/cta' => 'cta/index.php',
    '/cta/create' => 'cta/form.php',
    '/cta/edit' => 'cta/form.php',
    '/cta/view' => 'cta/view.php',
    '/partners' => 'partners/index.php',
    '/partners/create' => 'partners/form.php',
    '/partners/edit' => 'partners/form.php',
    '/partners/view' => 'partners/view.php',
    '/contact-social' => 'contact_social/index.php',
    '/contact-social/social' => 'contact_social/social_index.php',
    '/contact-social/social/create' => 'contact_social/social_form.php',
    '/contact-social/social/edit' => 'contact_social/social_form.php',
    '/contact-social/contact' => 'contact_social/contact_index.php',
    '/contact-social/contact/create' => 'contact_social/contact_form.php',
    '/contact-social/contact/edit' => 'contact_social/contact_form.php',
    '/contact-social/contact/view' => 'contact_social/contact_view.php',
    '/contact-form' => 'contact_form/index.php',
    '/contact-inquiries' => 'contact_inquiries/index.php',
    '/contact-inquiries/view' => 'contact_inquiries/view.php',
    '/trash' => 'trash/index.php',
    '/audit-logs' => 'audit/index.php',
];

if (!isset($routes[$path])) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

require dirname(__DIR__) . '/pages/' . $routes[$path];
