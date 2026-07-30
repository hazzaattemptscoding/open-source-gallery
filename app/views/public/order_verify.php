<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>View Order: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

<main style="max-width: 500px; margin: 0 auto; padding: 4rem 1rem;">
  <h1 style="font-family: 'Newsreader', serif; font-size: 2.25rem; font-weight: 700; margin: 0 0 0.5rem; letter-spacing: -0.02em;">View Your Order</h1>
  <p style="color: #999; margin: 0 0 2rem; font-size: 0.9rem;">Order #<?= e(substr($orderToken, 0, 8)) ?></p>

  <div style="background: #f7f6f3; padding: 2rem; border-radius: 8px;">
    <form method="get" style="display: flex; flex-direction: column; gap: 1rem;">
      <input type="hidden" name="order_token" value="<?= e($orderToken) ?>">

      <div>
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.875rem;">Email Address</label>
        <input type="email" name="email" required style="width: 100%; padding: 0.75rem; border: 1px solid #eee; border-radius: 6px; font-size: 1rem; font-family: inherit;">
        <p style="margin: 0.5rem 0 0; color: #999; font-size: 0.875rem;">Enter the email address you used for your purchase.</p>
      </div>

      <button type="submit" style="padding: 0.75rem 1.5rem; background: #111; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 160ms ease-out;">
        View Order
      </button>
    </form>
  </div>

  <div style="margin-top: 2rem; padding: 1.5rem; background: #f7f6f3; border-radius: 8px;">
    <h3 style="margin: 0 0 0.5rem; font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #999;">Can't find your order?</h3>
    <p style="margin: 0 0 0.5rem; color: #666;">Check your email for the original order confirmation. You can also contact us for help.</p>
    <a href="mailto:support@example.com" style="color: #111; font-weight: 600; text-decoration: none;">Get in touch →</a>
  </div>
</main>

</body>
</html>
