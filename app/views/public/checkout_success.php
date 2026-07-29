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

  <div class="confirmation-cta">
    <a href="<?= e($downloadLink) ?>" class="button">Download your files</a>
  </div>

  <p class="hint">Check your spam folder if you don't see the email within a few minutes. Or <a href="/">return to the gallery</a>.</p>
</main>
</body>
</html>
