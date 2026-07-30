<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cart: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/api/styles.css">
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
          <?php if ($line['type'] === 'photo' && !empty($line['public_token'])): ?>
            <span class="cart-line-image">
              <img src="/media/d/<?= e($line['public_token']) ?>-400.jpg" alt="<?= e($line['description']) ?>" loading="lazy">
            </span>
          <?php endif; ?>
          <span class="cart-line-desc"><?= e($line['description']) ?></span>
          <span class="cart-line-price"><?= e(format_pence((int)$line['unit_price_pence'], $currencyCode)) ?></span>
          <button type="button" class="cart-line-remove" aria-label="Remove from cart">&times;</button>
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
      <label for="checkout-email" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Email</label>
      <input type="email" id="checkout-email" name="email" placeholder="your@email.com" required>
      <button type="submit" class="checkout-button">Checkout</button>
      <div id="checkout-error" class="error-message" style="display: none;"></div>
    </form>
  <?php endif; ?>
</main>

<script src="/assets/js/cart.js" defer></script>
</body>
</html>
