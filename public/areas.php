<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug !== '') {
    $stmt = db()->prepare('SELECT * FROM areas WHERE slug = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$slug]);
    $area = $stmt->fetch();
} else {
    $area = null;
}

$page_title       = 'Areas We Cover — Vehicle Recovery Manchester | ' . site_name();
$page_description = 'MancWay Recovery covers all of Greater Manchester: Manchester, Salford, Trafford, Stockport, Tameside, Bury, Bolton, Rochdale, Oldham and Wigan — plus long-distance transport further afield.';
$page_canonical   = url('/areas.php');
$active = 'areas';
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero">
    <div class="container">
        <span class="pill">Coverage</span>
        <h1>Areas we cover</h1>
        <p class="lead">Our recovery vehicles come to you across all of Greater Manchester. If you're not sure whether we cover your area, just <a href="<?= e(url('/contact.php')) ?>">give us a call</a>.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($area): ?>
            <nav class="breadcrumbs"><a href="<?= e(url('/')) ?>">Home</a> › <a href="<?= e(url('/areas.php')) ?>">Areas</a> › <span><?= e($area['name']) ?></span></nav>
            <h2><?= e($area['name']) ?></h2>
            <p>We provide vehicle recovery services in <?= e($area['name']) ?> and surrounding postcodes: <?= e($area['postcodes']) ?>.</p>
            <p><a class="btn btn-primary" href="<?= e(url('/booking.php')) ?>">Book recovery in <?= e($area['name']) ?></a></p>
            <hr>
            <p><a href="<?= e(url('/areas.php')) ?>">← Back to all areas</a></p>
        <?php else:
            $areas = db()->query('SELECT * FROM areas WHERE is_active = 1 ORDER BY sort_order')->fetchAll(); ?>
            <span class="eyebrow">All areas</span>
            <div class="grid grid-3">
                <?php foreach ($areas as $a): ?>
                    <article class="card area-card">
                        <h3><?= e($a['name']) ?></h3>
                        <p class="muted">Postcodes: <?= e($a['postcodes']) ?></p>
                        <a class="btn btn-outline btn-block" href="<?= e(url('/booking.php')) ?>">Book recovery in <?= e($a['name']) ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-inner">
        <div><h2>Don't see your area?</h2><p>We may still be able to help. Get in touch for availability.</p></div>
        <div class="cta-buttons"><a class="btn btn-primary btn-lg" href="<?= e(url('/contact.php')) ?>">Contact us</a></div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
