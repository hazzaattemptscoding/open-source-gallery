<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order confirmed: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

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
  <div style="background: #f7f6f3; padding: 1.5rem; border-radius: 8px; margin: 2rem 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; text-align: center;">
    <div>
      <p style="margin: 0 0 0.5rem; font-size: 1.5rem;">🔒</p>
      <strong style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">Secure Payment</strong>
      <span style="color: #999; font-size: 0.85rem;">Powered by Stripe</span>
    </div>
    <div>
      <p style="margin: 0 0 0.5rem; font-size: 1.5rem;">📧</p>
      <strong style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">Email Confirmation</strong>
      <span style="color: #999; font-size: 0.85rem;">Sent to your inbox</span>
    </div>
    <div>
      <p style="margin: 0 0 0.5rem; font-size: 1.5rem;">⚡</p>
      <strong style="display: block; font-size: 0.875rem; margin-bottom: 0.25rem;">Instant Download</strong>
      <span style="color: #999; font-size: 0.85rem;">No delays or fees</span>
    </div>
  </div>

  <div class="confirmation-cta">
    <a href="<?= e($downloadLink) ?>" class="button">Download your files</a>
  </div>

  <p class="hint">Check your spam folder if you don't see the email within a few minutes. Or <a href="/">return to the gallery</a>.</p>
</main>
</body>
</html>
