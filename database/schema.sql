-- =====================================================================
--  MancWay Recovery — MySQL schema
--  Import via phpMyAdmin (hPanel → MySQL Databases → phpMyAdmin → Import)
--  Database: u514321141_mancway
--
--  IMPORTANT: This file is intentionally non-destructive. It may be run
--  against an existing database without deleting admins, bookings, messages,
--  settings or other CRM data. Use migration files for later schema changes.
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- ---------------------------------------------------------------------
-- Services offered
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(160) NOT NULL,
  title       VARCHAR(160) NOT NULL,
  icon        VARCHAR(60)  NOT NULL DEFAULT 'wrench',
  short_desc  VARCHAR(255) NOT NULL DEFAULT '',
  description TEXT,
  price_from  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug),
  KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Areas served (Greater Manchester boroughs)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS areas (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120) NOT NULL,
  slug       VARCHAR(160) NOT NULL,
  postcodes  VARCHAR(255) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Bookings (booking form submissions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference      VARCHAR(12)  NOT NULL,
  name           VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL DEFAULT '',
  phone          VARCHAR(30)  NOT NULL,
  vehicle_make   VARCHAR(80)  NOT NULL DEFAULT '',
  vehicle_model  VARCHAR(120) NOT NULL DEFAULT '',
  vehicle_reg    VARCHAR(20)  NOT NULL DEFAULT '',
  distance_miles DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  quoted_total   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 50.00,
  deposit_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  balance_status ENUM('not_due','unpaid','paid') NOT NULL DEFAULT 'not_due',
  service_id     INT UNSIGNED NULL,
  address        VARCHAR(255) NOT NULL DEFAULT '',
  postcode       VARCHAR(12)  NOT NULL DEFAULT '',
  preferred_date DATE         NULL,
  preferred_time VARCHAR(40)  NOT NULL DEFAULT '',
  notes          TEXT,
  status         ENUM('new','confirmed','complete','cancelled') NOT NULL DEFAULT 'new',
  ip             VARCHAR(45)  NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY reference (reference),
  KEY service_id (service_id),
  KEY status (status),
  KEY created_at (created_at),
  CONSTRAINT bookings_service_fk FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Invoices (created automatically for the £50 deposit)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
  id                         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id                 INT UNSIGNED NOT NULL,
  invoice_number             VARCHAR(24) NOT NULL,
  public_token               CHAR(64) NOT NULL,
  invoice_type               ENUM('deposit','balance','full') NOT NULL DEFAULT 'deposit',
  description                VARCHAR(255) NOT NULL DEFAULT '',
  subtotal                   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  amount_due                 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency                   CHAR(3) NOT NULL DEFAULT 'GBP',
  payment_method             ENUM('stripe','bank_transfer') NOT NULL DEFAULT 'stripe',
  status                     ENUM('draft','sent','paid','void','failed') NOT NULL DEFAULT 'draft',
  stripe_payment_link_id     VARCHAR(100) NOT NULL DEFAULT '',
  stripe_payment_link_url    TEXT,
  stripe_checkout_session_id VARCHAR(100) NOT NULL DEFAULT '',
  stripe_payment_intent_id   VARCHAR(100) NOT NULL DEFAULT '',
  stripe_error               TEXT,
  bank_reference             VARCHAR(60) NOT NULL DEFAULT '',
  email_sent_at              DATETIME NULL,
  paid_at                    DATETIME NULL,
  created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY invoice_number (invoice_number),
  UNIQUE KEY public_token (public_token),
  KEY booking_id (booking_id),
  KEY status (status),
  KEY stripe_payment_link_id (stripe_payment_link_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Contact messages
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(190) NOT NULL,
  phone      VARCHAR(30)  NOT NULL DEFAULT '',
  subject    VARCHAR(190) NOT NULL DEFAULT '',
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  ip         VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY is_read (is_read),
  KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Customer testimonials (only is_approved=1 are shown publicly)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS testimonials (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_name VARCHAR(120) NOT NULL,
  rating        TINYINT UNSIGNED NOT NULL DEFAULT 5,
  service_used  VARCHAR(160) NOT NULL DEFAULT '',
  content       TEXT NOT NULL,
  location      VARCHAR(120) NOT NULL DEFAULT '',
  is_approved   TINYINT(1) NOT NULL DEFAULT 0,
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY is_approved (is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Key/value site settings (managed from admin → Settings)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(80) NOT NULL,
  value TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Integration secrets (kept separate from editable website settings)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS integration_secrets (
  `key`      VARCHAR(80) NOT NULL,
  `value`    TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Admin users (created via setup.php on first run)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email         VARCHAR(190) NOT NULL DEFAULT '',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
