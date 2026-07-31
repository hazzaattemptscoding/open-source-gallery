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

      <?php if (!empty($stats['topPhotos'])): ?>
        <h3>Top Selling Photos</h3>
        <ul class="top-selling-list">
          <?php foreach ($stats['topPhotos'] as $photo): ?>
            <li>
              <div class="top-selling-item">
                <div>
                  <strong><?= e($photo['public_token']) ?></strong>
                  <div class="top-selling-meta">
                    <?= (int)$photo['sales'] ?> sale<?= (int)$photo['sales'] !== 1 ? 's' : '' ?>
                  </div>
                </div>
                <div>
                  <?= e(format_pence((int)$photo['revenue'], $currencyCode)) ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="empty-section">No sales yet.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (empty($eventStats)): ?>
    <p class="empty-section" style="text-align: center; padding: 2rem;">
      No events yet. <a href="/admin/events/new">Create your first event.</a>
    </p>
  <?php endif; ?>

</main>


<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
