<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page Not Found</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.error-page {
  max-width: 600px;
  margin: 120px auto;
  padding: 0 24px;
  text-align: center;
}

.error-code {
  font-size: 88px;
  font-weight: 700;
  line-height: 1;
  margin: 0 0 24px;
  letter-spacing: -2px;
}

.error-title {
  font-size: 28px;
  font-weight: 600;
  margin: 0 0 12px;
}

.error-description {
  font-size: 16px;
  color: #666;
  margin: 0 0 40px;
  line-height: 1.6;
}

.error-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.error-actions a {
  padding: 12px 24px;
  border: 1px solid #d0d0d0;
  border-radius: 6px;
  text-decoration: none;
  color: #111;
  font-weight: 500;
  transition: all 160ms ease-out;
}

.error-actions a:hover {
  background: #f5f5f5;
  border-color: #111;
}

.error-actions .primary {
  background: #111;
  color: white;
  border-color: #111;
}

.error-actions .primary:hover {
  background: #333;
}
</style>
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title">Gallery</a>
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
