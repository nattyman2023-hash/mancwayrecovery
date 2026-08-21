<?php
declare(strict_types=1);
/**
 * MancWay Recovery — CRM: Recovery vehicles.
 *
 * Seeded with one vehicle (Recovery Unit 01). Add more here as the fleet
 * grows — no schema changes needed.
 */
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

// Guard: this screen also needs the booking assignment column for job counts
// and for unlinking a vehicle safely on removal.
try {
    db()->query('SELECT 1 FROM recovery_vehicles LIMIT 1');
    db()->query('SELECT vehicle_id FROM bookings LIMIT 1');
}
catch (Throwable $e) { redirect(url('/admin/crm.php')); }

$flash = flash('flash');
$err   = flash('errors', []);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    $id    = (int)($_POST['id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $reg   = strtoupper(trim($_POST['registration'] ?? ''));
    $type  = trim($_POST['type'] ?? 'Flatbed');
    $status = $_POST['status'] ?? 'available';
    $notes = trim($_POST['notes'] ?? '');
    $active = isset($_POST['is_active']) ? 1 : 0;

    if ($action === 'delete' && $id) {
        // Unlink any enquiries first, then remove the vehicle.
        db()->prepare('UPDATE bookings SET vehicle_id=NULL WHERE vehicle_id=?')->execute([$id]);
        db()->prepare('DELETE FROM recovery_vehicles WHERE id=?')->execute([$id]);
        redirect_with(url('/admin/vehicles.php'), ['flash' => 'Vehicle removed. Enquiries unlinked.']);
    }
    if (!in_array($status, vehicle_statuses(), true)) $status = 'available';
    if ($name === '') $err['name'] = 'A name/label is required (e.g. Recovery Unit 01).';

    if (!$err) {
        if ($id) {
            $upd = db()->prepare('UPDATE recovery_vehicles SET name=?,registration=?,type=?,status=?,notes=?,is_active=? WHERE id=?');
            $upd->execute([$name, $reg, $type, $status, $notes, $active, $id]);
            redirect_with(url('/admin/vehicles.php'), ['flash' => 'Vehicle updated.']);
        } else {
            $ins = db()->prepare('INSERT INTO recovery_vehicles (name,registration,type,status,notes,is_active,created_at) VALUES (?,?,?,?,?,?,NOW())');
            $ins->execute([$name, $reg, $type, $status, $notes, $active]);
            redirect_with(url('/admin/vehicles.php'), ['flash' => 'Vehicle added.']);
        }
    }
    foreach (['name','registration','type','status','notes'] as $f) $_SESSION['_flash']['input_' . $f] = $$f;
    $_SESSION['_flash']['input_is_active'] = $active;
    $_SESSION['_flash']['errors'] = $err;
    redirect(url('/admin/vehicles.php' . ($id ? '?edit=' . $id : '')));
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM recovery_vehicles WHERE id=?');
    $st->execute([$editId]);
    $editing = $st->fetch() ?: null;
}

// Vehicle list with live job counts
$vehicles = db()->query("SELECT v.*,
    (SELECT COUNT(*) FROM bookings b WHERE b.vehicle_id = v.id AND b.status IN ('accepted','dispatched')) AS active_jobs,
    (SELECT COUNT(*) FROM bookings b WHERE b.vehicle_id = v.id) AS total_jobs
    FROM recovery_vehicles v ORDER BY v.is_active DESC, v.id")->fetchAll();
$formStatus = $editing['status'] ?? old('status', 'available');

$admin_title  = 'Recovery Vehicles';
$active_admin = 'vehicles';
$admin_actions_html = $editing ? '<a class="btn btn-outline btn-sm" href="' . e(url('/admin/vehicles.php')) . '">← Cancel edit</a>' : '';
require APP_DIR . '/views/layout/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>

<div class="admin-2col">
    <section class="panel">
        <div class="panel-head">
            <h2>Fleet roster</h2>
            <span class="muted"><?= count($vehicles) ?> unit<?= count($vehicles) === 1 ? '' : 's' ?></span>
        </div>

        <?php if (!$vehicles): ?>
            <div class="crm-empty">
                <div class="ico"><span class="mw-icon">local_shipping</span></div>
                <h3>No recovery vehicles yet</h3>
                <p>Add the first unit using the form alongside.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Unit</th><th>Registration</th><th>Type</th><th>Status</th><th>Jobs</th><th>Active</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr class="<?= $vehicle['is_active'] ? '' : 'is-muted' ?>">
                            <td>
                                <strong><?= e($vehicle['name']) ?></strong>
                                <?php if ($vehicle['notes']): ?><br><small class="muted"><?= e($vehicle['notes']) ?></small><?php endif; ?>
                            </td>
                            <td><span class="reg"><?= e($vehicle['registration'] ?: '—') ?></span></td>
                            <td><?= e($vehicle['type']) ?></td>
                            <td>
                                <span class="vehicle-state <?= e(vehicle_pill_class($vehicle['status'])) ?>">
                                    <span class="dot"></span><?= e(vehicle_status_label($vehicle['status'])) ?>
                                </span>
                            </td>
                            <td><strong><?= (int)$vehicle['active_jobs'] ?></strong> active<br><small class="muted"><?= (int)$vehicle['total_jobs'] ?> total</small></td>
                            <td><?= $vehicle['is_active'] ? 'Yes' : 'No' ?></td>
                            <td><a href="<?= e(url('/admin/vehicles.php?edit=' . (int)$vehicle['id'])) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2><?= $editing ? 'Edit: ' . e($editing['name']) : 'Add a vehicle' ?></h2>
        </div>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editing ? (int)$editing['id'] : '' ?>">

            <div class="field<?= isset($err['name']) ? ' has-error' : '' ?>">
                <label for="name">Unit name *</label>
                <input type="text" id="name" name="name" maxlength="80"
                       value="<?= $editing ? e($editing['name']) : old('name') ?>"
                       placeholder="e.g. Recovery Unit 01" required>
                <?= field_error($err, 'name') ?>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="registration">Registration</label>
                    <input type="text" id="registration" name="registration" maxlength="20"
                           value="<?= $editing ? e($editing['registration']) : old('registration') ?>"
                           placeholder="e.g. MW21 ABC">
                </div>
                <div class="field">
                    <label for="type">Vehicle type</label>
                    <input type="text" id="type" name="type" maxlength="40"
                           value="<?= $editing ? e($editing['type']) : old('type', 'Flatbed') ?>"
                           placeholder="Flatbed">
                </div>
            </div>

            <div class="field">
                <label for="status">Current status</label>
                <select id="status" name="status">
                    <?php foreach (vehicle_statuses() as $status): ?>
                        <option value="<?= e($status) ?>" <?= $formStatus === $status ? 'selected' : '' ?>>
                            <?= e(vehicle_status_label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" maxlength="255" rows="3" placeholder="Optional operational note"><?= $editing ? e($editing['notes']) : old('notes') ?></textarea>
            </div>

            <div class="field field-check">
                <label><input type="checkbox" name="is_active" <?= ($editing ? (int)$editing['is_active'] : (int)old('is_active', '1')) ? 'checked' : '' ?>> Active (available for assignment)</label>
            </div>

            <button type="submit" class="btn btn-primary"><?= $editing ? 'Update vehicle' : 'Add vehicle' ?></button>
            <?php if ($editing): ?>
                <button type="submit" name="action" value="delete" formnovalidate class="btn btn-ghost" onclick="return confirm('Remove this vehicle? Existing enquiries will be unassigned.')">Remove vehicle</button>
            <?php endif; ?>
        </form>
    </section>
</div>

<?php require APP_DIR . '/views/layout/admin_footer.php';
