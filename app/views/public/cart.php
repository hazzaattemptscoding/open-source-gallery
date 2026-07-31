<?php
$pageTitle = 'Cart: ' . e($siteName);
$metaDescription = 'Shopping cart - add photos and checkout';
$metaUrl = $GLOBALS['config']['site']['url'] . '/cart';
$showCart = false; // we're on the cart page itself
$pageScripts = '<script src="/assets/js/cart.js" defer></script>';
require __DIR__ . '/partials/layout_header.php';
?>

<main class="cart-page">
  <h1>Your cart</h1>

  <?php if (empty($lines)): ?>
    <?php
      $emptyState = [
        'title' => 'Your cart is empty',
        'message' => 'Start adding photos from the gallery to your cart.',
        'action' => [
          'label' => 'Browse events',
          'href' => '/',
        ],
      ];
      require __DIR__ . '/partials/empty-state.php';
    ?>
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

    <form id="checkout-form" class="checkout-form" aria-label="Checkout form" method="post" data-validate="email">
      <div class="form-group">
        <label for="checkout-email" class="form-label">Email address</label>
        <input
          type="email"
          id="checkout-email"
          name="email"
          class="form-input"
          placeholder="your@email.com"
          required
          aria-required="true"
          aria-describedby="email-helper">
        <span id="email-helper" class="form-helper">Your receipt will be sent to this email</span>
      </div>
      <button type="submit" class="checkout-button" id="checkout-submit">Checkout</button>
      <div id="checkout-error" class="error-message" style="display: none;"></div>
    </form>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
