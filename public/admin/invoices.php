<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();
ensure_payment_schema();

/** @return array<string,mixed> */
function admin_invoice_post_data(): array
{
    return [
        'booking_id' => (int)($_POST['booking_id'] ?? 0),
        'invoice_type' => (string)($_POST['invoice_type'] ?? 'custom'),
        'customer_name' => (string)($_POST['customer_name'] ?? ''),
        'company_name' => (string)($_POST['company_name'] ?? ''),
        'customer_email' => (string)($_POST['customer_email'] ?? ''),
        'customer_phone' => (string)($_POST['customer_phone'] ?? ''),
        'customer_address' => (string)($_POST['customer_address'] ?? ''),
        'customer_reference' => (string)($_POST['customer_reference'] ?? ''),
        'vehicle_make' => (string)($_POST['vehicle_make'] ?? ''),
        'vehicle_model' => (string)($_POST['vehicle_model'] ?? ''),
        'vehicle_reg' => (string)($_POST['vehicle_reg'] ?? ''),
        'collection_location' => (string)($_POST['collection_location'] ?? ''),
        'destination' => (string)($_POST['destination'] ?? ''),
        'recovery_date' => (string)($_POST['recovery_date'] ?? ''),
        'invoice_date' => (string)($_POST['invoice_date'] ?? ''),
        'due_date' => (string)($_POST['due_date'] ?? ''),
        'description' => (string)($_POST['description'] ?? ''),
        'amount_due_override' => (float)($_POST['amount_due_override'] ?? 0),
        'deposit_paid' => (float)($_POST['deposit_paid'] ?? 0),
        'discount_type' => (string)($_POST['discount_type'] ?? 'none'),
        'discount_value' => (float)($_POST['discount_value'] ?? 0),
        'vat_enabled' => !empty($_POST['vat_enabled']) ? 1 : 0,
        'vat_rate' => (float)($_POST['vat_rate'] ?? setting('vat_rate', '0')),
        'payment_method' => (string)($_POST['payment_method'] ?? ''),
        'payment_terms' => (string)($_POST['payment_terms'] ?? 'Payment is due upon receipt.'),
        'customer_notes' => (string)($_POST['customer_notes'] ?? ''),
        'internal_notes' => (string)($_POST['internal_notes'] ?? ''),
        'reminders_paused' => !empty($_POST['reminders_paused']) ? 1 : 0,
    ];
}

