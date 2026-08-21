<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    if (!empty($_POST['website'])) { http_response_code(422); exit; }

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || mb_strlen($name) > 120)   $errors['name'] = 'Please enter your name.';
    if (!valid_email($email))                      $errors['email'] = 'Please enter a valid email address.';
    if ($phone !== '' && !valid_phone($phone))     $errors['phone'] = 'Please enter a valid phone number.';
    if ($message === '')                           $errors['message'] = 'Please enter a message.';

    if (!$errors) {
        $ins = db()->prepare('INSERT INTO messages (name, email, phone, subject, message, is_read, ip, created_at) VALUES (?,?,?,?,?,0,?,' . 'NOW())');
        $ins->execute([$name, $email, $phone, mb_substr($subject, 0, 190), mb_substr($message, 0, 5000), client_ip()]);
        $body  = '<h2>New message</h2>';
        $body .= '<p><strong>Name:</strong> ' . e($name) . '<br><strong>Email:</strong> ' . e($email) . '<br>';
        $body .= '<strong>Phone:</strong> ' . e($phone ?: '—') . '<br><strong>Subject:</strong> ' . e($subject ?: '—') . '</p>';
        $body .= '<p><strong>Message:</strong><br>' . nl2br(e($message)) . '</p>';
        send_site_email('New contact message — ' . ($subject ?: 'no subject'), $body, $email);
        $customerBody  = '<h2>Thanks for contacting MancWay</h2>';
        $customerBody .= '<p>We have received your message and will get back to you as soon as we can.</p>';
        $customerBody .= '<p>If your vehicle needs urgent recovery, call <a href="tel:' . e(setting('phone_href', site_phone())) . '">' . e(site_phone()) . '</a>.</p>';
        send_customer_email($email, 'We received your message — ' . site_name(), $customerBody);
        redirect_with(url('/contact.php?done=1'), ['success' => true]);
    }
    foreach (['name','email','phone','subject','message'] as $f) {
        $_SESSION['_flash']['input_' . $f] = $$f;
    }
    $_SESSION['_flash']['errors'] = $errors;
    redirect(url('/contact.php'));
}

$success = flash('success');
$errors = flash('errors', []);

$page_title       = 'Contact Us — ' . site_name();
$page_description = 'Get in touch with MancWay Recovery. Call, message or book vehicle recovery across Greater Manchester.';
$page_canonical   = url('/contact.php');
$active = 'contact';
require APP_DIR . '/views/layout/header.php';
?>
<?php if ($success): ?>
    <section class="section"><div class="container narrow center success-box">
        <div class="success-ico">✔</div>
        <h1>Message sent!</h1>
        <p>Thanks for getting in touch. We'll reply as soon as we can.</p>
        <p class="mt-2"><a class="btn btn-primary" href="<?= e(url('/')) ?>">Back to home</a></p>
    </div></section>
<?php else: ?>
    <section class="page-hero"><div class="container">
        <span class="pill">Contact</span>
        <h1>Get in touch</h1>
        <p class="lead">Questions about a service, a quote or a booking? Send us a message — or just call.</p>
    </div></section>
    <section class="section"><div class="container grid grid-2-1">
        <div>
            <?php if ($errors): ?><div class="alert alert-error">Please correct the highlighted fields.</div><?php endif; ?>
            <form method="post" action="<?= e(url('/contact.php')) ?>" class="form" novalidate>
                <?= csrf_field() ?>
                <div class="hp" aria-hidden="true"><label>Leave empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="form-row">
                    <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" value="<?= old('name') ?>" maxlength="120" required>
                        <?= field_error($errors, 'name') ?>
                    </div>
                    <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" value="<?= old('email') ?>" required>
                        <?= field_error($errors, 'email') ?>
                    </div>
                </div>
                <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                    <label for="phone">Phone <span class="muted">(optional)</span></label>
                    <input type="tel" id="phone" name="phone" value="<?= old('phone') ?>">
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="field">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" value="<?= old('subject') ?>" maxlength="190">
                </div>
                <div class="field<?= isset($errors['message']) ? ' has-error' : '' ?>">
                    <label for="message">Message *</label>
                    <textarea id="message" name="message" rows="6" maxlength="5000" required><?= old('message') ?></textarea>
                    <?= field_error($errors, 'message') ?>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Send message</button>
            </form>
        </div>
        <aside class="card side-cta">
            <h3>Other ways to reach us</h3>
            <p><a class="link" href="tel:<?= e(setting('phone_href', site_phone())) ?>">📞 <?= e(site_phone()) ?></a></p>
            <p><a class="link" href="mailto:<?= e(setting('email')) ?>">✉ <?= e(setting('email')) ?></a></p>
            <p class="muted"><?= e(setting('address')) ?></p>
            <hr>
            <p><strong>Opening hours</strong></p>
            <p class="muted"><?= e(setting('hours_weekday')) ?><br><?= e(setting('hours_weekend')) ?></p>
            <a class="btn btn-primary btn-block mt" href="<?= e(url('/booking.php')) ?>">Book recovery</a>
        </aside>
    </div></section>
<?php endif; ?>
<?php require APP_DIR . '/views/layout/footer.php'; ?>
