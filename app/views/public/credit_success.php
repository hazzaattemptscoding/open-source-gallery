<?php
/**
 * Shown after paying for advance credit.
 *
 * Stripe redirects the browser here and delivers the confirming webhook
 * independently, and it is the webhook that activates the credit. So this page
 * can legitimately load a moment before the credit is spendable.
 *
 * It says so plainly rather than showing a zero balance, because someone who
 * has just paid and is told their credit is worth nothing will assume the
 * payment failed.
 */
$metaDescription = 'Your photo credit.';
$showCart = false;
$pending = ($credit['status'] ?? '') !== 'active';
require __DIR__ . '/partials/layout_header.php';
?>

<main class="credit-success-page">
  <h1>Thank you</h1>

  <div class="credit-code-block">
    <span class="credit-code-label">Your credit code</span>
    <code class="credit-code"><?= e($credit['code']) ?></code>
  </div>

  <?php if ($pending): ?>
    <p class="credit-status credit-status-pending">
      Confirming your payment. This usually takes a few seconds. Refresh this
      page in a moment, and keep your code either way: it is already yours.
    </p>
  <?php else: ?>
    <p class="credit-status credit-status-active">
      Ready to use. Balance
      <strong><?= e(format_pence((int)$credit['balance_pence'], $currencyCode)) ?></strong>.
    </p>
  <?php endif; ?>

  <p>
    Write this code down or keep this email. Enter it at checkout when the
    gallery opens and it comes off your total. Any unused balance stays on the
    code.
  </p>

  <p><a href="/">Back to the gallery</a></p>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
