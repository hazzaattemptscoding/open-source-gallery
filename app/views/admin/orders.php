<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Orders — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title"><?= e($siteName) ?></a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Log out</button>
  </form>
</header>

<main class="dashboard">
  <h1>Orders</h1>
  <p><?= e($totalOrders) ?> orders total</p>

  <?php if (empty($orders)): ?>
    <p class="empty-state">No orders yet.</p>
  <?php else: ?>
    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse;">
        <thead>
          <tr style="background: var(--bg-alt); border-bottom: 1px solid var(--border);">
            <th style="padding: 1rem; text-align: left; font-weight: 600;">Order #</th>
            <th style="padding: 1rem; text-align: left; font-weight: 600;">Customer</th>
            <th style="padding: 1rem; text-align: left; font-weight: 600;">Total</th>
            <th style="padding: 1rem; text-align: left; font-weight: 600;">Items</th>
            <th style="padding: 1rem; text-align: left; font-weight: 600;">Status</th>
            <th style="padding: 1rem; text-align: left; font-weight: 600;">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 1rem;">
                <code style="font-size: 0.85rem;"><?= e(substr($order['public_token'], 0, 8)) ?></code>
              </td>
              <td style="padding: 1rem;">
                <span title="<?= e($order['email']) ?>"><?= e(substr($order['email'], 0, 24)) ?></span>
              </td>
              <td style="padding: 1rem;">
                <?= e(format_pence((int)$order['total_pence'], $currencyCode)) ?>
              </td>
              <td style="padding: 1rem;">
                <?= (int)$order['item_count'] ?>
              </td>
              <td style="padding: 1rem;">
                <span style="
                  display: inline-block;
                  padding: 0.25rem 0.75rem;
                  border-radius: 3px;
                  font-size: 0.85rem;
                  font-weight: 500;
                  background: <?=
                    $order['status'] === 'paid' ? 'rgba(81, 207, 102, 0.2); color: #51cf66;' :
                    $order['status'] === 'refunded' ? 'rgba(255, 107, 107, 0.2); color: #ff6b6b;' :
                    $order['status'] === 'partial_refund' ? 'rgba(255, 159, 64, 0.2); color: #ff9f40;' :
                    'rgba(153, 153, 153, 0.2); color: #999999;'
                  ?>">
                  <?= e($order['status']) ?>
                </span>
              </td>
              <td style="padding: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                <?= $order['paid_at'] ? date('Y-m-d', strtotime($order['paid_at'])) : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div style="margin-top: 2rem; text-align: center;">
        <?php if ($currentPage > 1): ?>
          <a href="?page=<?= max(1, $currentPage - 1) ?>" style="margin-right: 1rem;">← Previous</a>
        <?php endif; ?>
        Page <?= $currentPage ?> of <?= $totalPages ?>
        <?php if ($currentPage < $totalPages): ?>
          <a href="?page=<?= min($totalPages, $currentPage + 1) ?>" style="margin-left: 1rem;">Next →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</main>

</body>
</html>
