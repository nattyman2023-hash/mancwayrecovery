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
    document.querySelectorAll('.nav-menu a').forEach(function (link) {
      link.addEventListener('click', function () {
        var menu = link.closest('.nav-menu');
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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setupNavToggles(); setupYear(); setupConfirm(); });
  } else {
    setupNavToggles(); setupYear(); setupConfirm();
  }
})();
