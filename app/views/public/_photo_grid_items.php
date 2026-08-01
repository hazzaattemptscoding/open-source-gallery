<?php
/**
 * Grid markup only — shared by the full event page render and the
 * /api/photos fragment endpoint so filtered results look identical
 * whichever path produced them. Expects $photos in scope.
 */
require_once __DIR__ . '/../../lib/seo.php';
?>
<?php if (empty($photos)): ?>
  <div class="empty-state" style="grid-column: 1 / -1;">
    <p>No photos match these filters.</p>
    <button type="button" class="clear-filters" data-reset-filters>Clear filters</button>
  </div>
<?php else: ?>
  <?php foreach ($photos as $photo): ?>
    <div class="photo-thumb"
         data-photo-id="<?= (int)$photo['id'] ?>"
         data-token="<?= e($photo['public_token']) ?>"
         data-kart-tags="<?= e($photo['kart_tags'] ?? '') ?>"
         data-class-tags="<?= e($photo['class_tags'] ?? '') ?>">
      <img
        src="/media/d/<?= e($photo['public_token']) ?>-800.jpg"
        srcset="/media/d/<?= e($photo['public_token']) ?>-400.jpg 400w, /media/d/<?= e($photo['public_token']) ?>-800.jpg 800w, /media/d/<?= e($photo['public_token']) ?>-1600.jpg 1600w"
        sizes="(max-width: 600px) 50vw, 25vw"
        loading="lazy"
        alt="<?= e(generate_gallery_photo_alt_text($photo)) ?>"
        width="<?= (int)$photo['width'] ?>"
        height="<?= (int)$photo['height'] ?>"
      >
      <button type="button" class="add-to-cart" data-photo-id="<?= (int)$photo['id'] ?>" aria-label="Add to cart">+</button>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
