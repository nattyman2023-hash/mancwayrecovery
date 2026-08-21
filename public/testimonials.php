<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$reviews = db()->query('SELECT * FROM testimonials WHERE is_approved = 1 ORDER BY sort_order, created_at DESC')->fetchAll();
$avg = count($reviews) ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 5;

$page_title       = 'Reviews — ' . site_name();
$page_description = 'Read genuine customer reviews of MancWay Recovery across Greater Manchester. Real feedback on our breakdown, accident and specialist vehicle recovery.';
$page_canonical   = url('/testimonials.php');
$active = 'testimonials';
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero" style="--page-hero-image:url('<?= e(asset('img/recovery-loading.jpg')) ?>')">
    <div class="container">
        <span class="pill">Reviews</span>
        <h1>What our customers say</h1>
        <p class="lead">Honest feedback from drivers across Greater Manchester. <strong><?= e($avg) ?>★</strong> average from <?= count($reviews) ?> reviews.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <span class="eyebrow">Customer stories</span>
        <div class="grid grid-3">
            <?php foreach ($reviews as $r): ?>
                <figure class="review">
                    <div class="stars"><?= e(render_stars((int)$r['rating'])) ?></div>
                    <blockquote><?= e($r['content']) ?></blockquote>
                    <figcaption><strong><?= e($r['customer_name']) ?></strong> · <?= e($r['location']) ?><br><small><?= e($r['service_used']) ?></small></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-inner">
        <div><h2>Join our happy customers</h2><p>Book your vehicle recovery today.</p></div>
        <div class="cta-buttons"><a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book Online</a></div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
