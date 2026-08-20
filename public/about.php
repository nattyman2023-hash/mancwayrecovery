<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$page_title       = 'About Us — ' . site_name();
$page_description = 'About MancWay Recovery: a Greater Manchester vehicle recovery service for breakdowns, accidents and specialist transport — honest pricing, fully insured, fast response.';
$page_canonical   = url('/about.php');
$active = 'about';
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero">
    <div class="container">
        <span class="pill">About</span>
        <h1>Recovery you can rely on</h1>
        <p class="lead">MancWay Recovery was founded on a simple idea: when you're stuck, help should be fast, honest and stress-free.</p>
    </div>
</section>

<section class="section">
    <div class="container grid grid-2-1">
        <div class="prose">
            <span class="eyebrow">Our story</span>
            <h2>Who we are</h2>
            <p>We're a team of experienced recovery operators based in Greater Manchester. Whether you've broken down, been in a collision, or need a vehicle moved long-distance, we send a fully-equipped recovery vehicle straight to you — day or night.</p>
            <h2>What we believe</h2>
            <ul class="ticks">
                <li><strong>Honesty first.</strong> We explain the situation and the cost upfront. No upselling.</li>
                <li><strong>Transparent pricing.</strong> You see the price before we set off.</li>
                <li><strong>Careful handling.</strong> Modern recovery equipment and secure loading — your vehicle arrives in the condition it left in.</li>
                <li><strong>Respect for your time.</strong> We turn up when we say we will.</li>
            </ul>
            <h2>Fully insured</h2>
            <p>Every recovery is fully insured and carried out by experienced operators — giving you peace of mind whether it's a roadside breakdown or a long-distance transport job.</p>
        </div>
        <aside class="card side-cta">
            <h3>Talk to our team</h3>
            <p>Need recovery or have a question? We're happy to help.</p>
            <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/booking.php')) ?>">Book online</a>
            <a class="btn btn-outline btn-block mt" href="tel:<?= e(setting('phone_href', site_phone())) ?>">📞 <?= e(site_phone()) ?></a>
            <p class="muted mt"><?= e(setting('hours_weekday')) ?><br><?= e(setting('hours_weekend')) ?></p>
        </aside>
    </div>
</section>

<section class="section bg-dark">
    <div class="container">
        <div class="section-head"><span class="eyebrow">Process</span><h2>How it works</h2><p>Three simple steps.</p></div>
        <div class="grid grid-3">
            <div class="stat-card"><strong>1</strong><h3>Call or request online</h3><p>Tell us what's happened, your location and vehicle. It takes under a minute.</p></div>
            <div class="stat-card"><strong>2</strong><h3>We come to you</h3><p>Our recovery vehicle arrives at your location, fast.</p></div>
            <div class="stat-card"><strong>3</strong><h3>Vehicle sorted</h3><p>Safely recovered to your chosen destination — home, garage or compound.</p></div>
        </div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
