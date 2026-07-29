<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Advanced Reporting: Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.reporting-container {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 0 1rem;
}

.reporting-container h1 {
  font-size: 1.75rem;
  font-weight: 600;
  color: var(--text);
  margin: 0 0 1.5rem 0;
}

.reporting-container h2 {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text);
  margin: 2.5rem 0 1rem 0;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--border);
}

.metrics {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin: 2rem 0;
}

.metric-card {
  background: var(--bg);
  border: 1px solid var(--border);
  padding: 1.5rem;
  border-radius: 8px;
  transition: all 160ms var(--ease-out);
}

.metric-card:hover {
  border-color: var(--text-muted);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.metric-card h3 {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin: 0 0 0.5rem 0;
  text-transform: uppercase;
  font-weight: 500;
  letter-spacing: 0.5px;
}

.metric-card .value {
  font-size: 2rem;
  font-weight: 600;
  color: var(--text);
}

.segments-table,
.cohorts-table,
.customers-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1.5rem;
  font-size: 0.95rem;
}

.segments-table th,
.cohorts-table th,
.customers-table th,
.segments-table td,
.cohorts-table td,
.customers-table td {
  padding: 0.75rem 1rem;
  text-align: left;
  border-bottom: 1px solid var(--border);
  color: var(--text);
}

.segments-table th,
.cohorts-table th,
.customers-table th {
  background: var(--bg-alt);
  font-weight: 600;
  color: var(--text);
}

.segments-table tbody tr,
.cohorts-table tbody tr,
.customers-table tbody tr {
  transition: background 160ms ease;
}

.segments-table tbody tr:hover,
.cohorts-table tbody tr:hover,
.customers-table tbody tr:hover {
  background: var(--bg-alt);
}

@media (max-width: 768px) {
  .metrics {
    grid-template-columns: 1fr;
  }

  .segments-table,
  .cohorts-table,
  .customers-table {
    font-size: 0.9rem;
  }

  .segments-table th,
  .cohorts-table th,
  .customers-table th,
  .segments-table td,
  .cohorts-table td,
  .customers-table td {
    padding: 0.5rem 0.75rem;
  }
}
</style>
<link rel="stylesheet" href="/api/styles.css">
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
