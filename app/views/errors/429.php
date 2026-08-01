<?php
/**
 * 429 Too Many Requests error page.
 * Displayed when rate limiting threshold is exceeded.
 */
$siteName = $GLOBALS['config']['site']['name'] ?? 'Gallery';
$pageTitle = 'Too Many Requests';
$metaDescription = 'Please wait a moment before trying again';
$metaUrl = (site_base_url($GLOBALS['config']) ?? '') . '/errors/429';
$showCart = false;
require __DIR__ . '/../public/partials/layout_header.php';
?>

<main>
<div class="error-page">
  <div class="error-code">429</div>
  <h1 class="error-title">Too Many Requests</h1>
  <p class="error-description">Please wait a moment before trying again.</p>
  <div class="error-actions">
    <a href="/" class="primary">← Back to Gallery</a>
  </div>
</div>
</main>

<?php require __DIR__ . '/../public/partials/layout_footer.php'; ?>
