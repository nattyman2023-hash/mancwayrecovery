<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$pages = [
    ['loc' => url('/'),                       'priority' => '1.0', 'freq' => 'weekly'],
    ['loc' => url('/services.php'),            'priority' => '0.9', 'freq' => 'weekly'],
    ['loc' => url('/areas.php'),               'priority' => '0.7', 'freq' => 'monthly'],
    ['loc' => url('/testimonials.php'),        'priority' => '0.6', 'freq' => 'weekly'],
    ['loc' => url('/about.php'),               'priority' => '0.6', 'freq' => 'monthly'],
    ['loc' => url('/faq.php'),                 'priority' => '0.5', 'freq' => 'monthly'],
    ['loc' => url('/booking.php'),             'priority' => '0.8', 'freq' => 'monthly'],
    ['loc' => url('/contact.php'),             'priority' => '0.7', 'freq' => 'monthly'],
];

$services = db()->query("SELECT slug FROM services WHERE is_active = 1")->fetchAll();
foreach ($services as $s) {
    $pages[] = ['loc' => url('/service.php?slug=' . $s['slug']), 'priority' => '0.7', 'freq' => 'weekly'];
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $p) {
    echo "  <url>\n";
    echo "    <loc>" . e($p['loc']) . "</loc>\n";
    echo "    <changefreq>{$p['freq']}</changefreq>\n";
    echo "    <priority>{$p['priority']}</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
