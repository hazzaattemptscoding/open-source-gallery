<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cart: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

<main class="cart-page">
  <h1>Your cart</h1>

  <?php if (empty($lines)): ?>
    <p class="empty-state">Your cart is empty. <a href="/">Browse events</a>.</p>
  <?php else: ?>
    <ul class="cart-lines">
      <?php foreach ($lines as $line): ?>
        <li class="cart-line" data-type="<?= e($line['type']) ?>" data-id="<?= (int)$line['id'] ?>">
          <span class="cart-line-desc"><?= e($line['description']) ?></span>
          <span class="cart-line-price"><?= e(format_pence((int)$line['unit_price_pence'], $currencyCode)) ?></span>
          <button type="button" class="cart-line-remove" aria-label="Remove">&times;</button>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ((int)($discountPence ?? 0) > 0): ?>
      <div class="discount-banner">
        <span>Discount applied</span>
        <span>-<?= e(format_pence((int)$discountPence, $currencyCode)) ?></span>
      </div>
    <?php endif; ?>

    <div class="cart-total">
      <span>Total</span>
      <span><?= e(format_pence($totalPence, $currencyCode)) ?></span>
    </div>

    <form id="checkout-form" class="checkout-form">
      <input type="email" name="email" placeholder="Your email" required>
      <button type="submit" class="checkout-button">Checkout</button>
      <div id="checkout-error" class="error-message" style="display: none;"></div>
    </form>
  <?php endif; ?>
</main>

<script src="/assets/js/cart.js" defer></script>
</body>
</html>
