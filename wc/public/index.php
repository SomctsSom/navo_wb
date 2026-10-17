<?php
declare(strict_types=1);

/**
 * Website Core front controller.
 * Document root should point here. Routes /api/* and /media/*.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$path = wc_request_path();
if ($path === '/') {
    $path = '/health';
}

try {
    if ($path === '/health') {
        json_ok(['status' => 'ok', 'app' => app_config('name'), 'time' => now_utc()]);
    }
    if ($path === '/health/db') {
        require dirname(__DIR__) . '/api/public/health_db.php';
        exit;
    }

    if (preg_match('#^/media/(.+)$#', $path, $m) === 1) {
        require dirname(__DIR__) . '/api/public/media.php';
        exit;
    }

    $apiMap = [
        // Auth
        'POST /api/auth/login' => 'auth/login.php',
        'POST /api/auth/logout' => 'auth/logout.php',
        'GET /api/auth/me' => 'auth/me.php',
        'POST /api/auth/change-password' => 'auth/change_password.php',

        // Users
        'GET /api/users' => 'users/list.php',
        'GET /api/users/view' => 'users/view.php',
        'POST /api/users/create' => 'users/create.php',
        'POST /api/users/update' => 'users/update.php',
        'POST /api/users/delete' => 'users/delete.php',
        'POST /api/users/restore' => 'users/restore.php',

        // Roles
        'GET /api/roles' => 'roles/list.php',
        'GET /api/roles/view' => 'roles/view.php',
        'POST /api/roles/create' => 'roles/create.php',
        'POST /api/roles/update' => 'roles/update.php',
        'POST /api/roles/delete' => 'roles/delete.php',
        'POST /api/roles/restore' => 'roles/restore.php',
        'POST /api/roles/assign-permissions' => 'roles/assign_permissions.php',

        // Permissions
        'GET /api/permissions' => 'permissions/list.php',
        'POST /api/permissions/create' => 'permissions/create.php',
        'POST /api/permissions/update' => 'permissions/update.php',
        'POST /api/permissions/delete' => 'permissions/delete.php',
        'POST /api/permissions/restore' => 'permissions/restore.php',

        // Menus
        'GET /api/menus' => 'menus/list.php',
        'GET /api/menus/view' => 'menus/view.php',
        'POST /api/menus/create' => 'menus/create.php',
        'POST /api/menus/update' => 'menus/update.php',
        'POST /api/menus/delete' => 'menus/delete.php',
        'POST /api/menus/restore' => 'menus/restore.php',
        'POST /api/menus/reorder' => 'menus/reorder.php',
        'POST /api/menus/assign-pages' => 'menus/assign_pages.php',

        // Pages
        'GET /api/pages' => 'pages/list.php',
        'GET /api/pages/view' => 'pages/view.php',
        'POST /api/pages/create' => 'pages/create.php',
        'POST /api/pages/update' => 'pages/update.php',
        'POST /api/pages/delete' => 'pages/delete.php',
        'POST /api/pages/restore' => 'pages/restore.php',

        // Hero
        'GET /api/hero' => 'hero/list.php',
        'GET /api/hero/view' => 'hero/view.php',
        'POST /api/hero/create' => 'hero/create.php',
        'POST /api/hero/update' => 'hero/update.php',
        'POST /api/hero/delete' => 'hero/delete.php',
        'POST /api/hero/restore' => 'hero/restore.php',
        'POST /api/hero/reorder' => 'hero/reorder.php',
        'GET /api/hero/size' => 'hero/size.php',
        'POST /api/hero/size' => 'hero/size.php',

        // Services
        'GET /api/services' => 'services/list.php',
        'GET /api/services/view' => 'services/view.php',
        'POST /api/services/create' => 'services/create.php',
        'POST /api/services/update' => 'services/update.php',
        'POST /api/services/delete' => 'services/delete.php',
        'POST /api/services/restore' => 'services/restore.php',
        'POST /api/services/reorder' => 'services/reorder.php',

        // Profile Overviews
        'GET /api/profile-overviews' => 'profile_overviews/list.php',
        'GET /api/profile-overviews/view' => 'profile_overviews/view.php',
        'POST /api/profile-overviews/create' => 'profile_overviews/create.php',
        'POST /api/profile-overviews/update' => 'profile_overviews/update.php',
        'POST /api/profile-overviews/delete' => 'profile_overviews/delete.php',
        'POST /api/profile-overviews/restore' => 'profile_overviews/restore.php',
        'POST /api/profile-overviews/reorder' => 'profile_overviews/reorder.php',

        // Vision & Mission
        'GET /api/vision-missions' => 'vision_missions/list.php',
        'GET /api/vision-missions/view' => 'vision_missions/view.php',
        'POST /api/vision-missions/create' => 'vision_missions/create.php',
        'POST /api/vision-missions/update' => 'vision_missions/update.php',
        'POST /api/vision-missions/delete' => 'vision_missions/delete.php',
        'POST /api/vision-missions/restore' => 'vision_missions/restore.php',
        'POST /api/vision-missions/reorder' => 'vision_missions/reorder.php',

        // Features
        'GET /api/features' => 'features/list.php',
        'GET /api/features/view' => 'features/view.php',
        'POST /api/features/create' => 'features/create.php',
        'POST /api/features/update' => 'features/update.php',
        'POST /api/features/delete' => 'features/delete.php',
        'POST /api/features/restore' => 'features/restore.php',
        'POST /api/features/reorder' => 'features/reorder.php',

        // Navbar colors
        'GET /api/navbar-colors' => 'navbar_colors/index.php',
        'POST /api/navbar-colors' => 'navbar_colors/index.php',
        'GET /api/footer-colors' => 'footer_colors/index.php',
        'POST /api/footer-colors' => 'footer_colors/index.php',
        'GET /api/website-header' => 'website_header/index.php',
        'POST /api/website-header' => 'website_header/index.php',
        'GET /api/contact-form' => 'contact_form/index.php',
        'POST /api/contact-form' => 'contact_form/index.php',
        'POST /api/contact-form/pages' => 'contact_form/pages.php',
        'GET /api/contact-form/fields' => 'contact_form/fields_list.php',
        'POST /api/contact-form/fields' => 'contact_form/fields_create.php',
        'POST /api/contact-form/fields/update' => 'contact_form/fields_update.php',
        'POST /api/contact-form/fields/delete' => 'contact_form/fields_delete.php',
        'GET /api/contact-inquiries' => 'contact_inquiries/list.php',
        'GET /api/contact-inquiries/view' => 'contact_inquiries/view.php',
        'POST /api/contact-inquiries/update' => 'contact_inquiries/update.php',
        'POST /api/contact-inquiries/delete' => 'contact_inquiries/delete.php',

        // Testimonials
        'GET /api/testimonials' => 'testimonials/list.php',
        'GET /api/testimonials/view' => 'testimonials/view.php',
        'POST /api/testimonials/create' => 'testimonials/create.php',
        'POST /api/testimonials/update' => 'testimonials/update.php',
        'POST /api/testimonials/delete' => 'testimonials/delete.php',
        'POST /api/testimonials/restore' => 'testimonials/restore.php',
        'POST /api/testimonials/reorder' => 'testimonials/reorder.php',

        // CTA Banners
        'GET /api/cta-banners' => 'cta_banners/list.php',
        'GET /api/cta-banners/view' => 'cta_banners/view.php',
        'POST /api/cta-banners/create' => 'cta_banners/create.php',
        'POST /api/cta-banners/update' => 'cta_banners/update.php',
        'POST /api/cta-banners/delete' => 'cta_banners/delete.php',
        'POST /api/cta-banners/restore' => 'cta_banners/restore.php',

        // Partners
        'GET /api/partners' => 'partners/list.php',
        'GET /api/partners/view' => 'partners/view.php',
        'POST /api/partners/create' => 'partners/create.php',
        'POST /api/partners/update' => 'partners/update.php',
        'POST /api/partners/delete' => 'partners/delete.php',
        'POST /api/partners/restore' => 'partners/restore.php',

        // Contact & Social
        'GET /api/social-links' => 'social_links/list.php',
        'GET /api/social-links/view' => 'social_links/view.php',
        'POST /api/social-links/create' => 'social_links/create.php',
        'POST /api/social-links/update' => 'social_links/update.php',
        'POST /api/social-links/delete' => 'social_links/delete.php',
        'POST /api/social-links/restore' => 'social_links/restore.php',
        'GET /api/contact-infos' => 'contact_infos/list.php',
        'GET /api/contact-infos/view' => 'contact_infos/view.php',
        'POST /api/contact-infos/create' => 'contact_infos/create.php',
        'POST /api/contact-infos/update' => 'contact_infos/update.php',
        'POST /api/contact-infos/delete' => 'contact_infos/delete.php',
        'POST /api/contact-infos/restore' => 'contact_infos/restore.php',

        // Trash & Audit
        'GET /api/trash' => 'trash/list.php',
        'POST /api/trash/restore' => 'trash/restore.php',
        'GET /api/audit' => 'audit/list.php',

        // Navigation for admin UI
        'GET /api/admin/navigation' => 'menus/admin_navigation.php',

        // Public (no auth)
        'GET /api/public/menus' => 'public/menus.php',
        'GET /api/public/pages' => 'public/pages.php',
        'GET /api/public/page' => 'public/page.php',
        'GET /api/public/hero' => 'public/hero.php',
        'GET /api/public/services' => 'public/services.php',
        'GET /api/public/profile-overviews' => 'public/profile_overviews.php',
        'GET /api/public/vision-missions' => 'public/vision_missions.php',
        'GET /api/public/features' => 'public/features.php',
        'GET /api/public/navbar-colors' => 'public/navbar_colors.php',
        'GET /api/public/footer-colors' => 'public/footer_colors.php',
        'GET /api/public/website-header' => 'public/website_header.php',
        'GET /api/public/testimonials' => 'public/testimonials.php',
        'GET /api/public/cta-banners' => 'public/cta_banners.php',
        'GET /api/public/partners' => 'public/partners.php',
        'GET /api/public/contact-social' => 'public/contact_social.php',
        'GET /api/public/contact-form' => 'public/contact_form.php',
        'POST /api/public/contact-inquiry' => 'public/contact_inquiry.php',
        'GET /api/public/branding' => 'public/branding.php',
    ];

    $routeKey = request_method() . ' ' . $path;
    if (!isset($apiMap[$routeKey])) {
        // Also allow /api/users/{id} style via query? we use query params
        json_error('Not found', 404);
    }

    $file = dirname(__DIR__) . '/api/' . $apiMap[$routeKey];
    if (!is_file($file)) {
        json_error('Endpoint missing', 500);
    }
    require $file;
} catch (Throwable $e) {
    error_log('WC error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $msg = env_bool('APP_DEBUG', false) ? $e->getMessage() : 'Internal server error';
    json_error($msg, 500);
}
