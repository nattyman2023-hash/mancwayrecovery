<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$request_path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($request_path === '/service.php' && $slug !== '') {
    redirect(url('/services/' . rawurlencode($slug)));
}
$stmt = db()->prepare('SELECT * FROM services WHERE slug = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$slug]);
$service = $stmt->fetch();

if (!$service) {
    http_response_code(404);
    $page_title = 'Service not found';
    $active = 'services';
    require APP_DIR . '/views/layout/header.php';
    echo '<section class="section"><div class="container center"><h1>Service not found</h1><p>The service you\'re looking for isn\'t available.</p><p><a class="btn btn-primary" href="' . e(url('/services')) . '">View all services</a></p></div></section>';
    require APP_DIR . '/views/layout/footer.php';
    exit;
}

$page_title       = $service['title'] . ' — Vehicle Recovery Manchester | ' . site_name();
$page_description = $service['short_desc'] ?: 'Book ' . $service['title'] . ' across Greater Manchester — fast, insured, fixed prices.';
$page_canonical   = url('/services/' . $service['slug']);
$active = 'services';
$page_schema = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Service',
            '@id' => $page_canonical . '#service',
            'name' => $service['title'],
            'serviceType' => $service['title'],
            'description' => $page_description,
            'provider' => ['@id' => rtrim(APP_URL, '/') . '/#business'],
            'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Greater Manchester'],
            'offers' => ['@type' => 'Offer', 'priceCurrency' => 'GBP', 'price' => (string)$service['price_from'], 'url' => $page_canonical],
            'url' => $page_canonical,
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Services', 'item' => url('/services')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $service['title'], 'item' => $page_canonical],
            ],
        ],
    ],
];
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero" style="--page-hero-image:url('<?= e(asset('img/recovery-transport.png')) ?>')">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Home</a> › <a href="<?= e(url('/services')) ?>">Services</a> › <span><?= e($service['title']) ?></span></nav>
        <span class="pill">Recovery Service</span>
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
                <li>Rapid dispatch to your location, 24/7</li>
                <li>Experienced, fully insured recovery operator</li>
                <li>Modern recovery equipment for safe loading &amp; transport</li>
                <li>Upfront, fixed pricing — no surprises</li>
                <li>Clear communication from pickup to drop-off</li>
            </ul>
            <h3>Good to know</h3>
            <p>Final pricing depends on distance and vehicle type — you'll always be told the exact cost before we set off. Get in touch for a precise quote.</p>
        </div>
        <aside class="card side-cta">
            <h3>Book <?= e($service['title']) ?></h3>
            <p class="price">From <?= e(format_price($service['price_from'])) ?></p>
            <p>Call now or request online and we'll come to you.</p>
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
        <div><h2>Need us now?</h2><p>Request online in under a minute, or call for immediate dispatch.</p></div>
        <div class="cta-buttons"><a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php?service=' . $service['slug'])) ?>">Book <?= e($service['title']) ?></a></div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
