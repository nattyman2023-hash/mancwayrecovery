<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$counts = [
    'new_bookings'   => (int) db()->query("SELECT COUNT(*) FROM bookings WHERE status='new'")->fetchColumn(),
    'unread_msgs'    => (int) db()->query("SELECT COUNT(*) FROM messages WHERE is_read=0")->fetchColumn(),
    'pending_reviews'=> (int) db()->query("SELECT COUNT(*) FROM testimonials WHERE is_approved=0")->fetchColumn(),
    'total_services' => (int) db()->query("SELECT COUNT(*) FROM services")->fetchColumn(),
];
$crmReady = false;
$crmStats = ['new' => $counts['new_bookings'], 'active' => 0, 'available' => 0, 'fleet' => 0];
try {
    db()->query('SELECT vehicle_id FROM bookings LIMIT 1');
    db()->query('SELECT 1 FROM recovery_vehicles LIMIT 1');
    $crmReady = true;
    $crmStats['active'] = (int) db()->query("SELECT COUNT(*) FROM bookings WHERE status IN ('accepted','dispatched')")->fetchColumn();
    $crmStats['fleet'] = (int) db()->query('SELECT COUNT(*) FROM recovery_vehicles WHERE is_active=1')->fetchColumn();
    $crmStats['available'] = (int) db()->query("SELECT COUNT(*) FROM recovery_vehicles WHERE is_active=1 AND status='available'")->fetchColumn();
} catch (Throwable $e) {
    $crmReady = false;
}
$recent = db()->query("SELECT b.*, s.title AS service_title FROM bookings b LEFT JOIN services s ON s.id=b.service_id ORDER BY b.created_at DESC LIMIT 5")->fetchAll();
$flash = flash('flash');
$contactError = flash('contact_error');

$admin_title = 'Dashboard';
$active_admin = 'dashboard';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($contactError): ?><div class="alert alert-error"><?= e($contactError) ?></div><?php endif; ?>
<?php if ($crmReady): ?>
<section class="crm-dashboard-banner">
    <div>
        <p class="eyebrow-caps">Dispatch Center</p>
        <h2><?= $crmStats['new'] ?> new <?= $crmStats['new'] === 1 ? 'enquiry' : 'enquiries' ?> need attention</h2>
        <p class="muted">Manage the recovery workflow, assign a unit, and keep live jobs moving.</p>
    </div>
    <div class="crm-dashboard-metrics">
        <span><strong><?= $crmStats['active'] ?></strong> live job<?= $crmStats['active'] === 1 ? '' : 's' ?></span>
        <span><strong><?= $crmStats['available'] ?>/<?= $crmStats['fleet'] ?></strong> units available</span>
        <a class="btn btn-amber btn-sm" href="<?= e(url('/admin/crm.php')) ?>">Open CRM</a>
    </div>
</section>
<?php else: ?>
<section class="crm-dashboard-banner crm-dashboard-banner-setup">
    <div>
        <p class="eyebrow-caps">Dispatch Center</p>
        <h2>CRM migration still needs importing</h2>
        <p class="muted">Bookings are being saved, but vehicle assignment and the dispatch workflow activate after <code>database/migration_crm.sql</code> is imported.</p>
    </div>
    <div class="crm-dashboard-metrics"><a class="btn btn-amber btn-sm" href="<?= e(url('/admin/crm.php')) ?>">View CRM setup</a></div>
</section>
<?php endif; ?>
<div class="stat-grid grid grid-4">
    <a class="stat-card stat-link" href="<?= e(url('/admin/bookings.php')) ?>">
        <strong><?= $counts['new_bookings'] ?></strong><span>New bookings</span>
    </a>
    <a class="stat-card stat-link" href="<?= e(url('/admin/messages.php')) ?>">
        <strong><?= $counts['unread_msgs'] ?></strong><span>Unread messages</span>
    </a>
    <a class="stat-card stat-link" href="<?= e(url('/admin/testimonials.php')) ?>">
        <strong><?= $counts['pending_reviews'] ?></strong><span>Reviews to approve</span>
    </a>
    <a class="stat-card stat-link" href="<?= e(url('/admin/services.php')) ?>">
        <strong><?= $counts['total_services'] ?></strong><span>Services listed</span>
    </a>
</div>

<section class="panel" id="contact-details">
    <div class="panel-head">
        <h2>Quick contact details</h2>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/settings.php#contact')) ?>">Full settings</a>
    </div>
    <p class="muted">These details appear on the public website, booking form, contact page and email notifications.</p>
    <form method="post" action="<?= e(url('/admin/settings.php')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="contact">
        <input type="hidden" name="return_to" value="dashboard">
        <div class="form-row">
            <div class="field"><label for="quick-phone">Phone</label><input type="text" id="quick-phone" name="phone" value="<?= e(setting('phone')) ?>" placeholder="0161 000 0000" required></div>
            <div class="field"><label for="quick-email">Public email</label><input type="email" id="quick-email" name="email" value="<?= e(setting('email')) ?>" required></div>
        </div>
        <div class="field"><label for="quick-address">Business address</label><input type="text" id="quick-address" name="address" value="<?= e(setting('address')) ?>" required></div>
        <input type="hidden" name="admin_email" value="<?= e(setting('admin_email')) ?>">
        <button type="submit" class="btn btn-primary">Save contact details</button>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Recent bookings</h2>
        <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/bookings.php')) ?>">View all →</a>
    </div>
    <?php if (!$recent): ?>
        <p class="muted">No bookings yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Ref</th><th>Name</th><th>Service</th><th>Date</th><th>Created</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $b): ?>
                <tr>
                    <td><a href="<?= e(url('/admin/bookings.php?view=' . (int)$b['id'])) ?>"><?= e($b['reference']) ?></a></td>
                    <td><?= e($b['name']) ?><br><small><?= e($b['phone']) ?></small></td>
                    <td><?= e($b['service_title'] ?: '—') ?></td>
                    <td><?= e($b['preferred_date'] ? date('j M Y', strtotime($b['preferred_date'])) : '—') ?><br><small><?= e($b['preferred_time']) ?></small></td>
                    <td><?= e(date('j M Y', strtotime($b['created_at']))) ?></td>
                    <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php require APP_DIR . '/views/layout/admin_footer.php'; ?>
