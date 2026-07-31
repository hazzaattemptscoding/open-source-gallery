<?php
$pageTitle = 'Order ' . e(substr($orderToken, 0, 8)) . ': ' . e($siteName);
$metaDescription = 'Track your order';
$metaUrl = $GLOBALS['config']['site']['url'] . '/order/' . e($orderToken);
$showCart = false;
require __DIR__ . '/partials/layout_header.php';
?>

<main class="order-container">
  <h1 class="order-title">Order Confirmation</h1>
  <p class="order-subtitle">Order #<?= e(substr($orderToken, 0, 8)) ?></p>

  <!-- Order status -->
  <div class="order-status-box">
    <div class="order-status-grid">
      <div class="order-status-metric">
        <p class="order-status-label">Date</p>
        <p class="order-status-value"><?= e(date('j M Y', strtotime($order['paid_at'] ?: 'now'))) ?></p>
      </div>
      <div class="order-status-metric">
        <p class="order-status-label">Status</p>
        <p class="order-status-value">
          <?php if ($order['status'] === 'paid'): ?>
            <span class="status-completed">Completed</span>
          <?php elseif ($order['status'] === 'refunded'): ?>
            <span class="status-refunded">Refunded</span>
          <?php else: ?>
            <span class="status-processing">Processing</span>
          <?php endif; ?>
        </p>
      </div>
      <div class="order-status-metric">
        <p class="order-status-label">Total</p>
        <p class="order-status-value"><?= e(format_pence((int)$order['total_pence'], $currencyCode)) ?></p>
      </div>
    </div>
  </div>

  <?php if ($order['status'] === 'paid'): ?>
    <!-- Download section. data-region promotes this to a labelled ARIA landmark via page-init.js. -->
    <div data-region="Download files" class="order-download-box">
      <h2 class="order-download-title">Download Your Files</h2>
      <p class="order-download-note">Your files are ready to download. Links expire on <strong><?= e($downloadExpiry) ?></strong>.</p>

      <table class="order-files-table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <strong><?= e($item['event_title']) ?></strong><br>
                <span class="order-file-item-meta">Photo <?= (int)$item['quantity'] ?></span>
              </td>
              <td><?= (int)$item['quantity'] ?></td>
              <td>
                <a href="<?= e($downloadUrls[$item['public_token']] ?? '#') ?>" class="order-download-btn" download>
                  Download
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <a href="<?= e($downloadUrls[array_key_first($downloadUrls)] ?? '#') ?>" class="order-download-btn large">Download All Files</a>
    </div>

    <!-- Back to gallery -->
    <div class="order-back-section">
      <p class="order-back-text">Ready for more photos?</p>
      <a href="/" class="order-download-btn large">Back to Gallery</a>
    </div>
  <?php else: ?>
    <!-- Processing state -->
    <div class="order-processing-box">
      <h2 class="order-processing-title">Order Processing</h2>
      <p class="order-processing-text">Your order is being processed. You'll receive a confirmation email with download links as soon as your files are ready.</p>
      <p class="order-processing-note">Typically this takes just a few moments.</p>
    </div>
  <?php endif; ?>

  <!-- Footer -->
  <div class="order-footer">
    <p>Order #<?= e(substr($orderToken, 0, 8)) ?> • <?= e($email) ?></p>
    <p>Questions? Contact us directly.</p>
  </div>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
