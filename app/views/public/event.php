<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($event['title']) ?>: <?= e($siteName) ?></title>
<link rel="stylesheet" href="/api/styles.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
  <a href="/cart" class="cart-badge" id="cartBadge">Cart (<span id="cartCount"><?= (int)$cartCount ?></span>)</a>
</header>

<!-- Full-screen hero -->
<section class="hero">
  <?php if ($heroToken): ?>
    <img class="hero-image" src="/media/d/<?= e($heroToken) ?>-1600.jpg" alt="<?= e($event['title']) ?> event photography">
  <?php endif; ?>
  <div class="hero-overlay">
    <span class="hero-eyebrow">Event Gallery</span>
    <h1 class="hero-title"><?= e($event['title']) ?></h1>
  </div>
</section>

<!-- Hero CTA block -->
<section class="hero-cta-block">
  <a href="#photos" class="button hero-cta-primary">View Gallery</a>
  <a href="/" class="hero-cta-secondary">View all events</a>
</section>

<!-- Meta tags: date, venue, session/class -->
<nav class="session-nav">
  <span><?= e(date('j M Y', strtotime($event['event_date']))) ?></span>
  <?php if ($event['venue']): ?><span><?= e($event['venue']) ?></span><?php endif; ?>
  <?php if ($activeSession): ?><span><?= e($activeSession['name']) ?></span><?php endif; ?>
</nav>

<!-- Conditional filter dropdowns -->
<?php
$hasKartFilter = !empty($kartOptions);
$hasDriverFilter = !empty($driverOptions);
$hasClassFilter = !empty($classOptions);
$hasAnyFilter = $hasKartFilter || $hasDriverFilter || $hasClassFilter;
?>

<?php if ($hasAnyFilter): ?>
<form class="filter-bar" id="filterForm" method="get"
      data-event-slug="<?= e($event['slug']) ?>"
      data-session-slug="<?= e($activeSession['slug'] ?? '') ?>"
      data-base-path="<?= e($basePath) ?>">
  <?php if ($hasKartFilter): ?>
    <select name="kart" id="filterKart">
      <option value="">All karts</option>
      <?php foreach ($kartOptions as $kart): ?>
        <option value="<?= e($kart) ?>" <?= $filters['kart'] === $kart ? 'selected' : '' ?>><?= e($kart) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <?php if ($hasDriverFilter): ?>
    <select name="driver" id="filterDriver">
      <option value="">All drivers</option>
      <?php foreach ($driverOptions as $driver): ?>
        <option value="<?= e($driver) ?>" <?= $filters['driver'] === $driver ? 'selected' : '' ?>><?= e($driver) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <?php if ($hasClassFilter): ?>
    <select name="class" id="filterClass">
      <option value="">All classes</option>
      <?php foreach ($classOptions as $class): ?>
        <option value="<?= e($class) ?>" <?= $filters['class'] === $class ? 'selected' : '' ?>><?= e($class) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <button type="submit">Filter</button>
  <?php if ($filters['kart'] || $filters['driver'] || $filters['class']): ?>
    <button type="button" class="clear-filters" onclick="location.href='<?= e($basePath) ?>'">Clear</button>
  <?php endif; ?>
</form>
<?php endif; ?>

<!-- Search box (prominent) -->
<div class="search-box">
  <input type="text" id="searchInput" class="search-input" placeholder="Search by name, number, or class...">
</div>

<main id="photos">
  <!-- Photo grid with empty state -->
  <div class="photo-grid" id="photoGrid"
       data-photo-ids="<?= e(json_encode(array_map('intval', array_column($photos, 'id')))) ?>"
       data-photo-tokens="<?= e(json_encode(array_column($photos, 'public_token'))) ?>">
    <?php if (!empty($photos)): ?>
      <?php require __DIR__ . '/_photo_grid_items.php'; ?>
    <?php else: ?>
      <div class="empty-state" style="grid-column: 1 / -1;">
        <p>No photos match your filters.</p>
        <button type="button" class="clear-filters" onclick="location.href='<?= e($basePath) ?>'">Clear filters</button>
      </div>
    <?php endif; ?>
  </div>

  <!-- Videos section -->
  <?php if (!empty($videos)): ?>
  <section class="video-section">
    <h2>Videos</h2>
    <div class="video-grid">
      <?php foreach ($videos as $video): ?>
        <div class="video-item">
          <video controls preload="metadata" poster="/media/d/<?= e($video['public_token']) ?>-800.jpg"
                 width="<?= (int)$video['width'] ?>"
                 height="<?= (int)$video['height'] ?>">
            <source src="/media/v/<?= e($video['public_token']) ?>.mp4" type="video/mp4">
          </video>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<!-- Lightbox for tap-to-enlarge -->
<div class="lightbox" id="lightbox">
  <button class="lightbox-close" id="lightboxClose" aria-label="Close">&times;</button>
  <button class="lightbox-prev" id="lightboxPrev" aria-label="Previous">&#8249;</button>
  <img id="lightboxImage" alt="">
  <button class="lightbox-next" id="lightboxNext" aria-label="Next">&#8250;</button>
  <button class="lightbox-cart" id="lightboxCart">Add to cart</button>
</div>

<script src="/assets/js/event.js" defer></script>
</body>
</html>
