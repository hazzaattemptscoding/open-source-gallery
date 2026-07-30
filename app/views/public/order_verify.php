<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>View Order: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

<main style="max-width: 500px; margin: 0 auto; padding: 4rem 1rem;">
  <h1 style="font-family: 'Newsreader', serif; font-size: 2.25rem; font-weight: 700; margin: 0 0 0.5rem; letter-spacing: -0.02em;">View Your Order</h1>
  <p style="color: #787774; margin: 0 0 2rem; font-size: 0.9rem;">Order #<?= e(substr($orderToken, 0, 8)) ?></p>

  <div style="background: #f7f6f3; padding: 2rem; border-radius: 8px;">
    <form id="order-verify-form" method="get" aria-label="Order verification form" novalidate>
      <input type="hidden" name="order_token" value="<?= e($orderToken) ?>">

      <div class="form-group">
        <label for="verify-email" class="form-label">Email Address</label>
        <input
          type="email"
          id="verify-email"
          name="email"
          class="form-input"
          placeholder="your@email.com"
          required
          aria-required="true"
          aria-describedby="email-help">
        <span id="email-help" class="form-helper">Enter the email address you used for your purchase.</span>
      </div>

      <button type="submit" style="width: 100%; padding: 0.75rem 1.5rem; background: #111; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 160ms ease-out; min-height: 44px;">
        View Order
      </button>
    </form>
  </div>

  <div style="margin-top: 2rem; padding: 1.5rem; background: #f7f6f3; border-radius: 8px;">
    <h3 style="margin: 0 0 0.5rem; font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #787774;">Can't find your order?</h3>
    <p style="margin: 0 0 0.5rem; color: #666;">Check your email for the original order confirmation. You can also contact us for help.</p>
    <a href="mailto:support@example.com" style="color: #111; font-weight: 600; text-decoration: none;">Get in touch →</a>
  </div>
</main>

<script src="/assets/js/ui-feedback.js" defer></script>
<script src="/assets/js/accessibility.js" defer></script>
<script>
  // Initialize form validation
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('order-verify-form');
    if (form && typeof UIFeedback !== 'undefined') {
      UIFeedback.enableRealtimeValidation(form, {
        email: {
          required: true,
          email: true,
          requiredMessage: 'Email is required',
          emailMessage: 'Please enter a valid email address',
        },
      });

      // Auto-focus email field
      UIFeedback.autoFocusForm(form);
    }

    // Initialize accessibility
    if (typeof A11y !== 'undefined') {
      A11y.init(document);
    }
  });
</script>
</body>
</html>
