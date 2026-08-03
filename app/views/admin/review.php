<?php
/**
 * Review queue: every uncertain attribution, grouped by driver.
 *
 * Grouping is the whole design. Four hundred uncertain photos presented as a
 * flat list is four hundred decisions. Grouped by number it is usually a dozen
 * groups, and a group is normally right or wrong as a whole, which is what
 * makes "confirm all" safe enough to be the primary action.
 *
 * Driver names appear here. This is an authenticated admin page, and the
 * photographer needs to know who they are looking at. The rule is that names
 * never reach a *public* surface; see docs/PRIVACY-DESIGN.md.
 */
require_once __DIR__ . '/partials/layout_header.php';
?>
<div class="dashboard">
  <h1>Review detections</h1>
  <p><a href="/admin">&larr; Back to dashboard</a></p>

  <?php if (isset($_GET['done'])): ?>
    <p class="success"><?= (int)$_GET['done'] ?> photo(s) updated.</p>
  <?php endif; ?>

  <form method="get" action="/admin/review" class="filter-bar">
    <select name="event" onchange="this.form.submit()">
      <option value="">Choose an event</option>
      <?php foreach ($events as $event): ?>
        <option value="<?= (int)$event['id'] ?>" <?= $eventId === (int)$event['id'] ? 'selected' : '' ?>>
          <?= e($event['title']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn-secondary">Show</button></noscript>
  </form>

  <?php if ($eventId <= 0): ?>
    <p>Choose an event to see what is waiting for review.</p>

  <?php elseif (empty($groups)): ?>
    <div class="admin-section">
      <h3>Nothing to review</h3>
      <p>
        Every detection for this event is either confident enough to be live or
        has already been ruled on. Nothing here needs you.
      </p>
    </div>

  <?php else: ?>
    <p class="hint">
      These are below the confidence threshold, so they are not showing in any
      gallery yet. Confirming adds them to that driver's photos; rejecting keeps
      the record so the same guess is not proposed again.
    </p>

    <?php foreach ($groups as $group): ?>
      <div class="admin-section review-group">
        <h3>
          #<?= e($group['number']) ?> <?= e($group['class_name']) ?>
          <?php if (!empty($group['driver_name'])): ?>
            <span class="review-driver"><?= e($group['driver_name']) ?></span>
          <?php endif; ?>
        </h3>
        <p class="review-count"><?= (int)$group['pending_count'] ?> photo(s) awaiting a decision</p>

        <div class="review-thumbs">
          <?php foreach ($group['photos'] as $photo): ?>
            <figure class="review-thumb">
              <img src="/media/d/<?= e($photo['public_token']) ?>-400.jpg" alt="" loading="lazy">
              <figcaption>
                <?= e(number_format((float)$photo['confidence'], 2)) ?>
                <span class="review-source"><?= e($photo['source']) ?></span>
              </figcaption>
            </figure>
          <?php endforeach; ?>
        </div>

        <?php if ((int)$group['pending_count'] > count($group['photos'])): ?>
          <p class="hint">
            Showing <?= count($group['photos']) ?> of <?= (int)$group['pending_count'] ?>.
            The buttons below act on all <?= (int)$group['pending_count'] ?>.
          </p>
        <?php endif; ?>

        <div class="review-actions">
          <form method="post" action="/admin/review" class="form-inline">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="event_id" value="<?= (int)$eventId ?>">
            <input type="hidden" name="entrant_id" value="<?= (int)$group['entrant_id'] ?>">
            <input type="hidden" name="action" value="confirm_all">
            <button type="submit" class="btn-primary"
                    data-confirm="Add all <?= (int)$group['pending_count'] ?> photos to #<?= e($group['number']) ?>?">
              These are all #<?= e($group['number']) ?>
            </button>
          </form>

          <form method="post" action="/admin/review" class="form-inline">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="event_id" value="<?= (int)$eventId ?>">
            <input type="hidden" name="entrant_id" value="<?= (int)$group['entrant_id'] ?>">
            <input type="hidden" name="action" value="reject_all">
            <button type="submit" class="btn-danger"
                    data-confirm="Reject all <?= (int)$group['pending_count'] ?> photos for #<?= e($group['number']) ?>?">
              None of these are #<?= e($group['number']) ?>
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<script src="/assets/js/admin-common.js" defer></script>
<?php require_once __DIR__ . '/partials/layout_footer.php'; ?>
