<?php
declare(strict_types=1);
$chat_phone = site_phone();
?>

<div class="mw-chat" data-chat-widget data-phone="<?= e(setting('phone_href', $chat_phone)) ?>" data-chat-endpoint="<?= e(url('/api/chat.php')) ?>" data-chat-booking-endpoint="<?= e(url('/api/chat-booking.php')) ?>">
    <button type="button" class="mw-chat-launch" data-chat-open aria-controls="mw-chat-panel" aria-expanded="false">
        <span class="mw-chat-launch-icon" aria-hidden="true">✦</span>
        <span class="mw-chat-launch-copy"><strong>Need a recovery?</strong><small>Chat with MancWay</small></span>
        <span class="mw-chat-launch-dot" aria-hidden="true"></span>
    </button>

    <section class="mw-chat-panel" id="mw-chat-panel" data-chat-panel hidden aria-label="MancWay Recovery assistant">
        <header class="mw-chat-header">
            <div class="mw-chat-identity">
                <span class="mw-chat-avatar" aria-hidden="true">MW</span>
                <span><strong>MancWay assistant</strong><small><span class="mw-chat-online-dot"></span> Online now</small></span>
            </div>
            <button type="button" class="mw-chat-close" data-chat-close aria-label="Close chat">×</button>
        </header>
        <div class="mw-chat-trust"><span>24/7 recovery</span><span>Greater Manchester</span><span>Fast replies</span></div>

        <div class="mw-chat-messages" data-chat-messages role="log" aria-live="polite" aria-relevant="additions">
            <div class="mw-chat-message is-bot">
                <div class="mw-chat-bubble">Hi, I’m the MancWay assistant. I can answer questions about recovery, help with your vehicle and take the details for a booking.</div>
                <div class="mw-chat-actions">
                    <button type="button" class="mw-chat-action is-primary" data-chat-booking-open>Book a recovery</button>
                    <button type="button" class="mw-chat-action" data-chat-prompt="What recovery services do you cover?">Our services</button>
                    <button type="button" class="mw-chat-action" data-chat-prompt="What areas do you cover and how quickly can you come?">Coverage &amp; response</button>
                </div>
            </div>
        </div>

        <div class="mw-chat-booking" data-chat-booking hidden>
            <div class="mw-chat-booking-head"><strong>Quick booking</strong><button type="button" data-chat-booking-close aria-label="Close quick booking">×</button></div>
            <p>Send the essentials and our team will confirm the details by phone.</p>
            <form data-chat-booking-form novalidate>
                <?= csrf_field() ?>
                <input class="mw-chat-honeypot" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="mw-chat-form-grid">
                    <label>Full name *<input type="text" name="name" maxlength="120" required autocomplete="name"></label>
                    <label>Phone *<input type="tel" name="phone" required autocomplete="tel"></label>
                </div>
                <label>Email <span>(optional)</span><input type="email" name="email" autocomplete="email"></label>
                <label>Vehicle registration *<input type="text" name="vehicle_reg" maxlength="20" required autocapitalize="characters" spellcheck="false" placeholder="e.g. AB12 CDE"></label>
                <label>Pickup address *<input type="text" name="address" maxlength="255" required autocomplete="street-address"></label>
                <div class="mw-chat-form-grid">
                    <label>Postcode *<input type="text" name="postcode" maxlength="12" required autocomplete="postal-code"></label>
                    <label>Service
                        <select name="service">
                            <option value="">General recovery</option>
                            <option value="breakdown-recovery">Breakdown recovery</option>
                            <option value="accident-recovery">Accident recovery</option>
                            <option value="vehicle-transport">Vehicle transport</option>
                            <option value="specialist-recovery">Specialist recovery</option>
                        </select>
                    </label>
                </div>
                <label>What happened? <span>(optional)</span><textarea name="notes" rows="2" maxlength="1000" placeholder="Tell us briefly what you need help with"></textarea></label>
                <p class="mw-chat-form-error" data-chat-booking-error role="alert"></p>
                <button type="submit" class="mw-chat-submit">Send booking request <span aria-hidden="true">→</span></button>
            </form>
        </div>

        <form class="mw-chat-composer" data-chat-form>
            <?= csrf_field() ?>
            <label class="sr-only" for="mw-chat-input">Message MancWay assistant</label>
            <input id="mw-chat-input" data-chat-input type="text" maxlength="1200" autocomplete="off" placeholder="Ask about recovery…">
            <button type="submit" aria-label="Send message">↑</button>
        </form>
        <p class="mw-chat-note">For urgent help, call <a href="tel:<?= e(setting('phone_href', $chat_phone)) ?>"><?= e($chat_phone) ?></a>.</p>
    </section>
</div>
