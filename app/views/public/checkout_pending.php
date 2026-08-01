<?php
$pageTitle = 'Payment Pending';
$metaDescription = 'Your payment is being processed';
$metaUrl = site_base_url($GLOBALS['config']) . '/checkout/pending';
$showCart = false;
require __DIR__ . '/partials/layout_header.php';
?>

<main>
<div class="error-page">
  <div class="error-code">●</div>
  <h1 class="error-title">Payment Pending</h1>
  <p class="error-description">Payment processing. Check your email for download links once confirmed.</p>
  <div class="error-actions">
    <a href="/" class="primary">← Back to Gallery</a>
  </div>
</div>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
