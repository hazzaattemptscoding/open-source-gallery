<?php
/**
 * 429 Too Many Requests error page.
 * Displayed when rate limiting threshold is exceeded.
 */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Too Many Requests</title>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName ?? 'Gallery') ?></a>
</header>

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
</body>
</html>
