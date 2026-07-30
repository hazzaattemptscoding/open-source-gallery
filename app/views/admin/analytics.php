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
        <canvas id="revenueChart"></canvas>
      <?php else: ?>
        <div class="no-data">No revenue data yet</div>
      <?php endif; ?>
    </div>

    <!-- Hourly Distribution -->
    <div class="chart-container">
      <h2>Orders by Hour</h2>
      <?php if (!empty($analytics['hourly_distribution'])): ?>
        <canvas id="hourlyChart"></canvas>
      <?php else: ?>
        <div class="no-data">No order data yet</div>
      <?php endif; ?>
    </div>

    <!-- Sales by Event -->
    <div class="chart-container">
      <h2>Sales by Event</h2>
      <?php if (!empty($analytics['sales_by_event'])): ?>
        <canvas id="eventsChart"></canvas>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
  const textColor = getComputedStyle(document.documentElement).getPropertyValue('--text').trim();
  const textMutedColor = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim();
  const bgAltColor = getComputedStyle(document.documentElement).getPropertyValue('--bg-alt').trim();

  // Revenue Trend
  const revenueTrendData = <?= json_encode($analytics['revenue_trend']) ?>;
  if (revenueTrendData && revenueTrendData.length > 0) {
    new Chart(document.getElementById('revenueChart'), {
      type: 'line',
      data: {
        labels: revenueTrendData.map(d => d.period),
        datasets: [{
          label: 'Revenue',
          data: revenueTrendData.map(d => d.revenue_pence / 100),
          borderColor: textColor,
          backgroundColor: 'rgba(0, 0, 0, 0.04)',
          tension: 0.4,
          fill: true,
          pointBackgroundColor: textColor,
          pointBorderColor: textColor,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: 'rgba(0, 0, 0, 0.8)' }
        },
        scales: {
          y: { beginAtZero: true, ticks: { callback: v => '£' + v, color: textMutedColor } },
          x: { ticks: { color: textMutedColor } }
        }
      }
    });
  }

  // Hourly Distribution
  const hourlyData = <?= json_encode($analytics['hourly_distribution']) ?>;
  if (hourlyData && hourlyData.length > 0) {
    new Chart(document.getElementById('hourlyChart'), {
      type: 'bar',
      data: {
        labels: hourlyData.map(d => d.hour + ':00'),
        datasets: [{
          label: 'Orders',
          data: hourlyData.map(d => d.orders),
          backgroundColor: textColor,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { color: textMutedColor } },
          x: { ticks: { color: textMutedColor } }
        }
      }
    });
  }

  // Sales by Event
  const eventData = <?= json_encode($analytics['sales_by_event']) ?>;
  if (eventData && eventData.length > 0) {
    const colors = [
      '#111111', '#2a2a2a', '#3f3f3f', '#535353', '#676767',
      '#7a7a7a', '#8d8d8d', '#a1a1a1', '#b4b4b4', '#c7c7c7'
    ];
    new Chart(document.getElementById('eventsChart'), {
      type: 'doughnut',
      data: {
        labels: eventData.map(e => e.title),
        datasets: [{
          data: eventData.map(e => e.revenue_pence / 100),
          backgroundColor: colors
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right', labels: { color: textColor } }
        }
      }
    });
  }
});
</script>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
