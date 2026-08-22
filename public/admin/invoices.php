<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();
ensure_payment_schema();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);

    if ($action === 'create') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $type = (string)($_POST['invoice_type'] ?? 'balance');
        $amount = (float)($_POST['amount_due'] ?? 0);
        $method = (string)($_POST['payment_method'] ?? '');
        if ($bookingId > 0) {
            $invoice = create_invoice_for_booking($bookingId, $type, $amount, $method);
        } else {
            $invoice = create_standalone_invoice(
                (string)($_POST['customer_name'] ?? ''),
                (string)($_POST['customer_email'] ?? ''),
                (string)($_POST['customer_phone'] ?? ''),
                (string)($_POST['customer_address'] ?? ''),
                (string)($_POST['description'] ?? ''),
                $amount,
                $method
            );
        }
        if ($invoice) {
            redirect_with(url('/admin/invoices.php'), ['flash' => 'Invoice ' . $invoice['invoice_number'] . ' created and emailed where a customer email is available.']);
        }
        redirect_with(url('/admin/invoices.php'), ['error' => 'The invoice could not be created. For a standalone invoice, enter a customer name and an amount.']);
    }
    if ($action === 'mark_paid' && $invoiceId > 0) {
        mark_invoice_paid($invoiceId);
        redirect_with(url('/admin/invoices.php'), ['flash' => 'Invoice marked as paid.']);
    }
    if ($action === 'resend' && $invoiceId > 0) {
        $sent = send_invoice_email($invoiceId);
        redirect_with(url('/admin/invoices.php'), ['flash' => $sent ? 'Invoice email sent again.' : 'The invoice email could not be sent.']);
    }
    if ($action === 'void' && $invoiceId > 0) {
        db()->prepare("UPDATE invoices SET status='void' WHERE id=? AND status <> 'paid'")->execute([$invoiceId]);
        redirect_with(url('/admin/invoices.php'), ['flash' => 'Invoice voided.']);
    }
    redirect(url('/admin/invoices.php'));
}

