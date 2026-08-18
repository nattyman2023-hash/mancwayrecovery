<?php
declare(strict_types=1);

/**
 * Admin authentication.
 */

function admin_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    // Upgrade hash if needed.
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $upd->execute([$newHash, $admin['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']       = (int) $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect(url('/admin/login.php'));
    }
}
