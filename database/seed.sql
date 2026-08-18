-- =====================================================================
--  MancWay Mobile Mechanics — seed data
--  Run AFTER schema.sql.
--  NOTE: No admin user is inserted here for security. Create the admin
--  account by visiting  https://<your-domain>/setup.php  once the site
--  is deployed, then DELETE setup.php.
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Services
-- ---------------------------------------------------------------------
INSERT INTO services (slug, title, icon, short_desc, description, price_from, sort_order, is_active) VALUES
('full-service',         'Full Service',           'cogs',      'Comprehensive annual vehicle service.',
 'A thorough annual service covering oil and filter change, full multipoint inspection, brake check, fluid top-ups and a road test — keeping your car healthy and its resale value high.', 149.00, 1, 1),
('interim-service',      'Interim Service',        'wrench',    'Keep things running between full services.',
 'A mid-year check covering oil and filter change plus a 30-point inspection. Ideal for higher-mileage drivers to stay ahead of wear and tear.', 89.00, 2, 1),
('mot-preparation',      'MOT Preparation',        'clipboard', 'Get ready to pass first time.',
 'We check your car against MOT failure points — lights, brakes, tyres, suspension, emissions — and fix minor issues on the spot so you pass first time.', 60.00, 3, 1),
('brakes',               'Brakes',                 'disc',      'Pads, discs and diagnostics.',
 'Brake pad and disc replacement, brake fluid change and ABS diagnostics. Genuine-quality parts fitted at your home or workplace.', 79.00, 4, 1),
('diagnostics',          'Diagnostics',            'search',    'Find and fix that warning light.',
 'Full OBD-II diagnostic scan, fault-code analysis and a clear written report. We explain what is urgent and what can wait — no guesswork, no upsell.', 45.00, 5, 1),
('battery-alternator',   'Battery & Alternator',   'bolt',      'Won''t start? We come to you.',
 'Battery testing, replacement and alternator/charging-system checks. Same-day mobile fitting across Greater Manchester.', 65.00, 6, 1),
('timing-belt-cambelt',  'Timing Belt / Cambelt',  'cog',       'Timing-belt replacement to schedule.',
 'Cambelt and water-pump replacement to manufacturer intervals. Avoid costly engine damage — book before your deadline.', 299.00, 7, 1),
('clutch',               'Clutch Replacement',     'gears',     'New clutch, fitted mobile.',
 'Clutch kit supply and fit, including dual-mass flywheel inspection. Quality kits, honest pricing, done at your convenience.', 380.00, 8, 1),
('tyres-alignment',      'Tyres & Alignment',      'tyre',      'Mobile tyre fit and tracking.',
 'Mobile tyre fitting, wheel balancing and 4-wheel alignment. We bring the workshop to you.', 25.00, 9, 1),
('mobile-breakdown',     'Mobile Breakdown',       'truck',     'Roadside help across Greater Manchester.',
 'Broken down or won''t start? Our mobile mechanic will reach you across Greater Manchester and get you moving — or recover you safely.', 49.00, 10, 1);

-- ---------------------------------------------------------------------
-- Areas served
-- ---------------------------------------------------------------------
INSERT INTO areas (name, slug, postcodes, sort_order, is_active) VALUES
('Manchester City', 'manchester-city', 'M1, M2, M3, M4, M11–M15, M20, M21', 1, 1),
('Salford',         'salford',          'M5, M6, M7, M27, M28, M30, M38', 2, 1),
('Trafford',        'trafford',         'M16, M17, M21, M22, M23, M32, M33, M41', 3, 1),
('Stockport',       'stockport',        'SK1–SK8', 4, 1),
('Tameside',         'tameside',         'M34, M35, M43, M44, OL5–OL7, SK14–SK16', 5, 1),
('Bury',             'bury',             'BL0, BL8, BL9, M25, M26, M45, M46', 6, 1),
('Bolton',           'bolton',           'BL1–BL7', 7, 1),
('Rochdale',         'rochdale',         'OL11–OL16, M24', 8, 1),
('Oldham',           'oldham',           'OL1–OL4, OL8, OL9', 9, 1),
('Wigan',            'wigan',            'WN1–WN8', 10, 1);
-- ---------------------------------------------------------------------
-- Site settings (edit later in admin -> Settings)
-- ---------------------------------------------------------------------
INSERT INTO settings (`key`, value) VALUES
('business_name', 'MancWay Mobile Mechanics'),
('tagline',       'Manchester''s Trusted Mobile Mechanic — We Come to You'),
('phone',         '0161 000 0000'),
('phone_href',    '01610000000'),
('email',         'info@mancway.co.uk'),
('address',       'Manchester, Greater Manchester, UK'),
('hours_weekday', 'Mon–Fri: 7:30am – 6:00pm'),
('hours_weekend', 'Sat: 8:00am – 2:00pm · Sun: Closed'),
('service_radius', 'Greater Manchester & surrounds'),
('google_maps_embed', ''),
('facebook',  ''),
('instagram', ''),
('whatsapp', ''),
('admin_email', 'info@mancway.co.uk'),
('vat_number', ''),
('company_number', '');

-- ---------------------------------------------------------------------
-- Sample testimonials (approved, shown publicly — replace with real ones)
-- ---------------------------------------------------------------------
INSERT INTO testimonials (customer_name, rating, service_used, content, location, is_approved, sort_order, created_at) VALUES
('Sarah M.', 5, 'Full Service', 'Booked a full service at home — the mechanic arrived on time, did a thorough job and explained everything. Saved me a trip to the garage on a rainy Saturday. Brilliant.', 'Chorlton, M21', 1, 1, NOW()),
('David O.', 5, 'MOT Preparation', 'Failed my MOT last year on minor bits. This time MancWay prepped it and I passed first go. Honest and fairly priced.', 'Stockport, SK4', 1, 2, NOW()),
('Priya K.', 5, 'Diagnostics', 'Engine management light came on the day before a long drive. They came out, diagnosed a faulty sensor and replaced it on the spot. Lifesavers.', 'Salford, M5', 1, 3, NOW()),
('Tom H.', 4, 'Brakes', 'New front pads and discs fitted at my workplace during my shift. No fuss. Would recommend.', 'Bolton, BL2', 1, 4, NOW()),
('Aisha B.', 5, 'Mobile Breakdown', 'Wouldn''t start on a Monday morning. Called and someone was with me within the hour. Great service, fair price.', 'Oldham, OL8', 1, 5, NOW());

