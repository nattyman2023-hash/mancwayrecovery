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
    'messages'     => ['Messages',     url('/admin/messages.php')],
    'services'     => ['Services',     url('/admin/services.php')],
    'areas'        => ['Areas',        url('/admin/areas.php')],
    'testimonials' => ['Testimonials', url('/admin/testimonials.php')],
    'settings'     => ['Settings',     url('/admin/settings.php')],
];
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($admin_title) ?> · <?= e(site_name()) ?> Admin</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin-crm.css')) ?>">
</head>
<body class="admin-body">
<header class="admin-topbar">
    <div class="container admin-topbar-inner">
        <a class="brand brand-admin" href="<?= e(url('/admin/index.php')) ?>">
            <img class="brand-logo" src="<?= e(asset('img/logo.jpeg')) ?>" alt="<?= e(site_name()) ?> logo">
            <span class="brand-text">Manc<span style="color:var(--mw-amber)">Way</span> <small>CRM</small></span>
        </a>
        <button class="nav-toggle" aria-expanded="false" aria-controls="admin-menu" aria-label="Menu"><span></span><span></span><span></span></button>
        <nav id="admin-menu" class="admin-nav" aria-label="Admin">
            <ul>
                <?php foreach ($admin_nav as $key => [$label, $href]):
                    $cls = ($active_admin === $key) ? ' is-active' : ''; ?>
                    <li><a class="<?= $cls ?>" href="<?= e($href) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="admin-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/')) ?>" target="_blank">View site</a>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/logout.php')) ?>">Log out</a>
        </div>
    </div>
</header>
<main class="admin-main container">
    <div class="admin-head">
        <h1 class="admin-h1"><?= e($admin_title) ?></h1>
        <?php if (!empty($admin_actions_html)) echo $admin_actions_html; ?>
    </div>
