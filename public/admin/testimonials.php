<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$flash = flash('flash');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'approve' && $id) {
        db()->prepare('UPDATE testimonials SET is_approved=1 WHERE id=?')->execute([$id]);
        redirect_with(url('/admin/testimonials.php'), ['flash' => 'Review approved.']);
    } elseif ($action === 'unapprove' && $id) {
        db()->prepare('UPDATE testimonials SET is_approved=0 WHERE id=?')->execute([$id]);
        redirect_with(url('/admin/testimonials.php'), ['flash' => 'Review hidden.']);
    } elseif ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM testimonials WHERE id=?')->execute([$id]);
        redirect_with(url('/admin/testimonials.php'), ['flash' => 'Review deleted.']);
    } elseif ($action === 'save' && $id) {
        $name = trim($_POST['customer_name'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $svc = trim($_POST['service_used'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $loc = trim($_POST['location'] ?? '');
        $sort = (int)($_POST['sort_order'] ?? 0);
        $approved = isset($_POST['is_approved']) ? 1 : 0;
        if ($name === '' || $content === '') redirect_with(url('/admin/testimonials.php?edit=' . $id), ['flash' => 'Name and content are required.']);
        $upd = db()->prepare('UPDATE testimonials SET customer_name=?,rating=?,service_used=?,content=?,location=?,sort_order=?,is_approved=? WHERE id=?');
        $upd->execute([$name,$rating,$svc,$content,$loc,$sort,$approved,$id]);
        redirect_with(url('/admin/testimonials.php'), ['flash' => 'Review updated.']);
    } elseif ($action === 'create') {
        $name = trim($_POST['customer_name'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $svc = trim($_POST['service_used'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $loc = trim($_POST['location'] ?? '');
        if ($name === '' || $content === '') redirect_with(url('/admin/testimonials.php'), ['flash' => 'Name and content are required.']);
        $ins = db()->prepare('INSERT INTO testimonials (customer_name,rating,service_used,content,location,is_approved,sort_order,created_at) VALUES (?,?,?,?,?,1,?,' . 'NOW())');
        $ins->execute([$name,$rating,$svc,$content,$loc,0]);
        redirect_with(url('/admin/testimonials.php'), ['flash' => 'Review added.']);
    }
    redirect(url('/admin/testimonials.php'));
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) { $st = db()->prepare('SELECT * FROM testimonials WHERE id=?'); $st->execute([$editId]); $editing = $st->fetch(); }
$pending = db()->query('SELECT * FROM testimonials WHERE is_approved=0 ORDER BY created_at DESC')->fetchAll();
$approved = db()->query('SELECT * FROM testimonials WHERE is_approved=1 ORDER BY sort_order, created_at DESC')->fetchAll();

$admin_title = 'Testimonials';
$active_admin = 'testimonials';
$admin_actions_html = $editing ? '<a class="btn btn-outline btn-sm" href="' . e(url('/admin/testimonials.php')) . '">← Cancel edit</a>' : '';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($editing): ?>
    <section class="panel">
        <div class="panel-head"><h2>Edit review</h2></div>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
            <div class="form-row">
                <div class="field"><label for="customer_name">Customer name *</label><input type="text" id="customer_name" name="customer_name" value="<?= e($editing['customer_name']) ?>" required></div>
                <div class="field"><label for="rating">Rating (1-5)</label><input type="number" id="rating" name="rating" min="1" max="5" value="<?= (int)$editing['rating'] ?>"></div>
            </div>
            <div class="form-row">
                <div class="field"><label for="service_used">Service used</label><input type="text" id="service_used" name="service_used" value="<?= e($editing['service_used']) ?>"></div>
                <div class="field"><label for="location">Location</label><input type="text" id="location" name="location" value="<?= e($editing['location']) ?>"></div>
            </div>
            <div class="field"><label for="content">Review *</label><textarea id="content" name="content" rows="4" required><?= e($editing['content']) ?></textarea></div>
            <div class="form-row">
                <div class="field"><label for="sort_order">Sort order</label><input type="number" id="sort_order" name="sort_order" value="<?= (int)$editing['sort_order'] ?>"></div>
                <div class="field field-check"><label><input type="checkbox" name="is_approved" <?= (int)$editing['is_approved'] ? 'checked' : '' ?>> Approved</label></div>
            </div>
            <button class="btn btn-primary" type="submit">Save review</button>
        </form>
    </section>
<?php else: ?>
    <section class="panel">
        <div class="panel-head"><h2>Add a review manually</h2></div>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="field"><label for="n_customer_name">Customer name *</label><input type="text" id="n_customer_name" name="customer_name" required></div>
                <div class="field"><label for="n_rating">Rating</label><input type="number" id="n_rating" name="rating" min="1" max="5" value="5"></div>
            </div>
            <div class="form-row">
                <div class="field"><label for="n_service_used">Service used</label><input type="text" id="n_service_used" name="service_used"></div>
                <div class="field"><label for="n_location">Location</label><input type="text" id="n_location" name="location"></div>
            </div>
            <div class="field"><label for="n_content">Review *</label><textarea id="n_content" name="content" rows="3" required></textarea></div>
            <button class="btn btn-primary" type="submit">Add review</button>
        </form>
    </section>
<?php endif; ?>
<?php if ($pending): ?>
<section class="panel">
    <div class="panel-head"><h2>Pending approval (<?= count($pending) ?>)</h2></div>
    <div class="msg-list">
    <?php foreach ($pending as $t): ?>
        <div class="msg-card is-unread">
            <div class="msg-head">
                <div><strong><?= e($t['customer_name']) ?></strong> — <?= e(render_stars((int)$t['rating'])) ?><br><small class="muted"><?= e($t['service_used']) ?> · <?= e($t['location']) ?> · <?= e(date('j M Y', strtotime($t['created_at']))) ?></small></div>
                <div class="msg-actions">
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-primary btn-sm" type="submit">Approve</button></form>
                    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/testimonials.php?edit=' . (int)$t['id'])) ?>">Edit</a>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Delete?')">Delete</button></form>
                </div>
            </div>
            <div class="msg-body"><?= nl2br(e($t['content'])) ?></div>
        </div>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-head"><h2>Published reviews (<?= count($approved) ?>)</h2></div>
    <?php if (!$approved): ?><p class="muted">No published reviews.</p><?php else: ?>
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Customer</th><th>Rating</th><th>Service</th><th>Order</th><th></th></tr></thead>
        <tbody><?php foreach ($approved as $t): ?>
            <tr><td><?= e($t['customer_name']) ?><br><small class="muted"><?= e($t['location']) ?></small></td>
                <td><?= e(render_stars((int)$t['rating'])) ?></td>
                <td><?= e($t['service_used'] ?: '—') ?></td>
                <td><?= (int)$t['sort_order'] ?></td>
                <td>
                    <a href="<?= e(url('/admin/testimonials.php?edit=' . (int)$t['id'])) ?>">Edit</a> ·
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="action" value="unapprove"><button class="link-btn" type="submit">Hide</button></form> ·
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="action" value="delete"><button class="link-btn" type="submit" onclick="return confirm('Delete?')">Delete</button></form>
                </td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php require APP_DIR . '/views/layout/admin_footer.php';


