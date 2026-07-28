<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

<main class="event-list-page">
  <?php if (empty($events)): ?>
    <p class="empty-state">No events published yet. Check back soon.</p>
  <?php else: ?>
    <div class="event-cards">
      <?php foreach ($events as $event): ?>
        <a class="event-card" href="/e/<?= e($event['slug']) ?>">
          <div class="event-card-image">
            <?php if ($event['cover_token']): ?>
              <img src="/media/d/<?= e($event['cover_token']) ?>-800.jpg" alt="" loading="lazy">
            <?php endif; ?>
          </div>
          <div class="event-card-body">
            <h2><?= e($event['title']) ?></h2>
            <p class="event-card-meta">
              <?= e(date('j M Y', strtotime($event['event_date']))) ?>
              <?php if ($event['venue']): ?> · <?= e($event['venue']) ?><?php endif; ?>
            </p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
