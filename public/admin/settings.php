<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$flash = flash('flash');
$err = flash('errors', []);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'contact') {
        $phone      = trim((string)($_POST['phone'] ?? ''));
        $email      = trim((string)($_POST['email'] ?? ''));
        $address    = trim((string)($_POST['address'] ?? ''));
        $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
        $returnTo   = ($_POST['return_to'] ?? '') === 'dashboard'
            ? url('/admin/index.php')
            : url('/admin/settings.php#contact');

        if (!valid_phone($phone)) {
            redirect_with($returnTo, ['contact_error' => 'Please enter a valid phone number.']);
        }
        if (!valid_email($email)) {
            redirect_with($returnTo, ['contact_error' => 'Please enter a valid public email address.']);
        }
        if ($address === '') {
            redirect_with($returnTo, ['contact_error' => 'Please enter the business address.']);
        }
        if ($adminEmail !== '' && !valid_email($adminEmail)) {
            redirect_with($returnTo, ['contact_error' => 'Please enter a valid notification email address.']);
        }

        $upd = db()->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
        $upd->execute(['phone', $phone]);
        // Keep telephone links in sync automatically when the display number changes.
        $upd->execute(['phone_href', (string)preg_replace('/[^0-9+]/', '', $phone)]);
        $upd->execute(['email', $email]);
        $upd->execute(['address', $address]);
        $upd->execute(['admin_email', $adminEmail]);
        redirect_with($returnTo, ['flash' => 'Contact details updated.']);
    }

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

    $fields = ['business_name','tagline','phone','phone_href','email','address','hours_weekday','hours_weekend','service_radius','google_maps_embed','facebook','instagram','whatsapp','whatsapp_handover_phone','admin_email','vat_number','company_number','payment_method_default','bank_account_name','bank_name','bank_sort_code','bank_account_number'];
    $upd = db()->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
    foreach ($fields as $k) { $upd->execute([$k, trim($_POST[$k] ?? '')]); }
    if (isset($_POST['phone'])) {
        $upd->execute(['phone_href', (string)preg_replace('/[^0-9+]/', '', trim((string)$_POST['phone']))]);
    }

    // Keep the secret out of the rendered page. A blank field leaves the
    // current DB-stored key unchanged; the checkbox explicitly clears it.
    $submittedDvlaKey = trim((string)($_POST['dvla_api_key'] ?? ''));
    if (!empty($_POST['clear_dvla_api_key'])) {
        $upd->execute(['dvla_api_key', '']);
        try { delete_integration_secret('dvla_api_key'); }
        catch (Throwable $e) { error_log('Could not clear the DVLA integration secret.'); }
    } elseif ($submittedDvlaKey !== '') {
        $upd->execute(['dvla_api_key', $submittedDvlaKey]);
        try { save_integration_secret('dvla_api_key', $submittedDvlaKey); }
        catch (Throwable $e) { error_log('Could not save the DVLA integration secret.'); }
    }

    $submittedDeepseekKey = trim((string)($_POST['deepseek_api_key'] ?? ''));
    if (!empty($_POST['clear_deepseek_api_key'])) {
        try { delete_integration_secret('deepseek_api_key'); }
        catch (Throwable $e) { error_log('Could not clear the DeepSeek integration secret.'); }
    } elseif ($submittedDeepseekKey !== '') {
        try { save_integration_secret('deepseek_api_key', $submittedDeepseekKey); }
        catch (Throwable $e) { error_log('Could not save the DeepSeek integration secret.'); }
    }

    foreach ([
        'stripe_secret_key' => 'stripe_secret_key',
        'stripe_publishable_key' => 'stripe_publishable_key',
        'stripe_webhook_secret' => 'stripe_webhook_secret',
    ] as $field => $secretKey) {
        $submitted = trim((string)($_POST[$field] ?? ''));
        if (!empty($_POST['clear_' . $field])) {
            try { delete_integration_secret($secretKey); }
            catch (Throwable $e) { error_log('Could not clear the ' . $secretKey . ' integration secret.'); }
        } elseif ($submitted !== '') {
            try { save_integration_secret($secretKey, $submitted); }
            catch (Throwable $e) { error_log('Could not save the ' . $secretKey . ' integration secret.'); }
        }
    }
    redirect_with(url('/admin/settings.php'), ['flash' => 'Settings saved.']);
}

