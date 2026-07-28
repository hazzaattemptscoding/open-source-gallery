<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($event['title']) ?> — <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/podium-ink.css">
</head>
<body>
<header class="site-header">
  <a href="/" class="site-title"><?= e($siteName) ?></a>
  <a href="/cart" class="cart-badge" id="cartBadge">Cart (<span id="cartCount"><?= (int)$cartCount ?></span>)</a>
</header>

<section class="hero" <?php if ($heroToken): ?>style="background-image: url('/media/d/<?= e($heroToken) ?>-1600.jpg')"<?php endif; ?>>
  <div class="hero-overlay">
    <h1><?= e($event['title']) ?></h1>
    <p class="hero-meta">
      <?= e(date('j M Y', strtotime($event['event_date']))) ?>
      <?php if ($event['venue']): ?> · <?= e($event['venue']) ?><?php endif; ?>
    </p>
  </div>
</section>

<nav class="session-nav">
  <a href="/e/<?= e($event['slug']) ?>" class="<?= $activeSession === null ? 'active' : '' ?>">All sessions</a>
  <?php foreach ($sessions as $session): ?>
    <a href="/e/<?= e($event['slug']) ?>/<?= e($session['slug']) ?>" class="<?= ($activeSession && $activeSession['id'] === $session['id']) ? 'active' : '' ?>">
      <?= e($session['name']) ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($event['price_single_pence'] || $event['price_session_pence'] || $event['price_event_pence']): ?>
<div class="price-banner">
  <span>Single photo: <?= e(format_pence((int)$event['price_single_pence'], $currencyCode)) ?></span>
  <?php if ($event['price_session_pence']): ?>
    <span>Full session: <?= e(format_pence((int)$event['price_session_pence'], $currencyCode)) ?></span>
  <?php endif; ?>
  <?php if ($event['price_event_pence']): ?>
    <span>Full event: <?= e(format_pence((int)$event['price_event_pence'], $currencyCode)) ?></span>
  <?php endif; ?>
</div>
<?php endif; ?>

<form class="filter-bar" id="filterForm" method="get">
  <select name="kart" id="filterKart">
    <option value="">All karts</option>
    <?php foreach ($kartOptions as $kart): ?>
      <option value="<?= e($kart) ?>" <?= $filters['kart'] === $kart ? 'selected' : '' ?>><?= e($kart) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="driver" id="filterDriver">
    <option value="">All drivers</option>
    <?php foreach ($driverOptions as $driver): ?>
      <option value="<?= e($driver) ?>" <?= $filters['driver'] === $driver ? 'selected' : '' ?>><?= e($driver) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="class" id="filterClass">
    <option value="">All classes</option>
    <?php foreach ($classOptions as $class): ?>
      <option value="<?= e($class) ?>" <?= $filters['class'] === $class ? 'selected' : '' ?>><?= e($class) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Filter</button>
  <?php if ($filters['kart'] || $filters['driver'] || $filters['class']): ?>
    <a href="<?= e($basePath) ?>" class="clear-filters">Clear</a>
  <?php endif; ?>
</form>

<main>
  <div class="photo-grid" id="photoGrid">
    <?php require __DIR__ . '/_photo_grid_items.php'; ?>
  </div>

  <?php if (!empty($videos)): ?>
  <section class="video-section">
    <h2>Videos</h2>
    <div class="video-grid">
      <?php foreach ($videos as $video): ?>
        <div class="video-item">
          <video controls preload="metadata" poster="/media/d/<?= e($video['public_token']) ?>-800.jpg">
            <source src="/media/v/<?= e($video['public_token']) ?>.mp4" type="video/mp4">
          </video>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<div class="lightbox" id="lightbox" hidden>
  <button class="lightbox-close" id="lightboxClose" aria-label="Close">&times;</button>
  <button class="lightbox-prev" id="lightboxPrev" aria-label="Previous">&#8249;</button>
  <img id="lightboxImage" alt="">
  <button class="lightbox-next" id="lightboxNext" aria-label="Next">&#8250;</button>
  <button class="lightbox-cart" id="lightboxCart">Add to cart</button>
</div>

