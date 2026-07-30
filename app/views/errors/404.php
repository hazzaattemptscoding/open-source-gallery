<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page Not Found</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/api/styles.css">
<style>
.error-page { max-width: 600px; margin: 4rem auto; padding: 0 1.5rem; text-align: center; }
.error-code { font-size: 3rem; font-weight: 600; line-height: 1; margin: 0 0 1rem; }
.error-title { font-size: 1.75rem; font-weight: 500; margin: 0 0 0.75rem; }
.error-description { font-size: 1rem; color: var(--text-muted); margin: 0 0 2rem; }
.error-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.error-actions a { padding: 0.75rem 1.5rem; background: var(--text); color: var(--bg); text-decoration: none; border-radius: 4px; font-weight: 500; transition: background 200ms ease; }
.error-actions a:hover { background: #333333; }
</style>
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName ?? 'Gallery') ?></a>
</header>

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
</body>
</html>
