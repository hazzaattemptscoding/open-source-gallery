<?php
/**
 * Reusable empty state component.
 * Usage: include 'path/to/empty-state.php' after setting $emptyState array
 * $emptyState = [
 *   'icon' => '📸',
 *   'title' => 'No photos yet',
 *   'message' => 'This gallery is still being populated...',
 *   'action' => ['label' => 'Browse other events', 'href' => '/'],
 * ]
 */
?>
<div class="empty-state">
  <?php if (!empty($emptyState['icon'])): ?>
    <div class="empty-state-icon"><?= e($emptyState['icon']) ?></div>
  <?php endif; ?>

  <?php if (!empty($emptyState['title'])): ?>
    <h2 class="empty-state-title"><?= e($emptyState['title']) ?></h2>
  <?php endif; ?>

  <?php if (!empty($emptyState['message'])): ?>
    <p class="empty-state-message"><?= e($emptyState['message']) ?></p>
  <?php endif; ?>

  <?php if (!empty($emptyState['action'])): ?>
    <a href="<?= e($emptyState['action']['href']) ?>" class="button empty-state-action">
      <?= e($emptyState['action']['label']) ?>
    </a>
  <?php endif; ?>
</div>
