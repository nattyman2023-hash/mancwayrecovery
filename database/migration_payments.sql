-- MancWay Recovery: pricing, deposit and invoice migration.
-- Safe to run after schema.sql and migration_crm.sql. It does not delete data.
SET NAMES utf8mb4;

UPDATE services SET price_from=50.00 WHERE slug='breakdown-recovery';
UPDATE services SET price_from=120.00 WHERE slug='accident-recovery';
UPDATE services SET price_from=120.00 WHERE slug='vehicle-transport';

SET @has_distance = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='distance_miles');
SET @sql = IF(@has_distance=0, 'ALTER TABLE bookings ADD COLUMN distance_miles DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER vehicle_reg', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_quote = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='quoted_total');
SET @sql = IF(@has_quote=0, 'ALTER TABLE bookings ADD COLUMN quoted_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER distance_miles', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_deposit = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='deposit_amount');
SET @sql = IF(@has_deposit=0, 'ALTER TABLE bookings ADD COLUMN deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 50.00 AFTER quoted_total', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_deposit_status = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='deposit_status');
SET @sql = IF(@has_deposit_status=0, "ALTER TABLE bookings ADD COLUMN deposit_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid' AFTER deposit_amount", 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_balance_status = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings' AND COLUMN_NAME='balance_status');
SET @sql = IF(@has_balance_status=0, "ALTER TABLE bookings ADD COLUMN balance_status ENUM('not_due','unpaid','paid') NOT NULL DEFAULT 'not_due' AFTER deposit_status", 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id INT UNSIGNED NULL,
  customer_name VARCHAR(120) NOT NULL DEFAULT '',
  customer_email VARCHAR(190) NOT NULL DEFAULT '',
  customer_phone VARCHAR(30) NOT NULL DEFAULT '',
  customer_address VARCHAR(255) NOT NULL DEFAULT '',
  invoice_number VARCHAR(24) NOT NULL,
  public_token CHAR(64) NOT NULL,
  invoice_type ENUM('deposit','balance','full','custom') NOT NULL DEFAULT 'deposit',
  description VARCHAR(255) NOT NULL DEFAULT '',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  amount_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'GBP',
  payment_method ENUM('stripe','bank_transfer') NOT NULL DEFAULT 'stripe',
  status ENUM('draft','sent','paid','void','failed') NOT NULL DEFAULT 'draft',
  stripe_payment_link_id VARCHAR(100) NOT NULL DEFAULT '',
  stripe_payment_link_url TEXT,
  stripe_checkout_session_id VARCHAR(100) NOT NULL DEFAULT '',
  stripe_payment_intent_id VARCHAR(100) NOT NULL DEFAULT '',
  stripe_error TEXT,
  bank_reference VARCHAR(60) NOT NULL DEFAULT '',
  email_sent_at DATETIME NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY invoice_number (invoice_number), UNIQUE KEY public_token (public_token),
  KEY booking_id (booking_id), KEY status (status), KEY stripe_payment_link_id (stripe_payment_link_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoices MODIFY booking_id INT UNSIGNED NULL, MODIFY invoice_type ENUM('deposit','balance','full','custom') NOT NULL DEFAULT 'deposit';
SET @has_customer_name = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoices' AND COLUMN_NAME='customer_name');
SET @sql = IF(@has_customer_name=0, "ALTER TABLE invoices ADD COLUMN customer_name VARCHAR(120) NOT NULL DEFAULT '' AFTER booking_id", 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_customer_email = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoices' AND COLUMN_NAME='customer_email');
SET @sql = IF(@has_customer_email=0, "ALTER TABLE invoices ADD COLUMN customer_email VARCHAR(190) NOT NULL DEFAULT '' AFTER customer_name", 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_customer_phone = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoices' AND COLUMN_NAME='customer_phone');
SET @sql = IF(@has_customer_phone=0, "ALTER TABLE invoices ADD COLUMN customer_phone VARCHAR(30) NOT NULL DEFAULT '' AFTER customer_email", 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_customer_address = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='invoices' AND COLUMN_NAME='customer_address');
SET @sql = IF(@has_customer_address=0, "ALTER TABLE invoices ADD COLUMN customer_address VARCHAR(255) NOT NULL DEFAULT '' AFTER customer_phone", 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
