<?php
declare(strict_types=1);

/**
 * Configuration loader.
 *
 * Resolution priority per key:
 *   1. Server environment variable (getenv)
 *   2. app/config/config.local.php  (gitignored, holds real secrets)
 *   3. The default passed to cfg()
 */

/**
 * Read a single configuration value.
 */
function cfg(string $key, $default = null)
{
    static $local = null;
    if ($local === null) {
        $local = [];
        $localPath = __DIR__ . '/config.local.php';
        if (is_file($localPath)) {
            $loaded = require $localPath;
            if (is_array($loaded)) {
                $local = $loaded;
            }
        }
    }

    $val = getenv($key);
    if (($val === false || $val === '') && isset($local[$key])) {
        $val = $local[$key];
    }
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

define('APP_ENV',        (string) cfg('APP_ENV', 'production'));
define('APP_URL',        rtrim((string) cfg('APP_URL', 'https://mancwayrecovery.co.uk'), '/'));
define('DB_HOST',        (string) cfg('DB_HOST', 'localhost'));
define('DB_NAME',        (string) cfg('DB_NAME', ''));
define('DB_USER',        (string) cfg('DB_USER', ''));
define('DB_PASS',        (string) cfg('DB_PASS', ''));
define('DB_CHARSET',     (string) cfg('DB_CHARSET', 'utf8mb4'));
define('SESSION_SECRET', (string) cfg('SESSION_SECRET', 'please-change-this'));
define('MAIL_TO',        (string) cfg('MAIL_TO', ''));
define('MAIL_FROM',      (string) cfg('MAIL_FROM', ''));
define('DVLA_API_KEY',   trim((string) cfg('DVLA_API_KEY', '')));
define('DVLA_API_URL',   'https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles');
define('DEEPSEEK_API_KEY', trim((string) cfg('DEEPSEEK_API_KEY', '')));
define('DEEPSEEK_API_URL', rtrim((string) cfg('DEEPSEEK_API_URL', 'https://api.deepseek.com'), '/'));
define('DEEPSEEK_MODEL',   trim((string) cfg('DEEPSEEK_MODEL', 'deepseek-v4-flash')));
define('EMAILIT_API_KEY',  trim((string) cfg('EMAILIT_API_KEY', '')));
define('EMAILIT_API_URL',  rtrim((string) cfg('EMAILIT_API_URL', 'https://api.emailit.com/v2'), '/'));
define('STRIPE_SECRET_KEY',      trim((string) cfg('STRIPE_SECRET_KEY', '')));
define('STRIPE_PUBLISHABLE_KEY', trim((string) cfg('STRIPE_PUBLISHABLE_KEY', '')));
define('STRIPE_WEBHOOK_SECRET',  trim((string) cfg('STRIPE_WEBHOOK_SECRET', '')));
define('IS_DEV',         APP_ENV === 'development');

// Error reporting — show errors in dev, hide & log in production.
if (IS_DEV) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set('Europe/London');
