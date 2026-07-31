<?php
$pageTitle = 'Reporting';
$currentPage = 'stats';
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="dashboard">
  <h1>Customer Analytics & Cohorts</h1>

  <div class="metrics">
    <div class="metric-card">
      <h3>Repeat Customer Rate</h3>
      <div class="value"><?= isset($reporting['repeat_rate']) ? round($reporting['repeat_rate'], 1) : 0 ?>%</div>
    </div>
  </div>

  <h2>Customer Segments</h2>
  <table class="segments-table">
    <thead>
      <tr>
        <th>Segment</th>
        <th>Customers</th>
        <th>Avg LTV</th>
        <th>Total Revenue</th>
        <th>Avg Orders</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reporting['segments'] as $seg): ?>
        <tr>
          <td><?= e($seg['segment']) ?></td>
          <td><?= (int)$seg['customer_count'] ?></td>
          <td><?= $currencySymbol ?><?= number_format($seg['avg_ltv_pence'] / 100, 2) ?></td>
          <td><?= $currencySymbol ?><?= number_format($seg['total_revenue_pence'] / 100, 2) ?></td>
          <td><?= round($seg['avg_orders'], 1) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Monthly Cohorts</h2>
  <table class="cohorts-table">
    <thead>
      <tr>
        <th>Month</th>
        <th>Customers</th>
        <th>Revenue</th>
        <th>1-Month Retention</th>
        <th>3-Month Retention</th>
        <th>6-Month Retention</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reporting['cohorts'] as $cohort): ?>
        <tr>
          <td><?= e($cohort['cohort_month']) ?></td>
          <td><?= (int)$cohort['customers_acquired'] ?></td>
          <td><?= $currencySymbol ?><?= number_format($cohort['revenue_pence'] / 100, 2) ?></td>
          <td><?= $cohort['retention_month_1'] ? $cohort['retention_month_1'] . '%' : '—' ?></td>
          <td><?= $cohort['retention_month_3'] ? $cohort['retention_month_3'] . '%' : '—' ?></td>
          <td><?= $cohort['retention_month_6'] ? $cohort['retention_month_6'] . '%' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Top Customers</h2>
  <table class="customers-table">
    <thead>
      <tr>
        <th>Email</th>
        <th>Lifetime Value</th>
        <th>Orders</th>
        <th>Last Order</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reporting['top_customers'] as $cust): ?>
        <tr>
          <td><?= e($cust['customer_email']) ?></td>
          <td><?= $currencySymbol ?><?= number_format($cust['lifetime_value_pence'] / 100, 2) ?></td>
          <td><?= (int)$cust['order_count'] ?></td>
          <td><?= $cust['last_order_at'] ? e(date('M d, Y', strtotime($cust['last_order_at']))) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
