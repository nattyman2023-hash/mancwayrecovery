<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$stmt = db()->prepare('SELECT * FROM services WHERE slug = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$slug]);
$service = $stmt->fetch();

if (!$service) {
    http_response_code(404);
    $page_title = 'Service not found';
    $active = 'services';
    require APP_DIR . '/views/layout/header.php';
    echo '<section class="section"><div class="container center"><h1>Service not found</h1><p>The service you\'re looking for isn\'t available.</p><p><a class="btn btn-primary" href="' . e(url('/services.php')) . '">View all services</a></p></div></section>';
    require APP_DIR . '/views/layout/footer.php';
    exit;
}

$page_title       = $service['title'] . ' — Mobile Mechanic Manchester | ' . site_name();
$page_description = $service['short_desc'] ?: 'Book ' . $service['title'] . ' at your home or workplace across Greater Manchester.';
$page_canonical   = url('/service.php?slug=' . $service['slug']);
$active = 'services';
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Home</a> › <a href="<?= e(url('/services.php')) ?>">Services</a> › <span><?= e($service['title']) ?></span></nav>
        <span class="pill"><?= e($service['icon']) ?></span>
        <h1><?= e($service['title']) ?></h1>
        <p class="lead"><?= e($service['short_desc']) ?></p>
        <div class="hero-cta">
            <a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php?service=' . $service['slug'])) ?>">Book this service</a>
            <span class="price-tag">From <?= e(format_price($service['price_from'])) ?></span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container grid grid-2-1">
        <div class="prose">
            <h2>About this service</h2>
            <p><?= nl2br(e($service['description'] ?: $service['short_desc'])) ?></p>
            <h3>What's included</h3>
            <ul class="ticks">
                <li>Mobile appointment at your home or workplace</li>
                <li>Qualified, insured mechanic</li>
                <li>OEM-equivalent quality parts</li>
                <li>Upfront, fixed pricing — no surprises</li>
                <li>Warranty on work carried out</li>
            </ul>
            <h3>Good to know</h3>
            <p>Final pricing depends on your vehicle and parts required — you'll always be told the exact cost before any work starts. Get in touch for a precise quote.</p>
        </div>
        <aside class="card side-cta">
            <h3>Book <?= e($service['title']) ?></h3>
            <p class="price">From <?= e(format_price($service['price_from'])) ?></p>
            <p>Choose a date and we'll come to you.</p>
            <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/booking.php?service=' . $service['slug'])) ?>">Book online</a>
            <hr>
            <p class="muted">Prefer to talk?</p>
            <a class="btn btn-outline btn-block" href="tel:<?= e(setting('phone_href', site_phone())) ?>">📞 <?= e(site_phone()) ?></a>
            <p class="muted mt">Serving <?= e(setting('service_radius')) ?></p>
        </aside>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-inner">
        <div><h2>Ready to book?</h2><p>Online booking takes less than a minute.</p></div>
        <div class="cta-buttons"><a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php?service=' . $service['slug'])) ?>">Book <?= e($service['title']) ?></a></div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
