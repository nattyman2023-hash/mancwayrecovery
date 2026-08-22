<?php
declare(strict_types=1);

/**
 * Application bootstrap — included at the top of every public file.
 *
 * Local repo:   require __DIR__ . '/../app/bootstrap.php';   (public/ sits next to app/)
 * Hostinger:    public_html/index.php → require __DIR__ . '/../app/bootstrap.php';
 *               which resolves to /home/u514321141/app/bootstrap.php
 */

define('APP_ROOT', dirname(__DIR__));   // project root (locally) / home dir (Hostinger)
define('APP_DIR',  __DIR__);            // /app

require APP_DIR . '/config/config.php';
require APP_DIR . '/db.php';
require APP_DIR . '/helpers.php';
require APP_DIR . '/csrf.php';
require APP_DIR . '/auth.php';
require APP_ROOT . '/app/payments.php';
require APP_ROOT . '/app/chat_handover.php';

// Harden session cookie + start session.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                       || (($_SERVER['SERVER_PORT'] ?? '') == 443),
    ]);
    session_name('mancway_sid');
    session_start();
}

csrf_init();
