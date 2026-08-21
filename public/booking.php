<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$services = db()->query('SELECT id, slug, title, price_from FROM services WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
$serviceMap = [];
foreach ($services as $s) { $serviceMap[$s['slug']] = $s; }

$prefillSlug = trim($_GET['service'] ?? '');
$prefillId   = isset($serviceMap[$prefillSlug]) ? $serviceMap[$prefillSlug]['id'] : '';
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) { http_response_code(422); exit; } // honeypot

    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $vmake     = trim($_POST['vehicle_make'] ?? '');
    $vmodel    = trim($_POST['vehicle_model'] ?? '');
    $vreg      = trim($_POST['vehicle_reg'] ?? '');
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $address   = trim($_POST['address'] ?? '');
    $postcode  = trim($_POST['postcode'] ?? '');
    $pdate     = trim($_POST['preferred_date'] ?? '');
    $ptime     = trim($_POST['preferred_time'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if ($name === '' || mb_strlen($name) > 120)      $errors['name'] = 'Please enter your full name.';
    if ($email !== '' && !valid_email($email))        $errors['email'] = 'Please enter a valid email address.';
    if (!valid_phone($phone))                         $errors['phone'] = 'Please enter a valid phone number.';
    if ($vmake === '')                                $errors['vehicle_make'] = 'Please enter your vehicle make (e.g. Ford).';
    if ($address === '')                              $errors['address'] = 'Please enter your address.';
    if (!valid_postcode($postcode))                   $errors['postcode'] = 'Please enter a valid UK postcode.';
    if ($pdate === '' || strtotime($pdate) < strtotime(date('Y-m-d'))) $errors['preferred_date'] = 'Please choose a valid date (today or later).';
    if ($ptime === '')                                $errors['preferred_time'] = 'Please choose a preferred time.';

    if (!$errors) {
        $reference = generate_reference();
        $ins = db()->prepare('INSERT INTO bookings
            (reference, name, email, phone, vehicle_make, vehicle_model, vehicle_reg, service_id, address, postcode, preferred_date, preferred_time, notes, status, ip, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,' . 'NOW())');
        $ins->execute([
            $reference, $name, $email, $phone, $vmake, $vmodel, strtoupper($vreg),
            $serviceId > 0 ? $serviceId : null, $address, strtoupper($postcode),
            $pdate, $ptime, mb_substr($notes, 0, 2000), 'new', client_ip()
        ]);
        $svcName = 'General enquiry';
        if ($serviceId > 0) {
            $svc = db()->prepare('SELECT title FROM services WHERE id = ?');
            $svc->execute([$serviceId]);
            $svcName = $svc->fetchColumn() ?: $svcName;
        }
        $body  = '<h2>New booking — ' . e($reference) . '</h2>';
        $body .= '<p><strong>Service:</strong> ' . e($svcName) . '<br><strong>Name:</strong> ' . e($name) . '<br>';
        $body .= '<strong>Phone:</strong> ' . e($phone) . '<br><strong>Email:</strong> ' . e($email ?: '—') . '<br>';
        $body .= '<strong>Vehicle:</strong> ' . e($vmake) . ' ' . e($vmodel) . ' (' . e(strtoupper($vreg)) . ')<br>';
        $body .= '<strong>Address:</strong> ' . e($address) . ', ' . e(strtoupper($postcode)) . '<br>';
        $body .= '<strong>Preferred:</strong> ' . e($pdate) . ' ' . e($ptime) . '</p>';
        $body .= '<p><strong>Notes:</strong><br>' . nl2br(e($notes ?: '—')) . '</p>';
        $body .= '<p>Manage this booking in the admin panel → Bookings.</p>';
        send_site_email('New booking ' . $reference . ' — ' . $svcName, $body, $email);
        if ($email !== '') {
            $customerBody  = '<h2>Booking request received</h2>';
            $customerBody .= '<p>Thanks, ' . e($name) . '. We have received your recovery request and will contact you to confirm the details.</p>';
            $customerBody .= '<p><strong>Reference:</strong> ' . e($reference) . '<br><strong>Service:</strong> ' . e($svcName) . '<br><strong>Requested:</strong> ' . e($pdate) . ' ' . e($ptime) . '</p>';
            $customerBody .= '<p>If you need to speak to us now, call <a href="tel:' . e(setting('phone_href', site_phone())) . '">' . e(site_phone()) . '</a>.</p>';
            send_customer_email($email, 'Your MancWay recovery request ' . $reference, $customerBody);
        }
        redirect_with(url('/booking.php?done=' . $reference), ['success' => $reference]);
    }

    foreach (['name','email','phone','vehicle_make','vehicle_model','vehicle_reg','address','postcode','preferred_date','preferred_time','notes'] as $f) {
        $_SESSION['_flash']['input_' . $f] = $$f;
    }
    $_SESSION['_flash']['input_service_id'] = $serviceId;
    $_SESSION['_flash']['errors'] = $errors;
    redirect(url('/booking.php' . ($prefillSlug ? '?service=' . $prefillSlug : '')));
}

$success = flash('success');
$errors  = flash('errors', []);

$page_title       = 'Book Vehicle Recovery — Manchester | ' . site_name();
$page_description = 'Book vehicle recovery online. Breakdown, accident and specialist recovery, plus long-distance transport, across Greater Manchester.';
$page_canonical   = url('/booking.php');
$active = 'booking';
require APP_DIR . '/views/layout/header.php';
?>
<?php if ($success): ?>
    <section class="section"><div class="container narrow center success-box">
        <div class="success-ico">✔</div>
        <h1>Booking received!</h1>
        <p>Thank you. Your booking reference is <strong><?= e($success) ?></strong>. One of our recovery team will be in touch shortly to confirm.</p>
        <p class="muted">We've sent the details to our team. Keep your reference handy in case you need to contact us about this booking.</p>
        <p class="mt-2">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">Back to home</a>
            <a class="btn btn-outline" href="tel:<?= e(setting('phone_href', site_phone())) ?>">Call <?= e(site_phone()) ?></a>
        </p>
    </div></section>
<?php else: ?>
    <section class="page-hero"><div class="container">
        <span class="pill">Booking</span>
        <h1>Book vehicle recovery</h1>
        <p class="lead">Tell us what's happened and we'll come to you. It takes under a minute.</p>
    </div></section>
    <section class="section"><div class="container grid grid-2-1"><div>
        <?php if ($errors): ?><div class="alert alert-error">Please correct the highlighted fields below.</div><?php endif; ?>
        <form method="post" action="<?= e(url('/booking.php')) ?>" class="form" novalidate>
            <?= csrf_field() ?>
            <div class="hp" aria-hidden="true"><label>Leave empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="form-row">
                <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                    <label for="name">Full name *</label>
                    <input type="text" id="name" name="name" value="<?= old('name') ?>" maxlength="120" required>
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                    <label for="phone">Phone *</label>
                    <input type="tel" id="phone" name="phone" value="<?= old('phone') ?>" required>
                    <?= field_error($errors, 'phone') ?>
                </div>
            </div>
            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email <span class="muted">(optional)</span></label>
                <input type="email" id="email" name="email" value="<?= old('email') ?>">
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="form-row">
                <div class="field<?= isset($errors['vehicle_make']) ? ' has-error' : '' ?>">
                    <label for="vehicle_make">Vehicle make *</label>
                    <input type="text" id="vehicle_make" name="vehicle_make" value="<?= old('vehicle_make') ?>" placeholder="e.g. Ford" required>
                    <?= field_error($errors, 'vehicle_make') ?>
                </div>
                <div class="field">
                    <label for="vehicle_model">Vehicle model</label>
                    <input type="text" id="vehicle_model" name="vehicle_model" value="<?= old('vehicle_model') ?>" placeholder="e.g. Focus 1.6">
                </div>
            </div>
            <div class="field vehicle-lookup" data-vehicle-lookup data-endpoint="<?= e(url('/api/dvla-vehicle.php')) ?>">
                <label for="vehicle_reg">Vehicle registration</label>
                <div class="vehicle-lookup-row">
                    <input type="text" id="vehicle_reg" name="vehicle_reg" value="<?= old('vehicle_reg') ?>" placeholder="e.g. AB12 CDE" autocomplete="off" autocapitalize="characters" spellcheck="false">
                    <button type="button" class="btn btn-outline vehicle-lookup-button" data-vehicle-lookup-button>Find details</button>
                </div>
                <p class="vehicle-lookup-help">Enter the registration and we’ll fill in the details DVLA holds.</p>
                <p class="vehicle-lookup-status" data-vehicle-lookup-status role="status" aria-live="polite"></p>
            </div>
            <div class="field">
                <label for="service_id">Service required</label>
                <select id="service_id" name="service_id">
                    <option value="">Select a service…</option>
                    <?php foreach ($services as $s):
                        $sel = (((int)old('service_id', (string)$prefillId)) === (int)$s['id']) ? ' selected' : ''; ?>
                        <option value="<?= (int)$s['id'] ?>"<?= $sel ?>><?= e($s['title']) ?> — from <?= e(format_price($s['price_from'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field<?= isset($errors['address']) ? ' has-error' : '' ?>">
                <label for="address">Pickup address / breakdown location *</label>
                <input type="text" id="address" name="address" value="<?= old('address') ?>" required>
                <?= field_error($errors, 'address') ?>
            </div>
            <div class="field<?= isset($errors['postcode']) ? ' has-error' : '' ?>">
                <label for="postcode">Postcode *</label>
                <input type="text" id="postcode" name="postcode" value="<?= old('postcode') ?>" required>
                <?= field_error($errors, 'postcode') ?>
            </div>
            <div class="form-row">
                <div class="field<?= isset($errors['preferred_date']) ? ' has-error' : '' ?>">
                    <label for="preferred_date">Preferred date *</label>
                    <input type="date" id="preferred_date" name="preferred_date" value="<?= old('preferred_date') ?>" min="<?= date('Y-m-d') ?>" required>
                    <?= field_error($errors, 'preferred_date') ?>
                </div>
                <div class="field<?= isset($errors['preferred_time']) ? ' has-error' : '' ?>">
                    <label for="preferred_time">Preferred time *</label>
                    <select id="preferred_time" name="preferred_time">
                        <option value="">Select…</option>
                        <?php foreach (['Morning (7:30–12:00)','Afternoon (12:00–18:00)','Anytime'] as $t):
                            $sel = (old('preferred_time') === $t) ? ' selected' : ''; ?>
                            <option<?= $sel ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'preferred_time') ?>
                </div>
            </div>
            <div class="field">
                <label for="notes">Notes <span class="muted">(optional)</span></label>
                <textarea id="notes" name="notes" rows="4" maxlength="2000" placeholder="Tell us what happened, and where the vehicle needs to go if it's not staying local…"><?= old('notes') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">Send booking request</button>
            <p class="form-foot muted">By submitting you agree to be contacted about your booking. We never share your details.</p>
        </form>
    </div>
    <aside class="card side-cta">
        <h3>Prefer to talk?</h3>
        <p>Call us — we can advise and book over the phone.</p>
        <a class="btn btn-outline btn-block btn-lg" href="tel:<?= e(setting('phone_href', site_phone())) ?>">📞 <?= e(site_phone()) ?></a>
        <hr>
        <h3>What happens next?</h3>
        <ol class="steps">
            <li>We confirm your booking by phone or email.</li>
            <li>Our recovery driver arrives at the agreed time.</li>
            <li>We recover your vehicle and you pay on completion.</li>
        </ol>
        <p class="muted"><?= e(setting('hours_weekday')) ?><br><?= e(setting('hours_weekend')) ?></p>
    </aside>
    </div></section>
<?php endif; ?>
<?php require APP_DIR . '/views/layout/footer.php'; ?>

