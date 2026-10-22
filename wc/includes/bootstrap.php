<?php
declare(strict_types=1);

/**
 * Core bootstrap — load config and shared includes.
 */

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/app.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/secure_file.php';
require_once __DIR__ . '/menus.php';
require_once __DIR__ . '/hero_helpers.php';
require_once __DIR__ . '/service_helpers.php';
require_once __DIR__ . '/profile_overview_helpers.php';
require_once __DIR__ . '/vision_mission_helpers.php';
require_once __DIR__ . '/feature_helpers.php';
require_once __DIR__ . '/navbar_helpers.php';
require_once __DIR__ . '/footer_helpers.php';
require_once __DIR__ . '/header_helpers.php';
require_once __DIR__ . '/testimonial_helpers.php';
require_once __DIR__ . '/cta_banner_helpers.php';
require_once __DIR__ . '/partner_helpers.php';
require_once __DIR__ . '/contact_social_helpers.php';
require_once __DIR__ . '/contact_form_helpers.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/page_section_helpers.php';

// Ensure app config is loaded via helper
app_config();

if (!env_bool('APP_DEBUG', false)) {
    ini_set('display_errors', '0');
}
error_reporting(E_ALL);
