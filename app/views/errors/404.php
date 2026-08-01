<?php
$siteName = $GLOBALS['config']['site']['name'] ?? 'Gallery';
$pageTitle = 'Page Not Found';
$metaDescription = 'The page you\'re looking for doesn\'t exist';
$metaUrl = (site_base_url($GLOBALS['config']) ?? '') . '/errors/404';
$showCart = false;
require __DIR__ . '/../public/partials/layout_header.php';
?>

<main>
<div class="error-page">
  <div class="error-code">404</div>
  <h1 class="error-title">Page Not Found</h1>
  <p class="error-description">The page you're looking for doesn't exist or has been moved.</p>
  <div class="error-actions">
    <a href="/" class="primary">← Back to Gallery</a>
    <a href="/search">Search Photos</a>
  </div>
</div>
</main>

<?php require __DIR__ . '/../public/partials/layout_footer.php'; ?>
