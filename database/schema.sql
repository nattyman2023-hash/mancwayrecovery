-- =====================================================================
--  MancWay Mobile Mechanics — MySQL schema
--  Import via phpMyAdmin (hPanel → MySQL Databases → phpMyAdmin → Import)
--  Database: u514321141_mancway
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS testimonials;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS areas;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS admins;

-- ---------------------------------------------------------------------
-- Services offered
-- ---------------------------------------------------------------------
CREATE TABLE services (
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
CREATE TABLE areas (
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
CREATE TABLE bookings (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference      VARCHAR(12)  NOT NULL,
  name           VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL DEFAULT '',
  phone          VARCHAR(30)  NOT NULL,
  vehicle_make   VARCHAR(80)  NOT NULL DEFAULT '',
  vehicle_model  VARCHAR(120) NOT NULL DEFAULT '',
  vehicle_reg    VARCHAR(20)  NOT NULL DEFAULT '',
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
-- Contact messages
-- ---------------------------------------------------------------------
CREATE TABLE messages (
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
CREATE TABLE testimonials (
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
CREATE TABLE settings (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(80) NOT NULL,
  value TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Admin users (created via setup.php on first run)
-- ---------------------------------------------------------------------
CREATE TABLE admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email         VARCHAR(190) NOT NULL DEFAULT '',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
