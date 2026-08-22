-- =====================================================================
--  MancWay Recovery — seed data
--  Run AFTER schema.sql.
--  NOTE: No admin user is inserted here for security. Create the admin
--  account by visiting  https://<your-domain>/setup.php  once the site
--  is deployed, then DELETE setup.php.
-- =====================================================================
SET NAMES utf8mb4;

-- This is starter content, not a live reset script. Do not re-import it after
-- customising production content unless you intentionally want to refresh the
-- sample testimonials. Settings are preserved so API keys and business
-- details do not disappear when starter data is imported again.

-- ---------------------------------------------------------------------
-- Services
-- ---------------------------------------------------------------------
INSERT IGNORE INTO services (slug, title, icon, short_desc, description, price_from, sort_order, is_active) VALUES
('breakdown-recovery',   'Breakdown Recovery',                              'truck',   'Fast roadside recovery when your car won''t start or move.',
 'Broken down at the roadside, at home, or won''t start? We reach you across Greater Manchester and either get you moving again or recover the vehicle safely to a garage, home or compound of your choice.', 50.00, 1, 1),
('accident-recovery',    'Accident Recovery',                               'shield',  'Careful recovery after a collision.',
 'Recovery for vehicles involved in a collision — to a bodyshop, insurer-approved compound, or your home. Handled carefully and discreetly, with clear communication throughout.', 120.00, 2, 1),
('vehicle-transport',    'Long-Distance Vehicle Transport',                 'map',     'Nationwide transport for cars, vans and non-runners.',
 'Moving a vehicle between two locations — dealer-to-dealer, house move, or getting a non-runner to a specialist garage. We quote based on the exact pickup and drop-off locations.', 120.00, 3, 1),
('specialist-recovery',  'Specialist Recovery (4x4 / Off-Road / Motorbike)', 'bike',    'Winching and recovery for vehicles standard tow trucks can''t handle.',
 'Specialist recovery and winching for 4x4s, off-road vehicles and motorbikes — including vehicles stuck off-road or in difficult access locations.', 90.00, 4, 1);

-- ---------------------------------------------------------------------
-- Areas served
-- ---------------------------------------------------------------------
INSERT IGNORE INTO areas (name, slug, postcodes, sort_order, is_active) VALUES
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
INSERT IGNORE INTO settings (`key`, value) VALUES
('business_name', 'MancWay Recovery'),
('tagline',       'Manchester''s Trusted Vehicle Recovery — We Come to You'),
('phone',         '07480 255634'),
('phone_href',    '07480255634'),
('email',         'info@mancwayrecovery.co.uk'),
('address',       'Upper Cyrus St, Manchester M40 7FD'),
('hours_weekday', 'Mon–Fri: 7:30am – 6:00pm'),
('hours_weekend', 'Sat: 8:00am – 2:00pm · Sun: Closed'),
('service_radius', 'Greater Manchester & surrounds'),
('google_maps_embed', ''),
('facebook',  ''),
('instagram', ''),
('whatsapp', ''),
('whatsapp_handover_phone', '07480 255634'),
('admin_email', 'info@mancwayrecovery.co.uk'),
('vat_number', ''),
('company_number', '');

-- ---------------------------------------------------------------------
-- Sample testimonials (approved, shown publicly — replace with real ones)
-- ---------------------------------------------------------------------
INSERT INTO testimonials (customer_name, rating, service_used, content, location, is_approved, sort_order, created_at) VALUES
('Sarah M.', 5, 'Breakdown Recovery', 'Broke down on the way to work — called MancWay and someone was with me within the hour. Recovered safely to my garage, no fuss. Brilliant.', 'Chorlton, M21', 1, 1, NOW()),
('David O.', 5, 'Accident Recovery', 'Had a minor collision and needed the car recovered to my insurer''s compound. They were quick, careful and kept me updated the whole time.', 'Stockport, SK4', 1, 2, NOW()),
('Priya K.', 5, 'Long-Distance Vehicle Transport', 'Needed a car moved from Manchester to Leeds for a specialist repair. Arrived exactly when quoted, no damage, fair price.', 'Salford, M5', 1, 3, NOW()),
('Tom H.', 4, 'Breakdown Recovery', 'Wouldn''t start one morning before work. Recovered to my usual garage within the hour. Would recommend.', 'Bolton, BL2', 1, 4, NOW()),
('Aisha B.', 5, 'Specialist Recovery', 'Got stuck off-road and needed a winch-out. Turned up with the right kit and got us moving safely. Great service, fair price.', 'Oldham, OL8', 1, 5, NOW());
