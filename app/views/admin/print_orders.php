<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Print Orders — Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.print-orders-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.print-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
}

.stat-card h3 {
    font-size: 0.9rem;
    color: #666;
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
}

.stat-card .value {
    font-size: 2rem;
    font-weight: 600;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 4px;
    overflow: hidden;
}

.orders-table th {
    background: #f9f9f9;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #eee;
    font-size: 0.9rem;
}

.orders-table td {
    padding: 1rem;
    border-bottom: 1px solid #eee;
}

.orders-table tr:last-child td {
    border-bottom: none;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 500;
}

.status-pending {
    background: #fff8e1;
    color: #f57f17;
}

.status-submitted {
    background: #e3f2fd;
    color: #1976d2;
}

.status-processing {
    background: #e8f5e9;
    color: #388e3c;
}

.status-shipped {
    background: #f3e5f5;
    color: #7b1fa2;
}

.status-delivered {
    background: #c8e6c9;
    color: #2e7d32;
}

.status-cancelled {
    background: #ffebee;
    color: #c62828;
}

.no-orders {
    text-align: center;
    padding: 3rem;
    color: #999;
}

@media (max-width: 768px) {
    .orders-table {
        font-size: 0.9rem;
    }

    .orders-table th,
    .orders-table td {
        padding: 0.75rem;
    }
}
</style>
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title">Print Orders</a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Logout</button>
  </form>
</header>

<div class="print-orders-container">

  <!-- Stats -->
  <?php if (!empty($printOrders)): ?>
    <?php
    $stats = ['pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0];
    foreach ($printOrders as $order) {
        if ($order['status'] === 'pending') $stats['pending']++;
        elseif ($order['status'] === 'processing') $stats['processing']++;
        elseif ($order['status'] === 'shipped') $stats['shipped']++;
        elseif ($order['status'] === 'delivered') $stats['delivered']++;
    }
    ?>
    <div class="print-stats">
      <div class="stat-card">
        <h3>Pending</h3>
        <div class="value"><?= $stats['pending'] ?></div>
      </div>
      <div class="stat-card">
        <h3>Processing</h3>
        <div class="value"><?= $stats['processing'] ?></div>
      </div>
      <div class="stat-card">
        <h3>Shipped</h3>
        <div class="value"><?= $stats['shipped'] ?></div>
      </div>
      <div class="stat-card">
        <h3>Delivered</h3>
        <div class="value"><?= $stats['delivered'] ?></div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Print Orders Table -->
  <?php if (!empty($printOrders)): ?>
    <table class="orders-table">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer Email</th>
          <th>Provider</th>
          <th>Items</th>
          <th>Status</th>
          <th>Tracking</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($printOrders as $order): ?>
          <tr>
            <td><strong>#<?= e($order['order_id']) ?></strong></td>
            <td><?= e($order['email']) ?></td>
            <td><?= e($order['provider_name']) ?></td>
            <td><?= e($order['item_count']) ?> item<?= $order['item_count'] != 1 ? 's' : '' ?></td>
            <td>
              <span class="status-badge status-<?= e(strtolower($order['status'])) ?>">
                <?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?>
              </span>
            </td>
            <td>
              <?php if ($order['tracking_number']): ?>
                <a href="<?= e($order['tracking_url'] ?? '#') ?>" target="_blank">
                  <?= e(substr($order['tracking_number'], 0, 20)) ?>...
                </a>
              <?php else: ?>
                <span style="color: #999;">—</span>
              <?php endif; ?>
            </td>
            <td><?= e(date('M d, Y', strtotime($order['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="no-orders">
      <h2>No Print Orders Yet</h2>
      <p>Print orders will appear here when customers order printed items.</p>
    </div>
  <?php endif; ?>

  <!-- Help Section -->
  <div style="margin-top: 3rem; padding: 1.5rem; background: #f9f9f9; border-radius: 4px;">
    <h3 style="margin-top: 0;">About Print Fulfillment</h3>
    <p>
      Print fulfillment allows customers to order printed versions of photos. Orders are automatically
      routed to your configured print provider (Printful, Printware, etc.) and tracked here.
    </p>
    <p>
      <a href="/admin/settings">Configure Print Settings</a>
    </p>
  </div>

</div>

</body>
</html>
