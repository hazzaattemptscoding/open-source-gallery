/**
 * Setup-wizard behaviours, shared by the storage-mode, admin-mode and
 * email-setup steps.
 *
 * These handlers used to live as an inline <script> in each of those three
 * views, with toggleHelp() copy-pasted identically into all of them and invoked
 * through onclick="". The app's Content-Security-Policy (default-src 'self',
 * no 'unsafe-inline') refused both the blocks and the attributes, so none of it
 * ran: help panels would not open and provider presets did not fill the form.
 *
 * Everything here is driven by data attributes so the markup stays declarative
 * and no page needs its own script block:
 *
 *   <div data-help-toggle>          click toggles the .hidden class on the next
 *                                   sibling and rotates a .help-toggle-icon
 *   <label class="mode-option">     click selects the radio inside it
 *   <button data-provider="gmail">  fills smtp_host / smtp_port from a preset
 */

(function () {
  'use strict';

  var SMTP_PRESETS = {
    gmail: { host: 'smtp.gmail.com', port: 587 },
    outlook: { host: 'smtp-mail.outlook.com', port: 587 },
    ionos: { host: 'smtp.ionos.com', port: 587 },
    custom: { host: '', port: 587 },
  };

  function init() {
    document.querySelectorAll('[data-help-toggle]').forEach(function (el) {
      el.addEventListener('click', function () {
        var content = el.nextElementSibling;
        if (!content) {
          return;
        }
        var hidden = content.classList.toggle('hidden');
        var icon = el.querySelector('.help-toggle-icon');
        if (icon) {
          // Rotation lives here rather than in CSS because the icon has no
          // state class of its own to hang a rule off.
          icon.style.transform = hidden ? '' : 'rotate(90deg)';
        }
      });
    });

    document.querySelectorAll('.mode-option').forEach(function (label) {
      label.addEventListener('click', function () {
        document.querySelectorAll('.mode-option').forEach(function (other) {
          other.classList.remove('active');
        });
        label.classList.add('active');
        var radio = label.querySelector('input[type="radio"]');
        if (radio) {
          radio.checked = true;
        }
      });
    });

    document.querySelectorAll('[data-provider]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var preset = SMTP_PRESETS[btn.dataset.provider];
        if (!preset) {
          return;
        }
        document.querySelectorAll('.provider-option').forEach(function (other) {
          other.classList.remove('active');
        });
        btn.classList.add('active');

        var field = document.getElementById('provider');
        if (field) {
          field.value = btn.dataset.provider;
        }
        var host = document.getElementById('smtp_host');
        var port = document.getElementById('smtp_port');
        // "Other" has a blank preset host, and clearing the field is deliberate:
        // it wipes a previously-selected provider's server so the admin starts
        // from an empty box rather than editing smtp.gmail.com by hand.
        if (host) {
          host.value = preset.host;
        }
        if (port) {
          port.value = preset.port;
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