$flash = flash('flash');
$error = flash('error');
$selectedBookingId = (int)($_GET['booking'] ?? 0);
$bookings = db()->query("SELECT b.id, b.reference, b.name, b.email, b.quoted_total, b.deposit_status, b.distance_miles, s.title AS service_title
    FROM bookings b LEFT JOIN services s ON s.id=b.service_id ORDER BY b.created_at DESC LIMIT 100")->fetchAll();
$invoices = db()->query("SELECT i.*, b.reference, COALESCE(NULLIF(i.customer_name, ''), b.name, '') AS display_name, COALESCE(NULLIF(i.customer_email, ''), b.email, '') AS display_email, s.title AS service_title
    FROM invoices i LEFT JOIN bookings b ON b.id=i.booking_id LEFT JOIN services s ON s.id=b.service_id
    ORDER BY i.created_at DESC LIMIT 100")->fetchAll();

$admin_title = 'Invoices';
$active_admin = 'invoices';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-2col">
    <section class="panel">
        <div class="panel-head"><h2>Create invoice</h2><span class="muted">Booking or standalone</span></div>
        <p class="muted">Choose a booking for a deposit or balance, or leave it unselected to create a normal/custom invoice for someone who has no booking.</p>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="field"><label for="booking_id">Booking <span class="muted">(optional)</span></label><select id="booking_id" name="booking_id"><option value="0">No booking — standalone invoice</option><?php foreach ($bookings as $booking): ?><option value="<?= (int)$booking['id'] ?>" <?= $selectedBookingId === (int)$booking['id'] ? 'selected' : '' ?>><?= e($booking['reference']) ?> — <?= e($booking['name']) ?><?= $booking['service_title'] ? ' — ' . e($booking['service_title']) : '' ?></option><?php endforeach; ?></select></div>
            <div class="form-row">
                <div class="field"><label for="customer_name">Customer name <span class="muted">(standalone only)</span></label><input type="text" id="customer_name" name="customer_name" maxlength="120" placeholder="Customer or company name"></div>
                <div class="field"><label for="customer_email">Customer email</label><input type="email" id="customer_email" name="customer_email" placeholder="Send invoice by email"></div>
            </div>
            <div class="form-row">
                <div class="field"><label for="customer_phone">Customer phone</label><input type="tel" id="customer_phone" name="customer_phone"></div>
                <div class="field"><label for="customer_address">Customer address</label><input type="text" id="customer_address" name="customer_address"></div>
            </div>
            <div class="field"><label for="description">Invoice description <span class="muted">(required for standalone invoices)</span></label><textarea id="description" name="description" rows="3" maxlength="255" placeholder="e.g. Vehicle recovery from Manchester to Leeds"></textarea></div>
            <div class="form-row">
                <div class="field"><label for="invoice_type">Invoice type</label><select id="invoice_type" name="invoice_type"><option value="balance">Balance after deposit</option><option value="full">Full amount</option><option value="deposit">£50 deposit</option><option value="custom">Standalone / custom invoice</option></select></div>
                <div class="field"><label for="amount_due">Amount due <span class="muted">(optional for booking quote)</span></label><input type="number" id="amount_due" name="amount_due" min="0.01" step="0.01" placeholder="Auto from booking quote"></div>
            </div>
            <div class="field"><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option value="">Use default setting</option><option value="stripe">Stripe payment link</option><option value="bank_transfer">Bank transfer</option></select></div>
            <button type="submit" class="btn btn-primary">Create and send invoice</button>
        </form>
    </section>
    <aside class="panel">
        <h2>Payment setup</h2>
        <p class="muted">Stripe invoices create a hosted Payment Link and send it to the customer by email. Bank-transfer invoices show your bank details and invoice reference instead.</p>
        <p><a class="btn btn-outline" href="<?= e(url('/admin/settings.php#payments')) ?>">Open payment settings</a></p>
        <p class="muted">For bank payments, mark the invoice paid here after the transfer clears. The booking’s deposit or balance status will update too.</p>
    </aside>
</div>

<section class="panel">
    <div class="panel-head"><h2>Invoice history</h2><span class="muted"><?= count($invoices) ?> shown</span></div>
    <?php if (!$invoices): ?>
        <p class="muted">No invoices yet. New booking requests will create their £50 deposit invoice here.</p>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Invoice</th><th>Booking</th><th>Amount</th><th>Method</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($invoices as $invoice): ?>
                <tr>
                    <td><strong><?= e($invoice['invoice_number']) ?></strong><br><small><?= e(invoice_type_label($invoice['invoice_type'])) ?></small></td>
                    <td><?php if ((int)$invoice['booking_id'] > 0): ?><a href="<?= e(url('/admin/bookings.php?view=' . (int)$invoice['booking_id'])) ?>"><?= e($invoice['reference']) ?></a><?php else: ?><span class="muted">Standalone</span><?php endif; ?><br><small><?= e($invoice['display_name']) ?></small></td>
                    <td><strong><?= e(format_price($invoice['amount_due'])) ?></strong><br><small><?= e($invoice['currency']) ?></small></td>
                    <td><?= $invoice['payment_method'] === 'stripe' ? 'Stripe' : 'Bank transfer' ?></td>
                    <td><span class="badge badge-<?= e($invoice['status']) ?>"><?= e(invoice_status_label($invoice['status'])) ?></span></td>
                    <td><?= e(date('d M Y H:i', strtotime($invoice['created_at']))) ?></td>
                    <td>
                        <div class="msg-actions">
                            <a class="btn btn-outline btn-sm" href="<?= e(invoice_public_url($invoice)) ?>" target="_blank" rel="noopener">View</a>
                            <?php if ($invoice['payment_method'] === 'stripe' && $invoice['stripe_payment_link_url'] && $invoice['status'] !== 'paid'): ?><a class="btn btn-primary btn-sm" href="<?= e($invoice['stripe_payment_link_url']) ?>" target="_blank" rel="noopener">Pay link</a><?php endif; ?>
                            <?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'void'): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Mark paid</button></form><?php endif; ?>
                            <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="resend"><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Resend</button></form>
                            <?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'void'): ?><form method="post" class="inline-form" data-confirm="Void this invoice?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="void"><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Void</button></form><?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require APP_DIR . '/views/layout/admin_footer.php'; ?>
