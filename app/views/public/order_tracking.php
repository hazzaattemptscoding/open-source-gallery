<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order <?= e(substr($orderToken, 0, 8)) ?>: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

<main style="max-width: 800px; margin: 0 auto; padding: 2rem 1rem;">
  <h1 style="font-family: 'Newsreader', serif; font-size: 2.25rem; font-weight: 700; margin: 0 0 0.5rem; letter-spacing: -0.02em;">Order Confirmation</h1>
  <p style="color: #999; margin: 0 0 2rem; font-size: 0.9rem;">Order #<?= e(substr($orderToken, 0, 8)) ?></p>

  <!-- Order status -->
  <div style="background: #f7f6f3; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 2rem;">
      <div>
        <p style="margin: 0 0 0.5rem; color: #999; font-size: 0.875rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em;">Date</p>
        <p style="margin: 0; font-size: 1.125rem; font-weight: 600;"><?= e(date('j M Y', strtotime($order['paid_at'] ?: 'now'))) ?></p>
      </div>
      <div>
        <p style="margin: 0 0 0.5rem; color: #999; font-size: 0.875rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em;">Status</p>
        <p style="margin: 0; font-size: 1.125rem; font-weight: 600;">
          <?php if ($order['status'] === 'paid'): ?>
            <span style="color: #346538;">Completed</span>
          <?php elseif ($order['status'] === 'refunded'): ?>
            <span style="color: #9f2f2d;">Refunded</span>
          <?php else: ?>
            <span style="color: #666;">Processing</span>
          <?php endif; ?>
        </p>
      </div>
      <div>
        <p style="margin: 0 0 0.5rem; color: #999; font-size: 0.875rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em;">Total</p>
        <p style="margin: 0; font-size: 1.125rem; font-weight: 600;"><?= e(format_pence((int)$order['total_pence'], $currencyCode)) ?></p>
      </div>
    </div>
  </div>

  <?php if ($order['status'] === 'paid'): ?>
    <!-- Download section. data-region promotes this to a labelled ARIA landmark
         via page-init.js. The old inline script tried to find it with
         querySelector('[style*="Download Your Files"]'), which matches a style
         ATTRIBUTE containing that text and so could never match anything. -->
    <div data-region="Download files" style="background: #fff; border: 1px solid #eee; padding: 2rem; border-radius: 8px; margin-bottom: 2rem;">
      <h2 style="margin: 0 0 1rem; font-size: 1.375rem; font-weight: 600;">Download Your Files</h2>
      <p style="color: #666; margin: 0 0 1.5rem;">Your files are ready to download. Links expire on <strong><?= e($downloadExpiry) ?></strong>.</p>

      <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem;">
        <thead>
          <tr style="background: #f7f6f3; border-bottom: 1px solid #eee;">
            <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.875rem;">Item</th>
            <th style="padding: 0.75rem; text-align: center; font-weight: 600; font-size: 0.875rem;">Qty</th>
            <th style="padding: 0.75rem; text-align: right; font-weight: 600; font-size: 0.875rem;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr style="border-bottom: 1px solid #eee;">
              <td style="padding: 1rem 0.75rem;">
                <strong><?= e($item['event_title']) ?></strong><br>
                <span style="color: #999; font-size: 0.875rem;">Photo <?= (int)$item['quantity'] ?></span>
              </td>
              <td style="padding: 0.75rem; text-align: center;"><?= (int)$item['quantity'] ?></td>
              <td style="padding: 0.75rem; text-align: right;">
                <a href="<?= e($downloadUrls[$item['public_token']] ?? '#') ?>" style="display: inline-block; padding: 0.5rem 1rem; background: #111; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.875rem; font-weight: 500; transition: background 160ms ease-out;" download>
                  Download
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <a href="<?= e($downloadUrls[array_key_first($downloadUrls)] ?? '#') ?>" style="display: inline-block; padding: 0.75rem 1.5rem; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background 160ms ease-out;">Download All Files</a>
    </div>

    <!-- Back to gallery -->
    <div style="text-align: center; padding: 2rem 0; border-top: 1px solid #eee;">
      <p style="margin: 0 0 1rem; color: #666;">Ready for more photos?</p>
      <a href="/" style="display: inline-block; padding: 0.75rem 1.5rem; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background 160ms ease-out;">Back to Gallery</a>
    </div>
  <?php else: ?>
    <!-- Processing state -->
    <div style="background: #f7f6f3; padding: 2rem; border-radius: 8px; text-align: center;">
      <h2 style="margin: 0 0 1rem; font-size: 1.375rem; font-weight: 600;">Order Processing</h2>
      <p style="margin: 0 0 1rem; color: #666;">Your order is being processed. You'll receive a confirmation email with download links as soon as your files are ready.</p>
      <p style="margin: 0; color: #999; font-size: 0.875rem;">Typically this takes just a few moments.</p>
    </div>
  <?php endif; ?>

  <!-- Footer -->
  <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #eee; text-align: center; color: #787774; font-size: 0.875rem;">
    <p style="margin: 0;">Order #<?= e(substr($orderToken, 0, 8)) ?> • <?= e($email) ?></p>
    <p style="margin: 0.5rem 0 0;">Questions? Contact us directly.</p>
  </div>
</main>

<script src="/assets/js/accessibility.js" defer></script>
<script src="/assets/js/page-init.js" defer></script>
</body>
</html>
