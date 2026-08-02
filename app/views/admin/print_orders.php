<?php
$pageTitle = 'Print_orders';
$currentPage = 'print_orders';
require_once __DIR__ . '/partials/layout_header.php';
?>
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
                <span class="text-muted">—</span>
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
  <div class="info-box">
    <h3>About Print Fulfillment</h3>
    <p>
      Print fulfillment allows customers to order printed versions of photos. Orders are automatically
      routed to your configured print provider (Printful, Printware, etc.) and tracked here.
    </p>
    <p>
      <a href="/admin/settings">Configure Print Settings</a>
    </p>
  </div>

</div>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
