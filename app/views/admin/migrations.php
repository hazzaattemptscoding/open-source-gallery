<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Migrations — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<div class="dashboard">
  <h1>Migrations</h1>
  <p><a href="/admin">← Back to dashboard</a></p>

  <?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($applied)): ?>
    <p>Applied:</p>
    <ul class="migration-list">
      <?php foreach ($applied as $filename): ?>
        <li class="migration-item applied"><?= e($filename) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (empty($pending)): ?>
    <p>Database is up to date. No pending migrations.</p>
  <?php else: ?>
    <p><?= count($pending) ?> pending migration(s):</p>
    <ul class="migration-list">
      <?php foreach ($pending as $filename): ?>
        <li class="migration-item"><?= e($filename) ?></li>
      <?php endforeach; ?>
    </ul>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <button type="submit" data-confirm="Apply all pending migrations? This changes the database schema and cannot be undone automatically.">Apply pending migrations</button>
    </form>
  <?php endif; ?>
</div>
<script src="/assets/js/admin-common.js" defer></script>
</body>
</html>
