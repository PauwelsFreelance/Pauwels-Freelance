-- ============================================================
-- Pauwels Freelance — admin panel schema
-- Run this ONCE against a fresh database via phpMyAdmin (or the
-- SQL import tool your hosting panel provides). Safe to re-run —
-- every statement uses IF NOT EXISTS / INSERT IGNORE.
-- ============================================================

CREATE TABLE IF NOT EXISTS admin_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Portfolio ----------
CREATE TABLE IF NOT EXISTS portfolio_projects (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(150) NOT NULL,
  description   TEXT         NOT NULL,
  tags          VARCHAR(255) NOT NULL DEFAULT '',   -- comma-separated: "PHP,SQL,JavaScript"
  image_filename VARCHAR(255) NOT NULL,              -- filename inside assets/, e.g. project-1.jpg
  cta_text      VARCHAR(100) NOT NULL DEFAULT 'I want something like this',
  contact_type  VARCHAR(150) NOT NULL DEFAULT '',    -- becomes contact.html?type=...
  sort_order    INT NOT NULL DEFAULT 0,
  is_published  TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Configurator: tiers ----------
CREATE TABLE IF NOT EXISTS configurator_tiers (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tier_key      VARCHAR(30)  NOT NULL UNIQUE,   -- 'small' / 'normal' / 'large'
  tag           VARCHAR(50)  NOT NULL,          -- 'Small'
  name          VARCHAR(150) NOT NULL,          -- 'One-page site'
  full_name     VARCHAR(200) NOT NULL,          -- 'Small project — one-page site'
  duration_text VARCHAR(100) NOT NULL,          -- 'About 1–2 weeks'
  sort_order    INT NOT NULL DEFAULT 0,
  is_published  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configurator_tier_features (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tier_id       INT NOT NULL,
  feature_text  VARCHAR(255) NOT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  FOREIGN KEY (tier_id) REFERENCES configurator_tiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Configurator: add-ons ----------
CREATE TABLE IF NOT EXISTS configurator_addon_categories (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(100) NOT NULL,   -- 'Accounts & access'
  sort_order    INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configurator_addons (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  category_id   INT NOT NULL,
  addon_key     VARCHAR(30)  NOT NULL UNIQUE,   -- 'login', 'gdpr', ...
  label         VARCHAR(255) NOT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  FOREIGN KEY (category_id) REFERENCES configurator_addon_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS configurator_presets (
  tier_id       INT NOT NULL,
  addon_id      INT NOT NULL,
  PRIMARY KEY (tier_id, addon_id),
  FOREIGN KEY (tier_id)  REFERENCES configurator_tiers(id)  ON DELETE CASCADE,
  FOREIGN KEY (addon_id) REFERENCES configurator_addons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Login throttling ----------
CREATE TABLE IF NOT EXISTS login_attempts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  ip_address    VARCHAR(45) NOT NULL,
  attempted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Customer submissions (contact form + configurator selections) ----------
CREATE TABLE IF NOT EXISTS submissions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(200) NOT NULL,
  email         VARCHAR(200) NOT NULL,
  project_type  VARCHAR(255) NOT NULL DEFAULT '',
  message       TEXT         NOT NULL,
  is_read       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- Seed data — mirrors what's currently hardcoded on the site.
-- Edit freely from the admin panel afterwards.
-- ============================================================

INSERT IGNORE INTO configurator_tiers (tier_key, tag, name, full_name, duration_text, sort_order) VALUES
('small',  'Small',  'One-page site',            'Small project — one-page site',            'About 1–2 weeks', 1),
('normal', 'Normal', 'Multi-page + database',    'Normal project — multi-page + database',   'About 3–5 weeks', 2),
('large',  'Large',  'Platform + admin panel',   'Large project — platform + admin panel',   'Scoped on a call', 3);

INSERT INTO configurator_tier_features (tier_id, feature_text, sort_order)
SELECT id, f.txt, f.ord FROM configurator_tiers
JOIN (
  SELECT 'small' AS tier_key, 'Single-page custom website' AS txt, 1 AS ord
  UNION ALL SELECT 'small', 'Works on phone, tablet and desktop', 2
  UNION ALL SELECT 'small', '2 rounds of revisions', 3
  UNION ALL SELECT 'small', 'Contact form included', 4
  UNION ALL SELECT 'small', 'Basic search-engine setup', 5
  UNION ALL SELECT 'normal', 'Up to 5 pages', 1
  UNION ALL SELECT 'normal', 'Database connection for forms, content or listings', 2
  UNION ALL SELECT 'normal', '3 rounds of revisions', 3
  UNION ALL SELECT 'normal', 'Works on phone, tablet and desktop', 4
  UNION ALL SELECT 'normal', 'Basic search-engine setup', 5
  UNION ALL SELECT 'large', '5+ pages, built to grow', 1
  UNION ALL SELECT 'large', 'Full database behind the site', 2
  UNION ALL SELECT 'large', 'Admin panel you log into from a browser', 3
  UNION ALL SELECT 'large', 'User accounts & permissions', 4
  UNION ALL SELECT 'large', 'Revisions scoped per project', 5
) f ON f.tier_key = configurator_tiers.tier_key
WHERE NOT EXISTS (SELECT 1 FROM configurator_tier_features WHERE tier_id = configurator_tiers.id);

INSERT IGNORE INTO configurator_addon_categories (id, title, sort_order) VALUES
(1, 'Accounts & access', 1),
(2, 'Content & data', 2),
(3, 'Integrations', 3),
(4, 'Trust & security', 4),
(5, 'Extras', 5);

INSERT IGNORE INTO configurator_addons (category_id, addon_key, label, sort_order) VALUES
(1, 'login',          'User sign-up & login',            1),
(1, 'social',         'Social login (Google, etc.)',      2),
(1, 'roles',          'Roles & permission levels',        3),
(1, 'twofa',          'Extra login security (2FA)',       4),
(1, 'pwreset',        'Self-service password reset',      5),
(2, 'blog',           'Blog / news section',              1),
(2, 'search',         'Search or filtering across listings', 2),
(2, 'migration',      'Migrating content from an existing site', 3),
(2, 'multilang',      'Multiple languages',               4),
(2, 'cms',            'Editable content via admin panel', 5),
(2, 'analytics',      'Analytics & visitor reporting setup', 6),
(3, 'payments',       'Online payments',                  1),
(3, 'booking',        'Booking / scheduling',             2),
(3, 'newsletter',     'Newsletter or CRM connection',     3),
(3, 'maps',           'Maps or other third-party embeds', 4),
(3, 'emailtemplates', 'Custom HTML email templates (branded, tested across clients)', 5),
(3, 'chat',           'Live chat widget',                 6),
(4, 'hardening',      'Extra security hardening',         1),
(4, 'gdpr',           'GDPR & cookie consent',            2),
(4, 'backups',        'Automated backups',                3),
(4, 'accessibility',  'Accessibility (WCAG) improvements', 4),
(5, 'photo',          'Custom illustrations or photography', 1),
(5, 'revisions',      'Extra rounds of revisions',        2),
(5, 'training',       'Training on the admin panel',      3),
(5, 'seo',            'Advanced SEO & structured data',   4),
(5, 'speed',          'Performance & image optimization', 5),
(5, 'rush',           'Rush delivery',                    6);

-- Presets: which add-ons come pre-checked per tier
INSERT IGNORE INTO configurator_presets (tier_id, addon_id)
SELECT t.id, a.id FROM configurator_tiers t, configurator_addons a
WHERE (t.tier_key = 'small'  AND a.addon_key IN ('gdpr'))
   OR (t.tier_key = 'normal' AND a.addon_key IN ('gdpr','backups','analytics','newsletter'))
   OR (t.tier_key = 'large'  AND a.addon_key IN ('login','roles','gdpr','backups','accessibility','hardening','training'));

INSERT IGNORE INTO portfolio_projects (id, title, description, tags, image_filename, cta_text, contact_type, sort_order) VALUES
(1, 'Knihovna DEN',
    'Redesigned the library''s public website and moved their book management system off MS Access into a browser-based tool the staff can use from anywhere.',
    'PHP,SQL,JavaScript,CSS,HTML', 'project-1.jpg', 'I want something like this', 'Inquiry about: Knihovna DEN', 1),
(2, 'PC Gear',
    'A price-comparison and affiliate site for computers, laptops and components, tracking thousands of products across Czech retailers.',
    'PHP,SQL,JavaScript,CSS,HTML', 'project-2.jpg', 'I want something like this', 'Inquiry about: PC Gear', 2);
