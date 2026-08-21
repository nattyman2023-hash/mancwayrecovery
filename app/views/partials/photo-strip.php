<?php
declare(strict_types=1);

$photo_items = $photo_items ?? [];
$photo_kicker = $photo_kicker ?? 'On the road';
$photo_title = $photo_title ?? 'Recovery in real situations.';
$photo_intro = $photo_intro ?? 'A closer look at the people, vehicles and care behind every MancWay recovery.';
if (!$photo_items) {
    return;
}
?>

<section class="section photo-strip-section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow"><?= e($photo_kicker) ?></span>
            <h2><?= e($photo_title) ?></h2>
            <p><?= e($photo_intro) ?></p>
        </div>
        <div class="photo-scroller" tabindex="0" aria-label="Recovery photography gallery">
            <?php foreach ($photo_items as $photo): ?>
                <figure class="photo-card photo-scroller-card">
                    <img src="<?= e(asset('img/' . $photo['image'])) ?>" alt="<?= e($photo['alt']) ?>" loading="lazy" decoding="async">
                    <figcaption>
                        <strong><?= e($photo['title']) ?></strong>
                        <span><?= e($photo['text']) ?></span>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <p class="photo-scroll-hint">Swipe or use Shift + mouse wheel to see more.</p>
    </div>
</section>
