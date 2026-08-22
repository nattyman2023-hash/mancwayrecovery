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

if (!empty($_GET['download'])) {
    $pdf = invoice_pdf_bytes($invoice);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string)$invoice['invoice_number']) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$invoiceItems = get_invoice_items((int)$invoice['id']);
$invoicePayments = get_invoice_payments((int)$invoice['id']);
$invoiceEvents = get_invoice_events((int)$invoice['id']);
$viewKey = 'invoice_viewed_' . (int)$invoice['id'];
if (empty($_SESSION[$viewKey])) {
    invoice_event((int)$invoice['id'], 'viewed', 'Secure invoice page viewed');
    $_SESSION[$viewKey] = 1;
}
$invoiceBalance = max(0, round((float)$invoice['amount_due'] - (float)($invoice['amount_paid'] ?? 0), 2));
$invoiceWhatsapp = chat_handover_whatsapp_url('Hi MancWay Recovery, I have a question about invoice ' . $invoice['invoice_number'] . '.');

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
        <div class="invoice-public-actions"><a class="btn btn-outline btn-sm" href="<?= e(invoice_public_url($invoice) . '&download=1') ?>">Download PDF</a><a class="btn btn-outline btn-sm" href="<?= e($invoiceWhatsapp) ?>" target="_blank" rel="noopener">WhatsApp us</a><a class="btn btn-outline btn-sm" href="tel:<?= e(setting('phone_href', site_phone())) ?>">Call us</a></div>
        <dl class="kv invoice-details">
            <dt>Customer</dt><dd><?= e($invoice['name']) ?></dd>
            <?php if (!empty($invoice['company_name'])): ?><dt>Company</dt><dd><?= e($invoice['company_name']) ?></dd><?php endif; ?>
            <?php if ($invoice['address']): ?><dt>Address</dt><dd><?= e($invoice['address']) ?></dd><?php endif; ?>
            <?php if ($invoice['reference']): ?><dt>Booking</dt><dd><?= e($invoice['reference']) ?></dd><?php endif; ?>
            <dt>Service</dt><dd><?= e($invoice['service_title'] ?: 'Recovery service') ?></dd>
            <?php if (!empty($invoice['display_vehicle_make']) || !empty($invoice['display_vehicle_model'])): ?><dt>Vehicle</dt><dd><?= e(trim(($invoice['display_vehicle_make'] ?? '') . ' ' . ($invoice['display_vehicle_model'] ?? ''))) ?></dd><?php endif; ?>
            <?php if (!empty($invoice['display_vehicle_reg'])): ?><dt>Registration</dt><dd><?= e($invoice['display_vehicle_reg']) ?></dd><?php endif; ?>
            <?php if (!empty($invoice['display_collection_location'])): ?><dt>Collection</dt><dd><?= e($invoice['display_collection_location']) ?></dd><?php endif; ?>
            <?php if (!empty($invoice['display_destination'])): ?><dt>Destination</dt><dd><?= e($invoice['display_destination']) ?></dd><?php endif; ?>
            <?php if ((float)$invoice['distance_miles'] > 0): ?><dt>Estimated mileage</dt><dd><?= e((string)$invoice['distance_miles']) ?> miles</dd><?php endif; ?>
        </dl>
        <div class="invoice-public-items"><h3>Invoice details</h3><table class="data-table"><thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead><tbody><?php foreach ($invoiceItems as $item): ?><tr><td><?= e($item['description']) ?></td><td><?= e((string)$item['quantity']) ?></td><td><?= e(format_price($item['unit_price'])) ?></td><td><?= e(format_price($item['line_total'])) ?></td></tr><?php endforeach; ?></tbody></table><div class="invoice-totals"><span>Subtotal <strong><?= e(format_price($invoice['subtotal'])) ?></strong></span><?php if ((float)$invoice['discount_amount'] > 0): ?><span>Discount <strong>-<?= e(format_price($invoice['discount_amount'])) ?></strong></span><?php endif; ?><?php if (!empty($invoice['vat_enabled'])): ?><span>VAT <strong><?= e(format_price($invoice['vat_amount'])) ?></strong></span><?php endif; ?><?php if ((float)$invoice['deposit_paid'] > 0): ?><span>Deposit paid <strong>-<?= e(format_price($invoice['deposit_paid'])) ?></strong></span><?php endif; ?><span class="invoice-total">Balance due <strong><?= e(format_price($invoiceBalance)) ?></strong></span></div></div>
        <?php if ($invoice['status'] === 'paid'): ?>
            <div class="alert alert-success">Payment received. We will confirm the recovery arrangements with you.</div>
        <?php elseif ($invoice['payment_method'] === 'stripe' && $invoice['stripe_payment_link_url'] && $invoiceBalance > 0): ?>
            <div class="invoice-payment-box"><h3>Pay securely online</h3><p class="muted">Your payment is processed securely by Stripe.</p><a class="btn btn-primary btn-lg" href="<?= e($invoice['stripe_payment_link_url']) ?>" target="_blank" rel="noopener">Pay <?= e(format_price($invoiceBalance)) ?> with Stripe</a></div>
        <?php elseif ($invoice['payment_method'] === 'bank_transfer'): ?>
            <?php $bank = payment_bank_details(); ?>
            <div class="invoice-payment-box"><h3>Pay by bank transfer</h3><p class="muted">Use invoice reference <strong><?= e($invoice['bank_reference'] ?: $invoice['invoice_number']) ?></strong> as your payment reference.</p><dl class="kv"><dt>Account name</dt><dd><?= e($bank['account_name'] ?: 'Please contact MancWay Recovery') ?></dd><dt>Bank</dt><dd><?= e($bank['bank_name'] ?: 'Please contact MancWay Recovery') ?></dd><dt>Sort code</dt><dd><?= e($bank['sort_code'] ?: 'On request') ?></dd><dt>Account number</dt><dd><?= e($bank['account_number'] ?: 'On request') ?></dd></dl><p class="muted">After transferring, please call or email us so we can confirm your payment.</p></div>
        <?php else: ?>
            <div class="alert alert-error">The online payment link is not available yet. Please contact us and we will issue a new payment option.</div>
        <?php endif; ?>
        <?php if ($invoicePayments): ?><div class="invoice-payment-history"><h3>Payments received</h3><?php foreach ($invoicePayments as $payment): ?><p><strong><?= e(format_price($payment['amount'])) ?></strong> · <?= e(invoice_payment_method_label($payment['payment_method'])) ?> · <?= e(date('d M Y', strtotime($payment['paid_at']))) ?></p><?php endforeach; ?></div><?php endif; ?>
        <?php if (!empty($invoice['customer_notes'])): ?><p class="invoice-customer-note"><strong>Note:</strong> <?= e($invoice['customer_notes']) ?></p><?php endif; ?>
        <p class="muted invoice-foot">Questions? Call <a href="tel:<?= e(setting('phone_href', site_phone())) ?>"><?= e(site_phone()) ?></a> or email <a href="mailto:<?= e(site_email()) ?>"><?= e(site_email()) ?></a>.</p>
    </div>
</div></section>
<?php require APP_DIR . '/views/layout/footer.php'; ?>
