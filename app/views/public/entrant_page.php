<?php
/**
 * The personal page: one driver's photos from one event.
 *
 * This is the destination the whole find-me flow exists to reach, and the page
 * people share. Three things it must get right:
 *
 *  - It identifies the driver by number and class, never by name. A large share
 *    of these drivers are minors and this page is public to anyone holding the
 *    link. See docs/PRIVACY-DESIGN.md.
 *  - The bundle is the headline, not a fallback. "Buy all my photos" is the
 *    action almost everyone wants, so it is the one large button.
 *  - The URL is durable and shareable. Parents post these into WhatsApp groups,
 *    which is the main way these galleries spread.
 */
$metaDescription = 'Photos of kart #' . ($entrant['number'] ?? '') . ' in '
    . ($entrant['class_name'] ?? '') . ' at ' . ($entrant['event_title'] ?? '') . '.';
$metaUrl = site_base_url($GLOBALS['config']) . '/e/' . rawurlencode($entrant['event_slug'])
    . '/d/' . rawurlencode($entrant['share_token']);
$pageScripts = '<script src="/assets/js/entrant.js" defer></script>';
require __DIR__ . '/partials/layout_header.php';
?>

<main class="entrant-page" data-entrant-token="<?= e($entrant['share_token']) ?>" data-csrf-token="<?= e($csrfToken) ?>">

  <nav class="entrant-breadcrumb">
    <a href="/e/<?= e($entrant['event_slug']) ?>">&larr; <?= e($entrant['event_title']) ?></a>
  </nav>

  <header class="entrant-header">
    <div class="entrant-identity">
      <span class="entrant-number">#<?= e($entrant['number']) ?></span>
      <span class="entrant-class"><?= e($entrant['class_name']) ?></span>
    </div>
    <p class="entrant-count">
      <?= (int)$totalPhotos === 1 ? '1 photo' : number_format((int)$totalPhotos) . ' photos' ?>
      from <?= e($entrant['event_title']) ?>
    </p>

    <?php if (!empty($sessions)): ?>
      <ul class="entrant-sessions">
        <?php foreach ($sessions as $session): ?>
          <li class="entrant-session">
            <span class="entrant-session-name"><?= e($session['session_name']) ?></span>
            <span class="entrant-session-count"><?= (int)$session['photo_count'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ((int)$totalPhotos > 0): ?>
      <div class="entrant-actions">
        <button type="button" class="entrant-buy-all" data-entrant-buy-all>
          Buy all <?= (int)$totalPhotos ?> photos
        </button>
        <a class="entrant-season-link" href="/driver/<?= e($entrant['share_token']) ?>">
          See this driver's whole season
        </a>
      </div>
    <?php endif; ?>
  </header>

  <?php if (empty($photos)): ?>
    <?php
      $emptyState = [
        'title' => 'No photos yet for this driver',
        'message' => 'Photos are added as they are processed and tagged. This link stays the same, so it is worth keeping.',
        'action' => ['label' => 'Browse the whole event', 'href' => '/e/' . $entrant['event_slug']],
      ];
      require __DIR__ . '/partials/empty-state.php';
    ?>
  <?php else: ?>
    <section class="entrant-grid-section">
      <ul class="photo-grid entrant-grid" id="entrantGrid">
        <?php foreach ($photos as $photo): ?>
          <li class="photo-tile" data-photo-id="<?= (int)$photo['id'] ?>">
            <img
              src="/media/d/<?= e($photo['public_token']) ?>-400.jpg"
              alt="Kart <?= e($entrant['number']) ?>, <?= e($entrant['class_name']) ?>"
              width="<?= (int)$photo['width'] ?>"
              height="<?= (int)$photo['height'] ?>"
              loading="lazy">
            <button type="button" class="add-to-cart" data-photo-id="<?= (int)$photo['id'] ?>" aria-label="Add to cart">+</button>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($hasMore): ?>
        <?php /* ?page= keeps every photo reachable without JavaScript. */ ?>
        <div class="entrant-more">
          <a class="load-more" href="/e/<?= e($entrant['event_slug']) ?>/d/<?= e($entrant['share_token']) ?>?page=<?= (int)$page + 1 ?>">
            Load more photos
          </a>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if (!empty($maybePhotos)): ?>
    <?php /*
      "Are there more of me?" Everything below the confidence threshold that
      nobody has ruled on yet. Each answer is written straight back to
      photo_entrants, so one visitor tidying up their own page improves the
      data for the photographer and for everyone who searches later.
    */ ?>
    <section class="entrant-maybe" id="entrantMaybe">
      <h2 class="entrant-maybe-title">Are there more of you?</h2>
      <p class="entrant-maybe-hint">
        These might be you. Tell us and we will add them to your photos.
      </p>

      <ul class="photo-grid entrant-maybe-grid">
        <?php foreach ($maybePhotos as $maybe): ?>
          <li class="photo-tile entrant-maybe-tile" data-photo-id="<?= (int)$maybe['id'] ?>">
            <img
              src="/media/d/<?= e($maybe['public_token']) ?>-400.jpg"
              alt="Possible match for kart <?= e($entrant['number']) ?>"
              width="<?= (int)$maybe['width'] ?>"
              height="<?= (int)$maybe['height'] ?>"
              loading="lazy">
            <div class="entrant-maybe-controls">
              <button type="button" class="entrant-verdict entrant-verdict-yes" data-verdict="mine" data-photo-id="<?= (int)$maybe['id'] ?>">
                That's me
              </button>
              <button type="button" class="entrant-verdict entrant-verdict-no" data-verdict="not_mine" data-photo-id="<?= (int)$maybe['id'] ?>">
                Not me
              </button>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
