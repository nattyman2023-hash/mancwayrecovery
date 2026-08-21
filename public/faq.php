<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$page_title       = 'FAQ — Vehicle Recovery Manchester | ' . site_name();
$page_description = 'Frequently asked questions about vehicle recovery: pricing, booking, what to expect, payment and coverage across Greater Manchester.';
$page_canonical   = url('/faq.php');
$active = 'faq';

$faqs = [
    ['What is vehicle recovery?', 'Vehicle recovery means we come to your car, wherever it is, and safely load and transport it — whether that\'s after a breakdown, an accident, or to move it somewhere specific. No towing it yourself, no waiting rooms.'],
    ['What areas do you cover?', 'We cover all of Greater Manchester — Manchester, Salford, Trafford, Stockport, Tameside, Bury, Bolton, Rochdale, Oldham and Wigan — plus long-distance transport further afield. If you\'re just outside our usual area, give us a call and we\'ll do our best to help.'],
    ['How much does it cost?', 'Every service has a "from" price shown on the site. The final price depends on distance and vehicle type, but you\'ll always be told the full cost before we set off — no surprises.'],
    ['Do you give a quote first?', 'Yes. We\'ll confirm the price when you book or call. For long-distance transport we\'ll confirm the exact cost based on the pickup and drop-off locations.'],
    ['How do I pay?', 'Payment is settled once the recovery is complete. We accept card and bank transfer. You\'ll receive confirmation of the job carried out.'],
    ['Is the recovery insured?', 'Yes. Every recovery is carried out by a fully insured operator, giving you peace of mind from pickup to drop-off.'],
    ['Can you recover my vehicle after an accident?', 'Yes. We provide accident recovery to a bodyshop, insurer-approved compound, or your home — handled carefully and discreetly.'],
    ['Can you transport non-runners or move a vehicle long-distance?', 'Yes. We transport cars, vans and non-runners over any distance — dealer-to-dealer, house moves, or getting a vehicle to a specialist garage.'],
    ['How quickly can you come out?', 'For breakdowns and accidents we aim to reach you as quickly as possible, 24/7, across Greater Manchester. For planned transport jobs, you choose the date and time that suits you.'],
    ['Do I need to be there?', 'It helps, but as long as we can access the vehicle and you\'re contactable by phone, we can often carry out the recovery without you present. We\'ll confirm the details when you book.'],
];
$page_schema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static function (array $faq): array {
        return ['@type' => 'Question', 'name' => $faq[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]]];
    }, $faqs),
];
require APP_DIR . '/views/layout/header.php';
?>

<section class="page-hero" style="--page-hero-image:url('<?= e(asset('img/recovery-assistance.png')) ?>')">
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
        <div><h2>Ready to book?</h2><p>Get your vehicle recovered without the hassle.</p></div>
        <div class="cta-buttons"><a class="btn btn-primary btn-lg" href="<?= e(url('/booking.php')) ?>">Book Recovery</a></div>
    </div>
</section>

<?php require APP_DIR . '/views/layout/footer.php'; ?>
