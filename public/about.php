<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$page_title       = 'About Us — ' . site_name();
$page_description = 'About MancWay Mobile Mechanics: a Greater Manchester mobile mechanic service bringing the garage to your door — honest pricing, qualified mechanics, quality parts.';
$page_canonical   = url('/about.php');
$active = 'about';
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero">
    <div class="container">
        <span class="pill">About</span>
        <h1>The garage that comes to you</h1>
        <p class="lead">MancWay Mobile Mechanics was founded on a simple idea: car care should fit around your life, not the other way around.</p>
    </div>
</section>

<section class="section">
    <div class="container grid grid-2-1">
        <div class="prose">
            <h2>Who we are</h2>
            <p>We're a team of qualified mechanics based in Greater Manchester. Instead of asking you to find time to visit a garage, queue for a loaner car, or sit in a waiting room, we bring fully-equipped mobile workshop vans to your home, office or roadside.</p>
            <h2>What we believe</h2>
            <ul class="ticks">
                <li><strong>Honesty first.</strong> We explain what's wrong, what's urgent, and what can wait. No upselling.</li>
                <li><strong>Transparent pricing.</strong> You see the price before we start work.</li>
                <li><strong>Quality work.</strong> OEM-equivalent parts, proper tools, and a warranty on the work we carry out.</li>
                <li><strong>Respect for your time.</strong> We turn up when we say we will.</li>
            </ul>
            <h2>Fully insured</h2>
            <p>All work is fully insured and carried out by experienced mechanics — giving you peace of mind whether we're changing your oil at home or fitting a clutch at your workplace.</p>
        </div>
        <aside class="card side-cta">
            <h3>Talk to a mechanic</h3>
            <p>Questions about your car? We're happy to help.</p>
            <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('/booking.php')) ?>">Book online</a>
            <a class="btn btn-outline btn-block mt" href="tel:<?= e(setting('phone_href', site_phone())) ?>">📞 <?= e(site_phone()) ?></a>
            <p class="muted mt"><?= e(setting('hours_weekday')) ?><br><?= e(setting('hours_weekend')) ?></p>
        </aside>
    </div>
</section>

<section class="section bg-dark">
    <div class="container">
        <div class="section-head"><h2>How it works</h2><p>Three simple steps.</p></div>
        <div class="grid grid-3">
            <div class="stat-card"><strong>1</strong><h3>Book online</h3><p>Choose your service, date and location. It takes under a minute.</p></div>
            <div class="stat-card"><strong>2</strong><h3>We come to you</h3><p>Our mobile mechanic arrives at your home or workplace, on time.</p></div>
            <div class="stat-card"><strong>3</strong><h3>Car sorted</h3><p>Fixed, checked and back on the road — with no waiting room required.</p></div>
        </div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
