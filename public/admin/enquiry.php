<?php
declare(strict_types=1);
/**
 * MancWay Recovery — CRM: Enquiry detail (job-details view).
 *
 * Workflow: New → Accepted → Dispatched → Completed / Cancelled.
 * Assign a recovery vehicle (one today, more later) and update status.
 */
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

// Guard: needs the CRM migration columns.
$crmReady = false;
try {
    db()->query('SELECT vehicle_id, updated_at FROM bookings LIMIT 1');
    db()->query('SELECT 1 FROM recovery_vehicles LIMIT 1');
    $crmReady = true;
} catch (Throwable $e) {
    $crmReady = false;
}
if (!$crmReady) {
    redirect(url('/admin/crm.php'));
}
ensure_payment_schema();

$viewId = (int)($_GET['view'] ?? 0);
$flash  = flash('flash');

/* --- Handle status / vehicle update --- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $vehId  = (int)($_POST['vehicle_id'] ?? 0);
    $action = $_POST['action'] ?? 'save';

    if (!$id || $id !== $viewId) {
        redirect(url('/admin/crm.php'));
    }

    if ($action === 'quick' && in_array($status, enquiry_statuses(), true)) {
        // Quick status advance from the buttons
        $upd = db()->prepare('UPDATE bookings SET status=? WHERE id=?');
        $upd->execute([$status, $id]);
        redirect_with(url('/admin/enquiry.php?view=' . $id), ['flash' => 'Marked ' . enquiry_status_label($status) . '.']);
    }

    if (in_array($status, enquiry_statuses(), true)) {
        // Only active fleet units can be assigned from the CRM. Validate the
        // submitted id server-side as well as filtering it in the form.
        $veh = null;
        if ($vehId > 0) {
            $vehicleCheck = db()->prepare('SELECT id FROM recovery_vehicles WHERE id=? AND is_active=1 LIMIT 1');
            $vehicleCheck->execute([$vehId]);
            $veh = $vehicleCheck->fetchColumn();
            if ($veh === false) $veh = null;
        }
        $upd = db()->prepare('UPDATE bookings SET status=?, vehicle_id=? WHERE id=?');
        $upd->execute([$status, $veh, $id]);
        redirect_with(url('/admin/enquiry.php?view=' . $id), ['flash' => 'Enquiry updated.']);
    }
    redirect(url('/admin/enquiry.php?view=' . $id));
}

/* --- Fetch enquiry + vehicles --- */
$stmt = db()->prepare('SELECT b.*, s.title AS service_title, s.slug AS service_slug, v.name AS vehicle_name
                       FROM bookings b
                       LEFT JOIN services s ON s.id = b.service_id
                       LEFT JOIN recovery_vehicles v ON v.id = b.vehicle_id
                       WHERE b.id = ? LIMIT 1');
$stmt->execute([$viewId]);
$b = $stmt->fetch();

if (!$b) {
    $admin_title  = 'Enquiry not found';
    $active_admin = 'crm';
    require APP_DIR . '/views/layout/admin_header.php';
    echo '<p>Enquiry not found. <a href="' . e(url('/admin/crm.php')) . '">← Back to CRM</a></p>';
    require APP_DIR . '/views/layout/admin_footer.php';
    exit;
}

$vehicles = db()->query('SELECT * FROM recovery_vehicles WHERE is_active = 1 ORDER BY id')->fetchAll();

$admin_title  = 'Enquiry ' . $b['reference'];
$active_admin = 'crm';
$admin_actions_html = '<a class="btn btn-outline btn-sm" href="' . e(url('/admin/crm.php')) . '">← All enquiries</a>';
require APP_DIR . '/views/layout/admin_header.php';
if ($flash) echo '<div class="alert alert-success">' . e($flash) . '</div>';
?>

<div class="crm-enquiry-hero">
    <div>
        <p class="eyebrow-caps">Recovery enquiry</p>
        <h1><?= e($b['reference']) ?></h1>
        <div class="hero-meta">
            <span>Submitted <?= e(date('j M Y, H:i', strtotime($b['created_at']))) ?></span>
            <span>· <?= e(enquiry_age($b['created_at'])) ?></span>
            <?php if ($b['updated_at'] && $b['updated_at'] !== $b['created_at']): ?>
                <span>· updated <?= e(date('j M Y, H:i', strtotime($b['updated_at']))) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div><?= enquiry_status_pill($b['status']) ?></div>
</div>

<div class="detail-grid">
    <!-- Customer -->
    <div class="panel">
        <h3><span class="mw-icon" style="vertical-align:-3px;margin-right:6px">person</span>Customer</h3>
        <dl class="kv">
            <dt>Name</dt><dd><?= e($b['name']) ?></dd>
            <dt>Phone</dt><dd><a href="tel:<?= e($b['phone']) ?>"><?= e($b['phone']) ?></a></dd>
            <dt>Email</dt><dd><?= $b['email'] ? '<a href="mailto:' . e($b['email']) . '">' . e($b['email']) . '</a>' : '—' ?></dd>
        </dl>
        <hr class="block-hr">
        <h3><span class="mw-icon" style="vertical-align:-3px;margin-right:6px">directions_car</span>Vehicle</h3>
        <dl class="kv">
            <dt>Make / model</dt><dd><?= e(trim($b['vehicle_make'] . ' ' . $b['vehicle_model']) ?: '—') ?></dd>
            <dt>Reg</dt><dd class="mono"><?= e($b['vehicle_reg'] ?: '—') ?></dd>
        </dl>
    </div>

    <div class="panel">
        <h3><span class="mw-icon" style="vertical-align:-3px;margin-right:6px">fact_check</span>DVLA vehicle details</h3>
        <dl class="kv">
            <dt>Year</dt><dd><?= e($b['vehicle_year'] ?: '—') ?></dd>
            <dt>Colour</dt><dd><?= e($b['vehicle_colour'] ?: '—') ?></dd>
            <dt>Fuel</dt><dd><?= e($b['vehicle_fuel'] ?: '—') ?></dd>
            <dt>MOT</dt><dd><?= e($b['vehicle_mot'] ?: '—') ?></dd>
        </dl>
    </div>

    <!-- Location / appointment -->
    <div class="panel">
        <h3><span class="mw-icon" style="vertical-align:-3px;margin-right:6px">location_on</span>Pickup location</h3>
        <dl class="kv">
            <dt>Address</dt><dd><?= e($b['address'] ?: '—') ?></dd>
            <dt>Postcode</dt><dd class="mono"><?= e($b['postcode'] ?: '—') ?></dd>
        </dl>
        <hr class="block-hr">
        <h3><span class="mw-icon" style="vertical-align:-3px;margin-right:6px">event</span>Preferred appointment</h3>
        <dl class="kv">
            <dt>Date</dt><dd><?= e($b['preferred_date'] ? date('j M Y', strtotime($b['preferred_date'])) : '—') ?></dd>
            <dt>Time</dt><dd><?= e($b['preferred_time'] ?: '—') ?></dd>
            <dt>Service</dt><dd><?= e($b['service_title'] ?: 'General enquiry') ?></dd>
        </dl>
        <?php if ($b['notes']): ?>
            <hr class="block-hr">
            <h3><span class="mw-icon" style="vertical-align:-3px;margin-right:6px">notes</span>Customer notes</h3>
            <p style="margin:0"><?= nl2br(e($b['notes'])) ?></p>
        <?php endif; ?>
    </div>

    <!-- Workflow: assign vehicle + status -->
    <div class="panel crm-workflow">
        <h3><span class="mw-icon" style="vertical-align:-3px;margin-right:6px">tune</span>Manage enquiry</h3>

        <p class="muted" style="font-size:.86rem;margin-top:0">
            Currently assigned to:
            <strong><?= e($b['vehicle_name'] ?: 'No vehicle assigned') ?></strong>
        </p>

        <!-- Quick actions: advance the workflow in one click -->
        <?php
        $next = [
            'new'        => ['accepted',   'Accept & take job', 'task_alt'],
            'accepted'   => ['dispatched', 'Dispatch unit',     'local_shipping'],
            'dispatched' => ['complete',   'Mark completed',    'check_circle'],
        ];
        ?>
        <?php if (isset($next[$b['status']])): $nx = $next[$b['status']]; ?>
        <div class="crm-quick-actions">
            <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="quick">
                <input type="hidden" name="status" value="<?= e($nx[0]) ?>">
                <button type="submit" class="btn btn-amber">
                    <span class="mw-icon" style="font-size:18px;margin-right:4px"><?= e($nx[2]) ?></span><?= e($nx[1]) ?>
                </button>
            </form>
            <?php if ($b['status'] !== 'cancelled'): ?>
            <form method="post" style="display:inline" data-confirm="Cancel this enquiry?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="quick">
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" class="btn btn-ghost">Cancel enquiry</button>
            </form>
            <?php endif; ?>
        </div>
        <?php elseif ($b['status'] === 'cancelled'): ?>
            <p class="muted" style="margin:.4em 0 0;font-size:.86rem">This enquiry was cancelled.</p>
        <?php else: ?>
            <p class="muted" style="margin:.4em 0 0;font-size:.86rem">This enquiry is complete.</p>
        <?php endif; ?>

        <hr class="block-hr">

        <!-- Full control: pick any status + vehicle -->
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <input type="hidden" name="action" value="save">
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (enquiry_statuses() as $st): ?>
                        <option value="<?= e($st) ?>" <?= $b['status'] === $st ? 'selected' : '' ?>><?= e(enquiry_status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="vehicle_id">Recovery vehicle</label>
                <select id="vehicle_id" name="vehicle_id">
                    <option value="0">— Not assigned —</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= (int)$b['vehicle_id'] === (int)$v['id'] ? 'selected' : '' ?>>
                            <?= e($v['name']) ?> (<?= e(vehicle_status_label($v['status'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
        <p class="muted" style="font-size:.8rem;margin:.6em 0 0">
            Tip: use the quick actions above for the normal flow
            (New → Accepted → Dispatched → Completed). Use this form to jump
            to any status or reassign a different vehicle.
        </p>
    </div>
</div>

<?php require APP_DIR . '/views/layout/admin_footer.php';