<script>
const photoTokens = <?= json_encode(array_column($photos, 'public_token')) ?>;
const photoIds = <?= json_encode(array_map('intval', array_column($photos, 'id'))) ?>;
let lightboxIndex = -1;

function openLightbox(index) {
  lightboxIndex = index;
  updateLightbox();
  document.getElementById('lightbox').hidden = false;
  document.body.style.overflow = 'hidden';
  fetch('/api/photos/view', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ photo_id: photoIds[lightboxIndex] }),
  }).catch(() => {});
}

function closeLightbox() {
  document.getElementById('lightbox').hidden = true;
  document.body.style.overflow = '';
}

function updateLightbox() {
  const token = photoTokens[lightboxIndex];
  document.getElementById('lightboxImage').src = `/media/d/${token}-1600.jpg`;
  [lightboxIndex - 1, lightboxIndex + 1].forEach(i => {
    if (i >= 0 && i < photoTokens.length) {
      new Image().src = `/media/d/${photoTokens[i]}-1600.jpg`;
    }
  });
}

function nextPhoto() {
  if (lightboxIndex < photoTokens.length - 1) {
    lightboxIndex++;
    updateLightbox();
  }
}

function prevPhoto() {
  if (lightboxIndex > 0) {
    lightboxIndex--;
    updateLightbox();
  }
}

document.getElementById('photoGrid').addEventListener('click', (e) => {
  const thumb = e.target.closest('.photo-thumb');
  if (thumb && !e.target.closest('.add-to-cart')) {
    openLightbox(parseInt(thumb.dataset.index, 10));
  }
});

document.getElementById('lightboxClose').addEventListener('click', closeLightbox);
document.getElementById('lightboxNext').addEventListener('click', nextPhoto);
document.getElementById('lightboxPrev').addEventListener('click', prevPhoto);
document.getElementById('lightbox').addEventListener('click', (e) => {
  if (e.target.id === 'lightbox') closeLightbox();
});

document.addEventListener('keydown', (e) => {
  if (document.getElementById('lightbox').hidden) return;
  if (e.key === 'Escape') closeLightbox();
  if (e.key === 'ArrowRight') nextPhoto();
  if (e.key === 'ArrowLeft') prevPhoto();
});

let touchStartX = 0;
document.getElementById('lightbox').addEventListener('touchstart', (e) => {
  touchStartX = e.touches[0].clientX;
});
document.getElementById('lightbox').addEventListener('touchend', (e) => {
  const dx = e.changedTouches[0].clientX - touchStartX;
  if (dx > 50) prevPhoto();
  if (dx < -50) nextPhoto();
});

async function addToCart(type, id, btn) {
  try {
    const response = await fetch('/cart/add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type, id }),
    });
    if (!response.ok) throw new Error('Failed to add');
    const data = await response.json();
    document.getElementById('cartCount').textContent = data.count;
    if (btn) {
      btn.classList.add('added');
      btn.textContent = '✓';
    }
  } catch (err) {
    console.error(err);
  }
}

document.getElementById('photoGrid').addEventListener('click', (e) => {
  const btn = e.target.closest('.add-to-cart');
  if (btn) {
    e.stopPropagation();
    addToCart('photo', parseInt(btn.dataset.photoId, 10), btn);
  }
});

document.getElementById('lightboxCart').addEventListener('click', () => {
  addToCart('photo', photoIds[lightboxIndex], null);
});

// Progressive enhancement: filter changes re-fetch /api/photos instead of a full reload.
document.getElementById('filterForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const params = new URLSearchParams(new FormData(e.target));
  const url = `<?= e($basePath) ?>?${params.toString()}`;
  const apiUrl = `/api/photos?event=<?= e($event['slug']) ?><?= $sessionId ? '&session=' . e($activeSession['slug']) : '' ?>&${params.toString()}`;

  try {
    const response = await fetch(apiUrl);
    if (!response.ok) throw new Error('Filter failed');
    const html = await response.text();
    document.getElementById('photoGrid').innerHTML = html;
    history.pushState({}, '', url);
  } catch (err) {
    window.location.href = url;
  }
});
</script>
</body>
</html>