$rows = db()->query('SELECT `key`, value FROM settings')->fetchAll();
$s = [];
foreach ($rows as $r) { $s[$r['key']] = $r['value']; }
// Copy a legacy key once into dedicated integration storage. Do not overwrite
// a newer dedicated value with an older settings row.
$legacyDvlaKey = trim((string)($s['dvla_api_key'] ?? ''));
if ($legacyDvlaKey !== '' && integration_secret('dvla_api_key', '') === '') {
    try { save_integration_secret('dvla_api_key', $legacyDvlaKey); }
    catch (Throwable $e) { error_log('Could not migrate the DVLA key to integration storage.'); }
}
function sv(array $s, string $k, string $def=''): string { return e($s[$k] ?? $def); }
$serverDvlaConfigured = DVLA_API_KEY !== '' && !str_contains(DVLA_API_KEY, 'PASTE_') && !str_contains(DVLA_API_KEY, 'CHANGE_ME');
$dvlaConfigured = dvla_api_key() !== '';
$serverDeepseekConfigured = DEEPSEEK_API_KEY !== '' && !str_contains(DEEPSEEK_API_KEY, 'PASTE_') && !str_contains(DEEPSEEK_API_KEY, 'CHANGE_ME');
$deepseekConfigured = deepseek_api_key() !== '';
$serverStripeConfigured = STRIPE_SECRET_KEY !== '' && !str_contains(STRIPE_SECRET_KEY, 'PASTE_') && !str_contains(STRIPE_SECRET_KEY, 'CHANGE_ME');
$stripeConfigured = stripe_secret_key() !== '';
$stripePublishableConfigured = stripe_publishable_key() !== '';
$stripeWebhookConfigured = stripe_webhook_secret() !== '';

$contactError = flash('contact_error');
$admin_title = 'Settings';
$active_admin = 'settings';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($contactError): ?><div class="alert alert-error"><?= e($contactError) ?></div><?php endif; ?>

