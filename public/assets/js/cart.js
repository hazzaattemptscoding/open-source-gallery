document.querySelectorAll('.cart-line-remove').forEach(btn => {
  btn.addEventListener('click', async () => {
    const line = btn.closest('.cart-line');
    const type = line.dataset.type;
    const id = parseInt(line.dataset.id, 10);

    try {
      const response = await fetch('/cart/remove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type, id }),
      });
      if (!response.ok) throw new Error('Failed to remove');
      location.reload();
    } catch (err) {
      console.error(err);
    }
  });
});

const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
  checkoutForm.addEventListener('submit', async e => {
    e.preventDefault();

    const email = checkoutForm.elements.email.value.trim();
    const errorDiv = document.getElementById('checkout-error');

    if (!email) {
      errorDiv.textContent = 'Email is required';
      errorDiv.style.display = 'block';
      return;
    }

    errorDiv.style.display = 'none';

    try {
      const response = await fetch('/checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
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
        throw new Error('Failed to load Stripe');
      };
      document.head.appendChild(script);
    } catch (err) {
      errorDiv.textContent = err.message || 'An error occurred';
      errorDiv.style.display = 'block';
      console.error(err);
    }
  });
}
