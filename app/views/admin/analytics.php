<?php
$pageTitle = 'Analytics';
$currentPage = 'analytics';
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="analytics-container">

  <!-- Summary Stats -->
  <div class="analytics-summary">
    <div class="stat-card">
      <h3>All-Time Revenue</h3>
      <div class="value"><?= $currencySymbol ?><?= number_format($analytics['summary']['all_time_revenue'] / 100, 0) ?></div>
      <div class="label">Total from all orders</div>
    </div>

    <div class="stat-card">
      <h3>All-Time Orders</h3>
      <div class="value"><?= number_format($analytics['summary']['all_time_orders']) ?></div>
      <div class="label">Completed sales</div>
    </div>

    <div class="stat-card">
      <h3>Last 30 Days Revenue</h3>
      <div class="value"><?= $currencySymbol ?><?= number_format($analytics['summary']['last_30_days_revenue'] / 100, 0) ?></div>
      <div class="label">Recent performance</div>
    </div>

    <div class="stat-card">
      <h3>Last 30 Days Orders</h3>
      <div class="value"><?= number_format($analytics['summary']['last_30_days_orders']) ?></div>
      <div class="label">Recent sales count</div>
    </div>

    <div class="stat-card">
      <h3>Avg Order Value</h3>
      <div class="value"><?= $currencySymbol ?><?= number_format($analytics['summary']['avg_order_value'] / 100, 2) ?></div>
      <div class="label">Average per order</div>
    </div>

    <div class="stat-card">
      <h3>Photos Live</h3>
      <div class="value"><?= number_format($analytics['summary']['photos_live']) ?></div>
      <div class="label">Published photos</div>
    </div>

    <div class="stat-card">
      <h3>Conversion Rate</h3>
      <div class="value"><?= number_format($analytics['conversion_metrics']['conversion_rate'], 2) ?>%</div>
      <div class="label">Views → Orders</div>
    </div>

    <div class="stat-card">
      <h3>Repeat Customers</h3>
      <div class="value"><?= number_format($analytics['customer_insights']['repeat_customers'] ?? 0) ?></div>
      <div class="label">Bought multiple times</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="analytics-grid">

    <!-- Revenue Trend -->
    <div class="chart-container">
      <h2>Revenue Trend (30 Days)</h2>
      <?php if (!empty($analytics['revenue_trend'])): ?>
        <div class="chart" data-chart="line" data-value-prefix="<?= e($currencySymbol) ?>"
             data-series="<?= e(json_encode(array_map(fn($d) => [
                 'label' => date('j M', strtotime($d['period'])),
                 'value' => $d['revenue_pence'] / 100,
             ], $analytics['revenue_trend']))) ?>"></div>
      <?php else: ?>
        <div class="no-data">No revenue data yet</div>
      <?php endif; ?>
    </div>

    <!-- Hourly Distribution -->
    <div class="chart-container">
      <h2>Orders by Hour</h2>
      <?php if (!empty($analytics['hourly_distribution'])): ?>
        <div class="chart" data-chart="bar"
             data-series="<?= e(json_encode(array_map(fn($d) => [
                 'label' => sprintf('%02d', $d['hour']),
                 'value' => $d['orders'],
             ], $analytics['hourly_distribution']))) ?>"></div>
      <?php else: ?>
        <div class="no-data">No order data yet</div>
      <?php endif; ?>
    </div>

    <!-- Sales by Event -->
    <div class="chart-container">
      <h2>Sales by Event</h2>
      <?php if (!empty($analytics['sales_by_event'])): ?>
        <div class="chart" data-chart="doughnut" data-value-prefix="<?= e($currencySymbol) ?>"
             data-series="<?= e(json_encode(array_map(fn($d) => [
                 'label' => $d['title'],
                 'value' => $d['revenue_pence'] / 100,
             ], $analytics['sales_by_event']))) ?>"></div>
      <?php else: ?>
        <div class="no-data">No sales data yet</div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Top Photos -->
  <?php if (!empty($analytics['top_photos'])): ?>
    <div class="top-photos">
      <h2>Top 10 Best-Selling Photos</h2>
      <table class="photos-table">
        <thead>
          <tr>
            <th>Photo</th>
            <th>Event</th>
            <th>Times Sold</th>
            <th>Units Sold</th>
            <th>Total Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($analytics['top_photos'] as $photo): ?>
            <tr>
              <td><strong><?= e(substr($photo['original_filename'], 0, 50)) ?></strong></td>
              <td><?= e($photo['event_title']) ?></td>
              <td><?= e($photo['times_sold']) ?></td>
              <td><?= e($photo['units_sold']) ?></td>
              <td><strong><?= $currencySymbol ?><?= number_format($photo['revenue_pence'] / 100, 2) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>

<!-- Charts.js initialization -->
<script src="/assets/js/admin-charts.js" defer></script>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
