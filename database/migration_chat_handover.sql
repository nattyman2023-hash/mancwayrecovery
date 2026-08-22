-- Durable website-chat CRM leads and handover timeline.
-- Safe to import after database/schema.sql; the application also creates these
-- tables lazily when the first handover is requested.
CREATE TABLE IF NOT EXISTS chat_leads (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_key           CHAR(64) NOT NULL,
  reference             VARCHAR(12) NOT NULL,
  name                  VARCHAR(120) NOT NULL DEFAULT '',
  email                 VARCHAR(190) NOT NULL DEFAULT '',
  phone                 VARCHAR(30) NOT NULL DEFAULT '',
  vehicle_make          VARCHAR(80) NOT NULL DEFAULT '',
  vehicle_model         VARCHAR(120) NOT NULL DEFAULT '',
  vehicle_reg           VARCHAR(20) NOT NULL DEFAULT '',
  address               VARCHAR(255) NOT NULL DEFAULT '',
  postcode              VARCHAR(12) NOT NULL DEFAULT '',
  current_location      VARCHAR(255) NOT NULL DEFAULT '',
  destination           VARCHAR(255) NOT NULL DEFAULT '',
  problem               TEXT,
  required_time         VARCHAR(120) NOT NULL DEFAULT '',
  service               VARCHAR(120) NOT NULL DEFAULT '',
  distance_miles        VARCHAR(30) NOT NULL DEFAULT '',
  conversation_json     LONGTEXT NOT NULL,
  handover_message      TEXT NOT NULL,
  status                ENUM('open','handover_requested','callback_requested','closed') NOT NULL DEFAULT 'open',
  handover_channel      VARCHAR(30) NOT NULL DEFAULT '',
  handover_requested_at DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY session_key (session_key),
  UNIQUE KEY reference (reference),
  KEY status (status),
  KEY updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_lead_events (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id    INT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  message    VARCHAR(255) NOT NULL,
  channel    VARCHAR(30) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY lead_id (lead_id),
  KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
