<?php
declare(strict_types=1);

/**
 * CSRF protection (synchroniser token pattern).
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_init(): void
{
    csrf_token(); // ensure a token exists for every request
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Reject POST requests with an invalid/missing token. */
function csrf_check(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!csrf_verify()) {
            http_response_code(419);
            die('Security token expired or invalid. Please go back, refresh the page and try again.');
        }
    }
}
