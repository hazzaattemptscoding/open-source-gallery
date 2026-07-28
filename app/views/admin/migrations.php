<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Migrations — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
ul { list-style: none; padding: 0; }
li { padding: 0.5rem 0.75rem; border: 1px solid var(--purple-deep); border-radius: 4px; margin: 0.5rem 0; font-family: monospace; }
.applied { background: rgba(109, 40, 217, 0.1); }
</style>
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
    <ul>
      <?php foreach ($applied as $filename): ?>
        <li class="applied"><?= e($filename) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (empty($pending)): ?>
    <p>Database is up to date. No pending migrations.</p>
  <?php else: ?>
    <p><?= count($pending) ?> pending migration(s):</p>
    <ul>
      <?php foreach ($pending as $filename): ?>
        <li><?= e($filename) ?></li>
      <?php endforeach; ?>
    </ul>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <button type="submit" onclick="return confirm('Apply all pending migrations? This changes the database schema and cannot be undone automatically.')">Apply pending migrations</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
