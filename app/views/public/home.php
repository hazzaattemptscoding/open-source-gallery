<?php
$pageTitle = $siteName;
$metaDescription = 'Professional sports photography gallery and sales platform';
$metaUrl = $GLOBALS['config']['site']['url'] ?? 'https://example.com';
$metaImageUrl = '';
$showCart = false; // home page doesn't show cart
$extraStyles = generate_organization_schema($GLOBALS['config']) ?? '';
require __DIR__ . '/partials/layout_header.php';
?>

<?php if (!empty($events)): ?>
<section class="hero">
  <?php if ($events[0]['cover_token']): ?>
    <img src="/media/d/<?= e($events[0]['cover_token']) ?>-1200.jpg" alt="<?= e($events[0]['title']) ?>" class="hero-image">
  <?php endif; ?>
  <div class="hero-overlay">
    <div class="hero-meta">
      <div class="hero-eyebrow">Latest event</div>
      <h2 class="hero-title"><?= e($events[0]['title']) ?></h2>
      <p class="hero-details">
        <?= e(date('j M Y', strtotime($events[0]['event_date']))) ?>
        <?php if ($events[0]['venue']): ?> · <?= e($events[0]['venue']) ?><?php endif; ?>
      </p>
      <a href="/e/<?= e($events[0]['slug']) ?>" class="button hero-cta">View gallery</a>
    </div>
  </div>
</section>
<?php endif; ?>

<main class="event-list-page">
  <?php if (empty($events)): ?>
    <p class="empty-state">No events published yet. Check back soon.</p>
  <?php else: ?>
    <div class="event-cards">
      <?php foreach ($events as $event): ?>
        <a class="event-card" href="/e/<?= e($event['slug']) ?>">
          <div class="event-card-image">
            <?php if ($event['cover_token']): ?>
              <img src="/media/d/<?= e($event['cover_token']) ?>-800.jpg" alt="<?= e($event['title']) ?>" loading="lazy">
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

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
