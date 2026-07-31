<?php
$pageTitle = 'Orders';
$currentPage = 'orders';
require_once __DIR__ . '/partials/layout_header.php';
?>
<main class="dashboard">
  <h1>Orders</h1>
  <p><?= e($totalOrders) ?> orders total</p>

  <?php if (empty($orders)): ?>
    <p class="empty-state">No orders yet.</p>
  <?php else: ?>
    <div class="table-wrapper">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Items</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td>
                <code class="table-order-id"><?= e(substr($order['public_token'], 0, 8)) ?></code>
              </td>
              <td>
                <span title="<?= e($order['email']) ?>"><?= e(substr($order['email'], 0, 24)) ?></span>
              </td>
              <td>
                <?= e(format_pence((int)$order['total_pence'], $currencyCode)) ?>
              </td>
              <td>
                <?= (int)$order['item_count'] ?>
              </td>
              <td>
                <span class="table-status <?= e($order['status']) ?>">
                  <?= e($order['status']) ?>
                </span>
              </td>
              <td class="table-date">
                <?= $order['paid_at'] ? date('Y-m-d', strtotime($order['paid_at'])) : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="table-pagination">
        <?php if ($paginationPage > 1): ?>
          <a href="?page=<?= max(1, $paginationPage - 1) ?>">← Previous</a>
        <?php endif; ?>
        Page <?= $paginationPage ?> of <?= $totalPages ?>
        <?php if ($paginationPage < $totalPages): ?>
          <a href="?page=<?= min($totalPages, $paginationPage + 1) ?>">Next →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</main>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