<form method="post" class="form" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <section class="panel" id="contact">
        <div class="panel-head"><h2>Contact details &amp; business basics</h2><span class="muted">Shown across the website</span></div>
        <p class="muted">For the quickest update, use the Quick contact details panel on the admin dashboard. Phone links update automatically when the display number changes.</p>
        <p class="muted">Website forms are saved in the CRM and inbox first. Email notifications currently go to <strong><?= e(site_notification_email() ?: 'No valid notification email configured') ?></strong>; the server-level <code>MAIL_TO</code> value takes priority when set.</p>
        <div class="form-row">
            <div class="field"><label for="business_name">Business name</label><input type="text" id="business_name" name="business_name" value="<?= sv($s,'business_name','MancWay Recovery') ?>"></div>
            <div class="field"><label for="tagline">Tagline</label><input type="text" id="tagline" name="tagline" value="<?= sv($s,'tagline') ?>"></div>
        </div>
        <div class="form-row">
            <div class="field"><label for="phone">Phone (display)</label><input type="text" id="phone" name="phone" value="<?= sv($s,'phone') ?>" placeholder="0161 000 0000"></div>
            <div class="field"><label for="phone_href">Phone for tel: links (no spaces)</label><input type="text" id="phone_href" name="phone_href" value="<?= sv($s,'phone_href') ?>" placeholder="07480255634"></div>
        </div>
        <div class="form-row">
            <div class="field"><label for="email">Public email</label><input type="email" id="email" name="email" value="<?= sv($s,'email') ?>"></div>
            <div class="field"><label for="admin_email">Notification email (where forms send)</label><input type="email" id="admin_email" name="admin_email" value="<?= sv($s,'admin_email') ?>"></div>
        </div>
        <div class="field"><label for="address">Business address</label><input type="text" id="address" name="address" value="<?= sv($s,'address') ?>"></div>
        <div class="form-row">
            <div class="field"><label for="hours_weekday">Weekday hours</label><input type="text" id="hours_weekday" name="hours_weekday" value="<?= sv($s,'hours_weekday') ?>"></div>
            <div class="field"><label for="hours_weekend">Weekend hours</label><input type="text" id="hours_weekend" name="hours_weekend" value="<?= sv($s,'hours_weekend') ?>"></div>
        </div>
        <div class="field"><label for="service_radius">Coverage line</label><input type="text" id="service_radius" name="service_radius" value="<?= sv($s,'service_radius') ?>"></div>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>API integrations</h2></div>
        <p class="muted">DVLA vehicle lookup status: <strong><?= $dvlaConfigured ? 'Configured' : 'Not configured' ?></strong>. The key is never shown after saving and is stored separately from editable website content.</p>
        <?php if ($serverDvlaConfigured): ?><p class="muted">A server-level DVLA key is active and takes priority over this admin setting.</p><?php endif; ?>
        <div class="field">
            <label for="dvla_api_key">DVLA Vehicle Enquiry API key</label>
            <div class="password-field">
                <input type="password" id="dvla_api_key" name="dvla_api_key" value="" autocomplete="new-password" placeholder="Paste a new DVLA API key to save or replace it">
                <button type="button" class="password-toggle" data-password-toggle data-password-target="dvla_api_key" aria-controls="dvla_api_key" aria-pressed="false" aria-label="Show API key">Show</button>
            </div>
            <small class="muted">Leave blank to keep the current key. This setting is used only by the server-side vehicle lookup.</small>
        </div>
        <label class="field-check"><input type="checkbox" name="clear_dvla_api_key" value="1"> Clear the saved admin setting</label>
        <hr>
        <p class="muted">DeepSeek chat assistant status: <strong><?= $deepseekConfigured ? 'Configured' : 'Fallback replies active' ?></strong>. Add a key to enable business-aware AI responses; the browser only talks to your server.</p>
        <?php if ($serverDeepseekConfigured): ?><p class="muted">A server-level DeepSeek key is active and takes priority over this admin setting.</p><?php endif; ?>
        <div class="field">
            <label for="deepseek_api_key">DeepSeek API key</label>
            <div class="password-field">
                <input type="password" id="deepseek_api_key" name="deepseek_api_key" value="" autocomplete="new-password" placeholder="Paste a new DeepSeek API key to save or replace it">
                <button type="button" class="password-toggle" data-password-toggle data-password-target="deepseek_api_key" aria-controls="deepseek_api_key" aria-pressed="false" aria-label="Show DeepSeek API key">Show</button>
            </div>
            <small class="muted">Leave blank to keep the current key. The assistant uses <?= e(deepseek_model()) ?> through the server-side API proxy.</small>
        </div>
        <label class="field-check"><input type="checkbox" name="clear_deepseek_api_key" value="1"> Clear the saved DeepSeek key</label>
    </section>
    <section class="panel" id="payments">
        <div class="panel-head"><h2>Payment &amp; invoicing</h2><span class="muted">Deposits, balances and payment links</span></div>
        <p class="muted">Every new booking creates a £50 deposit invoice. Stripe invoices use a hosted Stripe Payment Link; bank-transfer invoices show the details below. The secret key is never sent to the browser.</p>
        <p class="muted">Stripe secret key status: <strong><?= $stripeConfigured ? 'Configured' : 'Not configured' ?></strong>. Publishable key: <strong><?= $stripePublishableConfigured ? 'Saved' : 'Not saved' ?></strong>. Webhook signing secret: <strong><?= $stripeWebhookConfigured ? 'Saved' : 'Not saved' ?></strong>.</p>
        <?php if ($serverStripeConfigured): ?><p class="muted">A server-level Stripe secret key is active and takes priority over the saved admin setting.</p><?php endif; ?>
        <div class="form-row">
            <div class="field">
                <label for="stripe_secret_key">Stripe secret key</label>
                <div class="password-field"><input type="password" id="stripe_secret_key" name="stripe_secret_key" value="" autocomplete="new-password" placeholder="sk_live_… or sk_test_…"><button type="button" class="password-toggle" data-password-toggle data-password-target="stripe_secret_key" aria-controls="stripe_secret_key" aria-pressed="false" aria-label="Show Stripe secret key">Show</button></div>
                <small class="muted">Leave blank to keep the saved key.</small>
            </div>
            <div class="field">
                <label for="stripe_publishable_key">Stripe publishable key</label>
                <div class="password-field"><input type="password" id="stripe_publishable_key" name="stripe_publishable_key" value="" autocomplete="new-password" placeholder="pk_live_… or pk_test_…"><button type="button" class="password-toggle" data-password-toggle data-password-target="stripe_publishable_key" aria-controls="stripe_publishable_key" aria-pressed="false" aria-label="Show Stripe publishable key">Show</button></div>
                <small class="muted">Saved for your payment configuration; server-side Payment Links use the secret key.</small>
            </div>
        </div>
        <div class="field">
            <label for="stripe_webhook_secret">Stripe webhook signing secret</label>
            <div class="password-field"><input type="password" id="stripe_webhook_secret" name="stripe_webhook_secret" value="" autocomplete="new-password" placeholder="whsec_…"><button type="button" class="password-toggle" data-password-toggle data-password-target="stripe_webhook_secret" aria-controls="stripe_webhook_secret" aria-pressed="false" aria-label="Show Stripe webhook secret">Show</button></div>
            <small class="muted">Add the signing secret for the webhook endpoint <code>/api/stripe-webhook.php</code> so paid invoices update automatically.</small>
        </div>
        <div class="form-row">
            <label class="field-check"><input type="checkbox" name="clear_stripe_secret_key" value="1"> Clear saved secret key</label>
            <label class="field-check"><input type="checkbox" name="clear_stripe_publishable_key" value="1"> Clear saved publishable key</label>
        </div>
        <label class="field-check"><input type="checkbox" name="clear_stripe_webhook_secret" value="1"> Clear saved webhook secret</label>
        <hr>
        <div class="field"><label for="payment_method_default">Default payment method for new invoices</label><select id="payment_method_default" name="payment_method_default"><option value="stripe" <?= (($s['payment_method_default'] ?? 'stripe') === 'stripe') ? 'selected' : '' ?>>Stripe payment link</option><option value="bank_transfer" <?= (($s['payment_method_default'] ?? '') === 'bank_transfer') ? 'selected' : '' ?>>Bank transfer</option></select><small class="muted">You can override this on each CRM invoice.</small></div>
        <h3>Bank transfer details</h3>
        <div class="form-row">
            <div class="field"><label for="bank_account_name">Account name</label><input type="text" id="bank_account_name" name="bank_account_name" value="<?= sv($s,'bank_account_name') ?>"></div>
            <div class="field"><label for="bank_name">Bank name</label><input type="text" id="bank_name" name="bank_name" value="<?= sv($s,'bank_name') ?>"></div>
        </div>
        <div class="form-row">
            <div class="field"><label for="bank_sort_code">Sort code</label><input type="text" id="bank_sort_code" name="bank_sort_code" value="<?= sv($s,'bank_sort_code') ?>" inputmode="numeric"></div>
            <div class="field"><label for="bank_account_number">Account number</label><input type="text" id="bank_account_number" name="bank_account_number" value="<?= sv($s,'bank_account_number') ?>" inputmode="numeric"></div>
        </div>
        <p class="muted">To use bank transfer for a specific invoice, choose Bank transfer in CRM → Invoices. Stripe will not be used for that invoice.</p>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>Online presence</h2></div>
        <div class="form-row">
            <div class="field"><label for="facebook">Facebook URL</label><input type="url" id="facebook" name="facebook" value="<?= sv($s,'facebook') ?>"></div>
            <div class="field"><label for="instagram">Instagram URL</label><input type="url" id="instagram" name="instagram" value="<?= sv($s,'instagram') ?>"></div>
        </div>
        <div class="field"><label for="whatsapp">WhatsApp number/link</label><input type="text" id="whatsapp" name="whatsapp" value="<?= sv($s,'whatsapp') ?>"></div>
        <div class="field"><label for="whatsapp_handover_phone">Human handover WhatsApp number</label><input type="text" id="whatsapp_handover_phone" name="whatsapp_handover_phone" value="<?= sv($s,'whatsapp_handover_phone') ?>" placeholder="07480 255634" inputmode="tel"><small class="muted">This is used for the chatbot human handover and callback fallback. It defaults to 07480 255634 when left blank.</small></div>
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
            <div class="field<?= isset($err['new_password'])?' has-error':'' ?>"><label for="new_password">New password *</label><div class="password-field"><input type="password" id="new_password" name="new_password" minlength="10" required><button type="button" class="password-toggle" data-password-toggle data-password-target="new_password" aria-controls="new_password" aria-pressed="false" aria-label="Show password">Show</button></div><?= field_error($err, 'new_password') ?></div>
            <div class="field<?= isset($err['confirm_password'])?' has-error':'' ?>"><label for="confirm_password">Confirm *</label><div class="password-field"><input type="password" id="confirm_password" name="confirm_password" minlength="10" required><button type="button" class="password-toggle" data-password-toggle data-password-target="confirm_password" aria-controls="confirm_password" aria-pressed="false" aria-label="Show password">Show</button></div><?= field_error($err, 'confirm_password') ?></div>
        </div>
        <button type="submit" class="btn btn-primary">Change password</button>
    </form>
</section>
<?php require APP_DIR . '/views/layout/admin_footer.php';
