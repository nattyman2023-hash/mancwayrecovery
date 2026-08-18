<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$services = db()->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order LIMIT 6')->fetchAll();
$reviews  = db()->query('SELECT * FROM testimonials WHERE is_approved = 1 ORDER BY sort_order, created_at DESC LIMIT 3')->fetchAll();
$areas    = db()->query('SELECT * FROM areas WHERE is_active = 1 ORDER BY sort_order LIMIT 10')->fetchAll();

$page_title       = site_name() . ' — Mobile Mechanic in Manchester | We Come to You';
$page_description = 'MancWay Mobile Mechanics: professional mobile car servicing, MOT prep, brakes, diagnostics & breakdown cover across Greater Manchester. Honest, fixed prices — we come to your home or work.';
$page_canonical   = url('/');
$active = 'home';
require APP_DIR . '/views/layout/header.php';
?>

<section class="hero">
    <div class="container hero-inner">
        <div class="hero-text">
            <span class="pill">Mobile mechanics covering Greater Manchester</span>
            <h1>Manchester's trusted <span class="hl">mobile mechanic</span>. We come to you.</h1>
            <p class="lead">Servicing, MOT preparation, brakes, diagnostics and breakdown cover — fixed at your home or workplace. No towing, no waiting rooms, no fuss.</p>
            <div class="hero-cta">
                <a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book a Mechanic</a>
                <a class="btn btn-outline btn-lg" href="tel:<?= e(setting('phone_href', site_phone())) ?>"><?= e(site_phone()) ?></a>
            </div>
            <ul class="hero-trust">
                <li>✔ Fully insured</li><li>✔ Fixed, upfront pricing</li><li>✔ Same-day mobile service</li>
            </ul>
        </div>
        <div class="hero-card" aria-hidden="true">
            <div class="hero-card-head">
                <span class="brand-mark"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.8 2.8-2.4-2.4 2.8-2.8z"/></svg></span>
                <div><strong><?= e(site_name()) ?></strong><small>Booking &amp; enquiries</small></div>
            </div>
            <a class="hero-quick" href="<?= e(url('/booking.php')) ?>"><span>🛠️ Book a service online</span><span>→</span></a>
            <a class="hero-quick" href="tel:<?= e(setting('phone_href', site_phone())) ?>"><span>📞 Call <?= e(site_phone()) ?></span><span>→</span></a>
            <a class="hero-quick" href="<?= e(url('/contact.php')) ?>"><span>💬 Send a message</span><span>→</span></a>
            <p class="hero-card-foot"><?= e(setting('hours_weekday')) ?></p>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><h2>What can we do for your car?</h2><p>Common services — fixed prices, quality parts, done at your location.</p></div>
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
                </article>
            <?php endforeach; ?>
        </div>
        <p class="center mt-2"><a class="btn btn-outline" href="<?= e(url('/services.php')) ?>">View all services</a></p>
    </div>
</section>

<section class="section bg-dark">
    <div class="container grid grid-2 align-center">
        <div>
            <h2>The MancWay difference</h2>
            <p class="lead">We took the garage and put it in a van. Same professional tools and qualified mechanics — without the hassle of getting there.</p>
            <ul class="ticks">
                <li><strong>Truly mobile</strong> — we work at your home, office or roadside across Greater Manchester.</li>
                <li><strong>Transparent pricing</strong> — you see the price before we start. No surprise bills.</li>
                <li><strong>Qualified &amp; insured</strong> — experienced mechanics, fully insured work.</li>
                <li><strong>Quality parts</strong> — OEM-equivalent parts with warranty on work carried out.</li>
            </ul>
            <a class="btn btn-primary" href="<?= e(url('/about.php')) ?>">More about us</a>
        </div>
        <div class="stat-grid">
            <div class="stat"><strong>10+</strong><span>Years' experience</span></div>
            <div class="stat"><strong>Same-day</strong><span>Mobile service</span></div>
            <div class="stat"><strong>10</strong><span>GM boroughs covered</span></div>
            <div class="stat"><strong>4.9★</strong><span>Average rating</span></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><h2>Areas we cover</h2><p>Mobile mechanic service across Greater Manchester.</p></div>
        <div class="chips">
            <?php foreach ($areas as $a): ?>
                <a class="chip" href="<?= e(url('/areas.php')) ?>"><?= e($a['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section bg-soft">
    <div class="container">
        <div class="section-head"><h2>What our customers say</h2><p>Honest reviews from Greater Manchester drivers.</p></div>
        <div class="grid grid-3">
            <?php foreach ($reviews as $r): ?>
                <figure class="review">
                    <div class="stars"><?= e(render_stars((int)$r['rating'])) ?></div>
                    <blockquote><?= e($r['content']) ?></blockquote>
                    <figcaption><strong><?= e($r['customer_name']) ?></strong> · <?= e($r['location']) ?><br><small><?= e($r['service_used']) ?></small></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <p class="center mt-2"><a class="btn btn-outline" href="<?= e(url('/testimonials.php')) ?>">Read all reviews</a></p>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-inner">
        <div><h2>Need a mechanic today?</h2><p>Book online in under a minute, or call us. We cover all of Greater Manchester.</p></div>
        <div class="cta-buttons">
            <a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book Online</a>
            <a class="btn btn-ghost btn-lg" href="tel:<?= e(setting('phone_href', site_phone())) ?>">Call <?= e(site_phone()) ?></a>
        </div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>

