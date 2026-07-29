<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment Pending</title>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName ?? 'Gallery') ?></a>
</header>

<main>
<div class="error-page">
  <div class="error-code">⏳</div>
  <h1 class="error-title">Payment Pending</h1>
  <p class="error-description">Payment processing. Check your email for download links once confirmed.</p>
  <div class="error-actions">
    <a href="/" class="primary">← Back to Gallery</a>
  </div>
</div>
</main>
</body>
</html>
