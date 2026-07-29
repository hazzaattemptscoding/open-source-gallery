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
    font-weight: 500;
}

.stat-card .value {
    font-size: 2rem;
    font-weight: 600;
    color: #000;
    margin: 0.5rem 0;
}

.stat-card .label {
    font-size: 0.8rem;
    color: #999;
}

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.chart-container {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 1.5rem;
    position: relative;
    min-height: 400px;
}

.chart-container h2 {
    font-size: 1.1rem;
    margin-top: 0;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 1rem;
}

.top-photos {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 1.5rem;
    grid-column: 1 / -1;
}

.top-photos h2 {
    font-size: 1.1rem;
    margin-top: 0;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 1rem;
}

.photos-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.photos-table th {
    text-align: left;
    padding: 0.75rem;
    background: #f9f9f9;
    font-weight: 600;
    border-bottom: 2px solid #eee;
}

.photos-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #eee;
}

.photos-table tr:hover {
    background: #f9f9f9;
}

.no-data {
    text-align: center;
    padding: 2rem;
    color: #999;
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
          borderColor: '#000',
          backgroundColor: 'rgba(0, 0, 0, 0.1)',
          tension: 0.4,
          fill: true,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { callback: v => '£' + v } }
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
          backgroundColor: '#000',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  // Sales by Event
  const eventData = <?= json_encode($analytics['sales_by_event']) ?>;
  if (eventData && eventData.length > 0) {
    new Chart(document.getElementById('eventsChart'), {
      type: 'doughnut',
      data: {
        labels: eventData.map(e => e.title),
        datasets: [{
          data: eventData.map(e => e.revenue_pence / 100),
          backgroundColor: [
            '#000', '#333', '#666', '#999', '#ccc',
            '#111', '#222', '#444', '#777', '#aaa'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right' }
        }
      }
    });
  }
});
</script>

</body>
</html>
