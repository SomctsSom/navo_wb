<?php
declare(strict_types=1);

/** @var string $slug */

$menusRes = pw_api('/api/public/menus', ['menu_type' => 'website']);
$menus = !empty($menusRes['success']) ? ($menusRes['data']['items'] ?? []) : [];
$allServicesRes = pw_api('/api/public/services', ['all' => 1]);
$allServices = !empty($allServicesRes['success']) ? ($allServicesRes['data']['items'] ?? []) : [];
if (!is_array($allServices)) {
    $allServices = [];
}

$navColorsRes = pw_api('/api/public/navbar-colors');
$navColors = !empty($navColorsRes['success']) ? ($navColorsRes['data'] ?? []) : [];
$footerColorsRes = pw_api('/api/public/footer-colors');
$footerColors = !empty($footerColorsRes['success']) ? ($footerColorsRes['data'] ?? []) : [];
$headerBarRes = pw_api('/api/public/website-header');
$headerBar = !empty($headerBarRes['success']) ? ($headerBarRes['data'] ?? []) : [];
$headerBarEnabled = (int) ($headerBar['enabled'] ?? 1) === 1;
$headerTransparent = (int) ($headerBar['transparent'] ?? 0) === 1;
$headerShowPhone = (int) ($headerBar['show_phone'] ?? 1) === 1;
$headerShowEmail = (int) ($headerBar['show_email'] ?? 1) === 1;
$headerShowHours = (int) ($headerBar['show_hours'] ?? 1) === 1;
$headerShowSocial = (int) ($headerBar['show_social'] ?? 1) === 1;
$headerBg = (string) ($headerBar['bg_color'] ?? '#1a1a1a');
$headerText = (string) ($headerBar['text_color'] ?? '#ffffff');
$headerAccent = (string) ($headerBar['accent_color'] ?? '#e0893a');
$headerSocial = (string) ($headerBar['social_color'] ?? '#ffffff');
foreach (['headerBg' => '#1a1a1a', 'headerText' => '#ffffff', 'headerAccent' => '#e0893a', 'headerSocial' => '#ffffff'] as $var => $fallback) {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $$var)) {
        $$var = $fallback;
    }
}
$footerBg = (string) ($footerColors['bg_color'] ?? '#111111');
$footerText = (string) ($footerColors['text_color'] ?? '#d8d8d8');
$footerHeading = (string) ($footerColors['heading_color'] ?? '#ffffff');
$footerAccent = (string) ($footerColors['accent_color'] ?? '#e0893a');
$footerSocialBg = (string) ($footerColors['social_bg_color'] ?? '#2a2a2a');
foreach (['footerBg' => '#111111', 'footerText' => '#d8d8d8', 'footerHeading' => '#ffffff', 'footerAccent' => '#e0893a', 'footerSocialBg' => '#2a2a2a'] as $var => $fallback) {
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $$var)) {
        $$var = $fallback;
    }
}
$navTransparent = (int) ($navColors['transparent'] ?? 0) === 1;
$navBg = (string) ($navColors['bg_color'] ?? '#12352e');
$navMenu = (string) ($navColors['menu_color'] ?? '#ffffff');
$navActive = (string) ($navColors['active_color'] ?? '#e0893a');
$globalPageBg = (string) ($navColors['page_bg_color'] ?? '#f2ebe0');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $navBg)) {
    $navBg = '#12352e';
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $navMenu)) {
    $navMenu = '#ffffff';
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $navActive)) {
    $navActive = '#e0893a';
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $globalPageBg)) {
    $globalPageBg = '#f2ebe0';
}

$pageRes = pw_api('/api/public/page', ['slug' => $slug]);
$heroRes = null;
$sectionOrder = [];
if (empty($pageRes['success'])) {
    http_response_code(404);
    $page = ['title' => 'Page not found', 'slug' => $slug, 'template_key' => 'default'];
    $heroes = [];
    $services = [];
    $profiles = [];
    $visionMissions = [];
    $features = [];
    $testimonials = [];
    $ctaBanners = [];
    $partners = [];
    $partnersSectionTitle = 'Our Partners';
    $partnersSectionSubtitle = '';
    $contactSocial = ['social' => [], 'contact' => null];
} else {
    $page = $pageRes['data'];
    $sectionOrder = is_array($page['section_order'] ?? null) ? $page['section_order'] : [];
    $heroRes = pw_api('/api/public/hero', ['page_id' => $page['id']]);
    $heroes = !empty($heroRes['success']) ? ($heroRes['data']['items'] ?? []) : [];
    $servicesRes = pw_api('/api/public/services', ['page_id' => $page['id']]);
    $services = !empty($servicesRes['success']) ? ($servicesRes['data']['items'] ?? []) : [];
    $profilesRes = pw_api('/api/public/profile-overviews', ['page_id' => $page['id']]);
    $profiles = !empty($profilesRes['success']) ? ($profilesRes['data']['items'] ?? []) : [];
    $visionRes = pw_api('/api/public/vision-missions', ['page_id' => $page['id']]);
    $visionMissions = !empty($visionRes['success']) ? ($visionRes['data']['items'] ?? []) : [];
    $featuresRes = pw_api('/api/public/features', ['page_id' => $page['id']]);
    $features = !empty($featuresRes['success']) ? ($featuresRes['data']['items'] ?? []) : [];
    $testimonialsRes = pw_api('/api/public/testimonials', ['page_id' => $page['id']]);
    $testimonials = !empty($testimonialsRes['success']) ? ($testimonialsRes['data']['items'] ?? []) : [];
    $ctaRes = pw_api('/api/public/cta-banners', ['page_id' => $page['id']]);
    $ctaBanners = !empty($ctaRes['success']) ? ($ctaRes['data']['items'] ?? []) : [];
    $partnersRes = pw_api('/api/public/partners', ['page_id' => $page['id']]);
    $partners = !empty($partnersRes['success']) ? ($partnersRes['data']['items'] ?? []) : [];
    $partnersSectionTitle = !empty($partnersRes['success'])
        ? trim((string) ($partnersRes['data']['section_title'] ?? 'Our Partners'))
        : 'Our Partners';
    $partnersSectionSubtitle = !empty($partnersRes['success'])
        ? trim((string) ($partnersRes['data']['section_subtitle'] ?? ''))
        : '';
    $contactRes = pw_api('/api/public/contact-social', ['page_id' => $page['id']]);
    $contactSocial = !empty($contactRes['success']) ? ($contactRes['data'] ?? ['social' => [], 'contact' => null]) : ['social' => [], 'contact' => null];
}

$pageBg = $globalPageBg;
$pageOwnBg = trim((string) ($page['bg_color'] ?? ''));
if ($pageOwnBg !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $pageOwnBg)) {
    $pageBg = $pageOwnBg;
}

$cardBg = '#ffffff';
$pageOwnCardBg = trim((string) ($page['card_bg_color'] ?? ''));
if ($pageOwnCardBg !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $pageOwnCardBg)) {
    $cardBg = $pageOwnCardBg;
}

if ($sectionOrder === []) {
    $sectionOrder = [
        ['section_key' => 'services', 'is_enabled' => 1],
        ['section_key' => 'testimonials', 'is_enabled' => 1],
        ['section_key' => 'cta_banner', 'is_enabled' => 1],
        ['section_key' => 'partners', 'is_enabled' => 1],
        ['section_key' => 'contact_social', 'is_enabled' => 1],
        ['section_key' => 'profile_overviews', 'is_enabled' => 1],
        ['section_key' => 'vision_missions', 'is_enabled' => 1],
        ['section_key' => 'features', 'is_enabled' => 1],
    ];
}

