/**
 * Cart page: remove-line animation, checkout submission.
 *
 * Removing a line used to force a full page reload just to get an accurate
 * total (volume discounts are priced server-side, so the total can't be
 * safely recomputed in JS). We keep that server round-trip for correctness
 * but avoid the jarring reload: the line collapses in place, then we
 * re-fetch the cart page and swap in the fresh markup.
 */

function initCartPage() {
  attachRemoveHandlers();
  attachCheckoutHandler();
}

function attachRemoveHandlers() {
  document.querySelectorAll('.cart-line-remove').forEach(btn => {
    btn.addEventListener('click', () => handleRemove(btn));
  });
}

async function handleRemove(btn) {
  const line = btn.closest('.cart-line');
  const type = line.dataset.type;
  const id = parseInt(line.dataset.id, 10);
  btn.disabled = true;

  try {
    const response = await fetch('/cart/remove', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id }),
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('Failed to remove');
    collapseLine(line);
  } catch (err) {
    console.error(err);
    btn.disabled = false;
    showToast('Could not remove item. Please try again.');
  }
}

function collapseLine(line) {
  line.style.maxHeight = line.scrollHeight + 'px';
  line.classList.add('is-collapsible');
  // Two rAFs: first commits the explicit max-height, second starts the
  // transition to 0 — otherwise the browser can coalesce both style writes
  // into one frame and skip the animation entirely.
  requestAnimationFrame(() => {
    requestAnimationFrame(() => line.classList.add('is-removing'));
  });
  window.setTimeout(refreshCart, 260);
}

async function refreshCart() {
  try {
    const response = await fetch(location.pathname, { credentials: 'same-origin' });
    if (!response.ok) throw new Error('refresh failed');
    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const freshMain = doc.querySelector('.cart-page');
    const currentMain = document.querySelector('.cart-page');
    if (!freshMain || !currentMain) throw new Error('missing .cart-page');
    currentMain.replaceWith(freshMain);
    initCartPage();
  } catch (err) {
    console.error(err);
    location.reload();
  }
}

function attachCheckoutHandler() {
  const checkoutForm = document.getElementById('checkout-form');
  if (!checkoutForm) return;

  checkoutForm.addEventListener('submit', async e => {
    e.preventDefault();

    const email = checkoutForm.elements.email.value.trim();
    const errorDiv = document.getElementById('checkout-error');

    if (!email) {
      showFormError(errorDiv, 'Email is required');
      return;
    }

    hideFormError(errorDiv);

    // Read the box rather than assuming: consent has to reflect what the
    // customer actually did. The element is absent on any page that does not
    // offer the option, so guard before touching .checked.
    const consentBox = checkoutForm.elements.marketing_consent;
    const marketingConsent = Boolean(consentBox && consentBox.checked);

    try {
      const response = await fetch('/checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, marketing_consent: marketingConsent }),
        credentials: 'same-origin',
      });

      if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.error || 'Checkout failed');
      }

      const data = await response.json();
      if (!data.session_id || !data.publishable_key) {
        throw new Error('Invalid response from server');
      }

      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.onload = () => {
        const stripe = window.Stripe(data.publishable_key);
        stripe.redirectToCheckout({ sessionId: data.session_id });
      };
      script.onerror = () => {
        showFormError(errorDiv, 'Failed to load Stripe payment form. Please check your internet connection and try again.');
        console.error('Failed to load Stripe.js from CDN');
      };
      document.head.appendChild(script);
    } catch (err) {
      showFormError(errorDiv, err.message || 'An error occurred');
      console.error(err);
    }
  });
}

function showFormError(el, message) {
  el.textContent = message;
  el.style.display = 'block';
  el.classList.remove('is-visible');
  void el.offsetWidth; // restart the entrance animation if shown twice in a row
  el.classList.add('is-visible');
}

function hideFormError(el) {
  el.style.display = 'none';
  el.classList.remove('is-visible');
}

function showToast(message) {
  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  window.clearTimeout(toast._hideTimer);
  requestAnimationFrame(() => toast.classList.add('is-visible'));
  toast._hideTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 2600);
}

initCartPage();