/** @return array<int,array<string,mixed>> */
function admin_invoice_post_items(): array
{
    $items = $_POST['items'] ?? [];
    return is_array($items) ? $items : [];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);

    if ($action === 'create') {
        $invoice = create_invoice_from_data(admin_invoice_post_data(), admin_invoice_post_items(), !empty($_POST['send_now']));
        if ($invoice) redirect_with(url('/admin/invoices.php'), ['flash' => !empty($_POST['send_now']) ? 'Invoice ' . $invoice['invoice_number'] . ' created and sent.' : 'Invoice ' . $invoice['invoice_number'] . ' saved as a draft.']);
        redirect_with(url('/admin/invoices.php'), ['error' => 'The invoice could not be created. Check the customer name, email, amount and line items.']);
    }
    if ($action === 'update' && $invoiceId > 0) {
        $invoice = update_invoice_from_data($invoiceId, admin_invoice_post_data(), admin_invoice_post_items());
        if ($invoice && !empty($_POST['send_now'])) $invoice = finalize_invoice($invoiceId, ['id' => (int)$invoice['booking_id'], 'reference' => (string)$invoice['reference']]);
        redirect_with(url('/admin/invoices.php?edit=' . $invoiceId), ['flash' => $invoice ? 'Invoice updated.' : 'The invoice could not be updated. Paid or void invoices are locked.']);
    }
    if ($action === 'send' && $invoiceId > 0) {
        $invoice = get_invoice($invoiceId);
        if ($invoice) {
            if ($invoice['status'] === 'draft') finalize_invoice($invoiceId, ['id' => (int)$invoice['booking_id'], 'reference' => (string)$invoice['reference']]);
            else send_invoice_email($invoiceId);
        }
        redirect_with(url('/admin/invoices.php'), ['flash' => 'Invoice send requested.']);
    }
    if ($action === 'resend' && $invoiceId > 0) {
        $sent = send_invoice_email($invoiceId);
        redirect_with(url('/admin/invoices.php'), ['flash' => $sent ? 'Invoice emailed again.' : 'The invoice email could not be sent.']);
    }
    if ($action === 'record_payment' && $invoiceId > 0) {
        $ok = record_invoice_payment($invoiceId, (float)($_POST['payment_amount'] ?? 0), (string)($_POST['payment_method'] ?? 'other'), (string)($_POST['paid_at'] ?? ''), (string)($_POST['payment_reference'] ?? ''), (string)($_POST['payment_note'] ?? ''));
        redirect_with(url('/admin/invoices.php?payment=' . $invoiceId), ['flash' => $ok ? 'Payment recorded.' : 'Payment could not be recorded. Check the remaining balance.']);
    }
    if ($action === 'receipt' && $invoiceId > 0) {
        $sent = send_payment_receipt($invoiceId);
        redirect_with(url('/admin/invoices.php'), ['flash' => $sent ? 'Payment receipt sent.' : 'Receipt could not be sent; the invoice may not be paid or has no valid email.']);
    }
    if ($action === 'duplicate' && $invoiceId > 0) {
        $copy = duplicate_invoice($invoiceId);
        redirect_with(url('/admin/invoices.php?edit=' . (int)($copy['id'] ?? 0)), ['flash' => $copy ? 'New draft invoice created from ' . $copy['invoice_number'] . '.' : 'The invoice could not be duplicated.']);
    }
    if ($action === 'mark_paid' && $invoiceId > 0) {
        mark_invoice_paid($invoiceId);
        redirect_with(url('/admin/invoices.php'), ['flash' => 'Invoice marked as paid.']);
    }
    if ($action === 'void' && $invoiceId > 0) {
        db()->prepare("UPDATE invoices SET status='void' WHERE id=? AND status NOT IN ('paid','void','cancelled')")->execute([$invoiceId]);
        invoice_event($invoiceId, 'voided', 'Invoice voided');
        redirect_with(url('/admin/invoices.php'), ['flash' => 'Invoice voided. Financial history was retained.']);
    }
    if ($action === 'cancel' && $invoiceId > 0) {
        db()->prepare("UPDATE invoices SET status='cancelled' WHERE id=? AND status NOT IN ('paid','void','cancelled')")->execute([$invoiceId]);
        invoice_event($invoiceId, 'cancelled', 'Invoice cancelled');
        redirect_with(url('/admin/invoices.php'), ['flash' => 'Invoice cancelled. Financial history was retained.']);
    }
    redirect(url('/admin/invoices.php'));
}

$flash = flash('flash');
$error = flash('error');
$editId = (int)($_GET['edit'] ?? 0);
$paymentId = (int)($_GET['payment'] ?? 0);
$editing = $editId > 0 ? get_invoice($editId) : null;
$paymentInvoice = $paymentId > 0 ? get_invoice($paymentId) : null;
$editingItems = $editing ? get_invoice_items($editId) : [['description' => 'Vehicle recovery', 'quantity' => 1, 'unit_price' => 0, 'vat_rate' => 0]];
$vatDefaultChecked = $editing ? !empty($editing['vat_enabled']) : setting('vat_registered', '0') === '1';
$editEvents = $editing ? get_invoice_events($editId) : [];
$editPayments = $editing ? get_invoice_payments($editId) : [];
$selectedBookingId = (int)($_GET['booking'] ?? ($editing['booking_id'] ?? 0));
$bookings = db()->query("SELECT b.id, b.reference, b.name, b.email, b.quoted_total, b.deposit_status, b.distance_miles, s.title AS service_title FROM bookings b LEFT JOIN services s ON s.id=b.service_id ORDER BY b.created_at DESC LIMIT 100")->fetchAll();

// Keep the CRM status useful even when no reminder worker is running yet.
db()->exec("UPDATE invoices SET status='overdue' WHERE due_date < CURRENT_DATE AND status IN ('sent','viewed','part_paid') AND amount_paid < amount_due AND reminders_paused=0");

