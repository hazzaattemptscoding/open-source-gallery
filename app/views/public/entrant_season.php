<?php
/**
 * Season page: one driver, every event they appear in.
 *
 * Entrants are per-event rows, so a season is reconstructed by matching number
 * and class across events (see fetch_entrant_season). This is the durable link
 * a driver can keep for a whole year, and the foundation a season-pass product
 * will sit on later.
 *
 * As everywhere public, the driver is identified by number and class only.
 */
$metaDescription = 'Season photos for kart #' . ($entrant['number'] ?? '')
    . ' in ' . ($entrant['class_name'] ?? '') . '.';
$metaUrl = site_base_url($GLOBALS['config']) . '/driver/' . rawurlencode($entrant['share_token']);
require __DIR__ . '/partials/layout_header.php';
?>

<main class="season-page">

  <header class="entrant-header">
    <div class="entrant-identity">
      <span class="entrant-number">#<?= e($entrant['number']) ?></span>
      <span class="entrant-class"><?= e($entrant['class_name']) ?></span>
    </div>
    <p class="entrant-count">
      <?= count($events) === 1 ? '1 event' : count($events) . ' events' ?>,
      <?= number_format((int)$totalPhotos) ?> photos in total
    </p>
  </header>

  <?php if (empty($events)): ?>
    <?php
      $emptyState = [
        'title' => 'Nothing to show yet',
        'message' => 'Once photos from an event are tagged with this kart number, they will appear here.',
        'action' => ['label' => 'Browse events', 'href' => '/'],
      ];
      require __DIR__ . '/partials/empty-state.php';
    ?>
  <?php else: ?>
    <ul class="season-events">
      <?php foreach ($events as $ev): ?>
        <li class="season-event">
          <a class="season-event-link" href="/e/<?= e($ev['event_slug']) ?>/d/<?= e($ev['share_token']) ?>">
            <span class="season-event-title"><?= e($ev['event_title']) ?></span>
            <?php if (!empty($ev['event_date'])): ?>
              <span class="season-event-date"><?= e(date('j M Y', strtotime($ev['event_date']))) ?></span>
            <?php endif; ?>
            <span class="season-event-count">
              <?= (int)$ev['photo_count'] === 1 ? '1 photo' : (int)$ev['photo_count'] . ' photos' ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
