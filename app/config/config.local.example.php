<?php
/**
 * MancWay Recovery — local configuration.
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
    'APP_URL'        => 'https://mancwayrecovery.co.uk',

    // MySQL (create this DB + user in hPanel → MySQL Databases)
    'DB_HOST'        => 'localhost',
    'DB_NAME'        => 'u514321141_mancway',
    'DB_USER'        => 'u514321141_mancway',
    'DB_PASS'        => 'CHANGE_ME',
    'DB_CHARSET'     => 'utf8mb4',

    // A long random string used to harden sessions.
    'SESSION_SECRET' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',

    // Where booking / contact notifications are sent. If blank, the
    // admin_email setting in the database is used instead.
    'MAIL_TO'        => 'contact@mancwayrecovery.co.uk',
    'MAIL_FROM'      => 'no-reply@mancwayrecovery.co.uk',

    // DVLA Vehicle Enquiry Service key. Keep this server-side; never add it
    // to public JavaScript or the booking form.
    'DVLA_API_KEY'   => 'PASTE_DVLA_VES_KEY_HERE',

    // Optional DeepSeek assistant key. You can also save this securely from
    // CRM → Settings → API integrations. Never add it to public JavaScript.
    'DEEPSEEK_API_KEY' => '',
    'DEEPSEEK_API_URL' => 'https://api.deepseek.com',
    'DEEPSEEK_MODEL'   => 'deepseek-v4-flash',

    // Optional Emailit transactional email key. You can also save this
    // securely from CRM → Settings → API integrations.
    'EMAILIT_API_KEY' => '',
    'EMAILIT_API_URL' => 'https://api.emailit.com/v2',

    // Stripe is configured from CRM → Settings → Payment & invoicing, or
    // with these server-side values. Never add Stripe keys to public JS.
    'STRIPE_SECRET_KEY'      => '',
    'STRIPE_PUBLISHABLE_KEY' => '',
    'STRIPE_WEBHOOK_SECRET'  => '',
];
