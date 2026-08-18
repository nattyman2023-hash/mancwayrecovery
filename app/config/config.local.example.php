<?php
/**
 * MancWay Mobile Mechanics — local configuration.
 *
 * HOW TO USE:
 *   1. Copy this file to: app/config/config.local.php
 *   2. Fill in the values below.
 *
 * ON HOSTINGER (shared hosting):
 *   - Upload the "app" folder to: /home/u514321141/app/
 *   - Put this file at:             /home/u514321141/app/config/config.local.php
 *   - Upload the contents of "public" to: public_html/
 *
 * These values are read by app/config/config.php. Environment variables
 * (if set on the server) take precedence over the values here.
 */
return [
    // 'production' or 'development' (development shows errors)
    'APP_ENV'        => 'production',

    // Your live site URL, NO trailing slash.
    'APP_URL'        => 'https://mancway.co.uk',

    // MySQL (create this DB + user in hPanel → MySQL Databases)
    'DB_HOST'        => 'localhost',
    'DB_NAME'        => 'u514321141_mancway',
    'DB_USER'        => 'u514321141_mancway',
    'DB_PASS'        => 'CHANGE_ME',
    'DB_CHARSET'     => 'utf8mb4',

    // A long random string used to harden sessions.
    'SESSION_SECRET' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',

    // Where booking / contact notifications are sent.
    'MAIL_TO'        => 'info@mancway.co.uk',
    'MAIL_FROM'      => 'no-reply@mancway.co.uk',
];