$search = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(i.invoice_number LIKE ? OR i.customer_name LIKE ? OR i.customer_email LIKE ? OR i.customer_phone LIKE ? OR i.vehicle_reg LIKE ? OR b.reference LIKE ?)';
    for ($i = 0; $i < 6; $i++) $params[] = '%' . $search . '%';
}
if ($statusFilter === 'outstanding') $where[] = "i.status IN ('sent','viewed','part_paid','overdue')";
elseif ($statusFilter !== '' && in_array($statusFilter, ['draft','sent','viewed','part_paid','paid','overdue','cancelled','void','refunded','failed'], true)) { $where[] = 'i.status=?'; $params[] = $statusFilter; }
$sql = "SELECT i.*, b.reference, COALESCE(NULLIF(i.customer_name, ''), b.name, '') AS display_name, COALESCE(NULLIF(i.customer_email, ''), b.email, '') AS display_email, s.title AS service_title FROM invoices i LEFT JOIN bookings b ON b.id=i.booking_id LEFT JOIN services s ON s.id=b.service_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY i.created_at DESC LIMIT 100';
$stmt = db()->prepare($sql); $stmt->execute($params); $invoices = $stmt->fetchAll();
$summary = db()->query("SELECT COALESCE(SUM(CASE WHEN status IN ('sent','viewed','part_paid','overdue') THEN GREATEST(amount_due-amount_paid,0) ELSE 0 END),0) AS outstanding, COALESCE(SUM(CASE WHEN status='paid' AND paid_at >= DATE_FORMAT(CURRENT_DATE,'%Y-%m-01') THEN amount_paid ELSE 0 END),0) AS paid_month, COALESCE(SUM(CASE WHEN status='overdue' THEN GREATEST(amount_due-amount_paid,0) ELSE 0 END),0) AS overdue, COALESCE(SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END),0) AS drafts FROM invoices")->fetch() ?: [];

$admin_title = 'Invoices';
$active_admin = 'invoices';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="invoice-stat-grid"><div class="stat-card"><span>Outstanding</span><strong><?= e(format_price((float)($summary['outstanding'] ?? 0))) ?></strong></div><div class="stat-card"><span>Paid this month</span><strong><?= e(format_price((float)($summary['paid_month'] ?? 0))) ?></strong></div><div class="stat-card"><span>Overdue</span><strong><?= e(format_price((float)($summary['overdue'] ?? 0))) ?></strong></div><div class="stat-card"><span>Draft invoices</span><strong><?= e((string)($summary['drafts'] ?? 0)) ?></strong></div></div>

<?php if ($paymentInvoice): $paymentRemaining = max(0, round((float)$paymentInvoice['amount_due'] - (float)$paymentInvoice['amount_paid'], 2)); ?><section class="panel invoice-payment-panel"><div class="panel-head"><h2>Record payment · <?= e($paymentInvoice['invoice_number']) ?></h2><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/invoices.php')) ?>">Close</a></div><p class="muted">Remaining balance: <strong><?= e(format_price($paymentRemaining)) ?></strong>. Partial payments are kept in the payment history.</p><form method="post" class="form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="record_payment"><input type="hidden" name="invoice_id" value="<?= (int)$paymentInvoice['id'] ?>"><div class="form-row"><div class="field"><label for="payment_amount">Amount received</label><input id="payment_amount" name="payment_amount" type="number" min="0.01" max="<?= e((string)$paymentRemaining) ?>" step="0.01" value="<?= e((string)$paymentRemaining) ?>" required></div><div class="field"><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="card">Card</option><option value="other">Other</option></select></div><div class="field"><label for="paid_at">Payment date</label><input id="paid_at" name="paid_at" type="date" value="<?= e(date('Y-m-d')) ?>"></div></div><div class="form-row"><div class="field"><label for="payment_reference">Payment reference</label><input id="payment_reference" name="payment_reference" maxlength="120"></div><div class="field"><label for="payment_note">Internal note</label><input id="payment_note" name="payment_note" maxlength="255"></div></div><button class="btn btn-primary" type="submit">Record payment</button></form></section><?php endif; ?>

