<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

http_response_code(404);
$page_title = 'Page not found — ' . site_name();
$active = '';
require APP_DIR . '/views/layout/header.php';
?>
<section class="page-hero" style="--page-hero-image:url('<?= e(asset('img/recovery-hero.jpg')) ?>')">
    <div class="container center narrow">
        <p class="big-404">404</p>
        <h1>Page not found</h1>
        <p>Sorry — we couldn't find that page. It may have moved or no longer exists.</p>
        <p class="mt-2">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">Back to home</a>
            <a class="btn btn-outline" href="<?= e(url('/services')) ?>">View services</a>
            <a class="btn btn-outline" href="tel:<?= e(setting('phone_href', site_phone())) ?>">Call <?= e(site_phone()) ?></a>
        </p>
    </div>
</section>
<?php require APP_DIR . '/views/layout/footer.php'; ?>
