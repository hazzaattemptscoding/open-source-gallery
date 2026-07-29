<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stats — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
</head>
<body>

<header class="site-header">
  <a href="/admin" class="site-title"><?= e($siteName) ?></a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Logout</button>
  </form>
</header>

<main class="dashboard">
  <h1>Sales Dashboard</h1>

  <!-- Site-wide stats -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
    <div class="panel">
      <div style="font-size: 2rem; font-weight: 600; margin-bottom: 0.5rem;">
        <?= e((int)($siteStats['total_orders'] ?? 0)) ?>
      </div>
      <div style="color: var(--text-muted); font-size: 0.95rem;">
        Total Orders
      </div>
    </div>
    <div class="panel">
      <div style="font-size: 2rem; font-weight: 600; margin-bottom: 0.5rem;">
        <?= e(format_pence((int)($siteStats['total_revenue'] ?? 0), $currencyCode)) ?>
      </div>
      <div style="color: var(--text-muted); font-size: 0.95rem;">
        Total Revenue
      </div>
    </div>
  </div>

  <!-- Per-event stats -->
  <?php foreach ($eventStats as $stats): ?>
    <div class="panel">
      <h2>
        <a href="/admin/events/<?= e($stats['event']['slug']) ?>" style="color: inherit; text-decoration: none;">
          <?= e($stats['event']['title']) ?>
        </a>
      </h2>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <div>
          <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.25rem;">
            <?= e($stats['orderCount']) ?>
          </div>
          <div style="color: var(--text-muted); font-size: 0.9rem;">Orders</div>
        </div>
        <div>
          <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.25rem;">
            <?= e(format_pence($stats['revenue'], $currencyCode)) ?>
          </div>
          <div style="color: var(--text-muted); font-size: 0.9rem;">Revenue</div>
        </div>
      </div>

      <?php if (!empty($stats['topPhotos'])): ?>
        <h3 style="margin: 1.5rem 0 1rem; font-size: 1rem;">Top Selling Photos</h3>
        <ul style="list-style: none; padding: 0; margin: 0;">
          <?php foreach ($stats['topPhotos'] as $photo): ?>
            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--border); font-size: 0.9rem;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <strong><?= e($photo['public_token']) ?></strong>
                  <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem;">
                    <?= (int)$photo['sales'] ?> sale<?= (int)$photo['sales'] !== 1 ? 's' : '' ?>
                  </div>
                </div>
                <div style="text-align: right;">
                  <?= e(format_pence((int)$photo['revenue'], $currencyCode)) ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p style="color: var(--text-muted); font-size: 0.9rem;">No sales yet.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (empty($eventStats)): ?>
    <p style="color: var(--text-muted); text-align: center; padding: 2rem;">
      No events yet. <a href="/admin/events/new">Create your first event.</a>
    </p>
  <?php endif; ?>

</main>

</body>
</html>
