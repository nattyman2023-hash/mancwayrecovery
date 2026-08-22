<?php
declare(strict_types=1);
$chat_phone = site_phone();
?>

<div class="mw-chat" data-chat-widget data-phone="<?= e(setting('phone_href', $chat_phone)) ?>" data-chat-endpoint="<?= e(url('/api/chat.php')) ?>" data-chat-booking-endpoint="<?= e(url('/api/chat-booking.php')) ?>" data-chat-handover-endpoint="<?= e(url('/api/chat-handover.php')) ?>">
    <button type="button" class="mw-chat-launch" data-chat-open aria-controls="mw-chat-panel" aria-expanded="false">
        <span class="mw-chat-launch-icon" aria-hidden="true">✦</span>
        <span class="mw-chat-launch-copy"><strong>Need a recovery?</strong><small>Chat with MancWay</small></span>
        <span class="mw-chat-launch-dot" aria-hidden="true"></span>
    </button>

    <section class="mw-chat-panel" id="mw-chat-panel" data-chat-panel hidden aria-label="MancWay Recovery assistant">
        <header class="mw-chat-header">
            <div class="mw-chat-identity">
                <span class="mw-chat-avatar"><img src="<?= e(asset('img/chat-avatar.png')) ?>" alt=""></span>
                <span><strong>MancWay Assistant</strong><small><span class="mw-chat-online-dot"></span> Virtual Recovery Assistant · Online</small></span>
            </div>
            <button type="button" class="mw-chat-close" data-chat-close aria-label="Close chat">×</button>
        </header>
        <div class="mw-chat-trust"><span>24/7 recovery</span><span>Greater Manchester</span><span>Fast replies</span><button type="button" class="mw-chat-whatsapp-bar" data-chat-whatsapp-direct><span aria-hidden="true">&#x1F7E2;</span> WhatsApp Us</button></div>

        <div class="mw-chat-messages" data-chat-messages role="log" aria-live="polite" aria-relevant="additions">
            <div class="mw-chat-message is-bot">
                <div class="mw-chat-bubble">Hi, I’m the MancWay assistant. I can answer questions about recovery, help with your vehicle and take the details for a booking.</div>
                <div class="mw-chat-actions">
                    <button type="button" class="mw-chat-action is-primary" data-chat-booking-open>🚨 Book a Recovery</button>
                    <button type="button" class="mw-chat-action is-whatsapp" data-chat-whatsapp-direct>💬 WhatsApp Us</button>
                    <button type="button" class="mw-chat-action" data-chat-prompt="What recovery services do you cover?">🛠 Our Services</button>
                    <button type="button" class="mw-chat-action" data-chat-prompt="What areas do you cover and how quickly can you come?">📍 Coverage &amp; Response</button>
                    <button type="button" class="mw-chat-action" data-chat-handover-open>👤 Speak to a Human</button>
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
                <label>Email address *<input type="email" name="email" required autocomplete="email"></label>
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
                <label>Estimated recovery miles <span>(optional · £2.50 per mile)</span><input type="number" name="distance_miles" min="0" max="10000" step="0.1" inputmode="decimal" placeholder="e.g. 18"></label>
                <label>Destination <span>(optional)</span><input type="text" name="destination" maxlength="255" placeholder="Where does the vehicle need to go?"></label>
                <label>What happened? <span>(optional)</span><textarea name="notes" rows="2" maxlength="1000" placeholder="Tell us briefly what you need help with"></textarea></label>
                <p class="mw-chat-form-error" data-chat-booking-error role="alert"></p>
                <button type="submit" class="mw-chat-submit">Send booking request <span aria-hidden="true">→</span></button>
            </form>
        </div>

        <div class="mw-chat-handover" data-chat-handover hidden>
            <div class="mw-chat-handover-head">
                <div><strong>Speak to the MancWay Recovery Team</strong><span>Human handover</span></div>
                <button type="button" data-chat-handover-close aria-label="Back to assistant">&times;</button>
            </div>
            <p>Continue your conversation with a member of our team on WhatsApp. We will carry over the details you&apos;ve already provided so you don&apos;t have to start again.</p>
            <p class="mw-chat-handover-status" data-chat-handover-status role="status" aria-live="polite"></p>
            <div class="mw-chat-handover-saved" data-chat-handover-saved hidden>
                <p class="mw-chat-handover-ref">Saved reference: <strong data-chat-handover-reference>Pending</strong></p>
                <div class="mw-chat-handover-actions">
                    <a class="mw-chat-handover-primary" data-chat-whatsapp href="#" target="_blank" rel="noopener noreferrer">&#x1F7E2; Open WhatsApp</a>
                    <a class="mw-chat-action" data-chat-call href="tel:07480255634">&#x1F4DE; Call 07480 255634</a>
                </div>
                <div class="mw-chat-handover-fallbacks">
                    <button type="button" class="mw-chat-action" data-chat-whatsapp-retry>Try WhatsApp Again</button>
                    <button type="button" class="mw-chat-action" data-chat-callback-open>Leave Your Number for a Callback</button>
                </div>
                <form class="mw-chat-callback" data-chat-callback-form hidden novalidate>
                    <label>Phone number *<input type="tel" name="callback_phone" required autocomplete="tel"></label>
                    <p class="mw-chat-form-error" data-chat-callback-error role="alert"></p>
                    <button type="submit" class="mw-chat-submit">Request a callback <span aria-hidden="true">&rarr;</span></button>
                </form>
            </div>
        </div>

        <div class="mw-chat-quick-tools"><button type="button" class="mw-chat-whatsapp-bar" data-chat-whatsapp-direct><span aria-hidden="true">&#x1F7E2;</span> WhatsApp the team</button></div>
        <form class="mw-chat-composer" data-chat-form>
            <?= csrf_field() ?>
            <label class="sr-only" for="mw-chat-input">Message MancWay assistant</label>
            <input id="mw-chat-input" data-chat-input type="text" maxlength="1200" autocomplete="off" placeholder="Ask about recovery…">
            <button type="submit" aria-label="Send message">↑</button>
        </form>
        <p class="mw-chat-note">For urgent help, call <a href="tel:<?= e(setting('phone_href', $chat_phone)) ?>"><?= e($chat_phone) ?></a>.</p>
    </section>
</div>
