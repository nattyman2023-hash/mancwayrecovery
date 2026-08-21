-- =====================================================================
--  MancWay Recovery — CRM migration
--  Run ONCE AFTER schema.sql + seed.sql (via phpMyAdmin → Import).
--
--  Adds:
--   • recovery_vehicles table — the recovery fleet. Seeded with ONE
--     vehicle today (single recovery vehicle), but designed to hold many
--     in the future with no further schema changes.
--   • bookings.vehicle_id        — which recovery unit an enquiry is
--                                  assigned to (nullable, set from the CRM).
--   • bookings.status extended   — replaces legacy 'confirmed' with
--                                  'accepted' and adds 'dispatched'.
--   • bookings.updated_at        — audit timestamp for status changes.
--
--  Safe to re-run: statements are guarded so existing columns/tables are
--  not duplicated. Existing bookings keep their current status & data.
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Recovery vehicles (the fleet)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS recovery_vehicles (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(80)  NOT NULL,
  registration VARCHAR(20)  NOT NULL DEFAULT '',
  type         VARCHAR(40)  NOT NULL DEFAULT 'Flatbed',
  status       ENUM('available','on_job','off_duty') NOT NULL DEFAULT 'available',
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  notes        VARCHAR(255) NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY status (status),
  KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the single recovery vehicle (only if the table is empty).
INSERT INTO recovery_vehicles (name, registration, type, status, is_active, notes)
SELECT 'Recovery Unit 01', '', 'Flatbed', 'available', 1, 'Primary recovery vehicle'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM recovery_vehicles);

-- ---------------------------------------------------------------------
-- bookings: add vehicle assignment + audit timestamp
-- (guarded so re-running does not error if the column already exists)
-- ---------------------------------------------------------------------
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'vehicle_id');
SET @sql := IF(@col = 0,
  'ALTER TABLE bookings ADD COLUMN vehicle_id INT UNSIGNED NULL DEFAULT NULL AFTER service_id',
  'SELECT "vehicle_id already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'updated_at');
SET @sql := IF(@col = 0,
  'ALTER TABLE bookings ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
  'SELECT "updated_at already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add the foreign key linking bookings to recovery_vehicles (guarded).
SET @fk := (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'
              AND CONSTRAINT_NAME = 'bookings_vehicle_fk');
SET @sql := IF(@fk = 0,
  'ALTER TABLE bookings ADD CONSTRAINT bookings_vehicle_fk FOREIGN KEY (vehicle_id) REFERENCES recovery_vehicles (id) ON DELETE SET NULL',
  'SELECT "vehicle FK already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add an index on vehicle_id for the CRM filters (guarded).
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND INDEX_NAME = 'vehicle_id');
SET @sql := IF(@idx = 0,
  'ALTER TABLE bookings ADD KEY vehicle_id (vehicle_id)',
  'SELECT "vehicle_id index already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Extend bookings.status ENUM to include the new CRM workflow values.
-- Keep legacy 'confirmed' in the temporary superset so existing rows can be
-- migrated before the final enum removes that legacy label.
-- ---------------------------------------------------------------------
SET @has_crm_statuses := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'status'
    AND COLUMN_TYPE LIKE '%accepted%'
    AND COLUMN_TYPE LIKE '%dispatched%');
SET @sql := IF(@has_crm_statuses = 0,
  "ALTER TABLE bookings MODIFY COLUMN status ENUM('new','confirmed','accepted','dispatched','complete','cancelled') NOT NULL DEFAULT 'new'",
  'SELECT "status already includes dispatched" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- Migrate legacy 'confirmed' status rows to 'accepted' so the workflow
-- uses one consistent active label. (Only runs if confirmed existed.)
-- ---------------------------------------------------------------------
UPDATE bookings SET status = 'accepted' WHERE status = 'confirmed';

-- Remove the legacy value after all existing rows have been migrated. This is
-- also guarded so the migration remains safe to run more than once.
SET @has_confirmed := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'status'
    AND COLUMN_TYPE LIKE '%confirmed%');
SET @sql := IF(@has_confirmed > 0,
  "ALTER TABLE bookings MODIFY COLUMN status ENUM('new','accepted','dispatched','complete','cancelled') NOT NULL DEFAULT 'new'",
  'SELECT "legacy confirmed status already removed" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
