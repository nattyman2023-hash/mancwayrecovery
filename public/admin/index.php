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
$recent = db()->query("SELECT b.*, s.title AS service_title FROM bookings b LEFT JOIN services s ON s.id=b.service_id ORDER BY b.created_at DESC LIMIT 5")->fetchAll();

$admin_title = 'Dashboard';
$active_admin = 'dashboard';
require APP_DIR . '/views/layout/admin_header.php';
?>
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
