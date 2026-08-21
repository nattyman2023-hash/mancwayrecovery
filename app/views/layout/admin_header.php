<?php
declare(strict_types=1);
/**
 * Admin panel header. Requires an authenticated admin (require_admin()
 * is called in each page, not here, so the login page can use its own layout).
 */
$admin_title = $admin_title ?? 'Admin';
$active_admin = $active_admin ?? '';
$admin_nav = [
    'dashboard'    => ['Dashboard',    url('/admin/index.php')],
    'crm'          => ['CRM',          url('/admin/crm.php')],
    'vehicles'     => ['Vehicles',     url('/admin/vehicles.php')],
    'bookings'     => ['Bookings',     url('/admin/bookings.php')],
    'invoices'     => ['Invoices',     url('/admin/invoices.php')],
    'messages'     => ['Messages',     url('/admin/messages.php')],
    'services'     => ['Services',     url('/admin/services.php')],
    'areas'        => ['Areas',        url('/admin/areas.php')],
    'testimonials' => ['Testimonials', url('/admin/testimonials.php')],
    'settings'     => ['Settings',     url('/admin/settings.php')],
];
$admin_nav_icons = [
    'dashboard'    => 'dashboard',
    'crm'          => 'local_shipping',
    'vehicles'     => 'airport_shuttle',
    'bookings'     => 'event_available',
    'invoices'     => 'receipt_long',
    'messages'     => 'forum',
    'services'     => 'build',
    'areas'        => 'map',
    'testimonials' => 'reviews',
    'settings'     => 'settings',
];
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($admin_title) ?> · <?= e(site_name()) ?> Admin</title>
    <link rel="icon" type="image/jpeg" href="<?= e(asset('img/logo.jpeg')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/logo.jpeg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin-crm.css')) ?>">
</head>
<body class="admin-body">
<header class="admin-topbar">
    <div class="admin-topbar-inner">
        <button class="nav-toggle" aria-expanded="false" aria-controls="admin-menu" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <div class="admin-topbar-context">
            <span class="admin-context-label">MANCWAY RECOVERY / DISPATCH</span>
            <strong><?= e($admin_title) ?></strong>
        </div>
        <div class="admin-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/')) ?>" target="_blank">
                <span class="mw-icon">open_in_new</span><span class="admin-action-label">View site</span>
            </a>
            <span class="admin-user"><span class="mw-icon">account_circle</span><?= e($_SESSION['admin_username'] ?? 'Dispatcher') ?></span>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/logout.php')) ?>">
                <span class="mw-icon">logout</span><span class="admin-action-label">Log out</span>
            </a>
        </div>
    </div>
</header>
<div class="admin-shell">
<aside id="admin-menu" class="admin-sidebar" aria-label="Admin navigation">
    <a class="admin-sidebar-brand" href="<?= e(url('/admin/index.php')) ?>">
        <img src="<?= e(asset('img/logo.jpeg')) ?>" alt="<?= e(site_name()) ?> logo">
        <span><strong>Dispatch Center</strong><small><?= e(site_name()) ?></small></span>
    </a>
    <nav class="admin-nav">
        <span class="admin-nav-heading">Operations</span>
        <ul>
            <?php foreach ($admin_nav as $key => [$label, $href]):
                $cls = ($active_admin === $key) ? ' is-active' : ''; ?>
                <li><a class="<?= $cls ?>" href="<?= e($href) ?>">
                    <span class="mw-icon"><?= e($admin_nav_icons[$key] ?? 'circle') ?></span>
                    <span><?= e($label) ?></span>
                </a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="admin-sidebar-footer">
        <span class="mw-icon">verified_user</span>
        <span><strong>Live operations</strong><small>Secure admin workspace</small></span>
    </div>
</aside>
<main class="admin-main container">
    <div class="admin-head">
        <h1 class="admin-h1"><?= e($admin_title) ?></h1>
        <?php if (!empty($admin_actions_html)) echo $admin_actions_html; ?>
    </div>
