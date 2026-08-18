<?php
declare(strict_types=1);
/**
 * ONE-TIME ADMIN SETUP — create your admin account here, then DELETE this file.
 *
 * This file lets you securely create the first admin user (with a bcrypt-hashed
 * password) WITHOUT shipping a default password in the database seed.
 *
 * Steps:
 *   1. Make sure app/config/config.local.php is configured with your DB creds.
 *   2. Make sure database/schema.sql and seed.sql have been imported.
 *   3. Visit https://<your-domain>/setup.php in your browser.
 *   4. Create your admin account.
 *   5. DELETE setup.php from public_html.
 */
require __DIR__ . '/../app/bootstrap.php';

// If an admin already exists, lock this down.
$adminExists = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();

$done = false;
$errors = [];

if ($adminExists > 0) {
    $alreadyDone = true;
} else {
    $alreadyDone = false;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_check();
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) $errors['username'] = 'Username must be 3–60 chars (letters, numbers, . - _).';
        if ($email !== '' && !valid_email($email))              $errors['email'] = 'Please enter a valid email address.';
        if (strlen($password) < 10)                            $errors['password'] = 'Password must be at least 10 characters.';
        if ($password !== $confirm)                             $errors['confirm'] = 'Passwords do not match.';

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = db()->prepare('INSERT INTO admins (username, email, password_hash, created_at) VALUES (?,?,?,' . 'NOW())');
            try {
                $ins->execute([$username, $email, $hash]);
                $done = true;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors['username'] = 'That username is taken. Choose another.';
                } else {
                    $errors[] = 'Could not create admin: ' . (IS_DEV ? $e->getMessage() : 'please try again.');
                }
            }
        }
    }
}

$page_title = 'Setup — ' . site_name();
$admin_title = 'Setup';
require APP_DIR . '/views/layout/admin_header.php';
?>
<div class="setup-wrap">
    <?php if ($alreadyDone): ?>
        <div class="alert alert-success">
            <h2>Setup is already complete.</h2>
            <p>An admin account already exists. For security, please <strong>delete <code>setup.php</code></strong> from your <code>public_html</code> folder now.</p>
            <p><a class="btn btn-primary" href="<?= e(url('/admin/login.php')) ?>">Go to admin login</a></p>
        </div>
    <?php elseif ($done): ?>
        <div class="alert alert-success">
            <h2>Admin account created!</h2>
            <p>You can now log in to the admin panel.</p>
            <p><strong>Important:</strong> delete <code>setup.php</code> from <code>public_html</code> now that you're done.</p>
            <p><a class="btn btn-primary" href="<?= e(url('/admin/login.php')) ?>">Log in to admin</a></p>
        </div>
    <?php else: ?>
        <div class="card setup-card">
            <h2>Create your admin account</h2>
            <p class="muted">This is the one-time setup. Choose a strong username and password (10+ chars).</p>
            <?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" class="form" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= old('username') ?>" required minlength="3" maxlength="60" pattern="[a-zA-Z0-9_.\-]+" title="Letters, numbers, dot, dash, underscore">
                </div>
                <div class="field">
                    <label for="email">Email <span class="muted">(optional)</span></label>
                    <input type="email" id="email" name="email" value="<?= old('email') ?>">
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required minlength="10">
                    </div>
                    <div class="field">
                        <label for="confirm">Confirm password *</label>
                        <input type="password" id="confirm" name="confirm" required minlength="10">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Create admin account</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php require APP_DIR . '/views/layout/admin_footer.php'; ?>
