<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Health: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<style>
.health-card {
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.health-status {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}
.status-icon {
    font-size: 2rem;
    min-width: 2.5rem;
}
.status-ok { color: #4caf50; }
.status-warning { color: #ff9800; }
.status-error { color: #f44336; }
.status-message {
    font-size: 1.1rem;
    font-weight: 500;
}
.status-details {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-top: 0.5rem;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}
.stat-box {
    padding: 1rem;
    background-color: #f5f5f5;
    border-radius: 4px;
    text-align: center;
}
.stat-value {
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}
.stat-label {
    font-size: 0.85rem;
    color: var(--text-muted);
}
.refresh-note {
    padding: 1rem;
    background-color: #f5f5f5;
    border-radius: 4px;
    font-size: 0.9rem;
    color: var(--text-muted);
    text-align: center;
}
.error-log {
    background-color: #fff3f3;
    border: 1px solid #ffdddd;
    border-radius: 4px;
    padding: 1rem;
}
.error-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid #ffdddd;
    font-size: 0.9rem;
}
.error-item:last-child {
    border-bottom: none;
}
.error-time {
    color: #999;
    font-size: 0.85rem;
}
</style>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/admin" class="site-title"><?= e($siteName) ?></a>
  <form method="post" action="/admin/logout" class="logout-form">
    <button type="submit">Log out</button>
  </form>
</header>

<main class="dashboard">
  <h1>System Health</h1>

  <div class="refresh-note">
    🔄 Status as of <?= date('H:i:s') ?> — <a href="">Refresh</a>
  </div>

  <!-- Database -->
  <div class="health-card">
    <div class="health-status">
      <div class="status-icon status-<?= e($health['database']['status']) ?>">
        <?= $health['database']['status'] === 'ok' ? '✓' : ($health['database']['status'] === 'warning' ? '⚠' : '✗') ?>
      </div>
      <div>
        <div class="status-message">Database</div>
        <div class="status-details"><?= e($health['database']['message']) ?></div>
      </div>
    </div>
    <?php if (!empty($health['database']['stats'])): ?>
      <div class="stats-grid">
        <div class="stat-box">
          <div class="stat-value"><?= e($health['database']['stats']['orders']) ?></div>
          <div class="stat-label">Orders</div>
        </div>
        <div class="stat-box">
          <div class="stat-value"><?= e($health['database']['stats']['photos']) ?></div>
          <div class="stat-label">Photos</div>
        </div>
        <div class="stat-box">
          <div class="stat-value"><?= e($health['database']['stats']['admins']) ?></div>
          <div class="stat-label">Admins</div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Cron -->
  <div class="health-card">
    <div class="health-status">
      <div class="status-icon status-<?= e($health['cron']['status']) ?>">
        <?= $health['cron']['status'] === 'ok' ? '✓' : ($health['cron']['status'] === 'warning' ? '⚠' : '✗') ?>
      </div>
      <div>
        <div class="status-message">Background Jobs (Cron)</div>
        <div class="status-details"><?= e($health['cron']['message']) ?></div>
      </div>
    </div>
    <?php if (!empty($health['cron']['stats'])): ?>
      <div class="stats-grid">
        <div class="stat-box">
          <div class="stat-value"><?= e($health['cron']['stats']['pending'] ?? 0) ?></div>
          <div class="stat-label">Pending</div>
        </div>
        <div class="stat-box">
          <div class="stat-value"><?= e($health['cron']['stats']['running'] ?? 0) ?></div>
          <div class="stat-label">Running</div>
        </div>
        <div class="stat-box">
          <div class="stat-value"><?= e($health['cron']['stats']['failed'] ?? 0) ?></div>
          <div class="stat-label">Failed</div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Email -->
  <div class="health-card">
    <div class="health-status">
      <div class="status-icon status-<?= e($health['email']['status']) ?>">
        <?= $health['email']['status'] === 'ok' ? '✓' : '⚠' ?>
      </div>
      <div>
        <div class="status-message">Email</div>
        <div class="status-details">
          <?= e($health['email']['message']) ?><br>
          <span style="font-size: 0.85rem; color: #999;">Method: <?= e($health['email']['method'] ?? '—') ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Derivatives -->
  <div class="health-card">
    <div class="health-status">
      <div class="status-icon status-<?= e($health['derivatives']['status']) ?>">
        <?= $health['derivatives']['status'] === 'ok' ? '✓' : '⚠' ?>
      </div>
      <div>
        <div class="status-message">Photo Derivatives</div>
        <div class="status-details"><?= e($health['derivatives']['message']) ?></div>
      </div>
    </div>
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-value"><?= e($health['derivatives']['pending'] ?? 0) ?></div>
        <div class="stat-label">In Queue</div>
      </div>
    </div>
  </div>

  <!-- Storage -->
  <div class="health-card">
    <div class="health-status">
      <div class="status-icon status-<?= e($health['storage']['status']) ?>">
        <?= $health['storage']['status'] === 'ok' ? '✓' : ($health['storage']['status'] === 'warning' ? '⚠' : '✗') ?>
      </div>
      <div>
        <div class="status-message">Storage</div>
        <div class="status-details"><?= e($health['storage']['message']) ?></div>
      </div>
    </div>
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-value"><?= e($health['storage']['hires_size']) ?></div>
        <div class="stat-label">Original Photos</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= e($health['storage']['derivatives_size']) ?></div>
        <div class="stat-label">Derivatives</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= e($health['storage']['total_size']) ?></div>
        <div class="stat-label">Total Used</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= e($health['storage']['free_space']) ?></div>
        <div class="stat-label">Free Space</div>
      </div>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="health-card">
    <h2 style="margin-top: 0;">Last 24 Hours</h2>
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-value"><?= e($health['stats']['orders_24h'] ?? 0) ?></div>
        <div class="stat-label">New Orders</div>
      </div>
      <div class="stat-box">
        <div class="stat-value"><?= e($health['stats']['photos_24h'] ?? 0) ?></div>
        <div class="stat-label">Photos Uploaded</div>
      </div>
    </div>
  </div>

  <!-- Recent Errors -->
  <?php if (!empty($health['recent_errors'])): ?>
    <div class="health-card">
      <h2 style="margin-top: 0; margin-bottom: 1rem;">Recent Errors</h2>
      <div class="error-log">
        <?php foreach ($health['recent_errors'] as $error): ?>
          <div class="error-item">
            <strong><?= e($error['action']) ?></strong>
            <div style="color: #666;"><?= e(substr($error['details'] ?? '', 0, 80)) ?></div>
            <div class="error-time"><?= e(date('M d H:i:s', strtotime($error['created_at']))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="margin: 1rem 0 0 0; text-align: center;">
        <a href="/admin/audit-log?action=failed">View all errors →</a>
      </p>
    </div>
  <?php endif; ?>

  <div style="margin-top: 2rem; padding: 1.5rem; background-color: #f9f9f9; border-radius: 4px;">
    <h3 style="margin-top: 0;">Status Legend</h3>
    <ul style="margin: 1rem 0 0 0;">
      <li><span style="color: #4caf50;">✓</span> <strong>OK</strong> — System working normally</li>
      <li><span style="color: #ff9800;">⚠</span> <strong>Warning</strong> — Minor issue, may need attention</li>
      <li><span style="color: #f44336;">✗</span> <strong>Error</strong> — Critical issue, requires action</li>
    </ul>
  </div>

</main>

</body>
</html>
