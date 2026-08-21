<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$pages = [
    ['loc' => url('/'),            'priority' => '1.0'],
    ['loc' => url('/services'),    'priority' => '0.9'],
    ['loc' => url('/areas'),       'priority' => '0.8'],
    ['loc' => url('/testimonials.php'), 'priority' => '0.6'],
    ['loc' => url('/about.php'),   'priority' => '0.6'],
    ['loc' => url('/faq.php'),     'priority' => '0.6'],
    ['loc' => url('/booking.php'), 'priority' => '0.8'],
    ['loc' => url('/contact.php'), 'priority' => '0.7'],
];

$services = db()->query('SELECT slug FROM services WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
foreach ($services as $service) {
    $pages[] = ['loc' => url('/services/' . $service['slug']), 'priority' => '0.8'];
}

$areas = db()->query('SELECT slug FROM areas WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
foreach ($areas as $area) {
    $pages[] = ['loc' => url('/areas/' . $area['slug']), 'priority' => '0.8'];
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $page) {
    echo "  <url>\n";
    echo '    <loc>' . e($page['loc']) . "</loc>\n";
    echo '    <changefreq>weekly</changefreq>' . "\n";
    echo '    <priority>' . e($page['priority']) . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
