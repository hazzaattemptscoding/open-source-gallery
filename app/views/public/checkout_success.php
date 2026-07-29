<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order confirmed: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

<main class="cart-page">
  <h1>Order confirmed</h1>

  <p>Thank you for your purchase. A download link and receipt have been sent to <strong><?= e($order['email']) ?></strong>.</p>

  <div class="panel">
    <h2>Order details</h2>
    <div class="detail-row">
      <div class="detail-label">Order number</div>
      <div class="detail-value"><?= e($order['public_token']) ?></div>
    </div>
    <div class="detail-row">
      <div class="detail-label">Total</div>
      <div class="detail-value"><?= e(format_pence($order['total_pence'], $order['currency'])) ?></div>
    </div>
  </div>

  <div class="panel">
    <h2>Items</h2>
    <ul class="list-plain">
      <?php foreach ($items as $item): ?>
        <li><?= e($item['description']) ?>: <?= e(format_pence($item['unit_price_pence'], $order['currency'])) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="panel">
    <h2>Download</h2>
    <p><a href="<?= e($downloadLink) ?>" class="download-link">Download your files</a></p>
  </div>

  <p class="hint">If you don't see the download email within a few minutes, check your spam folder or <a href="/">return to the gallery</a>.</p>
</main>
</body>
</html>
