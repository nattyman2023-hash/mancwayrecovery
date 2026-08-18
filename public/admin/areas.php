<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$flash = flash('flash');
$err = flash('errors', []);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $postcodes = trim($_POST['postcodes'] ?? '');
    $sort = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM areas WHERE id=?')->execute([$id]);
        redirect_with(url('/admin/areas.php'), ['flash' => 'Area deleted.']);
    }
    if ($name === '') $err['name'] = 'Name is required.';
    $slug = slugify($name);
    if (!$err) {
        if ($id) {
            $upd = db()->prepare('UPDATE areas SET slug=?,name=?,postcodes=?,sort_order=?,is_active=? WHERE id=?');
            $upd->execute([$slug,$name,$postcodes,$sort,$active,$id]);
            redirect_with(url('/admin/areas.php'), ['flash' => 'Area updated.']);
        } else {
            $ins = db()->prepare('INSERT INTO areas (slug,name,postcodes,sort_order,is_active,created_at) VALUES (?,?,?,?,?,' . 'NOW())');
            $ins->execute([$slug,$name,$postcodes,$sort,$active]);
            redirect_with(url('/admin/areas.php'), ['flash' => 'Area added.']);
        }
    }
    foreach (['name','postcodes','sort_order'] as $f) $_SESSION['_flash']['input_' . $f] = $$f;
    $_SESSION['_flash']['input_is_active'] = $active;
    $_SESSION['_flash']['errors'] = $err;
    redirect(url('/admin/areas.php' . ($id ? '?edit=' . $id : '')));
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) { $st = db()->prepare('SELECT * FROM areas WHERE id=?'); $st->execute([$editId]); $editing = $st->fetch(); }
$areas = db()->query('SELECT * FROM areas ORDER BY sort_order, name')->fetchAll();

$admin_title = 'Areas';
$active_admin = 'areas';
$admin_actions_html = $editing ? '<a class="btn btn-outline btn-sm" href="' . e(url('/admin/areas.php')) . '">← Cancel edit</a>' : '';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<div class="grid grid-2-1 admin-2col">
    <section class="panel">
        <div class="panel-head"><h2>All areas</h2></div>
        <?php if (!$areas): ?><p class="muted">No areas yet.</p><?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Order</th><th>Name</th><th>Postcodes</th><th>Active</th><th></th></tr></thead>
            <tbody><?php foreach ($areas as $a): ?>
                <tr><td><?= (int)$a['sort_order'] ?></td><td><strong><?= e($a['name']) ?></strong></td>
                    <td><small class="muted"><?= e($a['postcodes']) ?></small></td>
                    <td><?= $a['is_active'] ? '✔' : '—' ?></td>
                    <td><a href="<?= e(url('/admin/areas.php?edit=' . (int)$a['id'])) ?>">Edit</a></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </section>
    <section class="panel">
        <div class="panel-head"><h2><?= $editing ? 'Edit: ' . e($editing['name']) : 'Add an area' ?></h2></div>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : '' ?>">
            <div class="field<?= isset($err['name'])?' has-error':'' ?>">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" value="<?= $editing ? e($editing['name']) : old('name') ?>" required>
                <?= field_error($err, 'name') ?>
            </div>
            <div class="field">
                <label for="postcodes">Postcodes</label>
                <input type="text" id="postcodes" name="postcodes" value="<?= $editing ? e($editing['postcodes']) : old('postcodes') ?>" placeholder="e.g. M5, M6, M7">
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="sort_order">Sort order</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= $editing ? (int)$editing['sort_order'] : old('sort_order','0') ?>">
                </div>
                <div class="field field-check">
                    <label><input type="checkbox" name="is_active" <?= ($editing ? (int)$editing['is_active'] : (int)old('is_active','1')) ? 'checked' : '' ?>> Active</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Update area' : 'Add area' ?></button>
            <?php if ($editing): ?>
                <button type="submit" name="action" value="delete" formnovalidate class="btn btn-ghost" onclick="return confirm('Delete this area?')">Delete</button>
            <?php endif; ?>
        </form>
    </section>
</div>
<?php require APP_DIR . '/views/layout/admin_footer.php';