<section class="panel" id="invoice-editor"><div class="panel-head"><h2><?= $editing ? 'Edit invoice ' . e($editing['invoice_number']) : 'Create invoice' ?></h2><span class="muted"><?= $editing ? 'Changes are added to the audit trail' : 'Booking-linked or standalone' ?></span></div><p class="muted">Build a professional invoice with line items, discount, optional VAT, payment terms and a secure customer link. Save a draft first or create and send it now.</p>
<form method="post" class="form" data-invoice-form novalidate><?= csrf_field() ?><input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>"><?php if ($editing): ?><input type="hidden" name="invoice_id" value="<?= (int)$editing['id'] ?>"><?php endif; ?><input type="hidden" name="send_now" value="0" data-invoice-send-now>
<div class="field"><label for="booking_id">Booking <span class="muted">(optional)</span></label><select id="booking_id" name="booking_id"><option value="0">No booking — standalone invoice</option><?php foreach ($bookings as $booking): ?><option value="<?= (int)$booking['id'] ?>" <?= $selectedBookingId === (int)$booking['id'] ? 'selected' : '' ?>><?= e($booking['reference']) ?> — <?= e($booking['name']) ?><?= $booking['service_title'] ? ' — ' . e($booking['service_title']) : '' ?></option><?php endforeach; ?></select></div>
<div class="form-row"><div class="field"><label for="customer_name">Customer name *</label><input type="text" id="customer_name" name="customer_name" maxlength="120" required value="<?= e((string)($editing['customer_name'] ?? '')) ?>"></div><div class="field"><label for="company_name">Company name</label><input type="text" id="company_name" name="company_name" maxlength="160" value="<?= e((string)($editing['company_name'] ?? '')) ?>"></div></div>
<div class="form-row"><div class="field"><label for="customer_email">Email</label><input type="email" id="customer_email" name="customer_email" value="<?= e((string)($editing['customer_email'] ?? '')) ?>"></div><div class="field"><label for="customer_phone">Telephone</label><input type="tel" id="customer_phone" name="customer_phone" value="<?= e((string)($editing['customer_phone'] ?? '')) ?>"></div></div>
<div class="form-row"><div class="field"><label for="customer_address">Billing address</label><input type="text" id="customer_address" name="customer_address" value="<?= e((string)($editing['customer_address'] ?? '')) ?>"></div><div class="field"><label for="customer_reference">Customer reference</label><input type="text" id="customer_reference" name="customer_reference" value="<?= e((string)($editing['customer_reference'] ?? '')) ?>"></div></div>
<div class="form-row"><div class="field"><label for="vehicle_make">Vehicle make</label><input type="text" id="vehicle_make" name="vehicle_make" value="<?= e((string)($editing['vehicle_make'] ?? '')) ?>"></div><div class="field"><label for="vehicle_model">Vehicle model</label><input type="text" id="vehicle_model" name="vehicle_model" value="<?= e((string)($editing['vehicle_model'] ?? '')) ?>"></div><div class="field"><label for="vehicle_reg">Registration</label><input type="text" id="vehicle_reg" name="vehicle_reg" value="<?= e((string)($editing['vehicle_reg'] ?? '')) ?>"></div></div>
<div class="form-row"><div class="field"><label for="collection_location">Collection location</label><input type="text" id="collection_location" name="collection_location" value="<?= e((string)($editing['collection_location'] ?? '')) ?>"></div><div class="field"><label for="destination">Destination</label><input type="text" id="destination" name="destination" value="<?= e((string)($editing['destination'] ?? '')) ?>"></div><div class="field"><label for="recovery_date">Recovery date</label><input type="date" id="recovery_date" name="recovery_date" value="<?= e((string)($editing['recovery_date'] ?? '')) ?>"></div></div>
<div class="form-row"><div class="field"><label for="invoice_type">Invoice type</label><select id="invoice_type" name="invoice_type"><option value="custom" <?= (($editing['invoice_type'] ?? 'custom') === 'custom') ? 'selected' : '' ?>>Custom invoice</option><option value="deposit" <?= (($editing['invoice_type'] ?? '') === 'deposit') ? 'selected' : '' ?>>£50 deposit</option><option value="balance" <?= (($editing['invoice_type'] ?? '') === 'balance') ? 'selected' : '' ?>>Balance after deposit</option><option value="full" <?= (($editing['invoice_type'] ?? '') === 'full') ? 'selected' : '' ?>>Full amount</option></select></div><div class="field"><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option value="" <?= empty($editing['payment_method']) ? 'selected' : '' ?>>Use default setting</option><?php foreach (['stripe'=>'Stripe payment link','bank_transfer'=>'Bank transfer','cash'=>'Cash','card'=>'Card','other'=>'Other'] as $method => $label): ?><option value="<?= e($method) ?>" <?= (($editing['payment_method'] ?? '') === $method) ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="field"><label for="deposit_paid">Deposit already paid</label><input type="number" id="deposit_paid" name="deposit_paid" min="0" step="0.01" value="<?= e((string)($editing['deposit_paid'] ?? '0')) ?>"></div></div>
<div class="invoice-items-editor"><div class="panel-head"><h3>Line items</h3><div class="invoice-item-tools"><select data-invoice-preset aria-label="Add service preset"><option value="">Add service preset</option><option value="Vehicle Recovery|50">Vehicle Recovery</option><option value="Breakdown Recovery|50">Breakdown Recovery</option><option value="Vehicle Transportation|120">Vehicle Transportation</option><option value="Accident Recovery|120">Accident Recovery</option><option value="Call-Out Charge|50">Call-Out Charge</option><option value="Waiting Time|25">Waiting Time</option><option value="Additional Mileage|2.5">Additional Mileage</option><option value="Storage|0">Storage</option><option value="Winching|0">Winching</option><option value="Custom Item|0">Custom Item</option></select><button type="button" class="btn btn-outline btn-sm" data-add-invoice-item>+ Add line item</button></div></div><div data-invoice-items><?php foreach ($editingItems as $index => $item): ?><div class="invoice-item-row" data-invoice-item><input type="text" name="items[<?= (int)$index ?>][description]" placeholder="Description" value="<?= e((string)($item['description'] ?? '')) ?>" required><input type="number" name="items[<?= (int)$index ?>][quantity]" min="0.01" step="0.01" value="<?= e((string)($item['quantity'] ?? '1')) ?>" aria-label="Quantity"><input type="number" name="items[<?= (int)$index ?>][unit_price]" min="0" step="0.01" value="<?= e((string)($item['unit_price'] ?? '0')) ?>" aria-label="Unit price"><output data-invoice-line-total><?= e(format_price((float)($item['line_total'] ?? 0))) ?></output><button type="button" class="btn btn-outline btn-sm" data-remove-invoice-item aria-label="Remove line item">Remove</button></div><?php endforeach; ?></div><div class="invoice-live-totals" data-invoice-live-totals>Totals update as you type.</div></div>
<div class="form-row"><div class="field"><label for="discount_type">Discount</label><select id="discount_type" name="discount_type"><option value="none">No discount</option><option value="fixed" <?= (($editing['discount_type'] ?? '') === 'fixed') ? 'selected' : '' ?>>Fixed amount</option><option value="percent" <?= (($editing['discount_type'] ?? '') === 'percent') ? 'selected' : '' ?>>Percentage</option></select></div><div class="field"><label for="discount_value">Discount value</label><input type="number" id="discount_value" name="discount_value" min="0" step="0.01" value="<?= e((string)($editing['discount_value'] ?? '0')) ?>"></div><div class="field"><label for="amount_due_override">Amount due override <span class="muted">(optional)</span></label><input type="number" id="amount_due_override" name="amount_due_override" min="0" step="0.01" placeholder="Auto-calculate"></div></div>
<div class="form-row"><div class="field"><label for="invoice_date">Invoice date</label><input type="date" id="invoice_date" name="invoice_date" value="<?= e((string)($editing['invoice_date'] ?? date('Y-m-d'))) ?>"></div><div class="field"><label for="due_date">Due date</label><input type="date" id="due_date" name="due_date" value="<?= e((string)($editing['due_date'] ?? date('Y-m-d'))) ?>"></div><div class="field"><label for="vat_rate">VAT rate</label><input type="number" id="vat_rate" name="vat_rate" min="0" max="100" step="0.01" value="<?= e((string)($editing['vat_rate'] ?? setting('vat_rate', '0'))) ?>"><label class="field-check"><input type="checkbox" name="vat_enabled" value="1" <?= $vatDefaultChecked ? 'checked' : '' ?>> VAT registered</label></div></div>
<div class="form-row"><div class="field"><label for="payment_terms">Payment terms</label><input type="text" id="payment_terms" name="payment_terms" maxlength="255" value="<?= e((string)($editing['payment_terms'] ?? 'Payment is due upon receipt.')) ?>"></div><div class="field"><label for="customer_notes">Customer notes</label><textarea id="customer_notes" name="customer_notes" rows="2"><?= e((string)($editing['customer_notes'] ?? '')) ?></textarea></div></div>
<div class="form-row"><div class="field"><label for="internal_notes">Internal notes</label><textarea id="internal_notes" name="internal_notes" rows="2"><?= e((string)($editing['internal_notes'] ?? '')) ?></textarea></div><label class="field-check"><input type="checkbox" name="reminders_paused" value="1" <?= !empty($editing['reminders_paused']) ? 'checked' : '' ?>> Pause invoice reminders</label></div>
<div class="invoice-editor-actions"><button type="submit" class="btn btn-outline" data-save-draft>Save draft</button><button type="submit" class="btn btn-primary" data-send-invoice><?= $editing ? 'Save &amp; send' : 'Create &amp; send invoice' ?></button><?php if ($editing): ?><a class="btn btn-outline" href="<?= e(invoice_public_url($editing)) ?>" target="_blank" rel="noopener">Preview</a><?php endif; ?><a class="btn btn-ghost" href="<?= e(url('/admin/invoices.php')) ?>">Cancel</a></div></form></section>

