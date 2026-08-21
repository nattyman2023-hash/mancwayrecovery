<?php
declare(strict_types=1);
/**
 * MancWay Recovery — CRM: Enquiries dashboard.
 *
 * Shows enquiries (booking-form submissions) in a stitch-style operations
 * dashboard. Built around ONE recovery vehicle today, but the vehicle_id
 * column + recovery_vehicles table let it scale to a fleet later.
 */
require __DIR__ . '/../../app/bootstrap.php';
require_admin();
require_once APP_DIR . '/crm_migration.php';

$flash = flash('flash');
$migrationError = flash('crm_migration_error');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'install_crm_migration') {
        try {
            install_crm_migration(db());
            redirect_with(url('/admin/crm.php'), ['flash' => 'CRM migration installed. Your enquiries and settings were preserved.']);
        } catch (Throwable $e) {
            error_log('CRM migration failed: ' . $e->getMessage());
            redirect_with(url('/admin/crm.php'), ['crm_migration_error' => 'The CRM migration could not be installed. Check that schema.sql has been imported first, then try again.']);
        }
    }
}

// Guard: the CRM migration adds recovery_vehicles + bookings.vehicle_id.
$crmReady = false;
try {
    db()->query('SELECT 1 FROM recovery_vehicles LIMIT 1');
    db()->query('SELECT vehicle_id FROM bookings LIMIT 1');
    $crmReady = true;
} catch (Throwable $e) {
    $crmReady = false;
}

if (!$crmReady) {
    $admin_title  = 'CRM — Enquiries';
    $active_admin = 'crm';
    require APP_DIR . '/views/layout/admin_header.php';
    ?>
    <?php if ($migrationError): ?><div class="alert alert-error"><?= e($migrationError) ?></div><?php endif; ?>
    <div class="alert alert-error">
        <strong>CRM not set up yet.</strong> Install the CRM tables and fields
        below to enable enquiries, vehicle assignment and dispatch workflow.
    </div>
    <p class="muted">The migration adds the <code>recovery_vehicles</code> table
       and the <code>bookings.vehicle_id</code> + <code>status</code> columns
       the CRM relies on. It is safe to re-run and does not delete bookings,
       admin accounts or settings.</p>
    <form method="post" class="form" style="margin-top:20px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="install_crm_migration">
        <button type="submit" class="btn btn-primary">Install CRM now</button>
    </form>
    <p class="muted" style="margin-top:12px">If your hosting account blocks schema changes from the site, you can still import <code>database/migration_crm.sql</code> in phpMyAdmin after the base schema.</p>
    <?php
    require APP_DIR . '/views/layout/admin_footer.php';
    exit;
}

/* --- Filters --- */
$statusFilter  = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$vehicleFilter = (int)($_GET['vehicle'] ?? 0);
$search        = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$search        = function_exists('mb_substr') ? mb_substr($search, 0, 100) : substr($search, 0, 100);
$validStatuses = enquiry_statuses();

$where  = [];
$params = [];
if (in_array($statusFilter, $validStatuses, true)) { $where[] = 'b.status = ?'; $params[] = $statusFilter; }
if ($vehicleFilter > 0) { $where[] = 'b.vehicle_id = ?'; $params[] = $vehicleFilter; }
if ($search !== '') {
    $where[] = '(b.reference LIKE ? OR b.name LIKE ? OR b.email LIKE ? OR b.phone LIKE ?
                 OR b.vehicle_reg LIKE ? OR b.vehicle_make LIKE ? OR b.vehicle_model LIKE ?
                 OR b.address LIKE ? OR b.postcode LIKE ?)';
    $needle = '%' . $search . '%';
    $params = array_merge($params, array_fill(0, 9, $needle));
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$searchSuffix = $search !== '' ? '&q=' . rawurlencode($search) : '';

$sql = "SELECT b.*, s.title AS service_title, v.name AS vehicle_name
        FROM bookings b
        LEFT JOIN services s ON s.id = b.service_id
        LEFT JOIN recovery_vehicles v ON v.id = b.vehicle_id
        $whereSql
        ORDER BY FIELD(b.status,'new','accepted','dispatched','complete','cancelled'), b.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$enquiries = $stmt->fetchAll();

/* --- Stats (bento) + counts per status + fleet summary --- */
$countBy = function (string $status) {
    $st = db()->prepare("SELECT COUNT(*) FROM bookings WHERE status = ?");
    $st->execute([$status]);
    return (int) $st->fetchColumn();
};
$todayCount = (int) db()->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = CURDATE()")->fetchColumn();

$vehicles = db()->query('SELECT * FROM recovery_vehicles WHERE is_active = 1 ORDER BY id')->fetchAll();
$vehicleCounts = [];
foreach ($vehicles as $v) {
    $st = db()->prepare("SELECT COUNT(*) FROM bookings WHERE vehicle_id = ? AND status IN ('accepted','dispatched')");
    $st->execute([$v['id']]);
    $vehicleCounts[$v['id']] = (int) $st->fetchColumn();
}
$primaryVehicle = $vehicles[0] ?? null;

$admin_title  = 'CRM — Enquiries';
$active_admin = 'crm';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>

<p class="eyebrow-caps" style="margin:0 0 6px">Dispatch Center · Recovery only</p>
<p class="muted" style="margin:0 0 20px;max-width:70ch">
    Every enquiry submitted through the site lands here. Accept it, assign it to a
    recovery vehicle, mark it dispatched when the driver is en route, then completed
    once the vehicle is recovered. Currently running a single recovery unit — add
    more under <a href="<?= e(url('/admin/vehicles.php')) ?>">Vehicles</a> as the fleet grows.
