<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
ensure_payment_schema();

$number = trim((string)($_GET['number'] ?? ''));
$token = trim((string)($_GET['token'] ?? ''));
$invoice = ($number !== '' && $token !== '') ? get_invoice_by_public_reference($number, $token) : null;
if (!$invoice) {
    http_response_code(404);
    $page_title = 'Invoice not found | ' . site_name();
    $page_description = 'The requested MancWay Recovery invoice could not be found.';
    require APP_DIR . '/views/layout/header.php';
    echo '<section class="section"><div class="container narrow center"><h1>Invoice not found</h1><p class="muted">Please check the invoice link or contact us for help.</p></div></section>';
    require APP_DIR . '/views/layout/footer.php';
    exit;
}

$page_title = 'Invoice ' . $invoice['invoice_number'] . ' | ' . site_name();
$page_description = $invoice['reference'] !== ''
    ? 'Secure invoice for MancWay Recovery booking ' . $invoice['reference'] . '.'
    : 'Secure MancWay Recovery invoice.';
$page_canonical = url('/invoice');
$page_robots = 'noindex,nofollow';
require APP_DIR . '/views/layout/header.php';
?>
<section class="page-hero" style="--page-hero-image:url('<?= e(asset('img/recovery-roadside.png')) ?>')"><div class="container">
    <span class="pill">Payment</span>
    <h1>Invoice <?= e($invoice['invoice_number']) ?></h1>
    <p class="lead"><?= $invoice['reference'] ? 'Booking ' . e($invoice['reference']) : e($invoice['description']) ?> · <?= e(invoice_type_label((string)$invoice['invoice_type'])) ?></p>
</div></section>
<section class="section"><div class="container narrow">
    <div class="invoice-card">
        <div class="invoice-card-head"><div><span class="muted">MancWay Recovery</span><h2><?= e(invoice_type_label((string)$invoice['invoice_type'])) ?></h2></div><span class="badge badge-<?= e($invoice['status']) ?>"><?= e(invoice_status_label((string)$invoice['status'])) ?></span></div>
        <dl class="kv invoice-details">
            <dt>Customer</dt><dd><?= e($invoice['name']) ?></dd>
            <?php if ($invoice['address']): ?><dt>Address</dt><dd><?= e($invoice['address']) ?></dd><?php endif; ?>
            <?php if ($invoice['reference']): ?><dt>Booking</dt><dd><?= e($invoice['reference']) ?></dd><?php endif; ?>
            <dt>Service</dt><dd><?= e($invoice['service_title'] ?: 'Recovery service') ?></dd>
            <?php if ((float)$invoice['distance_miles'] > 0): ?><dt>Estimated mileage</dt><dd><?= e((string)$invoice['distance_miles']) ?> miles</dd><?php endif; ?>
            <dt>Amount due</dt><dd class="invoice-total"><?= e(format_price($invoice['amount_due'])) ?></dd>
        </dl>
        <?php if ($invoice['status'] === 'paid'): ?>
            <div class="alert alert-success">Payment received. We will confirm the recovery arrangements with you.</div>
        <?php elseif ($invoice['payment_method'] === 'stripe' && $invoice['stripe_payment_link_url']): ?>
            <div class="invoice-payment-box"><h3>Pay securely online</h3><p class="muted">Your payment is processed securely by Stripe.</p><a class="btn btn-primary btn-lg" href="<?= e($invoice['stripe_payment_link_url']) ?>" target="_blank" rel="noopener">Pay <?= e(format_price($invoice['amount_due'])) ?> with Stripe</a></div>
        <?php elseif ($invoice['payment_method'] === 'bank_transfer'): ?>
            <?php $bank = payment_bank_details(); ?>
            <div class="invoice-payment-box"><h3>Pay by bank transfer</h3><p class="muted">Use invoice reference <strong><?= e($invoice['bank_reference'] ?: $invoice['invoice_number']) ?></strong> as your payment reference.</p><dl class="kv"><dt>Account name</dt><dd><?= e($bank['account_name'] ?: 'Please contact MancWay Recovery') ?></dd><dt>Bank</dt><dd><?= e($bank['bank_name'] ?: 'Please contact MancWay Recovery') ?></dd><dt>Sort code</dt><dd><?= e($bank['sort_code'] ?: 'On request') ?></dd><dt>Account number</dt><dd><?= e($bank['account_number'] ?: 'On request') ?></dd></dl><p class="muted">After transferring, please call or email us so we can confirm your payment.</p></div>
        <?php else: ?>
            <div class="alert alert-error">The online payment link is not available yet. Please contact us and we will issue a new payment option.</div>
        <?php endif; ?>
        <p class="muted invoice-foot">Questions? Call <a href="tel:<?= e(setting('phone_href', site_phone())) ?>"><?= e(site_phone()) ?></a> or email <a href="mailto:<?= e(site_email()) ?>"><?= e(site_email()) ?></a>.</p>
    </div>
</div></section>
<?php require APP_DIR . '/views/layout/footer.php'; ?>
