<?php
declare(strict_types=1);

/**
 * Install the CRM schema in a safe, repeatable way.
 *
 * This mirrors database/migration_crm.sql, but avoids requiring the site
 * owner to open phpMyAdmin. Every change is guarded so existing enquiries,
 * accounts and settings are preserved.
 */
function crm_schema_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function crm_schema_has_index(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function crm_schema_has_vehicle_foreign_key(PDO $pdo): bool
{
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'bookings'
           AND COLUMN_NAME = 'vehicle_id'
           AND REFERENCED_TABLE_NAME = 'recovery_vehicles'"
    );
    return (int)$stmt->fetchColumn() > 0;
}

function crm_schema_status_type(PDO $pdo): string
{
    $stmt = $pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'bookings'
           AND COLUMN_NAME = 'status'"
    );
    return strtolower((string)$stmt->fetchColumn());
}

function install_crm_migration(PDO $pdo): void
{
    // The CRM depends on the base bookings table. Fail clearly if the base
    // schema has not been imported yet, without making any partial changes.
    $bookings = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'"
    );
    if ((int)$bookings->fetchColumn() === 0) {
        throw new RuntimeException('The base bookings table is missing. Import schema.sql first.');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS recovery_vehicles (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "INSERT INTO recovery_vehicles (name, registration, type, status, is_active, notes)
         SELECT 'Recovery Unit 01', '', 'Flatbed', 'available', 1, 'Primary recovery vehicle'
         FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM recovery_vehicles)"
    );

    if (!crm_schema_has_column($pdo, 'bookings', 'vehicle_id')) {
        $pdo->exec(
            'ALTER TABLE bookings ADD COLUMN vehicle_id INT UNSIGNED NULL DEFAULT NULL'
        );
    }

    if (!crm_schema_has_column($pdo, 'bookings', 'updated_at')) {
        $pdo->exec(
            'ALTER TABLE bookings ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
    }

    if (!crm_schema_has_vehicle_foreign_key($pdo)) {
        $pdo->exec(
            'ALTER TABLE bookings ADD CONSTRAINT bookings_vehicle_fk
             FOREIGN KEY (vehicle_id) REFERENCES recovery_vehicles (id) ON DELETE SET NULL'
        );
    }

    if (!crm_schema_has_index($pdo, 'bookings', 'vehicle_id')) {
        $pdo->exec('ALTER TABLE bookings ADD KEY vehicle_id (vehicle_id)');
    }

    $statusType = crm_schema_status_type($pdo);
    if ($statusType === '') {
        throw new RuntimeException('The base bookings status column is missing. Re-import the current schema.sql first.');
    }

    if (strpos($statusType, 'accepted') === false || strpos($statusType, 'dispatched') === false) {
        $pdo->exec(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('new','confirmed','accepted','dispatched','complete','cancelled')
             NOT NULL DEFAULT 'new'"
        );
    }

    // Move legacy records before removing the old enum value.
    $pdo->exec("UPDATE bookings SET status = 'accepted' WHERE status = 'confirmed'");

    $statusType = crm_schema_status_type($pdo);
    if (strpos($statusType, 'confirmed') !== false) {
        $pdo->exec(
            "ALTER TABLE bookings MODIFY COLUMN status
             ENUM('new','accepted','dispatched','complete','cancelled')
             NOT NULL DEFAULT 'new'"
        );
    }
}
