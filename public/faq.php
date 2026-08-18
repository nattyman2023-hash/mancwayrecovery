<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$page_title       = 'FAQ — Mobile Mechanic Manchester | ' . site_name();
$page_description = 'Frequently asked questions about mobile mechanic services: pricing, booking, what to expect, warranty, payment and coverage across Greater Manchester.';
$page_canonical   = url('/faq.php');
$active = 'faq';
require APP_DIR . '/views/layout/header.php';

$faqs = [
    ['What is a mobile mechanic?', 'A mobile mechanic is a fully qualified mechanic who comes to your home, workplace or roadside instead of you going to a garage. We carry professional tools and parts in a fully-equipped van, so most jobs can be done on-site.'],
    ['What areas do you cover?', 'We cover all of Greater Manchester — Manchester, Salford, Trafford, Stockport, Tameside, Bury, Bolton, Rochdale, Oldham and Wigan. If you\'re just outside, give us a call and we\'ll do our best to help.'],
    ['How much does it cost?', 'Every service has a "from" price shown on the site. The final price depends on your vehicle and the exact parts required, but you\'ll always be told the full cost before any work starts — no surprises.'],
    ['Do you give a quote first?', 'Yes. For most jobs we\'ll confirm the price when you book. For anything that needs inspecting first, we\'ll tell you what we find and the cost before carrying out any work.'],
    ['How do I pay?', 'Payment is settled once the work is complete. We accept card and bank transfer. You\'ll receive confirmation of the work carried out.'],
    ['Is the work guaranteed?', 'Yes. All work we carry out is covered by our workmanship warranty, and parts carry their manufacturer warranty. We stand behind our work.'],
    ['Can you do an MOT?', 'We can prepare your car for its MOT and carry out any repairs needed to help it pass. The MOT test itself must be done at a registered MOT testing station — we can advise on the nearest options.'],
    ['What if you can\'t fix it on the spot?', 'If a job needs specialist equipment or a workshop, we\'ll tell you honestly and can arrange recovery or refer you to a trusted partner. We won\'t charge you for work we can\'t complete.'],
    ['How quickly can you come out?', 'For breakdowns we aim to reach you as quickly as possible across Greater Manchester. For booked services, you choose the date and time that suits you.'],
    ['Do I need to be there?', 'It helps, but as long as we can access the vehicle and you\'re contactable, we can often carry out the work with the keys left securely. We\'ll confirm the details when you book.'],
];
?>

<section class="page-hero">
    <div class="container">
        <span class="pill">FAQ</span>
        <h1>Frequently asked questions</h1>
        <p class="lead">Can't find your answer? <a href="<?= e(url('/contact.php')) ?>">Send us a message</a> and we'll get back to you.</p>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <div class="faq-list">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
                <details class="faq" <?= $i === 0 ? 'open' : '' ?>>
                    <summary><?= e($q) ?></summary>
                    <p><?= e($a) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-inner">
        <div><h2>Ready to book?</h2><p>Get your car sorted without leaving home.</p></div>
        <div class="cta-buttons"><a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book a Mechanic</a></div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