$contactFormSettingsRes = pw_api('/api/public/contact-form');
$contactFormSettings = !empty($contactFormSettingsRes['success'])
    ? ($contactFormSettingsRes['data'] ?? [])
    : [
        'show_full_name' => 1,
        'show_company_name' => 1,
        'show_phone' => 1,
        'show_email' => 1,
        'show_preferred_service' => 1,
        'show_message' => 1,
        'custom_fields' => [],
    ];
$contactCustomFields = is_array($contactFormSettings['custom_fields'] ?? null)
    ? $contactFormSettings['custom_fields']
    : [];
$showContactFormSection = false;
foreach ($sectionOrder as $sec) {
    if (($sec['section_key'] ?? '') === 'contact_form' && (int) ($sec['is_enabled'] ?? 1) === 1) {
        $showContactFormSection = true;
        break;
    }
}
if (!$showContactFormSection && (string) ($page['template_key'] ?? '') === 'contact') {
    $showContactFormSection = true;
    $sectionOrder[] = ['section_key' => 'contact_form', 'is_enabled' => 1];
}

$contactFormError = '';
$contactFormSuccess = '';
$contactFormReceipt = null;
$contactFormValues = [
    'full_name' => '',
    'company_name' => '',
    'phone' => '',
    'email' => '',
    'preferred_service_id' => '',
    'message' => '',
];
foreach ($contactCustomFields as $cf) {
    $ck = (string) ($cf['field_key'] ?? '');
    if ($ck !== '') {
        $contactFormValues[$ck] = '';
    }
}
if (
    $showContactFormSection
    && strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string) ($_POST['form_action'] ?? '') === 'contact_inquiry'
) {
    $contactFormValues = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'company_name' => trim((string) ($_POST['company_name'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'preferred_service_id' => (string) ($_POST['preferred_service_id'] ?? ''),
        'message' => trim((string) ($_POST['message'] ?? '')),
    ];
    $submitPayload = [
        'full_name' => $contactFormValues['full_name'],
        'company_name' => $contactFormValues['company_name'],
        'phone' => $contactFormValues['phone'],
        'email' => $contactFormValues['email'],
        'preferred_service_id' => (int) $contactFormValues['preferred_service_id'],
        'message' => $contactFormValues['message'],
        'page_id' => (int) ($page['id'] ?? 0),
    ];
    foreach ($contactCustomFields as $cf) {
        $ck = (string) ($cf['field_key'] ?? '');
        if ($ck === '') {
            continue;
        }
        $contactFormValues[$ck] = trim((string) ($_POST[$ck] ?? ''));
        $submitPayload[$ck] = $contactFormValues[$ck];
    }
    $submit = pw_api_post('/api/public/contact-inquiry', $submitPayload);
    if (!empty($submit['success'])) {
        $contactFormSuccess = (string) ($submit['message'] ?? 'Thank you. Your message has been sent.');
        $serviceLabel = '';
        $svcId = (int) ($contactFormValues['preferred_service_id'] ?? 0);
        if ($svcId > 0) {
            foreach ($allServices as $svc) {
                if ((int) ($svc['id'] ?? 0) === $svcId) {
                    $serviceLabel = trim((string) ($svc['title'] ?? ''));
                    break;
                }
            }
        }
        $customReceipt = [];
        foreach ($contactCustomFields as $cf) {
            $ck = (string) ($cf['field_key'] ?? '');
            if ($ck === '') {
                continue;
            }
            $cval = trim((string) ($contactFormValues[$ck] ?? ''));
            if ($cval === '') {
                continue;
            }
            $customReceipt[] = [
                'label' => (string) ($cf['label'] ?? $ck),
                'value' => $cval,
            ];
        }
        $contactFormReceipt = [
            'full_name' => (string) $contactFormValues['full_name'],
            'company_name' => (string) $contactFormValues['company_name'],
            'phone' => (string) $contactFormValues['phone'],
            'email' => (string) $contactFormValues['email'],
            'preferred_service' => $serviceLabel,
            'message' => (string) $contactFormValues['message'],
            'custom' => $customReceipt,
            'inquiry_id' => (int) (($submit['data']['id'] ?? 0)),
        ];
        $contactFormValues = [
            'full_name' => '',
            'company_name' => '',
            'phone' => '',
            'email' => '',
            'preferred_service_id' => '',
            'message' => '',
        ];
        foreach ($contactCustomFields as $cf) {
            $ck = (string) ($cf['field_key'] ?? '');
            if ($ck !== '') {
                $contactFormValues[$ck] = '';
            }
        }
    } else {
        $contactFormError = (string) ($submit['message'] ?? 'Could not send your message.');
    }
}

$siteName = pw_e((string) pw_env('APP_NAME', 'Public Website'));
$title = pw_e($page['title'] ?? 'Page');
$heroCount = count($heroes);
$serviceCount = count($services);
$profileCount = count($profiles);
$visionCount = count($visionMissions);
$featureCount = count($features);
$testimonialCount = count($testimonials);
$ctaBannerCount = count($ctaBanners ?? []);
$partnerCount = count($partners ?? []);
if (!isset($partnersSectionTitle) || trim((string) $partnersSectionTitle) === '') {
    $partnersSectionTitle = 'Our Partners';
}
if (!isset($partnersSectionSubtitle)) {
    $partnersSectionSubtitle = '';
}
$socialLinks = is_array($contactSocial['social'] ?? null) ? $contactSocial['social'] : [];
$contactBlock = is_array($contactSocial['contact'] ?? null) ? $contactSocial['contact'] : null;
$socialCount = count($socialLinks);
$hasContactBlock = $contactBlock !== null && (
    trim((string) ($contactBlock['company_summary'] ?? '')) !== ''
    || trim((string) ($contactBlock['company_name'] ?? '')) !== ''
    || trim((string) ($contactBlock['tagline'] ?? '')) !== ''
    || trim((string) ($contactBlock['email'] ?? '')) !== ''
    || trim((string) ($contactBlock['business_hours'] ?? '')) !== ''
    || !empty($contactBlock['logo_url'])
    || !empty($contactBlock['addresses'])
    || !empty($contactBlock['phones'])
);
$contactSocialCount = $socialCount + ($hasContactBlock ? 1 : 0);
$showSiteFooter = $hasContactBlock || $socialCount > 0;

$brandNameRaw = trim((string) (($contactBlock ?? [])['company_name'] ?? ''));
$brandLogo = trim((string) (($contactBlock ?? [])['logo_url'] ?? ''));
if ($brandLogo === '' || $brandNameRaw === '') {
    $brandFallbackRes = pw_api('/api/public/contact-social', ['slug' => 'home']);
    $brandFallbackContact = !empty($brandFallbackRes['success'])
        ? ($brandFallbackRes['data']['contact'] ?? null)
        : null;
    if (is_array($brandFallbackContact)) {
        if ($brandLogo === '') {
            $brandLogo = trim((string) ($brandFallbackContact['logo_url'] ?? ''));
        }
        if ($brandNameRaw === '') {
            $brandNameRaw = trim((string) ($brandFallbackContact['company_name'] ?? ''));
        }
    }
}
if ($brandNameRaw === '') {
    $brandNameRaw = (string) pw_env('APP_NAME', 'Public Website');
}
$brandName = pw_e($brandNameRaw);

$topbarPhone = '';
$topbarPhones = is_array(($contactBlock ?? [])['phones'] ?? null) ? $contactBlock['phones'] : [];
foreach ($topbarPhones as $ph) {
    $candidate = trim((string) ($ph['phone'] ?? ''));
    if ($candidate !== '') {
        $topbarPhone = $candidate;
        break;
    }
}
$topbarEmail = trim((string) (($contactBlock ?? [])['email'] ?? ''));
$topbarHours = trim((string) (($contactBlock ?? [])['business_hours'] ?? ''));
if (!$headerShowPhone) {
    $topbarPhone = '';
}
if (!$headerShowEmail) {
    $topbarEmail = '';
}
if (!$headerShowHours) {
    $topbarHours = '';
}
$topbarSocialLinks = $headerShowSocial ? $socialLinks : [];
$topbarSocialCount = count($topbarSocialLinks);
$showSiteTopbar = $headerBarEnabled && (
    $topbarPhone !== '' || $topbarEmail !== '' || $topbarHours !== '' || $topbarSocialCount > 0
);

$heroSize = (string) (($heroRes['data']['size_preset'] ?? null) ?? 'md');
if (!in_array($heroSize, ['sm', 'md', 'lg', 'xl'], true)) {
    $heroSize = 'md';
}

$bannerTitle = trim((string) ($page['banner_title'] ?? ''));
if ($bannerTitle === '') {
    $bannerTitle = (string) ($page['title'] ?? 'Page');
}
$bannerEyebrow = trim((string) ($page['banner_eyebrow'] ?? ''));
$bannerSubtitle = trim((string) ($page['banner_subtitle'] ?? ''));
$bannerImage = trim((string) ($page['banner_image_url'] ?? ''));
$bannerEnabled = (int) ($page['banner_enabled'] ?? 0) === 1;
$bannerSize = (string) ($page['banner_size_preset'] ?? 'md');
if (!in_array($bannerSize, ['sm', 'md', 'lg', 'xl'], true)) {
    $bannerSize = 'md';
}
$showPageBanner = $heroCount === 0 && !empty($pageRes['success']) && $bannerEnabled;
// Overlay navbar on hero OR on page banner when transparent (so image shows through, not body sand)
$navOverlay = $heroCount > 0 || ($navTransparent && $showPageBanner);
$headerClass = 'site-header';
if (!$navOverlay) {
    $headerClass .= ' site-header-solid';
}
if ($navTransparent) {
    $headerClass .= ' is-transparent';
}

$bodyClasses = [];
if ($showSiteTopbar) {
    $bodyClasses[] = 'has-site-topbar';
}
if ($navOverlay) {
    $bodyClasses[] = 'has-nav-overlay';
}
$bodyClassAttr = $bodyClasses !== [] ? ' class="' . pw_e(implode(' ', $bodyClasses)) . '"' : '';

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ?> · <?= $siteName ?></title>
  <?php if ($brandLogo !== ''): ?>
  <link rel="icon" href="<?= pw_e($brandLogo) ?>" type="image/png">
  <link rel="shortcut icon" href="<?= pw_e($brandLogo) ?>">
  <link rel="apple-touch-icon" href="<?= pw_e($brandLogo) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= pw_e(pw_url('/assets/css/site.css')) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/v4-shims.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body<?= $bodyClassAttr ?> style="--page-bg: <?= pw_e($pageBg) ?>; --sand: <?= pw_e($pageBg) ?>; --panel: <?= pw_e($cardBg) ?>; --card-bg: <?= pw_e($cardBg) ?>; --nav-active: <?= pw_e($navActive) ?>; background: <?= pw_e($pageBg) ?>;">
  <?php if ($showSiteTopbar): ?>
  <div
    class="site-topbar<?= $headerTransparent ? ' is-transparent' : '' ?>"
    aria-label="Contact bar"
    style="--topbar-bg: <?= pw_e($headerTransparent ? 'transparent' : $headerBg) ?>; --topbar-text: <?= pw_e($headerText) ?>; --topbar-accent: <?= pw_e($headerAccent) ?>; --topbar-social: <?= pw_e($headerSocial) ?>;"
  >
    <div class="site-topbar-inner">
      <div class="site-topbar-meta">
        <?php
        $topbarMetaParts = [];
        if ($topbarPhone !== '') {
            $telHref = preg_replace('/[^0-9+]/', '', $topbarPhone) ?: $topbarPhone;
            $topbarMetaParts[] = '<a class="site-topbar-item" href="tel:' . pw_e((string) $telHref) . '">'
                . '<span class="site-topbar-ico" aria-hidden="true"><i class="fas fa-phone"></i></span>'
                . '<span>' . pw_e($topbarPhone) . '</span></a>';
        }
        if ($topbarEmail !== '') {
            $topbarMetaParts[] = '<a class="site-topbar-item" href="mailto:' . pw_e($topbarEmail) . '">'
                . '<span class="site-topbar-ico" aria-hidden="true"><i class="fas fa-envelope"></i></span>'
                . '<span>' . pw_e($topbarEmail) . '</span></a>';
        }
        if ($topbarHours !== '') {
            $topbarMetaParts[] = '<span class="site-topbar-item">'
                . '<span class="site-topbar-ico" aria-hidden="true"><i class="fas fa-clock"></i></span>'
                . '<span>' . pw_e($topbarHours) . '</span></span>';
        }
        echo implode('<span class="site-topbar-sep" aria-hidden="true">|</span>', $topbarMetaParts);
        ?>
      </div>
      <?php if ($topbarSocialCount > 0): ?>
      <ul class="site-topbar-social">
        <?php foreach ($topbarSocialLinks as $sl): ?>
          <li>
            <a href="<?= pw_e((string) ($sl['link_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= pw_e((string) ($sl['label'] ?? 'Social')) ?>">
              <?= (string) ($sl['icon_html'] ?? '') ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <header
    class="<?= pw_e($headerClass) ?>"
    style="--nav-bg: <?= pw_e($navBg) ?>; --nav-menu: <?= pw_e($navMenu) ?>; --nav-active: <?= pw_e($navActive) ?>;"
  >
    <a class="logo" href="<?= pw_e(pw_url('/')) ?>">
      <?php if ($brandLogo !== ''): ?>
        <img class="logo-img" src="<?= pw_e($brandLogo) ?>" alt="<?= $brandName ?>">
      <?php endif; ?>
      <span class="logo-text"><?= $brandName ?></span>
    </a>
    <nav class="site-nav" data-site-nav aria-label="Main">
      <?php
      $currentPath = '/' . ltrim((string) $slug, '/');
      $renderNav = function (array $items, int $depth = 0) use (&$renderNav, $currentPath): void {
          $ulClass = $depth === 0 ? 'nav-root' : 'nav-sub';
          echo '<ul class="' . $ulClass . '">';
          foreach ($items as $item) {
              $href = (string) ($item['url'] ?? '#');
              if ($href === '' && !empty($item['pages'][0]['slug'])) {
                  $href = '/' . $item['pages'][0]['slug'];
              }
              if ($href === '') {
                  $href = '#';
              }
              $children = $item['children'] ?? [];
              $hasChildren = is_array($children) && count($children) > 0;
              $normalized = $href === '/' ? '/home' : rtrim($href, '/');
              $isActive = $normalized === $currentPath || $normalized === '/' . ltrim($currentPath, '/');
              $childActive = false;
              if ($hasChildren) {
                  foreach ($children as $child) {
                      $ch = (string) ($child['url'] ?? '');
                      if ($ch === '' && !empty($child['pages'][0]['slug'])) {
                          $ch = '/' . $child['pages'][0]['slug'];
                      }
                      $chNorm = $ch === '/' ? '/home' : rtrim($ch, '/');
                      if ($chNorm === $currentPath || $chNorm === '/' . ltrim($currentPath, '/')) {
                          $childActive = true;
                          break;
                      }
                  }
              }
              $liClass = 'nav-item';
              if ($hasChildren) {
                  $liClass .= ' has-children';
              }
              if ($isActive || $childActive) {
                  $liClass .= ' is-active';
              }
              echo '<li class="' . $liClass . '">';
              echo '<a class="nav-link' . ($isActive ? ' is-current' : '') . '" href="' . pw_e(pw_url($href)) . '"';
              if ($hasChildren) {
                  echo ' aria-haspopup="true" aria-expanded="false"';
              }
              echo '>' . pw_e((string) $item['title']);
              if ($hasChildren) {
                  echo '<span class="nav-caret" aria-hidden="true"></span>';
              }
              echo '</a>';
              if ($hasChildren) {
                  $renderNav($children, $depth + 1);
              }
              echo '</li>';
          }
          echo '</ul>';
      };
      $renderNav($menus);
      ?>
    </nav>
  </header>

  <?php if ($heroCount > 0): ?>
  <section class="hero-slider hero-size-<?= pw_e($heroSize) ?><?= $heroCount > 1 ? ' has-multiple' : '' ?>" data-hero-slider data-hero-size="<?= pw_e($heroSize) ?>" aria-roledescription="carousel" aria-label="Page hero">
    <div class="hero-track">
      <?php foreach ($heroes as $index => $hero): ?>
        <article
          class="hero-slide<?= $index === 0 ? ' is-active' : '' ?>"
          data-slide="<?= (int) $index ?>"
          style="--hero-image: url('<?= pw_e((string) $hero['image_url']) ?>')"
          <?= $index === 0 ? '' : 'hidden' ?>
          aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"
        >
          <div class="hero-overlay">
            <div class="hero-copy">
              <?php $heroSubtitle = trim((string) ($hero['subtitle'] ?? '')); ?>
              <?php if ($heroSubtitle !== ''): ?>
                <p class="hero-subtitle"><?= pw_e($heroSubtitle) ?></p>
              <?php endif; ?>
              <h1><?= pw_e((string) $hero['title']) ?></h1>
              <?php if (!empty($hero['description'])): ?>
                <p class="lede"><?= pw_e((string) $hero['description']) ?></p>
              <?php endif; ?>
              <?php
              $heroButtons = [];
              if (!empty($hero['buttons']) && is_array($hero['buttons'])) {
                  foreach ($hero['buttons'] as $heroButton) {
                      $buttonLabel = trim((string) ($heroButton['label'] ?? ''));
                      if ($buttonLabel === '') {
                          continue;
                      }
                      $heroButtons[] = [
                          'label' => $buttonLabel,
                          'url' => trim((string) ($heroButton['url'] ?? '')),
                      ];
                  }
              }
              if (!$heroButtons && trim((string) ($hero['button_text'] ?? '')) !== '') {
                  $heroButtons[] = [
                      'label' => trim((string) $hero['button_text']),
                      'url' => trim((string) ($hero['button_url'] ?? '')),
                  ];
              }
              ?>
              <?php if ($heroButtons): ?>
                <div class="hero-actions">
                  <?php foreach ($heroButtons as $buttonIndex => $heroButton): ?>
                    <?php
                    $buttonClass = 'cta' . ($buttonIndex > 0 ? ' cta-secondary' : '');
                    $buttonHref = pw_public_href($heroButton['url']);
                    ?>
                    <?php if ($buttonHref !== ''): ?>
                      <a class="<?= pw_e($buttonClass) ?>" href="<?= pw_e($buttonHref) ?>"><?= pw_e($heroButton['label']) ?></a>
                    <?php else: ?>
                      <span class="<?= pw_e($buttonClass) ?>"><?= pw_e($heroButton['label']) ?></span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($heroCount > 1): ?>
      <button type="button" class="hero-nav hero-prev" data-hero-prev aria-label="Previous slide"></button>
      <button type="button" class="hero-nav hero-next" data-hero-next aria-label="Next slide"></button>
      <div class="hero-dots" role="tablist" aria-label="Hero slides">
        <?php foreach ($heroes as $index => $hero): ?>
          <button
            type="button"
            class="hero-dot<?= $index === 0 ? ' is-active' : '' ?>"
            data-hero-dot="<?= (int) $index ?>"
            role="tab"
            aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
            aria-label="Show slide <?= (int) ($index + 1) ?>"
          ></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($showPageBanner): ?>
  <section
    class="page-banner banner-size-<?= pw_e($bannerSize) ?><?= $bannerImage !== '' ? ' has-image' : ' no-image' ?>"
    <?php if ($bannerImage !== ''): ?>style="--banner-image: url('<?= pw_e($bannerImage) ?>')"<?php endif; ?>
    aria-label="Page banner"
  >
    <div class="page-banner-overlay">
      <div class="page-banner-inner">
        <?php if ($bannerEyebrow !== ''): ?>
          <p class="page-banner-eyebrow"><?= pw_e($bannerEyebrow) ?></p>
        <?php endif; ?>
        <h1 class="page-banner-title"><?= pw_e($bannerTitle) ?></h1>
        <?php if ($bannerSubtitle !== ''): ?>
          <p class="page-banner-subtitle"><?= pw_e($bannerSubtitle) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php
  $hasPageData = $heroCount > 0 || $serviceCount > 0 || $profileCount > 0 || $visionCount > 0 || $featureCount > 0 || $testimonialCount > 0 || $ctaBannerCount > 0 || $partnerCount > 0 || $contactSocialCount > 0 || $showContactFormSection;
  ?>
  <?php if (!$hasPageData): ?>
  <main class="content<?= $showPageBanner ? '' : ' content-no-hero' ?> content-empty-page">
    <h2><?= $title ?></h2>
    <p class="muted-note">This page (`<?= pw_e($page['slug'] ?? '') ?>`) has no content sections to display yet.</p>
  </main>
  <?php endif; ?>

  <?php foreach ($sectionOrder as $section): ?>
    <?php
    if ((int) ($section['is_enabled'] ?? 1) !== 1) {
        continue;
    }
    $sectionKey = (string) ($section['section_key'] ?? '');
    ?>

    <?php if ($sectionKey === 'profile_overviews' && $profileCount > 0): ?>
  <section class="profile-section" aria-label="Profile overview">
    <?php foreach ($profiles as $profile): ?>
      <div class="profile-overview<?= (int)($profile['is_featured'] ?? 0) === 1 ? ' is-featured' : '' ?>">
        <div class="profile-copy">
          <h2 class="profile-title"><?= pw_e((string) $profile['title']) ?></h2>
          <div class="profile-body">
            <?= pw_rich((string) ($profile['body_html'] ?? '')) ?>
          </div>
        </div>
        <div class="profile-media">
          <?php if (!empty($profile['image_top_url'])): ?>
            <img class="profile-img profile-img-top" src="<?= pw_e((string) $profile['image_top_url']) ?>" alt="">
          <?php endif; ?>
          <div class="profile-media-row">
            <?php if (!empty($profile['image_left_url'])): ?>
              <img class="profile-img profile-img-left" src="<?= pw_e((string) $profile['image_left_url']) ?>" alt="">
            <?php endif; ?>
            <?php if (!empty($profile['image_right_url'])): ?>
              <img class="profile-img profile-img-right" src="<?= pw_e((string) $profile['image_right_url']) ?>" alt="">
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
    <?php endif; ?>

    <?php if ($sectionKey === 'vision_missions' && $visionCount > 0): ?>
  <section class="vision-section" aria-labelledby="vision-heading">
    <div class="vision-inner">
      <h2 id="vision-heading">Vision &amp; Mission</h2>
      <div class="vision-grid">
        <?php foreach ($visionMissions as $vm): ?>
          <article class="vision-card type-<?= pw_e((string)($vm['statement_type'] ?? 'vision')) ?><?= (int)($vm['is_featured'] ?? 0) === 1 ? ' is-featured' : '' ?>">
            <?php if (!empty($vm['image_url'])): ?>
              <img class="vision-image" src="<?= pw_e((string)$vm['image_url']) ?>" alt="">
            <?php endif; ?>
            <div class="vision-body">
              <p class="vision-type"><?= pw_e((string)($vm['statement_type_label'] ?? 'Vision')) ?></p>
              <h3><?= pw_e((string)$vm['title']) ?></h3>
              <div class="vision-copy">
                <?= pw_rich((string)($vm['body_html'] ?? '')) ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
    <?php endif; ?>

    <?php if ($sectionKey === 'features' && $featureCount > 0): ?>
  <?php foreach ($features as $feature): ?>
  <section class="features-section<?= (int)($feature['is_featured'] ?? 0) === 1 ? ' is-featured' : '' ?>" aria-label="<?= pw_e((string)$feature['title']) ?>">
    <div class="features-inner">
      <header class="features-header">
        <?php $featureMainTitle = trim((string) ($feature['main_title'] ?? '')); ?>
        <?php if ($featureMainTitle !== ''): ?>
          <p class="features-main-title"><?= pw_e($featureMainTitle) ?></p>
        <?php endif; ?>
        <h2 class="features-title"><?= pw_e((string)$feature['title']) ?></h2>
        <?php if (!empty($feature['description'])): ?>
          <p class="features-lede"><?= pw_e((string)$feature['description']) ?></p>
        <?php endif; ?>
      </header>
      <?php if (!empty($feature['cards'])): ?>
      <div class="features-grid">
        <?php foreach ($feature['cards'] as $card): ?>
          <article class="feature-card">
            <div class="feature-icon" aria-hidden="true">
              <?php if (!empty($card['icon_url'])): ?>
                <img src="<?= pw_e((string)$card['icon_url']) ?>" alt="">
              <?php else: ?>
                <?= (string)($card['icon_html'] ?? '') ?>
              <?php endif; ?>
            </div>
            <h3 class="feature-card-title"><?= pw_e((string)$card['title']) ?></h3>
            <?php if (!empty($card['description'])): ?>
              <p class="feature-card-desc"><?= pw_e((string)$card['description']) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sectionKey === 'testimonials' && $testimonialCount > 0): ?>
  <section
    class="testimonials-section<?= $testimonialCount > 1 ? ' has-multiple' : '' ?>"
    data-testimonials-slider
    aria-roledescription="carousel"
    aria-labelledby="testimonials-heading"
  >
    <header class="testimonials-header">
      <h2 id="testimonials-heading" class="testimonials-title">What Clients Say</h2>
    </header>
    <div class="testimonials-shell">
      <div class="testimonial-card-frame">
        <div class="testimonials-inner">
          <div class="testimonials-track">
            <?php foreach ($testimonials as $index => $t): ?>
              <article
                class="testimonial-card<?= $index === 0 ? ' is-active' : '' ?><?= (int)($t['is_featured'] ?? 0) === 1 ? ' is-featured' : '' ?>"
                data-testimonial-slide="<?= (int) $index ?>"
                <?= $index === 0 ? '' : 'hidden' ?>
                aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"
              >
                <div class="testimonial-stars" aria-label="<?= (int)($t['rating'] ?? 5) ?> out of 5 stars">
                  <?= (string)($t['stars_html'] ?? '') ?>
                </div>
                <p class="testimonial-quote">“<?= pw_e((string)($t['quote'] ?? '')) ?>”</p>
                <div class="testimonial-author">
                  <p class="testimonial-name"><?= pw_e((string)($t['author_name'] ?? '')) ?></p>
                  <?php if (!empty($t['author_role'])): ?>
                    <p class="testimonial-role"><?= pw_e((string)$t['author_role']) ?></p>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php if ($testimonialCount > 1): ?>
        <div class="testimonial-dots" role="tablist" aria-label="Testimonials">
          <?php foreach ($testimonials as $index => $t): ?>
            <button
              type="button"
              class="testimonial-dot<?= $index === 0 ? ' is-active' : '' ?>"
              data-testimonial-dot="<?= (int) $index ?>"
              role="tab"
              aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
              aria-label="Show testimonial <?= (int) ($index + 1) ?>"
            ></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
    <?php endif; ?>

    <?php if ($sectionKey === 'cta_banner' && $ctaBannerCount > 0): ?>
      <?php foreach ($ctaBanners as $cta): ?>
        <?php
          $ctaEyebrow = trim((string) ($cta['eyebrow'] ?? ''));
          $ctaTitle = trim((string) ($cta['title'] ?? ''));
          $ctaSubtitle = trim((string) ($cta['subtitle'] ?? ''));
          $ctaPrimaryText = trim((string) ($cta['primary_button_text'] ?? ''));
          $ctaPrimaryUrl = trim((string) ($cta['primary_button_url'] ?? ''));
          $ctaSecondaryText = trim((string) ($cta['secondary_button_text'] ?? ''));
          $ctaSecondaryUrl = trim((string) ($cta['secondary_button_url'] ?? ''));
          $ctaHeadingId = 'cta-heading-' . (int) ($cta['id'] ?? 0);
        ?>
  <section class="cta-banner-section" aria-labelledby="<?= pw_e($ctaHeadingId) ?>">
    <div class="cta-banner-inner">
      <div class="cta-banner-copy">
        <?php if ($ctaEyebrow !== ''): ?>
          <p class="cta-banner-eyebrow"><?= pw_e($ctaEyebrow) ?></p>
        <?php endif; ?>
        <?php if ($ctaTitle !== ''): ?>
          <h2 id="<?= pw_e($ctaHeadingId) ?>" class="cta-banner-title"><?= pw_e($ctaTitle) ?></h2>
        <?php endif; ?>
        <?php if ($ctaSubtitle !== ''): ?>
          <p class="cta-banner-subtitle"><?= pw_e($ctaSubtitle) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($ctaPrimaryText !== '' || $ctaSecondaryText !== ''): ?>
        <div class="cta-banner-actions">
          <?php if ($ctaPrimaryText !== ''): ?>
            <?php if ($ctaPrimaryUrl !== ''): ?>
              <a class="cta-banner-btn cta-banner-btn-primary" href="<?= pw_e(pw_public_href($ctaPrimaryUrl)) ?>"><?= pw_e($ctaPrimaryText) ?></a>
            <?php else: ?>
              <span class="cta-banner-btn cta-banner-btn-primary"><?= pw_e($ctaPrimaryText) ?></span>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($ctaSecondaryText !== ''): ?>
            <?php if ($ctaSecondaryUrl !== ''): ?>
              <a class="cta-banner-btn cta-banner-btn-secondary" href="<?= pw_e(pw_public_href($ctaSecondaryUrl)) ?>"><?= pw_e($ctaSecondaryText) ?></a>
            <?php else: ?>
              <span class="cta-banner-btn cta-banner-btn-secondary"><?= pw_e($ctaSecondaryText) ?></span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sectionKey === 'partners' && $partnerCount > 0): ?>
  <section class="partners-section" aria-labelledby="partners-heading">
    <div class="partners-inner">
      <header class="partners-header">
        <h2 id="partners-heading" class="partners-title"><?= pw_e((string) $partnersSectionTitle) ?></h2>
        <?php if (trim((string) $partnersSectionSubtitle) !== ''): ?>
          <p class="partners-subtitle"><?= pw_e((string) $partnersSectionSubtitle) ?></p>
        <?php endif; ?>
      </header>
      <div class="partners-grid">
        <?php foreach ($partners as $partner): ?>
          <?php
            $pName = trim((string) ($partner['name'] ?? ''));
            $pCategory = trim((string) ($partner['category'] ?? ''));
            $pBadge = trim((string) ($partner['badge_text'] ?? ''));
            $pDesc = trim((string) ($partner['description'] ?? ''));
            $pUseful = is_array($partner['useful_for_items'] ?? null)
                ? $partner['useful_for_items']
                : [];
            $pBtnText = trim((string) ($partner['button_text'] ?? ''));
            $pBtnUrl = trim((string) ($partner['button_url'] ?? ''));
            $pIconHtml = (string) ($partner['icon_html'] ?? '');
            if ($pIconHtml === '') {
                $pIconClass = trim((string) ($partner['icon_class_normalized'] ?? $partner['icon_class'] ?? 'fas fa-handshake'));
                if ($pIconClass === '') {
                    $pIconClass = 'fas fa-handshake';
                }
                if (preg_match('/^fa-[a-z0-9-]+$/i', $pIconClass) === 1) {
                    $pIconClass = 'fas ' . $pIconClass;
                }
                $pIconHtml = '<i class="' . pw_e($pIconClass) . '" aria-hidden="true"></i>';
            }
          ?>
          <article class="partner-card">
            <div class="partner-card-top">
              <div class="partner-icon" aria-hidden="true"><?= $pIconHtml ?></div>
            </div>
            <div class="partner-card-body">
              <?php if ($pBadge !== ''): ?>
                <p class="partner-badge"><?= pw_e($pBadge) ?></p>
              <?php endif; ?>
              <?php if ($pName !== ''): ?>
                <h3 class="partner-name"><?= pw_e($pName) ?></h3>
              <?php endif; ?>
              <?php if ($pCategory !== ''): ?>
                <p class="partner-category"><?= pw_e($pCategory) ?></p>
              <?php endif; ?>
              <?php if ($pDesc !== ''): ?>
                <p class="partner-desc"><?= pw_e($pDesc) ?></p>
              <?php endif; ?>
              <?php if ($pUseful !== []): ?>
                <p class="partner-useful-label">Useful for:</p>
                <ul class="partner-useful-list">
                  <?php foreach ($pUseful as $line): ?>
                    <li><?= pw_e((string) $line) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <?php if ($pBtnText !== ''): ?>
                <?php if ($pBtnUrl !== '' && $pBtnUrl !== '#'): ?>
                  <a class="partner-cta" href="<?= pw_e(pw_public_href($pBtnUrl)) ?>"><?= pw_e($pBtnText) ?></a>
                <?php else: ?>
                  <span class="partner-cta"><?= pw_e($pBtnText) ?></span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
    <?php endif; ?>

    <?php if ($sectionKey === 'services' && $serviceCount > 0): ?>
  <section class="services-section" aria-labelledby="services-heading">
    <div class="services-inner">
      <header class="services-header">
        <p class="services-eyebrow">Truck &amp; NEMT</p>
        <h2 id="services-heading">Our Dispatching Services</h2>
        <p class="services-lede">Choose the dispatching service that fits your transportation business.</p>
      </header>
      <div class="services-grid">
        <?php foreach ($services as $svc): ?>
          <?php
          $svcIconHtml = (string) ($svc['icon_html'] ?? '');
          if ($svcIconHtml === '') {
              $svcIconClass = trim((string) ($svc['icon_class'] ?? 'fa-truck'));
              if ($svcIconClass === '') {
                  $svcIconClass = 'fa-truck';
              }
              if (preg_match('/^fa-[a-z0-9-]+$/i', $svcIconClass) === 1) {
                  $svcIconClass = 'fas ' . $svcIconClass;
              }
              $svcIconHtml = '<i class="' . pw_e($svcIconClass) . '" aria-hidden="true"></i>';
          }
          $svcCtaText = trim((string) ($svc['button_text'] ?? ''));
          $svcCtaUrl = trim((string) ($svc['button_url'] ?? ''));
          if ($svcCtaText === '') {
              $svcCtaText = 'Learn More';
          }
          ?>
          <article class="service-card<?= (int)($svc['is_featured'] ?? 0) === 1 ? ' is-featured' : '' ?>">
            <div class="service-media<?= empty($svc['image_url']) ? ' is-empty' : '' ?>">
              <?php if (!empty($svc['image_url'])): ?>
                <img class="service-image" src="<?= pw_e((string)$svc['image_url']) ?>" alt="">
              <?php endif; ?>
            </div>
            <div class="service-body">
              <div class="service-icon" aria-hidden="true"><?= $svcIconHtml ?></div>
              <h3><?= pw_e((string)$svc['title']) ?></h3>
              <?php if (!empty($svc['description'])): ?>
                <p class="service-desc"><?= pw_e((string)$svc['description']) ?></p>
              <?php endif; ?>
              <?php if (!empty($svc['items'])): ?>
                <ul class="service-items">
                  <?php foreach ($svc['items'] as $item): ?>
                    <li>
                      <span class="service-item-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                      <span class="service-item-copy">
                        <strong><?= pw_e((string)$item['title']) ?></strong>
                        <?php if (!empty($item['body'])): ?>
                          <span><?= pw_e((string)$item['body']) ?></span>
                        <?php endif; ?>
                      </span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <?php if ($svcCtaUrl !== ''): ?>
                <a class="service-cta" href="<?= pw_e(pw_public_href($svcCtaUrl)) ?>"><?= pw_e($svcCtaText) ?></a>
              <?php else: ?>
                <span class="service-cta"><?= pw_e($svcCtaText) ?></span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
    <?php endif; ?>

    <?php if ($sectionKey === 'contact_social'): ?>
      <?php /* Contact & Social renders in the site footer */ continue; ?>
    <?php endif; ?>

    <?php if ($sectionKey === 'contact_form'): ?>
  <section class="contact-form-section" aria-labelledby="contact-form-heading">
    <div class="contact-form-wrap">
      <h2 id="contact-form-heading">Send us a message</h2>
      <p class="contact-form-lede">Tell us about your needs and we will get back to you.</p>
      <?php if ($contactFormError !== ''): ?>
        <div class="contact-form-alert is-error"><?= pw_e($contactFormError) ?></div>
      <?php endif; ?>
      <?php if ($contactFormSuccess !== '' && is_array($contactFormReceipt)): ?>
        <div class="contact-receipt" role="status" aria-live="polite">
          <div class="contact-receipt-badge" aria-hidden="true">✓</div>
          <h3 class="contact-receipt-title">Message sent successfully</h3>
          <p class="contact-receipt-lede"><?= pw_e($contactFormSuccess) ?> Here is a copy of what you submitted:</p>
          <dl class="contact-receipt-grid">
            <?php if ($contactFormReceipt['full_name'] !== ''): ?>
              <div><dt>Full Name</dt><dd><?= pw_e($contactFormReceipt['full_name']) ?></dd></div>
            <?php endif; ?>
            <?php if ($contactFormReceipt['company_name'] !== ''): ?>
              <div><dt>Company Name</dt><dd><?= pw_e($contactFormReceipt['company_name']) ?></dd></div>
            <?php endif; ?>
            <?php if ($contactFormReceipt['phone'] !== ''): ?>
              <div><dt>Phone Number</dt><dd><?= pw_e($contactFormReceipt['phone']) ?></dd></div>
            <?php endif; ?>
            <?php if ($contactFormReceipt['email'] !== ''): ?>
              <div><dt>Email Address</dt><dd><?= pw_e($contactFormReceipt['email']) ?></dd></div>
            <?php endif; ?>
            <?php if ($contactFormReceipt['preferred_service'] !== ''): ?>
              <div><dt>Preferred Service</dt><dd><?= pw_e($contactFormReceipt['preferred_service']) ?></dd></div>
            <?php endif; ?>
            <?php foreach ($contactFormReceipt['custom'] as $row): ?>
              <div><dt><?= pw_e((string) $row['label']) ?></dt><dd><?= pw_e((string) $row['value']) ?></dd></div>
            <?php endforeach; ?>
            <?php if ($contactFormReceipt['message'] !== ''): ?>
              <div class="contact-receipt-full"><dt>Comments / Message</dt><dd><?= nl2br(pw_e($contactFormReceipt['message'])) ?></dd></div>
            <?php endif; ?>
          </dl>
          <?php if ((int) ($contactFormReceipt['inquiry_id'] ?? 0) > 0): ?>
            <p class="contact-receipt-ref">Reference #<?= (int) $contactFormReceipt['inquiry_id'] ?></p>
          <?php endif; ?>
          <a class="contact-receipt-again" href="<?= pw_e(pw_url('/' . ltrim((string) ($page['slug'] ?? 'contact-us'), '/'))) ?>">Send another message</a>
        </div>
      <?php elseif ($contactFormSuccess !== ''): ?>
        <div class="contact-form-alert is-success"><?= pw_e($contactFormSuccess) ?></div>
      <?php endif; ?>
      <?php if ($contactFormSuccess === ''): ?>
      <form method="post" class="contact-form" novalidate>
        <input type="hidden" name="form_action" value="contact_inquiry">
        <?php if ((int) ($contactFormSettings['show_full_name'] ?? 1) === 1): ?>
          <label class="contact-field contact-field-half">
            <span class="contact-field-label">Full Name <span class="req" aria-hidden="true">*</span></span>
            <input type="text" name="full_name" required value="<?= pw_e($contactFormValues['full_name']) ?>" autocomplete="name" placeholder="Your full name">
          </label>
        <?php endif; ?>
        <?php if ((int) ($contactFormSettings['show_company_name'] ?? 1) === 1): ?>
          <label class="contact-field contact-field-half">
            <span class="contact-field-label">Company Name</span>
            <input type="text" name="company_name" value="<?= pw_e($contactFormValues['company_name']) ?>" autocomplete="organization" placeholder="Optional">
          </label>
        <?php endif; ?>
        <?php if ((int) ($contactFormSettings['show_phone'] ?? 1) === 1): ?>
          <label class="contact-field contact-field-third">
            <span class="contact-field-label">Phone Number <span class="req" aria-hidden="true">*</span></span>
            <input type="tel" name="phone" required value="<?= pw_e($contactFormValues['phone']) ?>" autocomplete="tel" placeholder="+252 …">
          </label>
        <?php endif; ?>
        <?php if ((int) ($contactFormSettings['show_email'] ?? 1) === 1): ?>
          <label class="contact-field contact-field-third">
            <span class="contact-field-label">Email Address <span class="req" aria-hidden="true">*</span></span>
            <input type="email" name="email" required value="<?= pw_e($contactFormValues['email']) ?>" autocomplete="email" placeholder="name@example.com">
          </label>
        <?php endif; ?>
        <?php if ((int) ($contactFormSettings['show_preferred_service'] ?? 1) === 1): ?>
          <label class="contact-field contact-field-third">
            <span class="contact-field-label">Preferred Service <span class="req" aria-hidden="true">*</span></span>
            <select name="preferred_service_id" required>
              <option value="">Select a service</option>
              <?php foreach ($allServices as $svc): ?>
                <option value="<?= (int) ($svc['id'] ?? 0) ?>" <?= (string) $contactFormValues['preferred_service_id'] === (string) ($svc['id'] ?? '') ? 'selected' : '' ?>>
                  <?= pw_e((string) ($svc['title'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>
        <?php foreach ($contactCustomFields as $cf): ?>
          <?php
            $ck = (string) ($cf['field_key'] ?? '');
            if ($ck === '') {
                continue;
            }
            $clabel = (string) ($cf['label'] ?? $ck);
            $ctype = (string) ($cf['field_type'] ?? 'text');
            $creq = (int) ($cf['is_required'] ?? 0) === 1;
            $cval = (string) ($contactFormValues[$ck] ?? '');
            $fieldClass = $ctype === 'textarea' ? 'contact-field contact-field-full' : 'contact-field contact-field-half';
          ?>
          <label class="<?= $fieldClass ?>">
            <span class="contact-field-label"><?= pw_e($clabel) ?><?php if ($creq): ?> <span class="req" aria-hidden="true">*</span><?php endif; ?></span>
            <?php if ($ctype === 'textarea'): ?>
              <textarea name="<?= pw_e($ck) ?>" rows="4" <?= $creq ? 'required' : '' ?>><?= pw_e($cval) ?></textarea>
            <?php elseif ($ctype === 'select'): ?>
              <select name="<?= pw_e($ck) ?>" <?= $creq ? 'required' : '' ?>>
                <option value="">Select…</option>
                <?php foreach (($cf['options'] ?? []) as $opt): ?>
                  <?php
                    if (is_array($opt)) {
                        $optValue = (string) ($opt['value'] ?? '');
                        $optLabel = (string) ($opt['label'] ?? $optValue);
                    } else {
                        $optValue = (string) $opt;
                        $optLabel = $optValue;
                    }
                    if ($optValue === '') {
                        continue;
                    }
                  ?>
                  <option value="<?= pw_e($optValue) ?>" <?= $cval === $optValue ? 'selected' : '' ?>><?= pw_e($optLabel) ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($ctype === 'number'): ?>
              <input type="number" step="any" name="<?= pw_e($ck) ?>" value="<?= pw_e($cval) ?>" <?= $creq ? 'required' : '' ?>>
            <?php elseif ($ctype === 'date'): ?>
              <input type="date" name="<?= pw_e($ck) ?>" value="<?= pw_e($cval) ?>" <?= $creq ? 'required' : '' ?>>
            <?php else: ?>
              <input type="text" name="<?= pw_e($ck) ?>" value="<?= pw_e($cval) ?>" <?= $creq ? 'required' : '' ?>>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
        <?php if ((int) ($contactFormSettings['show_message'] ?? 1) === 1): ?>
          <label class="contact-field contact-field-full">
            <span class="contact-field-label">Comments / Message <span class="req" aria-hidden="true">*</span></span>
            <textarea name="message" rows="5" required placeholder="How can we help you?"><?= pw_e($contactFormValues['message']) ?></textarea>
          </label>
        <?php endif; ?>
        <div class="contact-form-actions">
          <button type="submit" class="contact-form-submit">Submit</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </section>
    <?php endif; ?>

  <?php endforeach; ?>

  <?php
  // Quick Links: all active top-level website menus (same on every page)
  $footerQuickLinks = [];
  foreach ($menus as $item) {
      $title = trim((string) ($item['title'] ?? ''));
      if ($title === '') {
          continue;
      }
      $href = (string) ($item['url'] ?? '');
      if ($href === '' && !empty($item['pages'][0]['slug'])) {
          $href = '/' . $item['pages'][0]['slug'];
      }
      if ($href === '') {
          $href = '#';
      }
      $footerQuickLinks[] = ['title' => $title, 'url' => $href];
  }

  // Services: all active services from database (same on every page)
  $footerServiceLinks = [];
  $footerServicesPageUrl = '/services';
  foreach ($menus as $item) {
      if (strcasecmp(trim((string) ($item['title'] ?? '')), 'Services') !== 0) {
          continue;
      }
      $href = (string) ($item['url'] ?? '');
      if ($href === '' && !empty($item['pages'][0]['slug'])) {
          $href = '/' . $item['pages'][0]['slug'];
      }
      if ($href !== '') {
          $footerServicesPageUrl = $href;
      }
      break;
  }
  foreach ($allServices as $svc) {
      $title = trim((string) ($svc['title'] ?? ''));
      if ($title === '') {
          continue;
      }
      $footerServiceLinks[] = [
          'title' => $title,
          'url' => $footerServicesPageUrl,
      ];
  }

  $footerTagline = trim((string) (($contactBlock ?? [])['tagline'] ?? ''));
  $footerCompanyName = trim((string) (($contactBlock ?? [])['company_name'] ?? ''));
  $footerSummary = trim((string) (($contactBlock ?? [])['company_summary'] ?? ''));
  $footerEmail = trim((string) (($contactBlock ?? [])['email'] ?? ''));
  $footerHours = trim((string) (($contactBlock ?? [])['business_hours'] ?? ''));
  $footerLogo = trim((string) (($contactBlock ?? [])['logo_url'] ?? ''));
  $footerPhones = is_array(($contactBlock ?? [])['phones'] ?? null) ? $contactBlock['phones'] : [];
  $footerAddresses = is_array(($contactBlock ?? [])['addresses'] ?? null) ? $contactBlock['addresses'] : [];
  ?>

  <?php if ($showSiteFooter): ?>
  <footer
    class="site-footer-main"
    aria-label="Site footer"
    style="--footer-bg: <?= pw_e($footerBg) ?>; --footer-text: <?= pw_e($footerText) ?>; --footer-heading: <?= pw_e($footerHeading) ?>; --footer-accent: <?= pw_e($footerAccent) ?>; --footer-social-bg: <?= pw_e($footerSocialBg) ?>;"
  >
    <div class="footer-main-inner">
      <div class="footer-col footer-brand">
        <?php if ($footerLogo !== ''): ?>
          <img class="footer-logo-img" src="<?= pw_e($footerLogo) ?>" alt="<?= pw_e($footerCompanyName !== '' ? $footerCompanyName : strip_tags((string) pw_env('APP_NAME', 'N'))) ?>">
        <?php else: ?>
          <div class="footer-logo" aria-hidden="true"><?= mb_strtoupper(mb_substr($footerCompanyName !== '' ? $footerCompanyName : strip_tags((string) pw_env('APP_NAME', 'N')), 0, 1)) ?></div>
        <?php endif; ?>
        <?php if ($footerCompanyName !== ''): ?>
          <p class="footer-company-name"><?= pw_e($footerCompanyName) ?></p>
        <?php endif; ?>
        <?php if ($footerTagline !== ''): ?>
          <p class="footer-tagline"><?= pw_e($footerTagline) ?></p>
        <?php elseif ($footerCompanyName === ''): ?>
          <p class="footer-tagline"><?= $siteName ?></p>
        <?php endif; ?>
        <?php if ($footerSummary !== ''): ?>
          <p class="footer-summary"><?= pw_e($footerSummary) ?></p>
        <?php endif; ?>
      </div>

      <div class="footer-col">
        <h3 class="footer-heading">Quick Links</h3>
        <?php if ($footerQuickLinks): ?>
          <ul class="footer-menu">
            <?php foreach ($footerQuickLinks as $link): ?>
              <li><a href="<?= pw_e(pw_url($link['url'])) ?>"><?= pw_e($link['title']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="footer-empty">No menus yet.</p>
        <?php endif; ?>
      </div>

      <div class="footer-col">
        <h3 class="footer-heading"><a class="footer-heading-link" href="<?= pw_e(pw_url($footerServicesPageUrl)) ?>">Services</a></h3>
        <?php if ($footerServiceLinks): ?>
          <ul class="footer-menu">
            <?php foreach ($footerServiceLinks as $link): ?>
              <li><a href="<?= pw_e(pw_url($link['url'])) ?>"><?= pw_e($link['title']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="footer-empty">No services linked.</p>
        <?php endif; ?>
      </div>

      <div class="footer-col footer-company">
        <h3 class="footer-heading">Company Information</h3>
        <ul class="footer-contact">
          <?php foreach ($footerPhones as $ph): ?>
            <?php $phone = trim((string) ($ph['phone'] ?? '')); if ($phone === '') continue; ?>
            <li>
              <span class="footer-ico" aria-hidden="true"><i class="fas fa-phone"></i></span>
              <a href="tel:<?= pw_e(preg_replace('/[^0-9+]/', '', $phone) ?: $phone) ?>"><?= pw_e($phone) ?></a>
            </li>
          <?php endforeach; ?>
          <?php if ($footerEmail !== ''): ?>
            <li>
              <span class="footer-ico" aria-hidden="true"><i class="fas fa-envelope"></i></span>
              <a href="mailto:<?= pw_e($footerEmail) ?>"><?= pw_e($footerEmail) ?></a>
            </li>
          <?php endif; ?>
          <?php foreach ($footerAddresses as $addr): ?>
            <?php $text = trim((string) ($addr['address_text'] ?? '')); if ($text === '') continue; ?>
            <li>
              <span class="footer-ico" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
              <span><?= pw_e($text) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if ($footerHours !== ''): ?>
            <li>
              <span class="footer-ico" aria-hidden="true"><i class="fas fa-clock"></i></span>
              <span><?= pw_e($footerHours) ?></span>
            </li>
          <?php endif; ?>
        </ul>
        <?php if ($socialCount > 0): ?>
          <ul class="footer-social">
            <?php foreach ($socialLinks as $sl): ?>
              <li>
                <a href="<?= pw_e((string) ($sl['link_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= pw_e((string) ($sl['label'] ?? 'Social')) ?>">
                  <?= (string) ($sl['icon_html'] ?? '') ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> <?= $siteName ?>. All rights reserved.</p>
    </div>
  </footer>
  <?php else: ?>
  <footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= $siteName ?></p>
  </footer>
  <?php endif; ?>
  <script src="<?= pw_e(pw_url('/assets/js/site.js')) ?>"></script>
</body>
</html>
