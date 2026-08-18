<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

if (is_admin()) {
    redirect(url('/admin/index.php'));
}

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $errors[] = 'Please enter your username and password.';
    } elseif (!admin_login($username, $password)) {
        $errors[] = 'Invalid username or password.';
    } else {
        redirect(url('/admin/index.php'));
    }
}
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Admin Login — <?= e(site_name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="login-body">
    <main class="login-wrap">
        <div class="login-card card">
            <div class="brand brand-login">
                <span class="brand-mark"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.8 2.8-2.4-2.4 2.8-2.8z"/></svg></span>
                <span class="brand-text">Manc<span class="brand-accent">Way</span> <small>Admin</small></span>
            </div>
            <h1>Sign in</h1>
            <?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $m) echo '<p>' . e($m) . '</p>'; ?></div><?php endif; ?>
            <form method="post" class="form" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Sign in</button>
            </form>
            <p class="login-foot"><a href="<?= e(url('/')) ?>">← Back to website</a></p>
        </div>
    </main>
    <script src="<?= e(asset('js/main.js')) ?>" defer></script>
</body>
</html>
