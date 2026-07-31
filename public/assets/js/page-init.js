/**
 * Per-page bootstrap for the public site.
 *
 * This exists because the app sends a strict Content-Security-Policy
 * (default-src 'self', no 'unsafe-inline'). Every page used to carry its own
 * inline <script> to call A11y.init() and wire up form validation, and every
 * one of those blocks was silently refused by the browser — so accessibility
 * setup and realtime validation never actually ran anywhere on the public site.
 *
 * The fix is declarative: pages describe what they need with data attributes and
 * this file, loaded as an external script, reads them. Adding a behaviour to a
 * page is now an attribute, never a new inline block.
 *
 *   <form data-validate="email">          realtime email validation + autofocus
 *   <section data-region="Downloads">     labelled ARIA landmark
 *
 * A11y.init() runs on every page unconditionally; it is idempotent and there is
 * no page that should opt out of it.
 */

(function () {
  'use strict';

  function init() {
    if (typeof A11y !== 'undefined') {
      A11y.init(document);
    }

    // Forms opting into realtime validation. Only email is described here
    // because it is the only field the public site validates client-side —
    // everything else is checked server-side, which is where it has to be
    // enforced anyway.
    document.querySelectorAll('form[data-validate~="email"]').forEach(function (form) {
      if (typeof UIFeedback === 'undefined') {
        return;
      }
      UIFeedback.enableRealtimeValidation(form, {
        email: {
          required: true,
          email: true,
          requiredMessage: 'Email is required',
          emailMessage: 'Enter a valid email address',
        },
      });
      UIFeedback.autoFocusForm(form);
    });

    // Promote a section to a labelled landmark so screen readers can jump to it.
    document.querySelectorAll('[data-region]').forEach(function (el) {
      el.setAttribute('role', 'region');
      el.setAttribute('aria-label', el.dataset.region);
    });
  }

  // `defer` guarantees the DOM is parsed before this runs, but the file is also
  // safe to load without it.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
