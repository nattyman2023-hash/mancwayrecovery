<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$flash = flash('flash');
$err = flash('errors', []);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'password') {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 10) $err['new_password'] = 'Password must be at least 10 characters.';
        elseif ($new !== $confirm) $err['confirm_password'] = 'Passwords do not match.';
        if (!$err) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            db()->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([$hash, (int)$_SESSION['admin_id']]);
            redirect_with(url('/admin/settings.php'), ['flash' => 'Password changed.']);
        }
        $_SESSION['_flash']['errors'] = $err;
        redirect(url('/admin/settings.php#password'));
    }

    $fields = ['business_name','tagline','phone','phone_href','email','address','hours_weekday','hours_weekend','service_radius','google_maps_embed','facebook','instagram','whatsapp','admin_email','vat_number','company_number'];
    $upd = db()->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
    foreach ($fields as $k) { $upd->execute([$k, trim($_POST[$k] ?? '')]); }
    redirect_with(url('/admin/settings.php'), ['flash' => 'Settings saved.']);
}

$rows = db()->query('SELECT `key`, value FROM settings')->fetchAll();
$s = [];
foreach ($rows as $r) { $s[$r['key']] = $r['value']; }
function sv(array $s, string $k, string $def=''): string { return e($s[$k] ?? $def); }

$admin_title = 'Settings';
$active_admin = 'settings';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>

<form method="post" class="form" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <section class="panel">
        <div class="panel-head"><h2>Business details</h2></div>
        <div class="form-row">
            <div class="field"><label for="business_name">Business name</label><input type="text" id="business_name" name="business_name" value="<?= sv($s,'business_name','MancWay Mobile Mechanics') ?>"></div>
            <div class="field"><label for="tagline">Tagline</label><input type="text" id="tagline" name="tagline" value="<?= sv($s,'tagline') ?>"></div>
        </div>
        <div class="form-row">
            <div class="field"><label for="phone">Phone (display)</label><input type="text" id="phone" name="phone" value="<?= sv($s,'phone') ?>" placeholder="0161 000 0000"></div>
            <div class="field"><label for="phone_href">Phone for tel: links (no spaces)</label><input type="text" id="phone_href" name="phone_href" value="<?= sv($s,'phone_href') ?>" placeholder="01610000000"></div>
        </div>
        <div class="form-row">
            <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= sv($s,'email') ?>"></div>
            <div class="field"><label for="admin_email">Notification email (where forms send)</label><input type="email" id="admin_email" name="admin_email" value="<?= sv($s,'admin_email') ?>"></div>
        </div>
        <div class="field"><label for="address">Address / base</label><input type="text" id="address" name="address" value="<?= sv($s,'address') ?>"></div>
        <div class="form-row">
            <div class="field"><label for="hours_weekday">Weekday hours</label><input type="text" id="hours_weekday" name="hours_weekday" value="<?= sv($s,'hours_weekday') ?>"></div>
            <div class="field"><label for="hours_weekend">Weekend hours</label><input type="text" id="hours_weekend" name="hours_weekend" value="<?= sv($s,'hours_weekend') ?>"></div>
        </div>
        <div class="field"><label for="service_radius">Coverage line</label><input type="text" id="service_radius" name="service_radius" value="<?= sv($s,'service_radius') ?>"></div>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>Online presence</h2></div>
        <div class="form-row">
            <div class="field"><label for="facebook">Facebook URL</label><input type="url" id="facebook" name="facebook" value="<?= sv($s,'facebook') ?>"></div>
            <div class="field"><label for="instagram">Instagram URL</label><input type="url" id="instagram" name="instagram" value="<?= sv($s,'instagram') ?>"></div>
        </div>
        <div class="field"><label for="whatsapp">WhatsApp number/link</label><input type="text" id="whatsapp" name="whatsapp" value="<?= sv($s,'whatsapp') ?>"></div>
        <div class="field"><label for="google_maps_embed">Google Maps embed code</label><textarea id="google_maps_embed" name="google_maps_embed" rows="3" placeholder="Paste the full &lt;iframe&gt; embed from Google Maps"><?= e($s['google_maps_embed'] ?? '') ?></textarea></div>
        <div class="form-row">
            <div class="field"><label for="vat_number">VAT number (optional)</label><input type="text" id="vat_number" name="vat_number" value="<?= sv($s,'vat_number') ?>"></div>
            <div class="field"><label for="company_number">Company number (optional)</label><input type="text" id="company_number" name="company_number" value="<?= sv($s,'company_number') ?>"></div>
        </div>
        <button type="submit" class="btn btn-primary">Save settings</button>
    </section>
</form>

<section class="panel" id="password">
    <div class="panel-head"><h2>Change admin password</h2></div>
    <form method="post" class="form" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="form-row">
            <div class="field<?= isset($err['new_password'])?' has-error':'' ?>"><label for="new_password">New password *</label><input type="password" id="new_password" name="new_password" minlength="10" required><?= field_error($err, 'new_password') ?></div>
            <div class="field<?= isset($err['confirm_password'])?' has-error':'' ?>"><label for="confirm_password">Confirm *</label><input type="password" id="confirm_password" name="confirm_password" minlength="10" required><?= field_error($err, 'confirm_password') ?></div>
        </div>
        <button type="submit" class="btn btn-primary">Change password</button>
    </form>
</section>
<?php require APP_DIR . '/views/layout/admin_footer.php';

