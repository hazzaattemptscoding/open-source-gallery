/**
 * Advance credit purchase.
 *
 * Posts to /credit/buy, which creates a pending (unspendable) credit and a
 * Stripe session, then hands off to Stripe. The credit only becomes spendable
 * when Stripe's webhook confirms the payment, so nothing here can create value.
 */
(function () {
  'use strict';

  var form = document.getElementById('creditForm');
  if (!form) return;

  var submit = document.getElementById('creditSubmit');
  var errorBox = document.getElementById('credit-error');

  function showError(message) {
    if (!errorBox) return;
    errorBox.textContent = message;
    errorBox.hidden = false;
  }

  function clearError() {
    if (errorBox) errorBox.hidden = true;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    clearError();

    var email = form.elements.email.value.trim();
    var amount = parseInt(form.elements.amount_pence.value, 10);

    if (!email) { showError('Enter your email address.'); return; }
    if (!amount) { showError('Choose an amount.'); return; }

    submit.disabled = true;
    submit.textContent = 'Redirecting to payment…';

    fetch('/credit/buy', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ email: email, amount_pence: amount })
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.data.ok) {
          throw new Error((result.data && result.data.error) || 'Could not start that purchase');
        }

        if (typeof Stripe !== 'function') {
          throw new Error('Payment library did not load. Check your connection and try again.');
        }

        var stripe = Stripe(result.data.publishable_key);
        return stripe.redirectToCheckout({ sessionId: result.data.session_id });
      })
      .then(function (result) {
        // Only reached if redirectToCheckout failed; a success navigates away.
        if (result && result.error) throw new Error(result.error.message);
      })
      .catch(function (err) {
        showError(err.message || 'Something went wrong. Please try again.');
        submit.disabled = false;
        submit.textContent = 'Continue to payment';
      });
  });
})();
