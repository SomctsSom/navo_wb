-- Idempotent seeds for Partners module under Own menus
SET NAMES utf8mb4;
SET time_zone = '+00:00';

INSERT INTO permissions (module, action, code, description, is_system, created_at)
SELECT m.module, m.action, CONCAT(m.module, '.', m.action), m.description, 1, UTC_TIMESTAMP()
FROM (
    SELECT 'partners' AS module, 'view' AS action, 'View partners' AS description UNION ALL
    SELECT 'partners', 'create', 'Create partners' UNION ALL
    SELECT 'partners', 'edit', 'Edit partners' UNION ALL
    SELECT 'partners', 'delete', 'Soft-delete partners' UNION ALL
    SELECT 'partners', 'restore', 'Restore partners'
) AS m
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p WHERE p.code_active = CONCAT(m.module, '.', m.action)
);

INSERT INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, UTC_TIMESTAMP()
FROM roles r
CROSS JOIN permissions p
WHERE r.code_active = 'super_admin'
  AND p.code LIKE 'partners.%'
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
  AND p.code LIKE 'partners.%'
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
  AND p.code IN ('partners.view', 'partners.create', 'partners.edit')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id AND rp.deleted_at IS NULL
  );

INSERT INTO pages (page_type, title, slug, template_key, is_active, created_at)
SELECT 'admin', 'Partners', 'partners', 'partners', 1, UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM pages p WHERE p.type_slug_active = 'admin:partners'
);

-- Ensure Own menus parent exists
INSERT INTO menus (menu_type, parent_menu_id, title, icon, url, sort_order, is_active, created_at)
SELECT 'admin', NULL, 'Own menus', 'folder', NULL, 25, 1, UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM menus m
    WHERE m.menu_type = 'admin' AND m.title = 'Own menus' AND m.parent_menu_id IS NULL AND m.deleted_at IS NULL
);

INSERT INTO menus (menu_type, parent_menu_id, title, icon, url, sort_order, is_active, created_at)
SELECT 'admin', parent.id, 'Partners', NULL, '/partners', 20, 1, UTC_TIMESTAMP()
FROM menus parent
WHERE parent.menu_type = 'admin'
  AND parent.title = 'Own menus'
  AND parent.parent_menu_id IS NULL
  AND parent.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM menus m
      WHERE m.menu_type = 'admin' AND m.title = 'Partners' AND m.url = '/partners' AND m.deleted_at IS NULL
  );

INSERT INTO menu_pages (menu_id, page_id, sort_order, created_at)
SELECT m.id, p.id, 0, UTC_TIMESTAMP()
FROM menus m
JOIN pages p ON p.page_type = 'admin' AND p.slug = 'partners' AND p.deleted_at IS NULL
WHERE m.menu_type = 'admin' AND m.title = 'Partners' AND m.url = '/partners' AND m.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM menu_pages mp WHERE mp.menu_id = m.id AND mp.page_id = p.id AND mp.deleted_at IS NULL
  );

INSERT INTO partners (
    section_title, section_subtitle, icon_class, badge_text, name, category, description, useful_for,
    button_text, button_url, sort_order, is_active, created_at
)
SELECT s.section_title, s.section_subtitle, s.icon_class, s.badge_text, s.name, s.category, s.description, s.useful_for,
       s.button_text, s.button_url, s.sort_order, 1, UTC_TIMESTAMP()
FROM (
    SELECT
        'NEMT Software Partners' AS section_title,
        'Explore recommended load boards and dispatch tools.' AS section_subtitle,
        'fa-handshake' AS icon_class,
        'RECOMMENDED PARTNER — PARTNERSHIP PENDING' AS badge_text,
        'RouteGenie' AS name,
        'NEMT Software' AS category,
        'NEMT software covering scheduling, routing, dispatching, billing, reporting, and driver/passenger applications.' AS description,
        'Scheduling\nRouting\nDispatching\nBilling\nReporting\nDriver & passenger apps' AS useful_for,
        'Explore RouteGenie' AS button_text,
        '#' AS button_url,
        10 AS sort_order
    UNION ALL
    SELECT
        'NEMT Software Partners',
        'Explore recommended load boards and dispatch tools.',
        'fa-handshake',
        'RECOMMENDED PARTNER — PARTNERSHIP PENDING',
        'RouteOps',
        'Transportation Management',
        'Transportation-management tools designed for NEMT and healthcare transportation businesses.',
        'NEMT operations\nHealthcare transportation\nDispatch tools',
        'Explore RouteOps',
        '#',
        20
) AS s
WHERE NOT EXISTS (
    SELECT 1 FROM partners p WHERE p.name = s.name AND p.deleted_at IS NULL
);

-- Backfill subtitle for existing partner rows that share a section title
UPDATE partners
SET section_subtitle = 'Explore recommended load boards and dispatch tools.',
    updated_at = UTC_TIMESTAMP()
WHERE deleted_at IS NULL
  AND (section_subtitle IS NULL OR section_subtitle = '')
  AND (
    section_title LIKE '%Partners%'
    OR section_title LIKE '%Tools%'
    OR name IN ('RouteGenie', 'RouteOps', 'DAT Load Board', 'LoadOffice', 'LaneRunner')
  );

INSERT INTO partner_pages (partner_id, page_id, is_featured, is_active, sort_order, created_at)
SELECT pr.id, pg.id, IF(pr.sort_order = 10, 1, 0), 1, pr.sort_order, UTC_TIMESTAMP()
FROM partners pr
CROSS JOIN pages pg
WHERE pg.page_type = 'website'
  AND pg.slug = 'home'
  AND pg.deleted_at IS NULL
  AND pr.deleted_at IS NULL
  AND pr.name IN ('RouteGenie', 'RouteOps')
  AND NOT EXISTS (
      SELECT 1 FROM partner_pages pp
      WHERE pp.partner_id = pr.id AND pp.page_id = pg.id AND pp.deleted_at IS NULL
  );
