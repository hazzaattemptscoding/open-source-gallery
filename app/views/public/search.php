<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Search — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
<style>
.search-container {
    max-width: 1000px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.search-form {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
}

.search-form input {
    flex: 1;
    padding: 0.75rem;
    font-size: 1rem;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.search-form button {
    padding: 0.75rem 1.5rem;
    background: #000;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
}

.search-form button:hover {
    background: #333;
}

.search-layout {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 2rem;
}

.search-sidebar {
    background: #f5f5f5;
    padding: 1.5rem;
    border-radius: 4px;
    max-height: fit-content;
}

.filter-group {
    margin-bottom: 2rem;
}

.filter-group h3 {
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 1rem;
    color: #666;
}

.filter-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.filter-list li {
    margin-bottom: 0.5rem;
}

.filter-list label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 0.9rem;
}

.filter-list input[type="checkbox"] {
    margin-right: 0.5rem;
}

.filter-list .count {
    color: #999;
    font-size: 0.85rem;
    margin-left: auto;
}

.search-results {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.search-result {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 4px;
    overflow: hidden;
    transition: transform 0.2s;
}

.search-result:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.search-result-image {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    background: #f0f0f0;
}

.search-result-info {
    padding: 1rem;
}

.search-result-title {
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.search-result-meta {
    font-size: 0.75rem;
    color: #999;
    margin-bottom: 0.5rem;
}

.search-result-price {
    font-size: 1rem;
    font-weight: 600;
}

.search-no-results {
    text-align: center;
    padding: 3rem;
    color: #666;
}

.search-no-results h2 {
    color: #333;
    margin-bottom: 1rem;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #eee;
}

.pagination a, .pagination span {
    padding: 0.5rem 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
}

.pagination a:hover {
    background: #f0f0f0;
}

.pagination .current {
    background: #000;
    color: #fff;
    border-color: #000;
}

@media (max-width: 768px) {
    .search-layout {
        grid-template-columns: 1fr;
    }

    .search-sidebar {
        display: none;
    }

    .search-results {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
}
</style>
</head>
<body>

<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
</header>

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
                     loading="lazy">
                <div class="search-result-info">
                  <div class="search-result-title"><?= e($photo['original_filename']) ?></div>
                  <div class="search-result-meta"><?= e($photo['event_title']) ?></div>
                  <div class="search-result-price">
                    £<?= number_format($photo['price_pence'] / 100, 2) ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if ($results['pages'] > 1): ?>
            <div class="pagination">
              <?php if ($results['page'] > 1): ?>
                <a href="?q=<?= urlencode($query) ?>&page=1">First</a>
                <a href="?q=<?= urlencode($query) ?>&page=<?= $results['page'] - 1 ?>">← Prev</a>
              <?php endif; ?>

              <span class="current"><?= e($results['page']) ?> / <?= e($results['pages']) ?></span>

              <?php if ($results['page'] < $results['pages']): ?>
                <a href="?q=<?= urlencode($query) ?>&page=<?= $results['page'] + 1 ?>">Next →</a>
                <a href="?q=<?= urlencode($query) ?>&page=<?= $results['pages'] ?>">Last</a>
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

</body>
</html>
