<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Analytics — Admin</title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
.analytics-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.analytics-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 3rem;
}

.stat-card {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 1.5rem;
  text-align: center;
  transition: all 160ms var(--ease-out);
}

.stat-card:hover {
  border-color: var(--text-muted);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.stat-card h3 {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin: 0 0 0.5rem 0;
  text-transform: uppercase;
  font-weight: 500;
  letter-spacing: 0.5px;
}

.stat-card .value {
  font-size: 2rem;
  font-weight: 600;
  color: var(--text);
  margin: 0.5rem 0;
}

.stat-card .label {
  font-size: 0.8rem;
  color: var(--text-muted);
}

.analytics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 2rem;
  margin-bottom: 3rem;
}

.chart-container {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 1.5rem;
  position: relative;
  min-height: 400px;
}

.chart-container h2 {
  font-size: 1.1rem;
  margin-top: 0;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 1rem;
  color: var(--text);
  font-weight: 600;
}

.top-photos {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 1.5rem;
  grid-column: 1 / -1;
}

.top-photos h2 {
  font-size: 1.1rem;
  margin-top: 0;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 1rem;
  color: var(--text);
  font-weight: 600;
}

.photos-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.photos-table th {
  text-align: left;
  padding: 0.75rem;
  background: var(--bg-alt);
  font-weight: 600;
  color: var(--text);
  border-bottom: 1px solid var(--border);
}

.photos-table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--border);
  color: var(--text);
}

.photos-table tr:hover {
  background: var(--bg-alt);
}

.no-data {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
}

@media (max-width: 768px) {
  .analytics-grid {
    grid-template-columns: 1fr;
  }

  .analytics-summary {
    grid-template-columns: repeat(2, 1fr);
  }

  .chart-container {
    min-height: 300px;
  }
}
</style>
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title">Advanced Analytics</a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Log out</button>
  </form>
</header>

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

</body>
</html>
