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
    document.addEventListener('DOMContentLoaded', function () { setupNavToggles(); setupYear(); setupConfirm(); setupPasswordToggles(); setupVehicleLookup(); });
  } else {
    setupNavToggles(); setupYear(); setupConfirm(); setupPasswordToggles(); setupVehicleLookup();
  }
})();
