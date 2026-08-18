<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

// Mark read when viewing
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'read' && $id) {
        db()->prepare('UPDATE messages SET is_read=1 WHERE id=?')->execute([$id]);
    } elseif ($action === 'unread' && $id) {
        db()->prepare('UPDATE messages SET is_read=0 WHERE id=?')->execute([$id]);
    } elseif ($action === 'delete' && $id) {
        db()->prepare('DELETE FROM messages WHERE id=?')->execute([$id]);
    }
    redirect(url('/admin/messages.php'));
}

$filter = $_GET['filter'] ?? '';
$where = $filter === 'unread' ? 'WHERE is_read=0' : '';
$msgs = db()->query("SELECT * FROM messages $where ORDER BY created_at DESC")->fetchAll();

$admin_title = 'Messages';
$active_admin = 'messages';
require APP_DIR . '/views/layout/admin_header.php';
?>
<div class="filter-bar">
    <a class="chip <?= $filter===''?'active':'' ?>" href="<?= e(url('/admin/messages.php')) ?>">All</a>
    <a class="chip <?= $filter==='unread'?'active':'' ?>" href="<?= e(url('/admin/messages.php?filter=unread')) ?>">Unread</a>
</div>
<?php if (!$msgs): ?>
    <p class="muted">No messages.</p>
<?php else: ?>
    <div class="msg-list">
    <?php foreach ($msgs as $m): ?>
        <div class="msg-card <?= $m['is_read']?'is-read':'is-unread' ?>">
            <div class="msg-head">
                <div>
                    <strong><?= e($m['name']) ?></strong>
                    <?php if (!$m['is_read']): ?><span class="badge badge-new">New</span><?php endif; ?>
                    <br><small class="muted"><?= e(date('j M Y H:i', strtotime($m['created_at']))) ?></small>
                </div>
                <div class="msg-actions">
                    <?php if ($m['email']): ?><a class="btn btn-outline btn-sm" href="mailto:<?= e($m['email']) ?>">Reply</a><?php endif; ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <input type="hidden" name="action" value="<?= $m['is_read'] ? 'unread' : 'read' ?>">
                        <button class="btn btn-ghost btn-sm" type="submit"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button>
                    </form>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Delete this message?')">Delete</button>
                    </form>
                </div>
            </div>
            <dl class="kv"><dt>Email</dt><dd><?= $m['email'] ? e($m['email']) : '—' ?></dd>
                <dt>Phone</dt><dd><?= $m['phone'] ? e($m['phone']) : '—' ?></dd>
                <dt>Subject</dt><dd><?= e($m['subject'] ?: '—') ?></dd>
            </dl>
            <div class="msg-body"><?= nl2br(e($m['message'])) ?></div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require APP_DIR . '/views/layout/admin_footer.php';
