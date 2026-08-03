<?php
/**
 * Unsubscribe confirmation and result.
 *
 * Deliberately plain and short. This page has one job and offering anything
 * else here, a survey, a "are you sure?", a pitch to stay, is the pattern
 * regulators describe as making opt-out harder than opt-in.
 */
$metaDescription = 'Manage marketing email preferences.';
require __DIR__ . '/partials/layout_header.php';
?>

<main class="unsubscribe-page">

  <?php if (!empty($done)): ?>
    <h1>You have been unsubscribed</h1>
    <p>You will not receive any more marketing email from <?= e($siteName) ?>.</p>
    <p class="unsubscribe-note">
      You will still get receipts and download links for anything you buy. Those
      are part of the purchase itself, not marketing.
    </p>
    <p><a href="/">Back to the gallery</a></p>

  <?php elseif (!empty($alreadyDone)): ?>
    <h1>Already unsubscribed</h1>
    <p>This address is not receiving marketing email from <?= e($siteName) ?>.</p>
    <p><a href="/">Back to the gallery</a></p>

  <?php else: ?>
    <h1>Unsubscribe</h1>

    <?php if (!empty($error)): ?>
      <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <p>
      Stop receiving marketing email from <?= e($siteName) ?><?php if (!empty($contact['email'])): ?>
        at <strong><?= e($contact['email']) ?></strong><?php endif; ?>.
    </p>

    <form method="post" action="/unsubscribe" class="unsubscribe-form">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <button type="submit" class="unsubscribe-button">Unsubscribe</button>
    </form>

    <p class="unsubscribe-note">
      Receipts and download links for things you buy will still be sent. They are
      part of the purchase and are not affected by this.
    </p>
  <?php endif; ?>

</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
