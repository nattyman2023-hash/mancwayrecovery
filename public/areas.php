<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$request_path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($request_path === '/areas.php') {
    redirect(url($slug !== '' ? '/areas/' . rawurlencode($slug) : '/areas'));
}
$area = null;
if ($slug !== '') {
    $stmt = db()->prepare('SELECT * FROM areas WHERE slug = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$slug]);
    $area = $stmt->fetch();
    if (!$area) {
        http_response_code(404);
        $page_title = 'Location not found | ' . site_name();
        $page_description = 'The requested recovery service area could not be found.';
        $page_canonical = url('/areas');
        $active = 'areas';
        require APP_DIR . '/views/layout/header.php';
        echo '<section class="section"><div class="container narrow center"><h1>Location not found</h1><p>That service area is not available. Browse all areas we cover instead.</p><p><a class="btn btn-primary" href="' . e(url('/areas')) . '">View areas covered</a></p></div></section>';
        require APP_DIR . '/views/layout/footer.php';
        exit;
    }
}

$active = 'areas';
if ($area) {
    $profile = area_profile($area);
    $services = db()->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
    $area_faqs = [
        ['Do you provide vehicle recovery in ' . $area['name'] . '?', 'Yes. We provide breakdown, accident, specialist and vehicle transport recovery across ' . $profile['places'] . '.'],
        ['Which postcodes do you cover in ' . $area['name'] . '?', 'Our usual coverage includes ' . $area['postcodes'] . '. If you are just outside these areas, call us and we will check availability.'],
        ['How do I book recovery in ' . $area['name'] . '?', 'Request recovery online or call ' . site_phone() . ' with your location, vehicle details and what has happened. We will confirm the next steps and price before dispatch.'],
    ];
    $page_title = 'Vehicle Recovery in ' . $area['name'] . ' | Breakdown Recovery | ' . site_name();
    $page_description = 'Fast vehicle recovery in ' . $area['name'] . '. Breakdown, accident, specialist recovery and vehicle transport across ' . $profile['places'] . '. Call ' . site_phone() . ' or book online.';
    $page_canonical = url('/areas/' . $area['slug']);
    $page_schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Service',
                '@id' => $page_canonical . '#service',
                'name' => 'Vehicle recovery in ' . $area['name'],
                'serviceType' => 'Vehicle recovery',
                'description' => $page_description,
                'provider' => ['@id' => rtrim(APP_URL, '/') . '/#business'],
                'areaServed' => ['@type' => 'Place', 'name' => $area['name'], 'description' => $profile['places']],
                'url' => $page_canonical,
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Areas covered', 'item' => url('/areas')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $area['name'], 'item' => $page_canonical],
                ],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static function (array $faq): array {
                    return ['@type' => 'Question', 'name' => $faq[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]]];
                }, $area_faqs),
            ],
        ],
    ];
} else {
    $page_title = 'Areas We Cover | Vehicle Recovery Greater Manchester | ' . site_name();
    $page_description = 'MancWay Recovery provides breakdown, accident, specialist and vehicle transport recovery across every Greater Manchester borough.';
    $page_canonical = url('/areas');
    $page_schema = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Vehicle recovery areas covered',
        'description' => $page_description,
        'url' => $page_canonical,
        'isPartOf' => ['@id' => rtrim(APP_URL, '/') . '/#business'],
    ];
}
require APP_DIR . '/views/layout/header.php';
?>

<?php if ($area): ?>
<section class="page-hero">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(url('/')) ?>">Home</a> &rsaquo; <a href="<?= e(url('/areas')) ?>">Areas covered</a> &rsaquo; <span><?= e($area['name']) ?></span></nav>
        <span class="pill">Greater Manchester coverage</span>
        <h1>Vehicle recovery in <?= e($area['name']) ?></h1>
        <p class="lead"><?= e($profile['summary']) ?></p>
        <div class="hero-cta">
            <a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php?area=' . $area['slug'])) ?>">Book recovery in <?= e($area['name']) ?></a>
            <a class="btn btn-outline btn-lg" href="tel:<?= e(setting('phone_href', site_phone())) ?>">Call <?= e(site_phone()) ?></a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container grid grid-2-1">
        <div class="prose">
            <h2>Fast, careful recovery in <?= e($area['name']) ?></h2>
            <p><?= e($profile['summary']) ?> When your vehicle will not start, has been involved in an accident, or needs moving safely, MancWay Recovery can come to your location and arrange the right recovery option.</p>
            <p>We keep the process straightforward: tell us where the vehicle is, what you are driving and where it needs to go. We will explain the options, confirm the price and keep you updated from dispatch to drop-off.</p>
            <h3>Recovery services available in <?= e($area['name']) ?></h3>
            <div class="grid grid-2 location-services">
                <?php foreach ($services as $service): ?>
                    <a class="card location-service-card" href="<?= e(url('/services/' . $service['slug'])) ?>">
                        <span class="service-ico"><?= icon_emoji($service['icon']) ?></span>
                        <strong><?= e($service['title']) ?></strong>
                        <span><?= e($service['short_desc']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <aside class="card side-cta location-aside">
            <span class="eyebrow">Coverage details</span>
            <h3><?= e($area['name']) ?> postcodes</h3>
            <p class="location-postcodes"><?= e($area['postcodes']) ?></p>
            <p class="muted">We also cover nearby roads and postcodes. If you are outside the usual area, call us before booking and we will check availability.</p>
            <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/booking.php?area=' . $area['slug'])) ?>">Request recovery</a>
            <a class="btn btn-outline btn-block mt" href="tel:<?= e(setting('phone_href', site_phone())) ?>">Call <?= e(site_phone()) ?></a>
        </aside>
    </div>
</section>

<section class="section bg-soft">
    <div class="container narrow">
        <div class="section-head"><span class="eyebrow">Helpful answers</span><h2><?= e($area['name']) ?> recovery FAQs</h2></div>
        <div class="faq-list">
            <?php foreach ($area_faqs as $faq): ?>
                <details class="faq"><summary><?= e($faq[0]) ?></summary><p><?= e($faq[1]) ?></p></details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php else: ?>
<section class="page-hero">
    <div class="container">
        <span class="pill">Coverage</span>
        <h1>Areas we cover</h1>
        <p class="lead">Our recovery vehicles come to you across every Greater Manchester borough. Choose your area for local coverage, postcode and booking information.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <span class="eyebrow">Dedicated local pages</span>
        <div class="grid grid-3">
            <?php $areas = db()->query('SELECT * FROM areas WHERE is_active = 1 ORDER BY sort_order')->fetchAll(); ?>
            <?php foreach ($areas as $a): $summary = area_profile($a); ?>
                <article class="card area-card">
                    <h2><?= e($a['name']) ?></h2>
                    <p><?= e($summary['summary']) ?></p>
                    <p class="muted">Postcodes: <?= e($a['postcodes']) ?></p>
                    <a class="btn btn-outline btn-block" href="<?= e(url('/areas/' . $a['slug'])) ?>">View <?= e($a['name']) ?> recovery</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="cta-band">
    <div class="container cta-inner">
        <div><h2><?= $area ? 'Need recovery in ' . e($area['name']) . '?' : 'Do not see your area?' ?></h2><p>Call us or request recovery online and we will check the best option for you.</p></div>
        <div class="cta-buttons"><a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php' . ($area ? '?area=' . $area['slug'] : ''))) ?>">Book recovery</a><a class="btn btn-ghost btn-lg" href="<?= e(url('/contact.php')) ?>">Contact us</a></div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
