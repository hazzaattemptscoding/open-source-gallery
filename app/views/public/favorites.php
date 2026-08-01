<?php
$pageTitle = 'Favourites: ' . e($siteName);
$metaDescription = 'Your favourited photos';
$metaUrl = rtrim($GLOBALS['config']['site']['base_url'] ?? 'https://example.com', '/') . '/favorites';
$showCart = true;
$pageScripts = '<script src="/assets/js/favorites.js" defer></script>';
require __DIR__ . '/partials/layout_header.php';
?>

<main class="favorites-page">
  <h1>Your favourites</h1>

  <?php if (empty($items)): ?>
    <?php
      $emptyState = [
        'title' => 'No favourites yet',
        'message' => 'Tap the heart on any photo to save it here.',
        'action' => [
          'label' => 'Browse events',
          'href' => '/',
        ],
      ];
      require __DIR__ . '/partials/empty-state.php';
    ?>
  <?php else: ?>
    <ul class="favorites-grid" id="favoritesGrid">
      <?php foreach ($items as $item): ?>
        <li class="favorites-item" data-photo-id="<?= (int)$item['photo_id'] ?>">
          <img src="/media/d/<?= e($item['public_token']) ?>-400.jpg" alt="Favourited photo" loading="lazy">
          <span class="favorites-item-price"><?= e(format_pence((int)$item['price_pence'], $currencyCode)) ?></span>
          <button type="button" class="favorites-item-remove" aria-label="Remove from favourites">&times;</button>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="favorites-actions">
      <button type="button" id="addAllToCartBtn">Add all to cart</button>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
