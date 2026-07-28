<?php
/**
 * Grid markup only — shared by the full event page render and the
 * /api/photos fragment endpoint so filtered results look identical
 * whichever path produced them. Expects $photos in scope.
 */
?>
<?php if (empty($photos)): ?>
  <p class="empty-state">No photos match these filters.</p>
<?php else: ?>
  <?php foreach ($photos as $index => $photo): ?>
    <div class="photo-thumb" data-index="<?= (int)$index ?>" style="aspect-ratio: <?= (int)$photo['width'] ?> / <?= (int)$photo['height'] ?>;">
      <img
        src="/media/d/<?= e($photo['public_token']) ?>-800.jpg"
        srcset="/media/d/<?= e($photo['public_token']) ?>-400.jpg 400w, /media/d/<?= e($photo['public_token']) ?>-800.jpg 800w, /media/d/<?= e($photo['public_token']) ?>-1600.jpg 1600w"
        sizes="(max-width: 600px) 50vw, 25vw"
        loading="lazy"
        alt="">
      <button type="button" class="add-to-cart" data-photo-id="<?= (int)$photo['id'] ?>" aria-label="Add to cart">+</button>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
