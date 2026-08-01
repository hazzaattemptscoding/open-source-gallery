<?php
$pageTitle = 'View Order: ' . e($siteName);
$metaDescription = 'Verify your order email';
$metaUrl = site_base_url($GLOBALS['config']) . '/order/verify';
$showCart = false;
require __DIR__ . '/partials/layout_header.php';
?>

<main class="verify-container">
  <h1 class="verify-title">View Your Order</h1>
  <p class="verify-subtitle">Order #<?= e(substr($orderToken, 0, 8)) ?></p>

  <div class="verify-form-box">
    <form id="order-verify-form" method="post" aria-label="Order verification form" data-validate="email">
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

      <button type="submit" class="verify-submit-btn">
        View Order
      </button>
    </form>
  </div>

  <div class="verify-help-section">
    <h3 class="verify-help-title">Can't find your order?</h3>
    <p class="verify-help-text">Check your email for the original order confirmation. You can also contact us for help.</p>
    <a href="mailto:support@example.com" class="verify-help-link">Get in touch →</a>
  </div>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
