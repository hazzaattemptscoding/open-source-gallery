<?php
$siteName = $GLOBALS['config']['site']['name'] ?? 'Gallery';
$pageTitle = 'Server Error';
$metaDescription = 'Something went wrong on our end';
$metaUrl = (site_base_url($GLOBALS['config']) ?? '') . '/errors/500';
$showCart = false;
require __DIR__ . '/../public/partials/layout_header.php';
?>

<main>
<div class="error-page">
  <div class="error-code">500</div>
  <h1 class="error-title">Server Error</h1>
  <p class="error-description">Something went wrong on our end. We've been notified and are working on a fix. Please try again in a moment.</p>
  <div class="error-actions">
    <a href="/" class="primary">← Back to Gallery</a>
    <a href="/search">Search Photos</a>
  </div>
</div>
</main>

<?php require __DIR__ . '/../public/partials/layout_footer.php'; ?>
