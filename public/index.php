<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$services = db()->query('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order LIMIT 6')->fetchAll();
$reviews  = db()->query('SELECT * FROM testimonials WHERE is_approved = 1 ORDER BY sort_order, created_at DESC LIMIT 3')->fetchAll();
$areas    = db()->query('SELECT * FROM areas WHERE is_active = 1 ORDER BY sort_order LIMIT 10')->fetchAll();

$page_title       = site_name() . ' — Vehicle Recovery in Manchester | We Come to You';
$page_description = 'MancWay Recovery: breakdown, accident and specialist vehicle recovery across Greater Manchester. Fast, insured, fixed prices — day or night.';
$page_canonical   = url('/');
$active = 'home';
require APP_DIR . '/views/layout/header.php';
?>

<section class="hero">
    <div class="container hero-inner">
        <div class="hero-text">
            <span class="hero-badge">24/7 recovery dispatch — Greater Manchester</span>
            <h1>Manchester's trusted <span class="hl">vehicle recovery</span>. We come to you.</h1>
            <p class="lead">Breakdown, accident and specialist recovery — day or night, across Greater Manchester and beyond. No hidden fees, no fuss.</p>
            <div class="hero-cta">
                <a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book Recovery</a>
                <a class="btn btn-outline btn-lg" href="tel:<?= e(setting('phone_href', site_phone())) ?>"><?= e(site_phone()) ?></a>
            </div>
            <ul class="hero-trust">
                <li>✔ Fully insured</li><li>✔ Fixed, upfront pricing</li><li>✔ 24/7 rapid response</li>
            </ul>
            <div class="hero-stats">
                <div class="hero-stat"><strong>10+</strong><span>Years' experience</span></div>
                <div class="hero-stat"><strong>24/7</strong><span>Availability</span></div>
                <div class="hero-stat"><strong>10</strong><span>GM boroughs covered</span></div>
            </div>
        </div>
        <div class="hero-card" aria-hidden="true">
            <div class="hero-card-head">
                <span class="brand-mark"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.8 2.8-2.4-2.4 2.8-2.8z"/></svg></span>
                <div><strong><?= e(site_name()) ?></strong><small>Recovery &amp; enquiries</small></div>
            </div>
            <a class="hero-quick" href="<?= e(url('/booking.php')) ?>"><span>🚚 Request recovery online</span><span>→</span></a>
            <a class="hero-quick" href="tel:<?= e(setting('phone_href', site_phone())) ?>"><span>📞 Call <?= e(site_phone()) ?></span><span>→</span></a>
            <a class="hero-quick" href="<?= e(url('/contact.php')) ?>"><span>💬 Send a message</span><span>→</span></a>
            <p class="hero-card-foot"><?= e(setting('hours_weekday')) ?></p>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><span class="eyebrow">Our expertise</span><h2>How can we help today?</h2><p>Common recovery jobs — fixed prices, fast dispatch, wherever you are.</p></div>
        <div class="grid grid-3">
            <?php foreach ($services as $s): ?>
                <article class="card service-card">
                    <?php if ((int)$s['sort_order'] === 1): ?><span class="tag">Most requested</span><?php endif; ?>
                    <div class="service-ico"><?= icon_emoji($s['icon']) ?></div>
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
            <span class="eyebrow">Why MancWay</span>
            <h2>The MancWay difference</h2>
            <p class="lead">When your car won't move, we will. Fully equipped recovery vehicles ready to reach you fast, any time of day.</p>
            <ul class="ticks">
                <li><strong>Rapid response</strong> — we reach breakdowns and accidents across Greater Manchester, day or night.</li>
                <li><strong>Transparent pricing</strong> — you see the price before we set off. No surprise bills.</li>
                <li><strong>Fully insured</strong> — experienced recovery operators, fully insured for your peace of mind.</li>
                <li><strong>Careful handling</strong> — modern recovery equipment to load and transport your vehicle safely.</li>
            </ul>
            <a class="btn btn-primary" href="<?= e(url('/about.php')) ?>">More about us</a>
        </div>
        <div class="stat-grid">
            <div class="stat"><strong>10+</strong><span>Years' experience</span></div>
            <div class="stat"><strong>24/7</strong><span>Emergency response</span></div>
            <div class="stat"><strong>10</strong><span>GM boroughs covered</span></div>
            <div class="stat"><strong>4.9★</strong><span>Average rating</span></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><span class="eyebrow">Coverage</span><h2>Areas we cover</h2><p>Vehicle recovery across Greater Manchester — and beyond for long-distance transport.</p></div>
        <div class="chips">
            <?php foreach ($areas as $a): ?>
                <a class="chip" href="<?= e(url('/areas.php')) ?>"><?= e($a['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section bg-soft">
    <div class="container">
        <div class="section-head"><span class="eyebrow">Reviews</span><h2>What our customers say</h2><p>Honest reviews from Greater Manchester drivers.</p></div>
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
        <div><h2>Need recovery today?</h2><p>Request online in under a minute, or call us. We cover all of Greater Manchester.</p></div>
        <div class="cta-buttons">
            <a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book Recovery</a>
            <a class="btn btn-ghost btn-lg" href="tel:<?= e(setting('phone_href', site_phone())) ?>">Call <?= e(site_phone()) ?></a>
        </div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>

