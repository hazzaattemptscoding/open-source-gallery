<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Server Error</title>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName ?? 'Gallery') ?></a>
</header>

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
</body>
</html>
