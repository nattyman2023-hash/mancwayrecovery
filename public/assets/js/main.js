/* MancWay Recovery — site JS */
(function () {
  'use strict';

  // Mobile nav toggle (used on public site + admin)
  function setupNavToggles() {
    document.querySelectorAll('.nav-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = document.getElementById(btn.getAttribute('aria-controls') || '');
        if (!target) return;
        var open = target.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });

    // Close menu when a link is clicked (mobile)
    document.querySelectorAll('.nav-menu a, .admin-nav a').forEach(function (link) {
      link.addEventListener('click', function () {
        var menu = link.closest('.nav-menu, .admin-nav');
        if (menu) menu.classList.remove('open');
        var btn = document.querySelector('.nav-toggle');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Home hero slideshow. Images after the first are loaded only as they are
  // needed so the richer hero does not make the first paint unnecessarily heavy.
  function setupHeroSlideshows() {
    document.querySelectorAll('[data-hero-slideshow]').forEach(function (root) {
      var slides = Array.prototype.slice.call(root.querySelectorAll('[data-hero-slide]'));
      var dots = Array.prototype.slice.call(root.querySelectorAll('[data-hero-dot]'));
      var previous = root.querySelector('[data-hero-prev]');
      var next = root.querySelector('[data-hero-next]');
      var pause = root.querySelector('[data-hero-pause]');
      if (slides.length < 2) return;

      var current = 0;
      var timer = null;
      var manuallyPaused = false;
      var hovered = false;
      var focused = false;
      var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var interval = parseInt(root.getAttribute('data-hero-interval') || '6500', 10);

      function loadSlide(slide) {
        var image = slide.getAttribute('data-hero-image');
        if (!image || slide.dataset.loaded === 'true') return;
        slide.style.backgroundImage = 'url("' + image.replace(/"/g, '\\"') + '")';
        slide.dataset.loaded = 'true';
      }

      function showSlide(index) {
        current = (index + slides.length) % slides.length;
        loadSlide(slides[current]);
        loadSlide(slides[(current + 1) % slides.length]);
        slides.forEach(function (slide, i) {
          slide.classList.toggle('is-active', i === current);
        });
        dots.forEach(function (dot, i) {
          var active = i === current;
          dot.classList.toggle('is-active', active);
          dot.setAttribute('aria-selected', active ? 'true' : 'false');
        });
      }

      function schedule() {
        window.clearTimeout(timer);
        if (reducedMotion || manuallyPaused || hovered || focused) return;
        timer = window.setTimeout(function () {
          showSlide(current + 1);
          schedule();
        }, Math.max(4000, interval));
      }

      function moveBy(amount) {
        showSlide(current + amount);
        schedule();
      }

      if (previous) previous.addEventListener('click', function () { moveBy(-1); });
      if (next) next.addEventListener('click', function () { moveBy(1); });
      dots.forEach(function (dot, i) {
        dot.addEventListener('click', function () {
          showSlide(i);
          schedule();
        });
      });
      if (pause) {
        pause.addEventListener('click', function () {
          manuallyPaused = !manuallyPaused;
          pause.textContent = manuallyPaused ? 'Play' : 'Pause';
          pause.setAttribute('aria-label', manuallyPaused ? 'Play slideshow' : 'Pause slideshow');
          pause.setAttribute('aria-pressed', manuallyPaused ? 'true' : 'false');
          schedule();
        });
      }
      root.addEventListener('mouseenter', function () { hovered = true; window.clearTimeout(timer); });
      root.addEventListener('mouseleave', function () { hovered = false; schedule(); });
      root.addEventListener('focusin', function () { focused = true; window.clearTimeout(timer); });
      root.addEventListener('focusout', function () {
        window.setTimeout(function () {
          focused = root.contains(document.activeElement);
          schedule();
        }, 0);
      });
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) window.clearTimeout(timer); else schedule();
      });
      showSlide(0);
      schedule();
    });
  }

  // Friendly site assistant. It works with deterministic business replies
  // until a DeepSeek key is added in CRM settings, then uses the server proxy.
  function setupChatWidgets() {
    document.querySelectorAll('[data-chat-widget]').forEach(function (widget) {
      var panel = widget.querySelector('[data-chat-panel]');
      var openButton = widget.querySelector('[data-chat-open]');
      var closeButton = widget.querySelector('[data-chat-close]');
      var messages = widget.querySelector('[data-chat-messages]');
      var chatForm = widget.querySelector('[data-chat-form]');
      var input = widget.querySelector('[data-chat-input]');
      var booking = widget.querySelector('[data-chat-booking]');
      var bookingForm = widget.querySelector('[data-chat-booking-form]');
      var bookingError = widget.querySelector('[data-chat-booking-error]');
      var composer = widget.querySelector('.mw-chat-composer');
      var history = [];
      var endpoint = widget.getAttribute('data-chat-endpoint') || '/api/chat.php';
      var bookingEndpoint = widget.getAttribute('data-chat-booking-endpoint') || '/api/chat-booking.php';

      if (!panel || !openButton || !messages || !chatForm || !input) return;

      function scrollMessages() {
        messages.scrollTop = messages.scrollHeight;
      }

      function addMessage(text, type, actions) {
        var row = document.createElement('div');
        row.className = 'mw-chat-message ' + (type === 'user' ? 'is-user' : 'is-bot');
        var bubble = document.createElement('div');
        bubble.className = 'mw-chat-bubble';
        bubble.textContent = text;
        row.appendChild(bubble);
        if (Array.isArray(actions) && actions.length) {
          var actionWrap = document.createElement('div');
          actionWrap.className = 'mw-chat-actions';
          actions.forEach(function (action) {
            if (!action || !action.label) return;
            if (action.href) {
              var link = document.createElement('a');
              link.className = 'mw-chat-action' + (action.type === 'payment' ? ' is-primary' : '');
              link.href = action.href;
              link.textContent = action.label;
              if (action.external) { link.target = '_blank'; link.rel = 'noopener'; }
              actionWrap.appendChild(link);
              return;
            }
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'mw-chat-action' + (action.type === 'booking' ? ' is-primary' : '');
            button.textContent = action.label;
            if (action.type === 'booking') button.setAttribute('data-chat-booking-open', '');
            actionWrap.appendChild(button);
          });
          row.appendChild(actionWrap);
        }
        messages.appendChild(row);
        scrollMessages();
      }

      function showTyping() {
        var row = document.createElement('div');
        row.className = 'mw-chat-message is-bot is-typing';
        row.innerHTML = '<div class="mw-chat-bubble"><span></span><span></span><span></span></div>';
        messages.appendChild(row);
        scrollMessages();
        return row;
      }

      function togglePanel(open) {
        panel.hidden = !open;
        openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        widget.classList.toggle('is-open', open);
        if (open) {
          window.setTimeout(function () { input.focus(); scrollMessages(); }, 80);
        }
      }

      function toggleBooking(open) {
        booking.hidden = !open;
        composer.hidden = open;
        if (open) {
          bookingError.textContent = '';
          window.setTimeout(function () {
            var first = bookingForm.querySelector('input[name="name"]');
            if (first) first.focus();
            scrollMessages();
          }, 80);
        } else {
          input.focus();
        }
      }

      function sendMessage(message) {
        var csrf = chatForm.querySelector('input[name="csrf_token"]');
        if (!message || !csrf) return;
        addMessage(message, 'user');
        history.push({ role: 'user', content: message });
        input.value = '';
        input.disabled = true;
        var typing = showTyping();
        fetch(endpoint, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ csrf_token: csrf.value, message: message, history: history.slice(0, -1).slice(-10) })
        })
          .then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
              return { ok: response.ok && data.ok === true, data: data };
            });
          })
          .then(function (result) {
            var data = result.data || {};
            if (!result.ok) throw new Error(data.message || 'The assistant is unavailable.');
            var reply = data.reply || 'I’m sorry, I could not answer that just now. Please call us and we’ll help.';
            history.push({ role: 'assistant', content: reply });
            addMessage(reply, 'bot', data.actions || []);
          })
          .catch(function (error) {
            addMessage(error.message || 'Please call us and we’ll help.', 'bot', [{ type: 'booking', label: 'Book a recovery' }]);
          })
          .finally(function () {
            if (typing && typing.parentNode) typing.parentNode.removeChild(typing);
            input.disabled = false;
            input.focus();
          });
      }

      openButton.addEventListener('click', function () { togglePanel(panel.hidden); });
      if (closeButton) closeButton.addEventListener('click', function () { togglePanel(false); });
      chatForm.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage(input.value.trim());
      });
      messages.addEventListener('click', function (event) {
        var prompt = event.target.closest('[data-chat-prompt]');
        if (prompt) {
          sendMessage(prompt.getAttribute('data-chat-prompt') || 'How can you help?');
          return;
        }
        if (event.target.closest('[data-chat-booking-open]')) toggleBooking(true);
      });
      widget.addEventListener('click', function (event) {
        if (event.target.closest('[data-chat-booking-open]')) toggleBooking(true);
        if (event.target.closest('[data-chat-booking-close]')) toggleBooking(false);
      });
      if (bookingForm) {
        bookingForm.addEventListener('submit', function (event) {
          event.preventDefault();
          bookingError.textContent = '';
          var values = {};
          new FormData(bookingForm).forEach(function (value, key) { values[key] = value; });
          var submit = bookingForm.querySelector('button[type="submit"]');
          if (submit) { submit.disabled = true; submit.textContent = 'Sending…'; }
          fetch(bookingEndpoint, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(values)
          })
            .then(function (response) {
              return response.json().catch(function () { return {}; }).then(function (data) {
                return { ok: response.ok && data.ok === true, data: data };
              });
            })
            .then(function (result) {
              if (!result.ok) throw new Error(result.data.message || 'Please check the booking details.');
              toggleBooking(false);
              bookingForm.reset();
              var bookingActions = [{ type: 'call', label: 'Call the recovery team', href: 'tel:' + (widget.dataset.phone || '') }];
              if (result.data.payment_url) bookingActions.unshift({ type: 'payment', label: 'Pay £50 deposit', href: result.data.payment_url, external: true });
              if (result.data.invoice_url) bookingActions.push({ type: 'invoice', label: 'View invoice', href: result.data.invoice_url, external: true });
              addMessage(result.data.message + (result.data.reference ? ' Your reference is ' + result.data.reference + '.' : ''), 'bot', bookingActions);
            })
            .catch(function (error) { bookingError.textContent = error.message; })
            .finally(function () {
              if (submit) { submit.disabled = false; submit.innerHTML = 'Send booking request <span aria-hidden="true">→</span>'; }
            });
        });
      }
    });
  }

  // Auto-expand current year in footer (already server-side; this is a safety net)
  function setupYear() {
    document.querySelectorAll('[data-year]').forEach(function (el) {
      el.textContent = String(new Date().getFullYear());
    });
  }

  // Friendly confirmation for destructive actions
  function setupConfirm() {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      var q = el.getAttribute('data-confirm') || 'Are you sure?';
      el.addEventListener('submit', function (e) {
        if (!window.confirm(q)) e.preventDefault();
      });
    });
  }

  // Let users check password and API-key text before submitting, without
  // changing how the value is posted or stored.
  function setupPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
      var targetId = button.getAttribute('data-password-target') || '';
      var input = document.getElementById(targetId);
      if (!input) return;

      button.addEventListener('click', function () {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.textContent = showing ? 'Show' : 'Hide';
        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
        button.setAttribute('aria-label', showing ? 'Show value' : 'Hide value');
      });
    });
  }

  function setupPricingCalculators() {
    document.querySelectorAll('[data-pricing-calculator]').forEach(function (form) {
      var service = form.querySelector('#service_id');
      var miles = form.querySelector('#distance_miles');
      var summary = form.querySelector('[data-pricing-summary]');
      if (!service || !miles || !summary) return;
      var total = summary.querySelector('[data-pricing-total]');
      var deposit = summary.querySelector('[data-pricing-deposit]');
      var balance = summary.querySelector('[data-pricing-balance]');
      var config = {};
      try { config = JSON.parse(form.getAttribute('data-pricing-config') || '{}'); } catch (e) { config = {}; }
      var currency = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' });

      function update() {
        var item = config[service.value];
        var distance = Math.max(0, parseFloat(miles.value || '0') || 0);
        if (!item) {
          total.textContent = 'Choose a service';
          deposit.textContent = currency.format(50);
          balance.textContent = '—';
          return;
        }
        var quote = (Number(item.base) || 0) + (distance * (Number(item.rate) || 2.5));
        var due = Number(item.deposit) || 50;
        total.textContent = currency.format(quote);
        deposit.textContent = currency.format(due);
        balance.textContent = currency.format(Math.max(0, quote - due));
      }

      service.addEventListener('change', update);
      miles.addEventListener('input', update);
      update();
    });
  }

  // Look up vehicle details through the server-side DVLA proxy. The API key
  // never reaches the browser; the existing make/model inputs stay editable.
  function setupVehicleLookup() {
    document.querySelectorAll('[data-vehicle-lookup]').forEach(function (lookup) {
      var registration = lookup.querySelector('#vehicle_reg');
      var button = lookup.querySelector('[data-vehicle-lookup-button]');
      var status = lookup.querySelector('[data-vehicle-lookup-status]');
      var form = lookup.closest('form');
      var make = form ? form.querySelector('#vehicle_make') : null;
      var model = form ? form.querySelector('#vehicle_model') : null;
      var csrf = form ? form.querySelector('input[name="csrf_token"]') : null;
      var endpoint = lookup.getAttribute('data-endpoint') || '/api/dvla-vehicle.php';

      if (!registration || !button || !status || !csrf) return;

      function showStatus(message, type) {
        status.textContent = message || '';
        status.className = 'vehicle-lookup-status' + (type ? ' is-' + type : '');
      }

      function vehicleSummary(vehicle) {
        var details = [];
        if (vehicle.yearOfManufacture) details.push(String(vehicle.yearOfManufacture));
        if (vehicle.colour) details.push(String(vehicle.colour));
        if (vehicle.fuelType) details.push(String(vehicle.fuelType).toLowerCase());
        if (vehicle.motStatus) details.push('MOT: ' + String(vehicle.motStatus).toLowerCase());
        return details.length ? ' ' + details.join(' · ') + '.' : '';
      }

      function lookupVehicle() {
        var normalized = registration.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (!normalized || normalized.length > 8) {
          showStatus('Enter a valid registration, for example AB12 CDE.', 'error');
          registration.focus();
          return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.dataset.originalText = button.dataset.originalText || button.textContent;
        button.textContent = 'Checking…';
        showStatus('Checking DVLA vehicle details…', 'loading');

        var body = new URLSearchParams();
        body.set('registrationNumber', normalized);
        body.set('csrf_token', csrf.value);

        fetch(endpoint, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        })
          .then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
              return { ok: response.ok && data.ok === true, data: data };
            });
          })
          .then(function (result) {
            var data = result.data || {};
            if (!result.ok) {
              showStatus(data.message || 'We could not find that vehicle. You can enter the details manually.', 'error');
              return;
            }

            var vehicle = data.vehicle || {};
            registration.value = String(vehicle.registrationNumber || normalized);
            if (make && vehicle.make) make.value = vehicle.make;
            if (model && vehicle.model) model.value = vehicle.model;

            var message = data.message || 'Vehicle details found.';
            if (!vehicle.model && model) message += ' DVLA does not provide the model here, so please add it if known.';
            showStatus(message + vehicleSummary(vehicle), 'success');
          })
          .catch(function () {
            showStatus('Vehicle lookup is temporarily unavailable. You can enter the details manually.', 'error');
          })
          .finally(function () {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.textContent = button.dataset.originalText || 'Find details';
          });
      }

      registration.addEventListener('input', function () {
        registration.value = registration.value.toUpperCase();
      });
      registration.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
          event.preventDefault();
          lookupVehicle();
        }
      });
      button.addEventListener('click', lookupVehicle);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setupNavToggles(); setupHeroSlideshows(); setupChatWidgets(); setupPricingCalculators(); setupYear(); setupConfirm(); setupPasswordToggles(); setupVehicleLookup(); });
  } else {
    setupNavToggles(); setupHeroSlideshows(); setupChatWidgets(); setupPricingCalculators(); setupYear(); setupConfirm(); setupPasswordToggles(); setupVehicleLookup();
  }
})();
