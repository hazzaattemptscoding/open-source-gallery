<?php
/**
 * Buy advance credit.
 *
 * Sold on race day, spent when the gallery opens days later. The copy has to
 * make two things unambiguous, because this is money paid before the product
 * exists: what they get, and that it does not expire on them.
 */
$metaDescription = 'Buy photo credit now and spend it when the gallery goes live.';
$showCart = false;
$pageScripts = '<script src="/assets/js/credit.js" defer></script>'
    . '<script src="https://js.stripe.com/v3/"></script>';
require __DIR__ . '/partials/layout_header.php';
?>

<main class="credit-page">
  <h1>Buy photo credit</h1>
  <p class="credit-lede">
    Pay now, spend it when the photos go online. Credit never expires and you
    can use it across as many photos as you like until it runs out.
  </p>

  <?php if (empty($options)): ?>
    <p class="credit-unavailable">Credit is not on sale at the moment.</p>
  <?php else: ?>
    <form class="credit-form" id="creditForm">
      <div class="form-group">
        <label class="form-label" for="credit-email">Email address</label>
        <input
          type="email"
          id="credit-email"
          name="email"
          class="form-input"
          placeholder="your@email.com"
          required
          autocomplete="email">
        <span class="form-helper">Your credit code is sent here, and shown on the next screen.</span>
      </div>

      <fieldset class="credit-amounts">
        <legend class="form-label">Amount</legend>
        <?php foreach ($options as $index => $option): ?>
          <label class="credit-amount">
            <input
              type="radio"
              name="amount_pence"
              value="<?= (int)$option['pence'] ?>"
              <?= $index === 0 ? 'checked' : '' ?>>
            <span><?= e($option['formatted']) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <button type="submit" class="credit-submit" id="creditSubmit">Continue to payment</button>
      <div id="credit-error" class="error-message" hidden></div>
    </form>
  <?php endif; ?>

  <section class="credit-notes">
    <h2>How it works</h2>
    <ol>
      <li>Pay now and get a credit code.</li>
      <li>When the gallery opens, choose your photos as normal.</li>
      <li>Enter the code at checkout. It comes off your total.</li>
    </ol>
    <p class="credit-smallprint">
      Credit is not refundable for cash, but it does not expire and any unused
      balance stays on the code for next time.
    </p>
  </section>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
