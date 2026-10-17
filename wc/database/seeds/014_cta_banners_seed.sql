-- Idempotent seeds for CTA Banner module + Own menus parent
SET NAMES utf8mb4;
SET time_zone = '+00:00';

INSERT INTO permissions (module, action, code, description, is_system, created_at)
SELECT m.module, m.action, CONCAT(m.module, '.', m.action), m.description, 1, UTC_TIMESTAMP()
FROM (
    SELECT 'cta_banners' AS module, 'view' AS action, 'View CTA banners' AS description UNION ALL
    SELECT 'cta_banners', 'create', 'Create CTA banners' UNION ALL
    SELECT 'cta_banners', 'edit', 'Edit CTA banners' UNION ALL
    SELECT 'cta_banners', 'delete', 'Soft-delete CTA banners' UNION ALL
    SELECT 'cta_banners', 'restore', 'Restore CTA banners'
) AS m
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p WHERE p.code_active = CONCAT(m.module, '.', m.action)
);

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, UTC_TIMESTAMP()
FROM roles r
CROSS JOIN permissions p
WHERE r.code_active = 'super_admin'
  AND p.code LIKE 'cta_banners.%'
  AND p.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id AND rp.deleted_at IS NULL
  );

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, UTC_TIMESTAMP()
FROM roles r
CROSS JOIN permissions p
WHERE r.code_active = 'admin'
  AND p.code LIKE 'cta_banners.%'
  AND p.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id AND rp.deleted_at IS NULL
  );

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, UTC_TIMESTAMP()
FROM roles r
CROSS JOIN permissions p
WHERE r.code_active = 'editor'
  AND p.deleted_at IS NULL
  AND p.code IN ('cta_banners.view', 'cta_banners.create', 'cta_banners.edit')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id AND rp.deleted_at IS NULL
  );

INSERT INTO pages (page_type, title, slug, template_key, is_active, created_at)
SELECT 'admin', 'CTA', 'cta', 'cta', 1, UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM pages p WHERE p.type_slug_active = 'admin:cta'
);

-- Parent: Own menus
INSERT INTO menus (menu_type, parent_menu_id, title, icon, url, sort_order, is_active, created_at)
SELECT 'admin', NULL, 'Own menus', 'folder', NULL, 25, 1, UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM menus m
    WHERE m.menu_type = 'admin' AND m.title = 'Own menus' AND m.parent_menu_id IS NULL AND m.deleted_at IS NULL
);

-- Child: CTA under Own menus
INSERT INTO menus (menu_type, parent_menu_id, title, icon, url, sort_order, is_active, created_at)
SELECT 'admin', parent.id, 'CTA', NULL, '/cta', 10, 1, UTC_TIMESTAMP()
FROM menus parent
WHERE parent.menu_type = 'admin'
  AND parent.title = 'Own menus'
  AND parent.parent_menu_id IS NULL
  AND parent.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM menus m
      WHERE m.menu_type = 'admin' AND m.title = 'CTA' AND m.url = '/cta' AND m.deleted_at IS NULL
  );

INSERT INTO menu_pages (menu_id, page_id, sort_order, created_at)
SELECT m.id, p.id, 0, UTC_TIMESTAMP()
FROM menus m
JOIN pages p ON p.page_type = 'admin' AND p.slug = 'cta' AND p.deleted_at IS NULL
WHERE m.menu_type = 'admin' AND m.title = 'CTA' AND m.url = '/cta' AND m.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM menu_pages mp WHERE mp.menu_id = m.id AND mp.page_id = p.id AND mp.deleted_at IS NULL
  );

-- Sample CTA banner matching mockup
INSERT INTO cta_banners (
    eyebrow, title, subtitle,
    primary_button_text, primary_button_url,
    secondary_button_text, secondary_button_url,
    sort_order, is_active, created_at
)
SELECT
    'CONTACT NAVO DISPATCH',
    'Ready to Get Started?',
    'Reach out today for professional truck or NEMT dispatching support.',
    'Contact Us',
    '/contact-us',
    'Get Started',
    '/contact-us',
    10,
    1,
    UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM cta_banners c
    WHERE c.title = 'Ready to Get Started?' AND c.deleted_at IS NULL
);

INSERT INTO cta_banner_pages (cta_banner_id, page_id, is_featured, is_active, sort_order, created_at)
SELECT c.id, p.id, 1, 1, 10, UTC_TIMESTAMP()
FROM cta_banners c
CROSS JOIN pages p
WHERE p.page_type = 'website'
  AND p.slug = 'home'
  AND p.deleted_at IS NULL
  AND c.deleted_at IS NULL
  AND c.title = 'Ready to Get Started?'
  AND NOT EXISTS (
      SELECT 1 FROM cta_banner_pages cp
      WHERE cp.cta_banner_id = c.id AND cp.page_id = p.id AND cp.deleted_at IS NULL
  );
