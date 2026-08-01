<?php
// generate_meta_tags() escapes every value it is given (app/lib/seo.php),
// so these are built raw. They were passed through e() as well, which
// double-escaped any apostrophe in an event title.
$pageTitle = $event['title'] . ': ' . $siteName;
$metaDescription = isset($event['description']) ? $event['description'] : 'Event photography gallery';

$baseUrl = rtrim($GLOBALS['config']['site']['base_url'] ?? '', '/');
$metaUrl = $baseUrl . '/e/' . $event['slug'];

// $heroToken, not $event['cover_token']: the controller resolves the hero
// separately (falling back to the event's first live photo when no cover is
// set) and never selects a cover_token column at all, so this read was always
// undefined and every event's social card went out with no image. Absolute,
// because og:image is fetched by scrapers that have no page context to
// resolve a relative path against.
$metaImageUrl = $heroToken ? $baseUrl . '/media/d/' . $heroToken . '-1600.jpg' : '';
$showCart = true;
$pageScripts = '<script src="/assets/js/event.js" defer></script>';

// rel=prev/next belong in <head>, not the body content further down where
// $page/$hasMorePhotos become known -- $extraStyles is layout_header.php's
// existing head-injection slot, reused here for link tags rather than adding
// a second slot for the same purpose.
$extraStyles = '';
if ($page > 1) {
    $extraStyles .= '<link rel="prev" href="' . e($basePath) . '?page=' . ((int)$page - 1) . '">' . "\n";
}
if ($hasMorePhotos) {
    $extraStyles .= '<link rel="next" href="' . e($basePath) . '?page=' . ((int)$page + 1) . '">' . "\n";
}

require __DIR__ . '/partials/layout_header.php';
?>

<!-- Full-screen hero -->
<section class="hero">
  <?php if ($heroToken): ?>
    <img class="hero-image" src="/media/d/<?= e($heroToken) ?>-1600.jpg" alt="<?= e($event['title']) ?> event photography">
  <?php endif; ?>
  <div class="hero-overlay">
    <span class="hero-eyebrow">Event Gallery</span>
    <h1 class="hero-title"><?= e($event['title']) ?></h1>
    <div class="hero-search-box">
      <input type="text" id="searchInput" class="hero-search-input" placeholder="Search by name, number, or class...">
    </div>
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
$hasClassFilter = !empty($classOptions);
$hasAnyFilter = $hasKartFilter || $hasClassFilter;
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

  <?php if ($hasClassFilter): ?>
    <select name="class" id="filterClass">
      <option value="">All classes</option>
      <?php foreach ($classOptions as $class): ?>
        <option value="<?= e($class) ?>" <?= $filters['class'] === $class ? 'selected' : '' ?>><?= e($class) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <button type="submit">Filter</button>
  <?php if ($filters['kart'] || $filters['class']): ?>
    <button type="button" class="clear-filters" data-clear-href="<?= e($basePath) ?>">Clear</button>
  <?php endif; ?>
</form>
<?php endif; ?>

<main id="photos">
  <?php if ($totalPhotos > GALLERY_PAGE_SIZE): ?>
    <p class="photo-count">Showing <?= (int)min($page * GALLERY_PAGE_SIZE, $totalPhotos) ?> of <?= (int)$totalPhotos ?> photos</p>
  <?php endif; ?>

  <!-- Photo grid with empty state -->
  <div class="photo-grid" id="photoGrid"
       data-page="<?= (int)$page ?>">
    <?php if (!empty($photos)): ?>
      <?php require __DIR__ . '/_photo_grid_items.php'; ?>
    <?php else: ?>
      <?php
        $emptyState = [
          'title' => 'No photos match your filters',
          'message' => 'Try adjusting your search or filters to find what you\'re looking for.',
          'action' => [
            'label' => 'Clear filters',
            'href' => e($basePath),
          ],
        ];
        require __DIR__ . '/partials/empty-state.php';
      ?>
    <?php endif; ?>
  </div>

  <?php if ($hasMorePhotos): ?>
    <div class="load-more-row">
      <button type="button" id="loadMoreBtn"
              data-base-path="<?= e($basePath) ?>"
              data-event-slug="<?= e($event['slug']) ?>"
              data-session-slug="<?= e($activeSession['slug'] ?? '') ?>">Load more</button>
    </div>
  <?php endif; ?>

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

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
