<?php
/**
 * Home page: hero on the newest event, a strip of the three after it, then
 * everything older grouped by year.
 *
 * Nothing here touches photo_tags, so no driver name can reach this page. That
 * is deliberate and load-bearing, not incidental: see the driver-name removal
 * in commit d750edf.
 */
require_once __DIR__ . '/../../lib/seo.php';
$pageTitle = $siteName;
$metaDescription = 'Professional sports photography gallery and sales platform';
$metaUrl = $GLOBALS['config']['site']['url'] ?? 'https://example.com';
$metaImageUrl = '';
$showCart = false; // home page doesn't show cart
$extraStyles = generate_organization_schema($GLOBALS['config']) ?? '';
require __DIR__ . '/partials/layout_header.php';

/**
 * One event card. Identical markup in the strip and the archive, so the two
 * cannot drift apart as either changes.
 */
function home_event_card(array $event, string $sizeSuffix = '800'): void {
    $timestamp = !empty($event['event_date']) ? strtotime((string) $event['event_date']) : false;
    ?>
    <a class="event-card" href="/e/<?= e($event['slug']) ?>">
      <div class="event-card-image">
        <?php if ($event['cover_token']): ?>
          <img src="/media/d/<?= e($event['cover_token']) ?>-<?= e($sizeSuffix) ?>.jpg"
               alt="<?= e($event['title']) ?>" loading="lazy">
        <?php endif; ?>
      </div>
      <div class="event-card-body">
        <h3><?= e($event['title']) ?></h3>
        <p class="event-card-meta">
          <?= $timestamp !== false ? e(date('j M Y', $timestamp)) : 'Date to be confirmed' ?>
          <?php if ($event['venue']): ?> · <?= e($event['venue']) ?><?php endif; ?>
        </p>
      </div>
    </a>
    <?php
}
?>

<?php if ($hero !== null): ?>
  <?php $heroTimestamp = !empty($hero['event_date']) ? strtotime((string) $hero['event_date']) : false; ?>
  <section class="hero">
    <?php if ($hero['cover_token']): ?>
      <img src="/media/d/<?= e($hero['cover_token']) ?>-1200.jpg" alt="<?= e($hero['title']) ?>" class="hero-image">
    <?php endif; ?>
    <div class="hero-overlay">
      <div class="hero-meta">
        <div class="hero-eyebrow">Latest event</div>
        <h2 class="hero-title"><?= e($hero['title']) ?></h2>
        <p class="hero-details">
          <?= $heroTimestamp !== false ? e(date('j M Y', $heroTimestamp)) : '' ?>
          <?php if ($hero['venue']): ?> · <?= e($hero['venue']) ?><?php endif; ?>
        </p>
        <a href="/e/<?= e($hero['slug']) ?>" class="button hero-cta">View gallery</a>
      </div>
    </div>
  </section>
<?php endif; ?>

<main class="event-list-page">
  <?php if ($hero === null): ?>
    <p class="empty-state">No events published yet. Check back soon.</p>
  <?php endif; ?>

  <?php if (!empty($recent)): ?>
    <section class="home-section">
      <h2 class="home-section-title">Recent galleries</h2>
      <?php /* Scroll-snap rather than a JS carousel: no build step and no
               dependency, and where all three fit it simply reads as a row. */ ?>
      <div class="event-rail">
        <?php foreach ($recent as $event): ?>
          <?php home_event_card($event); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($archiveByYear)): ?>
    <section class="home-section">
      <h2 class="home-section-title">All galleries</h2>
      <?php foreach ($archiveByYear as $year => $yearEvents): ?>
        <div class="archive-year">
          <h3 class="archive-year-title"><?= e((string) $year) ?></h3>
          <div class="event-cards">
            <?php foreach ($yearEvents as $event): ?>
              <?php home_event_card($event); ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
