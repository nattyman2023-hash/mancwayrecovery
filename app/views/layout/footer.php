    </main>
    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-col">
                <div class="brand brand-footer">
                    <img class="brand-logo" src="<?= e(asset('img/logo.jpeg')) ?>" alt="<?= e(site_name()) ?> logo">
                </div>
                <p class="footer-tagline"><?= e(setting('tagline')) ?></p>
                <p class="footer-hours"><?= e(setting('hours_weekday')) ?><br><?= e(setting('hours_weekend')) ?></p>
            </div>
            <div class="footer-col">
                <h3 class="footer-h">Explore</h3>
                <ul class="footer-links">
                    <li><a href="<?= e(url('/services.php')) ?>">Services</a></li>
                    <li><a href="<?= e(url('/areas.php')) ?>">Areas Served</a></li>
                    <li><a href="<?= e(url('/testimonials.php')) ?>">Reviews</a></li>
                    <li><a href="<?= e(url('/about.php')) ?>">About Us</a></li>
                    <li><a href="<?= e(url('/faq.php')) ?>">FAQ</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3 class="footer-h">Get in touch</h3>
                <ul class="footer-links">
                    <li><a href="tel:<?= e(setting('phone_href', site_phone())) ?>">Phone: <?= e(site_phone()) ?></a></li>
                    <li><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li>
                    <li><?= e(setting('address')) ?></li>
                </ul>
                <a class="btn btn-primary" href="<?= e(url('/booking.php')) ?>">Book Online</a>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e(site_name()) ?>. All rights reserved.</p>
            <p><?= e(setting('service_radius')) ?></p>
        </div>
    </footer>
    <script src="<?= e(asset('js/main.js')) ?>" defer></script>
</body>
</html>
