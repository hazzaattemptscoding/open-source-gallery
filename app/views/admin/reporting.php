<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Advanced Reporting: Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.reporting-container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
.metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
.metric-card { background: #fff; border: 1px solid #eee; padding: 1.5rem; border-radius: 8px; }
.metric-card h3 { font-size: 0.9rem; color: #666; margin: 0 0 0.5rem 0; text-transform: uppercase; }
.metric-card .value { font-size: 2rem; font-weight: 600; }
.segments-table, .cohorts-table, .customers-table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
.segments-table th, .cohorts-table th, .customers-table th, .segments-table td, .cohorts-table td, .customers-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
.segments-table th, .cohorts-table th, .customers-table th { background: #f9f9f9; font-weight: 600; }
</style>
</head>
<body>
<header class="site-header">
  <a href="/admin" class="site-title">Advanced Reporting</a>
</header>

<div class="reporting-container">
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

</body>
</html>