</p>

<div class="crm-stats">
    <a class="crm-stat is-new" href="<?= e(url('/admin/crm.php?status=new')) ?>">
        <span class="crm-stat-ico"><span class="mw-icon">inbox</span></span>
        <strong><?= $countBy('new') ?></strong><span>New enquiries</span>
    </a>
    <a class="crm-stat is-accepted" href="<?= e(url('/admin/crm.php?status=accepted')) ?>">
        <span class="crm-stat-ico"><span class="mw-icon">task_alt</span></span>
        <strong><?= $countBy('accepted') ?></strong><span>Accepted</span>
    </a>
    <a class="crm-stat is-dispatched" href="<?= e(url('/admin/crm.php?status=dispatched')) ?>">
        <span class="crm-stat-ico"><span class="mw-icon">local_shipping</span></span>
        <strong><?= $countBy('dispatched') ?></strong><span>Dispatched</span>
    </a>
    <div class="crm-stat is-fleet">
        <span class="crm-stat-ico"><span class="mw-icon">precision_manufacturing</span></span>
        <strong><?= count($vehicles) ?></strong><span>Recovery unit<?= count($vehicles) === 1 ? '' : 's' ?></span>
        <?php if ($primaryVehicle): ?>
            <span class="crm-fleet-pill <?= e(vehicle_pill_class($primaryVehicle['status'])) ?>">
                <span class="dot"></span><?= e($primaryVehicle['name']) ?> · <?= e(vehicle_status_label($primaryVehicle['status'])) ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<section class="panel">
    <form class="crm-search crm-search-wide" method="get" action="<?= e(url('/admin/crm.php')) ?>" role="search">
        <span class="mw-icon" aria-hidden="true">search</span>
        <label class="sr-only" for="crm-search">Search enquiries</label>
        <input id="crm-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Search reference, customer, phone, or registration" autocomplete="off">
        <?php if (in_array($statusFilter, $validStatuses, true)): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
        <?php if ($vehicleFilter > 0): ?><input type="hidden" name="vehicle" value="<?= $vehicleFilter ?>"><?php endif; ?>
        <?php if ($search !== ''): ?><a class="crm-search-clear" href="<?= e(url('/admin/crm.php')) ?>">Clear</a><?php endif; ?>
        <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>
    <div class="panel-head">
        <h2>Live enquiries <?= $todayCount ? '<span class="muted" style="font-size:.8rem;font-weight:600">· ' . (int)$todayCount . ' today</span>' : '' ?></h2>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/bookings.php')) ?>">Legacy bookings view →</a>
    </div>

    <div class="filter-bar">
        <a class="chip <?= $statusFilter === '' && $vehicleFilter === 0 ? 'active' : '' ?>" href="<?= e(url('/admin/crm.php' . ($search !== '' ? '?q=' . rawurlencode($search) : ''))) ?>">All</a>
        <?php foreach ($validStatuses as $st): ?>
            <a class="chip <?= $statusFilter === $st && $vehicleFilter === 0 ? 'active' : '' ?>" href="<?= e(url('/admin/crm.php?status=' . $st . $searchSuffix)) ?>">
                <?= e(enquiry_status_label($st)) ?><span class="count"><?= $countBy($st) ?></span>
            </a>
        <?php endforeach; ?>
        <?php if (count($vehicles) > 1): ?>
            <span style="flex:1"></span>
            <?php foreach ($vehicles as $v): ?>
                <a class="chip <?= $vehicleFilter === (int)$v['id'] ? 'active' : '' ?>" href="<?= e(url('/admin/crm.php?vehicle=' . (int)$v['id'] . $searchSuffix)) ?>">
                    <?= e($v['name']) ?><span class="count"><?= (int)($vehicleCounts[$v['id']] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!$enquiries): ?>
        <div class="crm-empty">
            <div class="ico"><span class="mw-icon">inbox</span></div>
            <h3><?= $search !== '' ? 'No matching enquiries' : 'No enquiries to show' ?></h3>
            <p><?= $search !== '' ? 'Try a different reference, customer name, phone number, or registration.' : 'When a customer submits a recovery enquiry on the site, it appears here instantly.' ?></p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <th>Ref</th><th>Customer</th><th>Vehicle</th><th>Location</th>
                <th>Service</th><th>Status</th><th>Unit</th><th>Age</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($enquiries as $b): ?>
                <tr>
                    <td><a class="ref" href="<?= e(url('/admin/enquiry.php?view=' . (int)$b['id'])) ?>"><?= e($b['reference']) ?></a></td>
                    <td><strong><?= e($b['name']) ?></strong><br><a href="tel:<?= e($b['phone']) ?>" style="font-weight:600"><?= e($b['phone']) ?></a></td>
                    <td><?php if ($b['vehicle_reg']): ?><span class="reg"><?= e($b['vehicle_reg']) ?></span><?php else: ?><span class="muted"><?= e(trim($b['vehicle_make'] . ' ' . $b['vehicle_model']) ?: '—') ?></span><?php endif; ?></td>
                    <td><?= e($b['postcode'] ?: $b['address']) ?></td>
                    <td><?= e($b['service_title'] ?: 'General') ?></td>
                    <td><?= enquiry_status_pill($b['status']) ?></td>
                    <td><?= e($b['vehicle_name'] ?: '—') ?></td>
                    <td class="age"><?= e(enquiry_age($b['created_at'])) ?></td>
                    <td><a href="<?= e(url('/admin/enquiry.php?view=' . (int)$b['id'])) ?>">Open →</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require APP_DIR . '/views/layout/admin_footer.php';
