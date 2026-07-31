<?php
$pageTitle = 'Order confirmed: ' . e($siteName);
$metaDescription = 'Your order has been confirmed';
$metaUrl = $GLOBALS['config']['site']['url'] . '/checkout/success';
$showCart = false;
require __DIR__ . '/partials/layout_header.php';
?>

<main class="cart-page">
  <h1>Order confirmed</h1>

  <p>Download link and receipt sent to <strong><?= e($order['email']) ?></strong>.</p>

  <div class="panel">
    <h2>Order summary</h2>
    <div class="detail-row">
      <div class="detail-label">Order number</div>
      <div class="detail-value"><?= e($order['public_token']) ?></div>
    </div>
    <div class="detail-row">
      <div class="detail-label">Total</div>
      <div class="detail-value"><?= e(format_pence($order['total_pence'], $order['currency'])) ?></div>
    </div>
    <div class="detail-row">
      <div class="detail-label">Items</div>
      <div class="detail-value">
        <ul class="list-plain">
          <?php foreach ($items as $item): ?>
            <li><?= e($item['description']) ?>: <?= e(format_pence($item['unit_price_pence'], $order['currency'])) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Trust signals -->
  <div class="trust-signals">
    <div class="trust-signal">
      <div class="trust-signal-icon">●</div>
      <strong>Secure Payment</strong>
      <span>Powered by Stripe</span>
    </div>
    <div class="trust-signal">
      <div class="trust-signal-icon">●</div>
      <strong>Email Confirmation</strong>
      <span>Sent to your inbox</span>
    </div>
    <div class="trust-signal">
      <div class="trust-signal-icon">●</div>
      <strong>Instant Download</strong>
      <span>No delays or fees</span>
    </div>
  </div>

  <div class="confirmation-cta">
    <a href="<?= e($downloadLink) ?>" class="button">Download your files</a>
  </div>

  <p class="hint">Check your spam folder if you don't see the email within a few minutes. Or <a href="/">return to the gallery</a>.</p>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
