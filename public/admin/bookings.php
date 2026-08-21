<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

// The CRM migration adds the accepted/dispatched workflow. Keep the original
// status set available until that migration has been run.
$crmReady = false;
try {
    db()->query('SELECT vehicle_id, updated_at FROM bookings LIMIT 1');
    db()->query('SELECT 1 FROM recovery_vehicles LIMIT 1');
    $crmReady = true;
} catch (Throwable $e) {
    $crmReady = false;
}
$statusOptions = $crmReady ? enquiry_statuses() : ['new', 'confirmed', 'complete', 'cancelled'];
$services = db()->query('SELECT id, slug, title, price_from FROM services WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
$vehicles = $crmReady
    ? db()->query('SELECT id, name, registration FROM recovery_vehicles WHERE is_active = 1 ORDER BY id')->fetchAll()
    : [];

// Update status or create a booking entered by the admin (for example, a
// booking taken over the phone). Admin-created bookings use the same bookings
// table as the public form, so they appear in CRM immediately.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'status';

    if ($action === 'create') {
        $name       = trim((string)($_POST['name'] ?? ''));
        $email      = trim((string)($_POST['email'] ?? ''));
        $phone      = trim((string)($_POST['phone'] ?? ''));
        $vmake      = trim((string)($_POST['vehicle_make'] ?? ''));
        $vmodel     = trim((string)($_POST['vehicle_model'] ?? ''));
        $vreg       = strtoupper(trim((string)($_POST['vehicle_reg'] ?? '')));
        $distanceRaw = trim((string)($_POST['distance_miles'] ?? ''));
        $distanceMiles = $distanceRaw === '' ? 0.0 : (float)$distanceRaw;
        $serviceId  = (int)($_POST['service_id'] ?? 0);
        $vehicleId  = (int)($_POST['vehicle_id'] ?? 0);
        $address    = trim((string)($_POST['address'] ?? ''));
        $postcode   = strtoupper(trim((string)($_POST['postcode'] ?? '')));
        $pdate      = trim((string)($_POST['preferred_date'] ?? ''));
        $ptime      = trim((string)($_POST['preferred_time'] ?? ''));
        $notes      = trim((string)($_POST['notes'] ?? ''));
        $status     = (string)($_POST['status'] ?? 'new');
        $errors     = [];

        if ($name === '' || mb_strlen($name) > 120) $errors['name'] = 'Please enter the customer name.';
        if ($email !== '' && !valid_email($email)) $errors['email'] = 'Please enter a valid email address.';
        if (!valid_phone($phone)) $errors['phone'] = 'Please enter a valid phone number.';
        if ($vreg === '') $errors['vehicle_reg'] = 'Please enter the vehicle registration.';
        if ($distanceRaw !== '' && (!is_numeric($distanceRaw) || $distanceMiles < 0 || $distanceMiles > 10000)) $errors['distance_miles'] = 'Please enter estimated miles between 0 and 10,000.';
        if ($address === '') $errors['address'] = 'Please enter the pickup address.';
        if (!valid_postcode($postcode)) $errors['postcode'] = 'Please enter a valid UK postcode.';
        if ($pdate === '' || strtotime($pdate) < strtotime(date('Y-m-d'))) $errors['preferred_date'] = 'Please choose today or a future date.';
        if (!in_array($ptime, ['Morning (7:30–12:00)', 'Afternoon (12:00–18:00)', 'Anytime'], true)) $errors['preferred_time'] = 'Please choose a preferred time.';
        if (!in_array($status, $statusOptions, true)) $status = $statusOptions[0];

        if ($serviceId > 0) {
            $check = db()->prepare('SELECT COUNT(*) FROM services WHERE id=? AND is_active=1');
            $check->execute([$serviceId]);
            if ((int)$check->fetchColumn() === 0) $serviceId = 0;
        }
        if ($crmReady && $vehicleId > 0) {
            $check = db()->prepare('SELECT COUNT(*) FROM recovery_vehicles WHERE id=? AND is_active=1');
            $check->execute([$vehicleId]);
            if ((int)$check->fetchColumn() === 0) $errors['vehicle_id'] = 'Please choose an active recovery vehicle.';
        }

        $serviceSlug = '';
        $serviceFallback = 0.0;
        foreach ($services as $service) {
            if ((int)$service['id'] === $serviceId) {
                $serviceSlug = (string)$service['slug'];
                $serviceFallback = (float)$service['price_from'];
                break;
            }
        }
        $quote = booking_quote_for_service($serviceSlug, $distanceMiles, $serviceFallback);

        if (!$errors) {
            ensure_payment_schema();
            $reference = generate_reference();
            if ($crmReady) {
                $ins = db()->prepare('INSERT INTO bookings
                    (reference, name, email, phone, vehicle_make, vehicle_model, vehicle_reg, distance_miles, quoted_total, deposit_amount, deposit_status, balance_status, service_id, vehicle_id, address, postcode, preferred_date, preferred_time, notes, status, ip, created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                $ins->execute([
                    $reference, $name, $email, $phone, $vmake, $vmodel, $vreg, $distanceMiles, $quote['total'], 50.00, 'unpaid', 'not_due',
                    $serviceId > 0 ? $serviceId : null, $vehicleId > 0 ? $vehicleId : null,
                    $address, $postcode, $pdate, $ptime, mb_substr($notes, 0, 2000), $status, client_ip()
                ]);
            } else {
                $ins = db()->prepare('INSERT INTO bookings
                    (reference, name, email, phone, vehicle_make, vehicle_model, vehicle_reg, distance_miles, quoted_total, deposit_amount, deposit_status, balance_status, service_id, address, postcode, preferred_date, preferred_time, notes, status, ip, created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
                $ins->execute([
                    $reference, $name, $email, $phone, $vmake, $vmodel, $vreg, $distanceMiles, $quote['total'], 50.00, 'unpaid', 'not_due',
                    $serviceId > 0 ? $serviceId : null, $address, $postcode, $pdate, $ptime, mb_substr($notes, 0, 2000), $status, client_ip()
                ]);
            }
            $bookingId = (int)db()->lastInsertId();
            create_booking_deposit_invoice($bookingId);
            redirect_with(url('/admin/bookings.php?view=' . $bookingId), ['flash' => 'Booking created. Reference ' . $reference . '. Deposit invoice created.']);
        }

        foreach (['name','email','phone','vehicle_make','vehicle_model','vehicle_reg','distance_miles','address','postcode','preferred_date','preferred_time','notes','status'] as $field) {
            $_SESSION['_flash']['input_' . $field] = $$field;
        }
        $_SESSION['_flash']['input_service_id'] = $serviceId;
        $_SESSION['_flash']['input_vehicle_id'] = $vehicleId;
        $_SESSION['_flash']['errors'] = $errors;
        redirect(url('/admin/bookings.php?new=1'));
    }

    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, $statusOptions, true)) {
        $upd = db()->prepare('UPDATE bookings SET status=? WHERE id=?');
        $upd->execute([$status, $id]);
        redirect_with(url('/admin/bookings.php?view=' . $id), ['flash' => 'Booking updated.']);
    }
    redirect(url('/admin/bookings.php'));
}

$viewId = (int)($_GET['view'] ?? 0);
$newBooking = isset($_GET['new']) && (string)$_GET['new'] !== '0';
$flash = flash('flash');
$errors = flash('errors', []);

// ---- New booking form ----
if ($newBooking) {
    $admin_title = 'New booking';
    $active_admin = 'bookings';
    $admin_actions_html = '<a class="btn btn-outline btn-sm" href="' . e(url('/admin/bookings.php')) . '">← All bookings</a>';
    $form = [];
    foreach (['name','email','phone','vehicle_make','vehicle_model','vehicle_reg','distance_miles','address','postcode','preferred_date','preferred_time','notes'] as $field) {
        $form[$field] = old($field);
    }
    $formServiceId = (int)old('service_id', '0');
    $formVehicleId = (int)old('vehicle_id', '0');
    $formStatus = old('status', 'new');
    require APP_DIR . '/views/layout/admin_header.php';
    ?>
    <?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error">Please correct the highlighted fields below.</div><?php endif; ?>
    <div class="admin-2col">
        <section class="panel">
            <div class="panel-head"><h2>Enter booking details</h2><span class="muted">Internal booking</span></div>
            <p class="muted">Use this form for bookings taken by phone, WhatsApp or another channel. It will appear in the CRM immediately.</p>
            <form method="post" class="form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <h3>Customer</h3>
                <div class="form-row">
                    <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><label for="name">Full name *</label><input type="text" id="name" name="name" value="<?= $form['name'] ?>" maxlength="120" required><?= field_error($errors, 'name') ?></div>
                    <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>"><label for="phone">Phone *</label><input type="tel" id="phone" name="phone" value="<?= $form['phone'] ?>" required><?= field_error($errors, 'phone') ?></div>
                </div>
                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>"><label for="email">Email <span class="muted">(optional)</span></label><input type="email" id="email" name="email" value="<?= $form['email'] ?>"><?= field_error($errors, 'email') ?></div>
                <h3>Vehicle</h3>
                <div class="field<?= isset($errors['vehicle_reg']) ? ' has-error' : '' ?>"><label for="vehicle_reg">Vehicle registration *</label><input type="text" id="vehicle_reg" name="vehicle_reg" value="<?= $form['vehicle_reg'] ?>" maxlength="20" required><?= field_error($errors, 'vehicle_reg') ?></div>
                <div class="form-row">
                    <div class="field"><label for="vehicle_make">Make <span class="muted">(optional)</span></label><input type="text" id="vehicle_make" name="vehicle_make" value="<?= $form['vehicle_make'] ?>" maxlength="80"></div>
                    <div class="field"><label for="vehicle_model">Model <span class="muted">(optional)</span></label><input type="text" id="vehicle_model" name="vehicle_model" value="<?= $form['vehicle_model'] ?>" maxlength="120"></div>
                </div>
                <h3>Recovery details</h3>
                <div class="field"><label for="service_id">Service</label><select id="service_id" name="service_id"><option value="0">General recovery</option><?php foreach ($services as $service): ?><option value="<?= (int)$service['id'] ?>" <?= $formServiceId === (int)$service['id'] ? 'selected' : '' ?>><?= e($service['title']) ?><?= (float)$service['price_from'] > 0 ? ' — from ' . e(format_price(booking_base_price_for_service((string)$service['slug'], (float)$service['price_from']))) : '' ?></option><?php endforeach; ?></select></div>
                <div class="field<?= isset($errors['distance_miles']) ? ' has-error' : '' ?>"><label for="distance_miles">Estimated recovery miles <span class="muted">(optional)</span></label><input type="number" id="distance_miles" name="distance_miles" value="<?= $form['distance_miles'] ?>" min="0" max="10000" step="0.1" placeholder="e.g. 18"><?= field_error($errors, 'distance_miles') ?></div>
                <div class="field<?= isset($errors['address']) ? ' has-error' : '' ?>"><label for="address">Pickup address / breakdown location *</label><input type="text" id="address" name="address" value="<?= $form['address'] ?>" required><?= field_error($errors, 'address') ?></div>
                <div class="field<?= isset($errors['postcode']) ? ' has-error' : '' ?>"><label for="postcode">Postcode *</label><input type="text" id="postcode" name="postcode" value="<?= $form['postcode'] ?>" maxlength="12" required><?= field_error($errors, 'postcode') ?></div>
                <div class="form-row">
                    <div class="field<?= isset($errors['preferred_date']) ? ' has-error' : '' ?>"><label for="preferred_date">Preferred date *</label><input type="date" id="preferred_date" name="preferred_date" value="<?= $form['preferred_date'] ?>" min="<?= date('Y-m-d') ?>" required><?= field_error($errors, 'preferred_date') ?></div>
                    <div class="field<?= isset($errors['preferred_time']) ? ' has-error' : '' ?>"><label for="preferred_time">Preferred time *</label><select id="preferred_time" name="preferred_time"><option value="">Select…</option><?php foreach (['Morning (7:30–12:00)','Afternoon (12:00–18:00)','Anytime'] as $time): ?><option <?= $form['preferred_time'] === e($time) ? 'selected' : '' ?>><?= e($time) ?></option><?php endforeach; ?></select><?= field_error($errors, 'preferred_time') ?></div>
                </div>
                <?php if ($crmReady): ?>
                <div class="field<?= isset($errors['vehicle_id']) ? ' has-error' : '' ?>"><label for="vehicle_id">Assign recovery vehicle <span class="muted">(optional)</span></label><select id="vehicle_id" name="vehicle_id"><option value="0">Unassigned</option><?php foreach ($vehicles as $vehicle): ?><option value="<?= (int)$vehicle['id'] ?>" <?= $formVehicleId === (int)$vehicle['id'] ? 'selected' : '' ?>><?= e($vehicle['name']) ?><?= $vehicle['registration'] ? ' — ' . e($vehicle['registration']) : '' ?></option><?php endforeach; ?></select><?= field_error($errors, 'vehicle_id') ?></div>
                <?php endif; ?>
                <div class="field"><label for="status">Status</label><select id="status" name="status"><?php foreach ($statusOptions as $status): ?><option value="<?= e($status) ?>" <?= $formStatus === $status ? 'selected' : '' ?>><?= e(enquiry_status_label($status)) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="notes">Notes <span class="muted">(optional)</span></label><textarea id="notes" name="notes" rows="4" maxlength="2000"><?= $form['notes'] ?></textarea></div>
                <button type="submit" class="btn btn-primary">Create booking</button>
            </form>
        </section>
        <aside class="panel">
            <h2>What happens next?</h2>
            <p class="muted">The booking is stored in the same database as website enquiries. You can then open it from CRM, assign a vehicle, and move it through the dispatch workflow.</p>
            <ul class="steps"><li>Save the customer and recovery details.</li><li>Open the booking to review or update it.</li><li>Use CRM to accept, dispatch and complete the job.</li></ul>
        </aside>
    </div>
    <?php
    require APP_DIR . '/views/layout/admin_footer.php';
    exit;
}

// ---- Detail view ----
if ($viewId) {
    ensure_payment_schema();
    $stmt = db()->prepare('SELECT b.*, s.title AS service_title FROM bookings b LEFT JOIN services s ON s.id=b.service_id WHERE b.id=? LIMIT 1');
    $stmt->execute([$viewId]);
    $b = $stmt->fetch();
    if (!$b) {
        $admin_title = 'Booking not found';
        $active_admin = 'bookings';
        require APP_DIR . '/views/layout/admin_header.php';
        echo '<p>Booking not found. <a href="' . e(url('/admin/bookings.php')) . '">← Back to bookings</a></p>';
        require APP_DIR . '/views/layout/admin_footer.php';
        exit;
    }
    $invoiceStmt = db()->prepare('SELECT * FROM invoices WHERE booking_id=? ORDER BY created_at DESC');
    $invoiceStmt->execute([$viewId]);
    $bookingInvoices = $invoiceStmt->fetchAll();
    $admin_title = 'Booking ' . $b['reference'];
    $active_admin = 'bookings';
    $admin_actions_html = '<a class="btn btn-outline btn-sm" href="' . e(url('/admin/bookings.php')) . '">← All bookings</a>';
    require APP_DIR . '/views/layout/admin_header.php';
    if ($flash) echo '<div class="alert alert-success">' . e($flash) . '</div>';
    ?>
    <div class="detail-grid">
        <div class="panel">
            <h3>Customer</h3>
            <dl class="kv"><dt>Name</dt><dd><?= e($b['name']) ?></dd>
                <dt>Phone</dt><dd><a href="tel:<?= e($b['phone']) ?>"><?= e($b['phone']) ?></a></dd>
                <dt>Email</dt><dd><?= $b['email'] ? '<a href="mailto:' . e($b['email']) . '">' . e($b['email']) . '</a>' : '—' ?></dd>
            </dl>
            <h3>Vehicle</h3>
            <dl class="kv"><dt>Make</dt><dd><?= e($b['vehicle_make']) ?></dd>
                <dt>Model</dt><dd><?= e($b['vehicle_model'] ?: '—') ?></dd>
                <dt>Reg</dt><dd><?= e($b['vehicle_reg'] ?: '—') ?></dd>
            </dl>
        </div>
        <div class="panel">
            <h3>Appointment</h3>
            <dl class="kv"><dt>Service</dt><dd><?= e($b['service_title'] ?: '—') ?></dd>
                <dt>Address</dt><dd><?= e($b['address']) ?>, <?= e($b['postcode']) ?></dd>
                <dt>Preferred date</dt><dd><?= e($b['preferred_date'] ? date('j M Y', strtotime($b['preferred_date'])) : '—') ?></dd>
                <dt>Preferred time</dt><dd><?= e($b['preferred_time']) ?></dd>
                <dt>Submitted</dt><dd><?= e(date('j M Y H:i', strtotime($b['created_at']))) ?></dd>
            </dl>
            <?php if ($b['notes']): ?><h3>Notes</h3><p><?= nl2br(e($b['notes'])) ?></p><?php endif; ?>
            <h3>Pricing &amp; payments</h3>
            <dl class="kv"><dt>Estimated miles</dt><dd><?= e((string)($b['distance_miles'] ?? '0')) ?></dd>
                <dt>Estimated total</dt><dd><?= e(format_price($b['quoted_total'] ?? 0)) ?></dd>
                <dt>Deposit</dt><dd><?= e(ucfirst((string)($b['deposit_status'] ?? 'unpaid'))) ?></dd>
                <dt>Balance</dt><dd><?= e(ucfirst((string)($b['balance_status'] ?? 'not due'))) ?></dd>
            </dl>
            <?php if ($bookingInvoices): ?><ul class="footer-links invoice-list"><?php foreach ($bookingInvoices as $invoice): ?><li><a href="<?= e(invoice_public_url($invoice)) ?>" target="_blank" rel="noopener"><?= e($invoice['invoice_number']) ?> · <?= e(format_price($invoice['amount_due'])) ?> · <?= e(invoice_status_label($invoice['status'])) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
            <p><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/invoices.php?booking=' . (int)$b['id'])) ?>">Create / manage invoices</a></p>
        </div>
        <div class="panel">
            <h3>Update status</h3>
            <form method="post" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <div class="field"><label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($statusOptions as $st): ?>
                            <option value="<?= e($st) ?>" <?= $b['status']===$st?'selected':'' ?>><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Save</button>
            </form>
        </div>
    </div>
    <?php
    require APP_DIR . '/views/layout/admin_footer.php';
    exit;
}

// ---- List view ----
$statusFilter = $_GET['status'] ?? '';
$where = ''; $params = [];
if (in_array($statusFilter, $statusOptions, true)) { $where = 'WHERE b.status=?'; $params[] = $statusFilter; }
$stmt = db()->prepare("SELECT b.*, s.title AS service_title FROM bookings b LEFT JOIN services s ON s.id=b.service_id $where ORDER BY b.created_at DESC");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$admin_title = 'Bookings';
$active_admin = 'bookings';
$admin_actions_html = '<a class="btn btn-primary btn-sm" href="' . e(url('/admin/bookings.php?new=1')) . '"><span class="mw-icon">add</span> New booking</a>';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<div class="filter-bar">
    <a class="chip <?= $statusFilter===''?'active':'' ?>" href="<?= e(url('/admin/bookings.php')) ?>">All</a>
    <?php foreach ($statusOptions as $st): ?>
        <a class="chip <?= $statusFilter===$st?'active':'' ?>" href="<?= e(url('/admin/bookings.php?status=' . $st)) ?>"><?= e(ucfirst($st)) ?></a>
    <?php endforeach; ?>
</div>
<?php if (!$bookings): ?>
    <p class="muted">No bookings to show.</p>
<?php else: ?>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Ref</th><th>Name</th><th>Phone</th><th>Service</th><th>Date</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $b): ?>
            <tr>
                <td><a href="<?= e(url('/admin/bookings.php?view=' . (int)$b['id'])) ?>"><?= e($b['reference']) ?></a></td>
                <td><?= e($b['name']) ?></td>
                <td><a href="tel:<?= e($b['phone']) ?>"><?= e($b['phone']) ?></a></td>
                <td><?= e($b['service_title'] ?: '—') ?></td>
                <td><?= e($b['preferred_date'] ? date('j M y', strtotime($b['preferred_date'])) : '—') ?></td>
                <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                <td><a href="<?= e(url('/admin/bookings.php?view=' . (int)$b['id'])) ?>">View →</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php require APP_DIR . '/views/layout/admin_footer.php';
