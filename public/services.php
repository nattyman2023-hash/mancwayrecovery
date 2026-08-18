<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$services = db()->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

$page_title       = 'Our Services — Mobile Mechanic Manchester | ' . site_name();
$page_description = 'Full mobile mechanic services across Greater Manchester: servicing, MOT prep, brakes, diagnostics, battery & alternator, timing belt, clutch, tyres & alignment, and roadside breakdown cover.';
$page_canonical   = url('/services.php');
$active = 'services';
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero">
    <div class="container">
        <span class="pill">Services</span>
        <h1>Mobile mechanic services</h1>
        <p class="lead">Honest, fixed pricing for the work most drivers need — all carried out at your home or workplace across Greater Manchester. Can't see what you need? <a href="<?= e(url('/contact.php')) ?>">Just ask</a>.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="grid grid-3">
            <?php foreach ($services as $s): ?>
                <article class="card service-card">
                    <div class="service-ico"><?= e($s['icon']) ?></div>
                    <h3><?= e($s['title']) ?></h3>
                    <p><?= e($s['short_desc']) ?></p>
                    <div class="service-foot">
                        <span class="price">From <?= e(format_price($s['price_from'])) ?></span>
                        <a class="link" href="<?= e(url('/service.php?slug=' . $s['slug'])) ?>">Details →</a>
                    </div>
                    <a class="btn btn-outline btn-block" href="<?= e(url('/booking.php?service=' . $s['slug'])) ?>">Book this service</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-inner">
        <div><h2>Not sure what you need?</h2><p>Describe the symptom and we'll tell you what's likely involved — honestly.</p></div>
        <div class="cta-buttons">
            <a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book a Mechanic</a>
            <a class="btn btn-ghost btn-lg" href="<?= e(url('/contact.php')) ?>">Ask a question</a>
        </div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