<?php if ($editing): ?><section class="panel"><div class="panel-head"><h2>Invoice activity</h2><span class="muted">Financial history is retained</span></div><?php foreach ($editEvents as $event): ?><p class="invoice-event"><strong><?= e(date('d M Y H:i', strtotime($event['created_at']))) ?></strong> · <?= e($event['description']) ?></p><?php endforeach; ?><?php foreach ($editPayments as $payment): ?><p class="invoice-event"><strong><?= e(date('d M Y H:i', strtotime($payment['paid_at']))) ?></strong> · Payment <?= e(format_price($payment['amount'])) ?> via <?= e(invoice_payment_method_label($payment['payment_method'])) ?></p><?php endforeach; ?></section><?php endif; ?>

<section class="panel"><div class="panel-head"><h2>Invoice history</h2><span class="muted"><?= count($invoices) ?> shown</span></div><form class="invoice-filter" method="get"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search invoice, customer, email, phone, reg or booking"><select name="status"><option value="">All statuses</option><?php foreach (['outstanding'=>'Outstanding','draft'=>'Draft','sent'=>'Sent','part_paid'=>'Part paid','paid'=>'Paid','overdue'=>'Overdue','cancelled'=>'Cancelled','void'=>'Void'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn btn-outline" type="submit">Filter</button></form><?php if (!$invoices): ?><p class="muted">No invoices match this view.</p><?php else: ?><div class="table-wrap"><table class="data-table invoice-history-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Date / due</th><th>Actions</th></tr></thead><tbody><?php foreach ($invoices as $invoice): $balance = max(0, round((float)$invoice['amount_due'] - (float)$invoice['amount_paid'], 2)); ?><tr><td><strong><?= e($invoice['invoice_number']) ?></strong><br><small><?= e(invoice_type_label($invoice['invoice_type'])) ?></small></td><td><?= e($invoice['display_name']) ?><br><small><?= e($invoice['display_email']) ?></small><?php if ($invoice['vehicle_reg']): ?><br><small><?= e($invoice['vehicle_reg']) ?></small><?php endif; ?></td><td><strong><?= e(format_price($invoice['total_amount'] ?: $invoice['amount_due'])) ?></strong></td><td><?= e(format_price($invoice['amount_paid'])) ?></td><td><?= e(format_price($balance)) ?></td><td><span class="badge badge-<?= e($invoice['status']) ?>"><?= e(invoice_status_label($invoice['status'])) ?></span></td><td><?= e(date('d M Y', strtotime($invoice['created_at']))) ?><br><small>Due <?= e((string)($invoice['due_date'] ?: '—')) ?></small></td><td><div class="msg-actions"><a class="btn btn-outline btn-sm" href="<?= e(invoice_public_url($invoice)) ?>" target="_blank" rel="noopener">View</a><a class="btn btn-outline btn-sm" href="<?= e(invoice_public_url($invoice) . '&download=1') ?>">PDF</a><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/invoices.php?edit=' . (int)$invoice['id'])) ?>">Edit</a><?php if (!in_array($invoice['status'], ['paid','void','cancelled'], true)): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/invoices.php?payment=' . (int)$invoice['id'])) ?>">Payment</a><?php endif; ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $invoice['status'] === 'draft' ? 'send' : 'resend' ?>"><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>"><button class="btn btn-primary btn-sm" type="submit"><?= $invoice['status'] === 'draft' ? 'Send' : 'Resend' ?></button></form><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Duplicate</button></form><a class="btn btn-outline btn-sm" href="<?= e(chat_handover_whatsapp_url('Hi ' . $invoice['display_name'] . ', your MancWay Recovery invoice ' . $invoice['invoice_number'] . ' is ready: ' . invoice_public_url($invoice))) ?>" target="_blank" rel="noopener">WhatsApp</a><?php if ($invoice['status'] !== 'paid' && !in_array($invoice['status'], ['void','cancelled'], true)): ?><form method="post" class="inline-form" data-confirm="Void this invoice?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="void"><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Void</button></form><?php endif; ?><?php if ($invoice['status'] === 'paid'): ?><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="receipt"><input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>"><button class="btn btn-outline btn-sm" type="submit">Receipt</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('[data-invoice-form]'); if (!form) return;
  var list = form.querySelector('[data-invoice-items]'); var add = form.querySelector('[data-add-invoice-item]'); var totals = form.querySelector('[data-invoice-live-totals]'); var index = list.querySelectorAll('[data-invoice-item]').length;
  function money(value) { return '£' + Number(value || 0).toFixed(2); }
  function recalc() { var subtotal = 0; list.querySelectorAll('[data-invoice-item]').forEach(function (row) { var qty = Number((row.querySelector('[name*="[quantity]"]') || {}).value || 0); var price = Number((row.querySelector('[name*="[unit_price]"]') || {}).value || 0); var line = Math.max(0, qty * price); subtotal += line; var output = row.querySelector('[data-invoice-line-total]'); if (output) output.textContent = money(line); }); var type = (form.querySelector('[name="discount_type"]') || {}).value || 'none'; var discountValue = Number((form.querySelector('[name="discount_value"]') || {}).value || 0); var discount = type === 'percent' ? subtotal * Math.min(100, discountValue) / 100 : (type === 'fixed' ? Math.min(subtotal, discountValue) : 0); var vatInput = form.querySelector('[name="vat_enabled"]'); var vat = vatInput && vatInput.checked ? (subtotal - discount) * Number((form.querySelector('[name="vat_rate"]') || {}).value || 0) / 100 : 0; var deposit = Number((form.querySelector('[name="deposit_paid"]') || {}).value || 0); if (totals) totals.textContent = 'Subtotal ' + money(subtotal) + ' · Discount -' + money(discount) + ' · VAT ' + money(vat) + ' · Total ' + money(Math.max(0, subtotal - discount + vat)) + ' · Balance after deposit ' + money(Math.max(0, subtotal - discount + vat - deposit)); }
  function wire(row) { row.querySelectorAll('input').forEach(function (input) { input.addEventListener('input', recalc); }); var remove = row.querySelector('[data-remove-invoice-item]'); if (remove) remove.addEventListener('click', function () { if (list.querySelectorAll('[data-invoice-item]').length > 1) { row.remove(); recalc(); } }); }
  list.querySelectorAll('[data-invoice-item]').forEach(wire);
  if (add) add.addEventListener('click', function () { var row = document.createElement('div'); row.className = 'invoice-item-row'; row.setAttribute('data-invoice-item', ''); row.innerHTML = '<input type="text" name="items[' + index + '][description]" placeholder="Description" required><input type="number" name="items[' + index + '][quantity]" min="0.01" step="0.01" value="1" aria-label="Quantity"><input type="number" name="items[' + index + '][unit_price]" min="0" step="0.01" value="0" aria-label="Unit price"><output data-invoice-line-total>£0.00</output><button type="button" class="btn btn-outline btn-sm" data-remove-invoice-item>Remove</button>'; index += 1; list.appendChild(row); wire(row); recalc(); });
   var preset = form.querySelector('[data-invoice-preset]');
   if (preset) preset.addEventListener('change', function () { if (!preset.value) return; var parts = preset.value.split('|'); if (add) add.click(); var rows = list.querySelectorAll('[data-invoice-item]'); var row = rows[rows.length - 1]; if (row) { var description = row.querySelector('[name*="[description]"]'); var price = row.querySelector('[name*="[unit_price]"]'); if (description) description.value = parts[0] || ''; if (price) price.value = parts[1] || '0'; recalc(); } preset.value = ''; });
   form.querySelectorAll('input,select,textarea').forEach(function (input) { input.addEventListener('input', recalc); input.addEventListener('change', recalc); });
  form.querySelectorAll('[data-save-draft],[data-send-invoice]').forEach(function (button) { button.addEventListener('click', function () { var send = form.querySelector('[data-invoice-send-now]'); if (send) send.value = button.hasAttribute('data-send-invoice') ? '1' : '0'; }); });
  recalc();
});
</script>
<?php require APP_DIR . '/views/layout/admin_footer.php'; ?>
