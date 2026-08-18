<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

// Update status
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['new','confirmed','complete','cancelled'], true)) {
        $upd = db()->prepare('UPDATE bookings SET status=? WHERE id=?');
        $upd->execute([$status, $id]);
        redirect_with(url('/admin/bookings.php?view=' . $id), ['flash' => 'Booking updated.']);
    }
    redirect(url('/admin/bookings.php'));
}

$viewId = (int)($_GET['view'] ?? 0);
$flash = flash('flash');

// ---- Detail view ----
if ($viewId) {
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
        </div>
        <div class="panel">
            <h3>Update status</h3>
            <form method="post" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <div class="field"><label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach (['new','confirmed','complete','cancelled'] as $st): ?>
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
if (in_array($statusFilter, ['new','confirmed','complete','cancelled'], true)) { $where = 'WHERE b.status=?'; $params[] = $statusFilter; }
$stmt = db()->prepare("SELECT b.*, s.title AS service_title FROM bookings b LEFT JOIN services s ON s.id=b.service_id $where ORDER BY b.created_at DESC");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$admin_title = 'Bookings';
$active_admin = 'bookings';
$admin_actions_html = '';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<div class="filter-bar">
    <a class="chip <?= $statusFilter===''?'active':'' ?>" href="<?= e(url('/admin/bookings.php')) ?>">All</a>
    <?php foreach (['new','confirmed','complete','cancelled'] as $st): ?>
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
