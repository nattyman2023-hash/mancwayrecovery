<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$request_path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($request_path === '/services.php') {
    redirect(url('/services'));
}

$services = db()->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

$page_title       = 'Our Services — Vehicle Recovery Manchester | ' . site_name();
$page_description = 'Vehicle recovery services across Greater Manchester: breakdown recovery, accident recovery, long-distance vehicle transport and specialist 4x4/off-road/motorbike recovery.';
$page_canonical   = url('/services');
$active = 'services';
$page_schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Vehicle recovery services',
    'description' => $page_description,
    'url' => $page_canonical,
    'isPartOf' => ['@id' => rtrim(APP_URL, '/') . '/#business'],
];
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero" style="--page-hero-image:url('<?= e(asset('img/recovery-transport.png')) ?>')">
    <div class="container">
        <span class="pill">Services</span>
        <h1>Vehicle recovery services</h1>
        <p class="lead">Honest, fixed pricing for breakdown, accident and specialist recovery — wherever you are across Greater Manchester. Can't see what you need? <a href="<?= e(url('/contact.php')) ?>">Just ask</a>.</p>
    </div>
</section>

<?php
$photo_items = [
    ['image' => 'recovery-transport.png', 'alt' => 'Recovery operator securing a silver car to a flatbed truck at a motorway service area', 'title' => 'Long-distance transport', 'text' => 'Careful loading for vehicle moves across the UK.'],
    ['image' => 'recovery-assistance.png', 'alt' => 'Recovery operator talking with a driver beside a car with its bonnet open', 'title' => 'Breakdown support', 'text' => 'Straightforward help when your vehicle stops unexpectedly.'],
    ['image' => 'recovery-dusk.png', 'alt' => 'Recovery operator securing a vehicle on a flatbed truck at dusk', 'title' => 'Specialist recovery', 'text' => 'The right approach for difficult locations, weather and vehicle types.'],
];
$photo_kicker = 'The work behind the service';
$photo_title = 'Equipped for the job in front of us.';
$photo_intro = 'From a quick roadside assist to a planned vehicle move, every recovery starts with careful handling.';
partial('partials/photo-strip', compact('photo_items', 'photo_kicker', 'photo_title', 'photo_intro'));
?>

<section class="section">
    <div class="container">
        <div class="grid grid-3">
            <?php foreach ($services as $s): ?>
                <article class="card service-card">
                    <?php if ((int)$s['sort_order'] === 1): ?><span class="tag">Most requested</span><?php endif; ?>
                    <div class="service-ico"><?= icon_emoji($s['icon']) ?></div>
                    <h3><?= e($s['title']) ?></h3>
                    <p><?= e($s['short_desc']) ?></p>
                    <div class="service-foot">
                        <span class="price">From <?= e(format_price(booking_base_price_for_service((string)$s['slug'], (float)$s['price_from']))) ?></span>
                    <a class="link" href="<?= e(url('/services/' . $s['slug'])) ?>">Details →</a>
                    </div>
                    <a class="btn btn-outline btn-block" href="<?= e(url('/booking.php?service=' . $s['slug'])) ?>">Request this service</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-inner">
        <div><h2>Not sure what you need?</h2><p>Tell us what's happened and we'll advise honestly — no guesswork.</p></div>
        <div class="cta-buttons">
            <a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book Recovery</a>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('/contact.php')) ?>">Ask a question</a>
        </div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
