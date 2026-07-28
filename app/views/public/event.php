<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($event['title']) ?> — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
  <a href="/cart" class="cart-badge" id="cartBadge">Cart (<span id="cartCount"><?= (int)$cartCount ?></span>)</a>
</header>

<section class="hero">
  <?php if ($heroToken): ?>
    <img class="hero-image" src="/media/d/<?= e($heroToken) ?>-1600.jpg" alt="">
  <?php endif; ?>
  <div class="hero-overlay">
    <h1><?= e($event['title']) ?></h1>
    <p class="hero-meta">
      <?= e(date('j M Y', strtotime($event['event_date']))) ?>
      <?php if ($event['venue']): ?> · <?= e($event['venue']) ?><?php endif; ?>
    </p>
  </div>
</section>

<nav class="session-nav">
  <a href="/e/<?= e($event['slug']) ?>" class="<?= $activeSession === null ? 'active' : '' ?>">All sessions</a>
  <?php foreach ($sessions as $session): ?>
    <a href="/e/<?= e($event['slug']) ?>/<?= e($session['slug']) ?>" class="<?= ($activeSession && $activeSession['id'] === $session['id']) ? 'active' : '' ?>">
      <?= e($session['name']) ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($event['price_single_pence'] || $event['price_session_pence'] || $event['price_event_pence']): ?>
<div class="price-banner">
  <span>Single photo: <?= e(format_pence((int)$event['price_single_pence'], $currencyCode)) ?></span>
  <?php if ($event['price_session_pence']): ?>
    <span>Full session: <?= e(format_pence((int)$event['price_session_pence'], $currencyCode)) ?></span>
  <?php endif; ?>
  <?php if ($event['price_event_pence']): ?>
    <span>Full event: <?= e(format_pence((int)$event['price_event_pence'], $currencyCode)) ?></span>
  <?php endif; ?>
</div>
<?php endif; ?>

<form class="filter-bar" id="filterForm" method="get"
      data-event-slug="<?= e($event['slug']) ?>"
      data-session-slug="<?= e($activeSession['slug'] ?? '') ?>"
      data-base-path="<?= e($basePath) ?>">
  <select name="kart" id="filterKart">
    <option value="">All karts</option>
    <?php foreach ($kartOptions as $kart): ?>
      <option value="<?= e($kart) ?>" <?= $filters['kart'] === $kart ? 'selected' : '' ?>><?= e($kart) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="driver" id="filterDriver">
    <option value="">All drivers</option>
    <?php foreach ($driverOptions as $driver): ?>
      <option value="<?= e($driver) ?>" <?= $filters['driver'] === $driver ? 'selected' : '' ?>><?= e($driver) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="class" id="filterClass">
    <option value="">All classes</option>
    <?php foreach ($classOptions as $class): ?>
      <option value="<?= e($class) ?>" <?= $filters['class'] === $class ? 'selected' : '' ?>><?= e($class) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Filter</button>
  <?php if ($filters['kart'] || $filters['driver'] || $filters['class']): ?>
    <a href="<?= e($basePath) ?>" class="clear-filters">Clear</a>
  <?php endif; ?>
</form>

<main>
  <div class="photo-grid" id="photoGrid"
       data-photo-ids="<?= e(json_encode(array_map('intval', array_column($photos, 'id')))) ?>"
       data-photo-tokens="<?= e(json_encode(array_column($photos, 'public_token'))) ?>">
    <?php require __DIR__ . '/_photo_grid_items.php'; ?>
  </div>

  <?php if (!empty($videos)): ?>
  <section class="video-section">
    <h2>Videos</h2>
    <div class="video-grid">
      <?php foreach ($videos as $video): ?>
        <div class="video-item">
          <video controls preload="metadata" poster="/media/d/<?= e($video['public_token']) ?>-800.jpg">
            <source src="/media/v/<?= e($video['public_token']) ?>.mp4" type="video/mp4">
          </video>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<div class="lightbox" id="lightbox" hidden>
  <button class="lightbox-close" id="lightboxClose" aria-label="Close">&times;</button>
  <button class="lightbox-prev" id="lightboxPrev" aria-label="Previous">&#8249;</button>
  <img id="lightboxImage" alt="">
  <button class="lightbox-next" id="lightboxNext" aria-label="Next">&#8250;</button>
  <button class="lightbox-cart" id="lightboxCart">Add to cart</button>
</div>

<script src="/assets/js/event.js" defer></script>
</body>
</html>
