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
        $invoice = create_invoice_for_booking($bookingId, $type, $amount, $method);
        if ($invoice) {
            redirect_with(url('/admin/invoices.php'), ['flash' => 'Invoice ' . $invoice['invoice_number'] . ' created and emailed where a customer email is available.']);
        }
        redirect_with(url('/admin/invoices.php'), ['error' => 'The invoice could not be created. Check the booking and amount.']);
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
$invoices = db()->query("SELECT i.*, b.reference, b.name, b.email, b.quoted_total, s.title AS service_title
    FROM invoices i JOIN bookings b ON b.id=i.booking_id LEFT JOIN services s ON s.id=b.service_id
    ORDER BY i.created_at DESC LIMIT 100")->fetchAll();

$admin_title = 'Invoices';
$active_admin = 'invoices';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="admin-2col">
    <section class="panel">
        <div class="panel-head"><h2>Create invoice</h2><span class="muted">Deposit or balance</span></div>
        <p class="muted">Every booking receives a £50 deposit invoice automatically. Use this form for a balance, a full quote or a replacement invoice.</p>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="field"><label for="booking_id">Booking</label><select id="booking_id" name="booking_id" required><option value="">Select a booking…</option><?php foreach ($bookings as $booking): ?><option value="<?= (int)$booking['id'] ?>" <?= $selectedBookingId === (int)$booking['id'] ? 'selected' : '' ?>><?= e($booking['reference']) ?> — <?= e($booking['name']) ?><?= $booking['service_title'] ? ' — ' . e($booking['service_title']) : '' ?></option><?php endforeach; ?></select></div>
            <div class="form-row">
                <div class="field"><label for="invoice_type">Invoice type</label><select id="invoice_type" name="invoice_type"><option value="balance">Balance after deposit</option><option value="full">Full amount</option><option value="deposit">£50 deposit</option></select></div>
                <div class="field"><label for="amount_due">Amount due <span class="muted">(optional)</span></label><input type="number" id="amount_due" name="amount_due" min="0.01" step="0.01" placeholder="Auto from booking quote"></div>
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
                    <td><a href="<?= e(url('/admin/bookings.php?view=' . (int)$invoice['booking_id'])) ?>"><?= e($invoice['reference']) ?></a><br><small><?= e($invoice['name']) ?></small></td>
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
