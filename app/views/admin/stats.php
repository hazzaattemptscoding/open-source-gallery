<?php
$pageTitle = 'Stats';
$currentPage = 'stats';
require_once __DIR__ . '/partials/layout_header.php';
?>
<main class="dashboard">
  <h1>Sales Dashboard</h1>

  <!-- Site-wide stats -->
  <div class="sales-stats-grid">
    <div class="panel">
      <div class="stat-value">
        <?= e((int)($siteStats['total_orders'] ?? 0)) ?>
      </div>
      <div class="stat-label">
        Total Orders
      </div>
    </div>
    <div class="panel">
      <div class="stat-value">
        <?= e(format_pence((int)($siteStats['total_revenue'] ?? 0), $currencyCode)) ?>
      </div>
      <div class="stat-label">
        Total Revenue
      </div>
    </div>
  </div>

  <!-- Per-event stats -->
  <?php foreach ($eventStats as $stats): ?>
    <div class="panel event-stats-section">
      <h2>
        <a href="/admin/events/<?= e($stats['event']['slug']) ?>">
          <?= e($stats['event']['title']) ?>
        </a>
      </h2>

      <div class="event-metrics">
        <div>
          <div class="event-metric-value">
            <?= e($stats['orderCount']) ?>
          </div>
          <div class="event-metric-label">Orders</div>
        </div>
        <div>
          <div class="event-metric-value">
            <?= e(format_pence($stats['revenue'], $currencyCode)) ?>
          </div>
          <div class="event-metric-label">Revenue</div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (empty($eventStats)): ?>
    <p class="empty-section empty-section-centered">
      No events yet. <a href="/admin/events/new">Create your first event.</a>
    </p>
  <?php endif; ?>

</main>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
