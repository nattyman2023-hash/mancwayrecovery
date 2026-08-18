<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$flash = flash('flash');
$err = flash('errors', []);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    $id    = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $icon  = trim($_POST['icon'] ?? 'wrench');
    $short = trim($_POST['short_desc'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $price = (float)str_replace(['£', ','], '', $_POST['price_from'] ?? '0');
    $sort  = (int)($_POST['sort_order'] ?? 0);
    $active= isset($_POST['is_active']) ? 1 : 0;

    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM services WHERE id=?')->execute([$id]);
        redirect_with(url('/admin/services.php'), ['flash' => 'Service deleted.']);
    }
    if ($title === '') $err['title'] = 'Title is required.';
    $slug = slugify($title);
    if (!$err) {
        if ($id) {
            $upd = db()->prepare('UPDATE services SET slug=?,title=?,icon=?,short_desc=?,description=?,price_from=?,sort_order=?,is_active=? WHERE id=?');
            $upd->execute([$slug,$title,$icon,$short,$desc,$price,$sort,$active,$id]);
            redirect_with(url('/admin/services.php'), ['flash' => 'Service updated.']);
        } else {
            $ins = db()->prepare('INSERT INTO services (slug,title,icon,short_desc,description,price_from,sort_order,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,' . 'NOW())');
            $ins->execute([$slug,$title,$icon,$short,$desc,$price,$sort,$active]);
            redirect_with(url('/admin/services.php'), ['flash' => 'Service added.']);
        }
    }
    foreach (['title','icon','short_desc','description','price_from','sort_order'] as $f) $_SESSION['_flash']['input_' . $f] = $$f;
    $_SESSION['_flash']['input_is_active'] = $active;
    $_SESSION['_flash']['errors'] = $err;
    redirect(url('/admin/services.php' . ($id ? '?edit=' . $id : '')));
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM services WHERE id=?');
    $st->execute([$editId]);
    $editing = $st->fetch();
}
$services = db()->query('SELECT * FROM services ORDER BY sort_order, title')->fetchAll();

$admin_title = 'Services';
$active_admin = 'services';
$admin_actions_html = $editing ? '<a class="btn btn-outline btn-sm" href="' . e(url('/admin/services.php')) . '">← Cancel edit</a>' : '';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<div class="grid grid-2-1 admin-2col">
    <section class="panel">
        <div class="panel-head"><h2>All services</h2></div>
        <?php if (!$services): ?><p class="muted">No services yet.</p><?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Title</th><th>From</th><th>Active</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td><?= (int)$s['sort_order'] ?></td>
                        <td><strong><?= e($s['title']) ?></strong><br><small class="muted"><?= e($s['short_desc']) ?></small></td>
                        <td><?= e(format_price($s['price_from'])) ?></td>
                        <td><?= $s['is_active'] ? '✔' : '—' ?></td>
                        <td><a href="<?= e(url('/admin/services.php?edit=' . (int)$s['id'])) ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <section class="panel">
        <div class="panel-head"><h2><?= $editing ? 'Edit: ' . e($editing['title']) : 'Add a service' ?></h2></div>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : '' ?>">
            <div class="field<?= isset($err['title'])?' has-error':'' ?>">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" value="<?= $editing ? e($editing['title']) : old('title') ?>" required>
                <?= field_error($err, 'title') ?>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="icon">Icon (emoji or short word)</label>
                    <input type="text" id="icon" name="icon" value="<?= $editing ? e($editing['icon']) : old('icon','wrench') ?>">
                </div>
                <div class="field">
                    <label for="price_from">Price from (£)</label>
                    <input type="number" id="price_from" name="price_from" step="0.01" min="0" value="<?= $editing ? e($editing['price_from']) : old('price_from','0') ?>">
                </div>
            </div>
            <div class="field">
                <label for="short_desc">Short description</label>
                <input type="text" id="short_desc" name="short_desc" maxlength="255" value="<?= $editing ? e($editing['short_desc']) : old('short_desc') ?>">
            </div>
            <div class="field">
                <label for="description">Full description</label>
                <textarea id="description" name="description" rows="5"><?= $editing ? e($editing['description']) : old('description') ?></textarea>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="sort_order">Sort order</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= $editing ? (int)$editing['sort_order'] : old('sort_order','0') ?>">
                </div>
                <div class="field field-check">
                    <label><input type="checkbox" name="is_active" <?= ($editing ? (int)$editing['is_active'] : (int)old('is_active','1')) ? 'checked' : '' ?>> Active (show on site)</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Update service' : 'Add service' ?></button>
            <?php if ($editing): ?>
                <button type="submit" name="action" value="delete" formnovalidate class="btn btn-ghost" onclick="return confirm('Delete this service? Bookings keep their reference.')">Delete</button>
            <?php endif; ?>
        </form>
    </section>
</div>
<?php require APP_DIR . '/views/layout/admin_footer.php';

