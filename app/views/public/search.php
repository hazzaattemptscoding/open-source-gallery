<?php
require_once __DIR__ . '/../../lib/currency.php';

$pageTitle = 'Search: ' . e($siteName);
$metaDescription = 'Search for photos';
$metaUrl = ($GLOBALS['config']['site']['url'] ?? '') . '/search';
$showCart = false;
require __DIR__ . '/partials/layout_header.php';
?>

<div class="search-container">
  <form method="get" action="/search" class="search-form">
    <input type="text" name="q" placeholder="Search photos..." value="<?= e($query) ?>" autofocus>
    <button type="submit">Search</button>
  </form>

  <?php if (!empty($query) || !empty($filters)): ?>

    <?php if ($results['total'] > 0): ?>

      <div class="search-layout">
        <!-- Sidebar: Filters -->
        <aside class="search-sidebar">
          <h2 style="font-size: 1rem; margin-top: 0;">Filters</h2>

          <?php if (!empty($results['facets']['events'])): ?>
            <div class="filter-group">
              <h3>Events</h3>
              <ul class="filter-list">
                <?php foreach ($results['facets']['events'] as $event): ?>
                  <li>
                    <label>
                      <input type="checkbox" name="event" value="<?= e($event['id']) ?>"
                        <?= ($filters['event_id'] ?? null) == $event['id'] ? 'checked' : '' ?>>
                      <?= e($event['title']) ?>
                      <span class="count"><?= e($event['count']) ?></span>
                    </label>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($results['facets']['karts'])): ?>
            <div class="filter-group">
              <h3>Karts</h3>
              <ul class="filter-list">
                <?php foreach (array_slice($results['facets']['karts'], 0, 10) as $kart): ?>
                  <li>
                    <label>
                      <input type="checkbox" name="kart" value="<?= e($kart['kart_number']) ?>"
                        <?= ($filters['kart'] ?? null) == $kart['kart_number'] ? 'checked' : '' ?>>
                      #<?= e($kart['kart_number']) ?>
                      <span class="count"><?= e($kart['count']) ?></span>
                    </label>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($results['facets']['drivers'])): ?>
            <div class="filter-group">
              <h3>Drivers</h3>
              <ul class="filter-list">
                <?php foreach (array_slice($results['facets']['drivers'], 0, 10) as $driver): ?>
                  <li>
                    <label>
                      <input type="checkbox" name="driver" value="<?= e($driver['driver_name']) ?>"
                        <?= ($filters['driver'] ?? null) == $driver['driver_name'] ? 'checked' : '' ?>>
                      <?= e($driver['driver_name']) ?>
                      <span class="count"><?= e($driver['count']) ?></span>
                    </label>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </aside>

        <!-- Main: Results -->
        <main>
          <p style="color: #666; margin-bottom: 2rem;">
            Found <strong><?= e($results['total']) ?></strong> photo<?= $results['total'] != 1 ? 's' : '' ?>
            <?php if (!empty($query)): ?>
              matching "<?= e($query) ?>"
            <?php endif; ?>
          </p>

          <div class="search-results">
            <?php foreach ($results['photos'] as $photo): ?>
              <a href="/e/<?= e($photo['event_slug']) ?>/<?= e($photo['session_slug']) ?>" class="search-result">
                <img src="/media/d/<?= e($photo['public_token']) ?>-800.jpg"
                     alt="<?= e($photo['original_filename']) ?>"
                     class="search-result-image"
                     loading="lazy"
                     width="<?= (int)$photo['width'] ?>"
                     height="<?= (int)$photo['height'] ?>">
                <div class="search-result-info">
                  <div class="search-result-title"><?= e($photo['original_filename']) ?></div>
                  <div class="search-result-meta"><?= e($photo['event_title']) ?></div>
                  <div class="search-result-price">
                    <?= e(format_pence((int)$photo['price_single_pence'], $currencyCode)) ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if ($results['pages'] > 1): ?>
            <div class="pagination">
              <?php
                // Build filter query string to preserve filters on pagination
                $filterParams = [];
                if (!empty($query)) $filterParams[] = 'q=' . urlencode($query);
                if (!empty($filters['event_id'])) $filterParams[] = 'event=' . (int)$filters['event_id'];
                if (!empty($filters['kart'])) $filterParams[] = 'kart=' . urlencode($filters['kart']);
                if (!empty($filters['driver'])) $filterParams[] = 'driver=' . urlencode($filters['driver']);
                if (!empty($filters['class'])) $filterParams[] = 'class=' . urlencode($filters['class']);
                if (!empty($filters['price_min'])) $filterParams[] = 'price_min=' . (int)$filters['price_min'];
                if (!empty($filters['price_max'])) $filterParams[] = 'price_max=' . (int)$filters['price_max'];
                if (!empty($filters['date_from'])) $filterParams[] = 'date_from=' . urlencode($filters['date_from']);
                if (!empty($filters['date_to'])) $filterParams[] = 'date_to=' . urlencode($filters['date_to']);
                $baseUrl = count($filterParams) > 0 ? '?' . implode('&', $filterParams) . '&' : '?';
              ?>
              <?php if ($results['page'] > 1): ?>
                <a href="<?= $baseUrl ?>page=1">First</a>
                <a href="<?= $baseUrl ?>page=<?= $results['page'] - 1 ?>">← Prev</a>
              <?php endif; ?>

              <span class="current"><?= e($results['page']) ?> / <?= e($results['pages']) ?></span>

              <?php if ($results['page'] < $results['pages']): ?>
                <a href="<?= $baseUrl ?>page=<?= $results['page'] + 1 ?>">Next →</a>
                <a href="<?= $baseUrl ?>page=<?= $results['pages'] ?>">Last</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </main>
      </div>

    <?php else: ?>

      <div class="search-no-results">
        <h2>No results found</h2>
        <p>Try different keywords or adjust your filters.</p>
      </div>

    <?php endif; ?>

  <?php else: ?>

    <div class="search-no-results">
      <h2>Enter a search query</h2>
      <p>Search for photos by filename, kart number, driver name, or class.</p>
    </div>

  <?php endif; ?>

</div>

<?php require __DIR__ . '/partials/layout_footer.php'; ?>
