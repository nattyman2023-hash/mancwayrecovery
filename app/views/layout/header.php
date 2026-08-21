<?php
declare(strict_types=1);
/**
 * Public site header partial.
 * Set these before including: $page_title, $page_description, $page_canonical,
 * $active and optionally $page_schema for page-specific structured data.
 */
$page_title       = $page_title       ?? site_name() . ' — Vehicle Recovery in Manchester';
$page_description = $page_description ?? setting('tagline', "Manchester's trusted vehicle recovery. Breakdown, accident and specialist recovery across Greater Manchester — we come to you, day or night.");
$page_canonical   = $page_canonical   ?? '';
$active           = $active           ?? '';
$page_schema      = $page_schema      ?? [];
$nav = [
    'home'         => ['Home',         url('/')],
    'services'     => ['Services',     url('/services')],
    'areas'        => ['Areas',        url('/areas')],
    'testimonials' => ['Reviews',      url('/testimonials.php')],
    'about'        => ['About',        url('/about.php')],
    'faq'          => ['FAQ',          url('/faq.php')],
];
$business_schema = [
    '@type'       => 'AutomotiveBusiness',
    '@id'         => rtrim(APP_URL, '/') . '/#business',
    'name'        => site_name(),
    'url'         => APP_URL,
    'image'       => [asset('img/logo.jpeg'), asset('img/recovery-hero.jpg')],
    'logo'        => asset('img/logo.jpeg'),
    'telephone'   => site_phone(),
    'email'       => site_email(),
    'priceRange'  => '££',
    'description' => $page_description,
    'areaServed'  => ['@type' => 'AdministrativeArea', 'name' => 'Greater Manchester'],
    'serviceType' => ['Breakdown recovery', 'Accident recovery', 'Vehicle transport', 'Specialist recovery'],
    'address'     => [
        '@type'           => 'PostalAddress',
        'addressLocality' => 'Manchester',
        'addressRegion'   => 'Greater Manchester',
        'addressCountry'  => 'GB',
    ],
    'contactPoint' => [[
        '@type'             => 'ContactPoint',
        'telephone'         => site_phone(),
        'contactType'       => 'customer service',
        'areaServed'        => 'GB',
        'availableLanguage' => 'English',
    ]],
    'openingHoursSpecification' => [[
        '@type'     => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        'opens'     => '00:00',
        'closes'    => '23:59',
    ]],
];
$schema_nodes = [$business_schema];
if (is_array($page_schema) && $page_schema) {
    if (isset($page_schema['@graph'])) {
        $extra_nodes = $page_schema['@graph'];
    } else {
        $page_schema_node = $page_schema;
        unset($page_schema_node['@context']);
        $extra_nodes = [$page_schema_node];
    }
    $schema_nodes = array_merge($schema_nodes, $extra_nodes);
}
$schema_json = json_encode(
    ['@context' => 'https://schema.org', '@graph' => $schema_nodes],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="<?= e($page_description) ?>">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <?php if ($page_canonical): ?><link rel="canonical" href="<?= e($page_canonical) ?>"><?php endif; ?>
    <meta name="theme-color" content="#0b1f3a">
    <link rel="icon" type="image/jpeg" href="<?= e(asset('img/logo.jpeg')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/logo.jpeg')) ?>">
    <meta property="og:title" content="<?= e($page_title) ?>">
    <meta property="og:description" content="<?= e($page_description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_GB">
    <meta property="og:site_name" content="<?= e(site_name()) ?>">
    <meta property="og:url" content="<?= e($page_canonical ?: APP_URL) ?>">
    <meta property="og:image" content="<?= e(asset('img/logo.jpeg')) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <script type="application/ld+json"><?= $schema_json ?></script>
    <?php if (false): ?><script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "AutomotiveBusiness",
      "name": <?= json_encode(site_name()) ?>,
      "image": <?= json_encode(asset('img/logo.jpeg')) ?>,
      "telephone": <?= json_encode(site_phone()) ?>,
      "email": <?= json_encode(setting('email')) ?>,
      "url": <?= json_encode(APP_URL) ?>,
      "priceRange": "££",
      "areaServed": "Greater Manchester",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Manchester",
        "addressRegion": "Greater Manchester",
        "addressCountry": "GB"
      },
      "openingHoursSpecification": [{
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
        "opens": "07:30","closes": "18:00"
      }]
    }
    </script><?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" id="top">
    <div class="container nav-wrap">
        <a class="brand brand-public" href="<?= e(url('/')) ?>" aria-label="<?= e(site_name()) ?> home">
            <img class="brand-logo" src="<?= e(asset('img/logo.jpeg')) ?>" alt="<?= e(site_name()) ?> logo">
        </a>
        <nav class="main-nav" aria-label="Primary">
            <button class="nav-toggle" aria-expanded="false" aria-controls="nav-menu" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <ul id="nav-menu" class="nav-menu">
                <?php foreach ($nav as $key => [$label, $href]):
                    $cls = ($active === $key) ? ' is-active' : ''; ?>
                    <li><a class="nav-link<?= $cls ?>" href="<?= e($href) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="nav-cta">
            <span class="nav-status" aria-label="Available 24 hours a day, 7 days a week">
                <span class="nav-status-dot" aria-hidden="true"></span>
                <span><strong>Available now</strong><small>24/7 recovery</small></span>
            </span>
            <a class="nav-phone" href="tel:<?= e(setting('phone_href', site_phone())) ?>">
                <span class="nav-phone-label">24/7 Recovery Line</span>
                <span class="nav-phone-number"><?= e(site_phone()) ?></span>
            </a>
            <a class="btn btn-primary nav-book" href="<?= e(url('/booking.php')) ?>"><span>Book Recovery</span><span class="nav-book-arrow" aria-hidden="true">→</span></a>
        </div>
    </div>
</header>
<main id="main">
